<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use Language;
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

	/** @var array */
	private $urlOverrides;

	/**
	 * @param WANObjectCache $cache
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param Language $language
	 * @param User $user
	 * @param ConfigParserFactory $configParserFactory
	 * @param RequestConfig $requestConfig
	 * @param array $urlOverrides
	 *
	 * @internal Only for use by ConfigFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		UserOptionsLookup $userOptionsLookup,
		Language $language,
		User $user,
		ConfigParserFactory $configParserFactory,
		RequestConfig $requestConfig,
		array $urlOverrides
	) {
		parent::__construct(
			$cache,
			$userOptionsLookup,
			$language,
			$user
		);

		$this->configParserFactory = $configParserFactory;
		$this->requestConfig = $requestConfig;
		$this->urlOverrides = $urlOverrides;
	}

	/**
	 * @inheritDoc
	 */
	final protected function initialize() : void {
		$configHash = $this->requestConfig->getConfigHash();
		$cacheKey = $this->makeCacheKey();

		$cachedValue = $this->cache->get(
			$cacheKey,
			$ttl,
			[ $this->makeInvalidateTimestampKey() ]
		);

		// Check if the cached value's hash matches
		if ( !$cachedValue || $cachedValue['hash'] !== $configHash ) {
			// Regenerate cache
			$configParser = $this->configParserFactory->newConfigParser(
				$this->requestConfig->getConfigArray(),
				$this->user,
				$this->language
			);
			$this->parsedConfig = $configParser->getParsedConfig();
			$this->usedTemplates = $configParser->getTemplates();

			$this->cache->set(
				$cacheKey,
				[
					'hash' => $configHash,
					'config' => $this->parsedConfig,
					'templates' => $this->usedTemplates,
				],
				$this->cache::TTL_INDEFINITE
			);
		} else {
			$this->parsedConfig = $cachedValue['config'];
			$this->usedTemplates = $cachedValue['templates'];
		}

		// Apply config overrides from URL
		$this->parsedConfig = array_replace_recursive(
			$this->parsedConfig,
			$this->urlOverrides
		);
	}

	/**
	 * @inheritDoc
	 */
	public function invalidateCache() : void {
		$this->cache->touchCheckKey( $this->makeInvalidateTimestampKey() );
	}
}
