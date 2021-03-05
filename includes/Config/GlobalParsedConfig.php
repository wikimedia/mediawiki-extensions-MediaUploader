<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use JobQueueGroup;
use Language;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserOptionsLookup;
use User;
use WANObjectCache;

/**
 * Represents the parsed global MediaUploader config.
 * Automatically handles caching.
 *
 * Consider using RawConfig instead if you don't need the parsed values.
 */
class GlobalParsedConfig extends ParsedConfig {

	/** @var ConfigParserFactory */
	private $configParserFactory;

	/** @var RequestConfig */
	private $requestConfig;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/** @var array */
	private $urlOverrides;

	/**
	 * @param WANObjectCache $cache
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param ConfigCacheInvalidator $cacheInvalidator
	 * @param Language $language
	 * @param User $user
	 * @param ConfigParserFactory $configParserFactory
	 * @param RequestConfig $requestConfig
	 * @param JobQueueGroup $jobQueueGroup
	 * @param array $urlOverrides
	 * @param ServiceOptions $options
	 *
	 * @internal Only for use by ConfigFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		UserOptionsLookup $userOptionsLookup,
		ConfigCacheInvalidator $cacheInvalidator,
		Language $language,
		User $user,
		ConfigParserFactory $configParserFactory,
		RequestConfig $requestConfig,
		JobQueueGroup $jobQueueGroup,
		array $urlOverrides,
		ServiceOptions $options
	) {
		parent::__construct(
			$cache,
			$userOptionsLookup,
			$cacheInvalidator,
			$language,
			$user,
			$options
		);

		$this->configParserFactory = $configParserFactory;
		$this->requestConfig = $requestConfig;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->urlOverrides = $urlOverrides;
	}

	/**
	 * @inheritDoc
	 */
	final protected function initialize( bool $noCache ) : void {
		if ( $noCache ) {
			// Just reparse the config
			$this->parseConfig();
			$this->applyUrlOverrides();
			return;
		}

		$configHash = $this->requestConfig->getConfigHash();
		$cacheKey = $this->makeCacheKey();
		$cachedValue = $this->cache->get(
			$cacheKey,
			$ttl,
			[ $this->invalidator->makeInvalidateTimestampKey() ]
		);

		if ( $ttl < 0 ) {
			// The cache has expired or was invalidated, reparse and save it
			$this->parseConfig();
			$this->saveConfigToCache( $cacheKey, $configHash );
		} elseif ( !$cachedValue || $cachedValue['hash'] !== $configHash ) {
			// There's no cache or the raw config has changed
			$this->parseConfig();
			$this->saveConfigToCache( $cacheKey, $configHash );

			// The set of templates used in the config may have changed, the
			// update will take care of that.
			$this->jobQueueGroup->lazyPush(
				GlobalConfigAnchorUpdateJob::newSpec()
			);
		} else {
			$this->parsedConfig = $cachedValue['config'];
			$this->usedTemplates = $cachedValue['templates'];
		}

		// Apply config overrides from URL
		$this->applyUrlOverrides();
	}

	/**
	 * Parses the config and sets appropriate fields.
	 */
	private function parseConfig() : void {
		$configParser = $this->configParserFactory->newConfigParser(
			$this->requestConfig->getConfigArray(),
			$this->user,
			$this->language
		);

		$this->parsedConfig = $configParser->getParsedConfig();
		$this->usedTemplates = $configParser->getTemplates();
	}

	/**
	 * Saves the parsed config to cache.
	 *
	 * @param string $cacheKey
	 * @param string $configHash
	 */
	private function saveConfigToCache(
		string $cacheKey, string $configHash
	) : void {
		$this->cache->set(
			$cacheKey,
			[
				'hash' => $configHash,
				'config' => $this->parsedConfig,
				'templates' => $this->usedTemplates,
			],
			// Set this to a week and not indefinite to allow for cache
			// invalidation using 'checkKeys'.
			$this->cache::TTL_WEEK
		);
	}

	/**
	 * Applies URL overrides to the parsed config.
	 */
	private function applyUrlOverrides() : void {
		$this->parsedConfig = array_replace_recursive(
			$this->parsedConfig,
			$this->urlOverrides
		);
	}
}
