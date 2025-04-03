<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Page\PageReference;
use MediaWiki\User\UserOptionsLookup;
use ParserOptions;
use WANObjectCache;

/**
 * Represents the parsed configuration of a campaign.
 *
 * Config caching is handled automatically.
 */
class CampaignParsedConfig extends ParsedConfig {

	/** @var ConfigParserFactory */
	private $configParserFactory;

	/** @var RequestConfig */
	private $requestConfig;

	/** @var array */
	private $urlOverrides;

	/** @var CampaignRecord */
	private $campaignRecord;

	/** @var PageReference */
	private $campaignPage;

	/**
	 * @param WANObjectCache $cache
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param ConfigCacheInvalidator $cacheInvalidator
	 * @param ParserOptions $parserOptions
	 * @param ConfigParserFactory $configParserFactory
	 * @param RequestConfig $requestConfig
	 * @param array $urlOverrides
	 * @param CampaignRecord $campaignRecord assumed to be valid
	 * @param PageReference $campaignPage
	 * @param ServiceOptions $options
	 *
	 * @internal Only for use by ConfigFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		UserOptionsLookup $userOptionsLookup,
		ConfigCacheInvalidator $cacheInvalidator,
		ParserOptions $parserOptions,
		ConfigParserFactory $configParserFactory,
		RequestConfig $requestConfig,
		array $urlOverrides,
		CampaignRecord $campaignRecord,
		PageReference $campaignPage,
		ServiceOptions $options
	) {
		parent::__construct(
			$cache,
			$userOptionsLookup,
			$cacheInvalidator,
			$parserOptions,
			$options
		);

		$this->configParserFactory = $configParserFactory;
		$this->requestConfig = $requestConfig;
		$this->urlOverrides = $urlOverrides;
		$this->campaignRecord = $campaignRecord;
		$this->campaignPage = $campaignPage;
	}

	/**
	 * Returns the name of the campaign.
	 */
	public function getName(): string {
		return $this->campaignPage->getDBkey();
	}

	/**
	 * @inheritDoc
	 */
	protected function initialize( bool $noCache ): void {
		if ( $noCache ) {
			// Just parse the config
			$configValue = $this->parseConfigForCache();
		} else {
			$cacheKey = $this->makeCacheKey( $this->getName() );
			$invalidateKey = $this->invalidator->makeInvalidateTimestampKey( $this->getName() );
			$opts = [ 'checkKeys' => [ $invalidateKey ] ];

			$configValue = $this->cache->getWithSetCallback(
				$cacheKey,
				// Set this to a week and not indefinite to allow for cache
				// invalidation using 'checkKeys'.
				$this->cache::TTL_WEEK,
				function ( $oldValue, &$ttl, array &$setOpts ) {
					return $this->parseConfigForCache();
				},
				$opts
			);
		}

		// Apply config overrides from URL
		$parsedConfig = array_replace_recursive(
			$configValue['config'],
			$this->urlOverrides
		);
		$this->parsedConfig = $this->applyTimeBasedModifiers( $parsedConfig );

		$this->usedTemplates = $configValue['templates'];
	}

	/**
	 * Parses the config and returns an array to be saved in cache.
	 */
	private function parseConfigForCache(): array {
		$configParser = $this->configParserFactory->newConfigParser(
			$this->arrayReplaceSanely(
				$this->requestConfig->getConfigArray(),
				$this->campaignRecord->getContent() ?: []
			),
			$this->parserOptions,
			$this->campaignPage
		);

		return [
			'timestamp' => time(),
			'config' => $configParser->getParsedConfig(),
			'templates' => $configParser->getTemplates(),
		];
	}

	/**
	 * Modifies the parsed config array if there are time-based modifiers that are active.
	 *
	 * @param array $configArray
	 *
	 * @return array Modified configuration array
	 */
	protected function applyTimeBasedModifiers( array $configArray ): array {
		if ( $this->isActive( $configArray ) ) {
			$activeModifiers = $configArray['whileActive'] ?? [];
		} elseif ( $this->wasActive( $configArray ) ) {
			$activeModifiers = $configArray['afterActive'] ?? [];
		} else {
			$activeModifiers = $configArray['beforeActive'] ?? [];
		}

		foreach ( $activeModifiers as $cnf => $modifier ) {
			switch ( $cnf ) {
				case 'display':
					if ( !array_key_exists( $cnf, $configArray ) ) {
						$configArray[$cnf] = [];
					}

					$configArray[$cnf] = array_merge( $configArray[$cnf], $modifier );
					break;
			}
		}

		return $configArray;
	}

	/**
	 * Checks the current date against the configured start and end dates to determine
	 * whether the campaign is currently active.
	 *
	 * @param array $configArray
	 *
	 * @return bool
	 */
	private function isActive( array $configArray ): bool {
		$now = time();
		$start = array_key_exists(
			'start', $configArray
		) ? strtotime( $configArray['start'] ) : null;
		$end = array_key_exists(
			'end', $configArray
		) ? strtotime( $configArray['end'] ) : null;

		return ( $start === null || $start <= $now ) && ( $end === null || $end > $now );
	}

	/**
	 * Checks the current date against the configured start and end dates to determine
	 * whether the campaign was active in the past (and is not anymore)
	 *
	 * @param array $configArray
	 *
	 * @return bool
	 */
	private function wasActive( array $configArray ): bool {
		$now = time();
		$start = array_key_exists(
			'start', $configArray
		) ? strtotime( $configArray['start'] ) : null;

		return ( $start === null || $start <= $now ) && !$this->isActive( $configArray );
	}
}
