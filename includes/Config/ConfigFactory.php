<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use JobQueueGroup;
use Language;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\BaseCampaignException;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use WANObjectCache;

/**
 * Constructs GlobalParsedConfig and CampaignParsedConfig objects.
 */
class ConfigFactory {

	/** @var WANObjectCache */
	private $cache;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var Language */
	private $contentLanguage;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/** @var RawConfig */
	private $rawGlobalConfig;

	/** @var ConfigParserFactory */
	private $configParserFactory;

	/** @var ConfigCacheInvalidator */
	private $configCacheInvalidator;

	/**
	 * @param WANObjectCache $cache
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param LanguageNameUtils $languageNameUtils
	 * @param Language $contentLanguage
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param RawConfig $rawGlobalConfig
	 * @param ConfigParserFactory $configParserFactory
	 * @param ConfigCacheInvalidator $cacheInvalidator
	 *
	 * @internal Only for use by ServiceWiring
	 */
	public function __construct(
		WANObjectCache $cache,
		UserOptionsLookup $userOptionsLookup,
		LanguageNameUtils $languageNameUtils,
		Language $contentLanguage,
		LinkBatchFactory $linkBatchFactory,
		JobQueueGroup $jobQueueGroup,
		RawConfig $rawGlobalConfig,
		ConfigParserFactory $configParserFactory,
		ConfigCacheInvalidator $cacheInvalidator
	) {
		$this->cache = $cache;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->languageNameUtils = $languageNameUtils;
		$this->contentLanguage = $contentLanguage;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->rawGlobalConfig = $rawGlobalConfig;
		$this->configParserFactory = $configParserFactory;
		$this->configCacheInvalidator = $cacheInvalidator;
	}

	/**
	 * @param Language $language
	 *
	 * @return RequestConfig
	 */
	private function newRequestConfig( Language $language ): RequestConfig {
		return new RequestConfig(
			$this->cache,
			$this->languageNameUtils,
			$this->linkBatchFactory,
			$language,
			$this->rawGlobalConfig
		);
	}

	/**
	 * Returns the global parsed config.
	 *
	 * @param UserIdentity $user
	 * @param Language|null $language will fallback to wiki's content language
	 * @param array $urlOverrides URL parameter overrides in the form of an
	 *   associative array. Use with caution and do not pass unvalidated user
	 *   input.
	 * @param bool $noCache Whether to ignore config cache
	 *
	 * @return GlobalParsedConfig
	 */
	public function newGlobalConfig(
		UserIdentity $user,
		?Language $language,
		array $urlOverrides = [],
		bool $noCache = false
	): GlobalParsedConfig {
		$language = $language ?: $this->contentLanguage;
		return new GlobalParsedConfig(
			$this->cache,
			$this->userOptionsLookup,
			$this->configCacheInvalidator,
			$language,
			$user,
			$this->configParserFactory,
			$this->newRequestConfig( $language ),
			$this->jobQueueGroup,
			$urlOverrides,
			new ServiceOptions(
				ParsedConfig::CONSTRUCTOR_OPTIONS,
				[ ParsedConfig::NO_CACHE => $noCache ]
			)
		);
	}

	/**
	 * Returns the parsed config of a campaign.
	 *
	 * @param UserIdentity $user
	 * @param Language|null $language will fallback to wiki's content language
	 * @param CampaignRecord $campaignRecord
	 * @param LinkTarget $campaignLinkTarget
	 * @param array $urlOverrides URL parameter overrides in the form of an
	 *   associative array. Use with caution and do not pass unvalidated user
	 *   input.
	 * @param bool $noCache Whether to ignore config cache
	 *
	 * @return CampaignParsedConfig
	 * @throws BaseCampaignException
	 */
	public function newCampaignConfig(
		UserIdentity $user,
		?Language $language,
		CampaignRecord $campaignRecord,
		LinkTarget $campaignLinkTarget,
		array $urlOverrides = [],
		bool $noCache = false
	): CampaignParsedConfig {
		$campaignRecord->assertValid(
			$campaignLinkTarget->getDBkey(),
			CampaignStore::SELECT_CONTENT
		);

		$language = $language ?: $this->contentLanguage;
		return new CampaignParsedConfig(
			$this->cache,
			$this->userOptionsLookup,
			$this->configCacheInvalidator,
			$language,
			$user,
			$this->configParserFactory,
			$this->newRequestConfig( $language ),
			$urlOverrides,
			$campaignRecord,
			$campaignLinkTarget,
			new ServiceOptions(
				ParsedConfig::CONSTRUCTOR_OPTIONS,
				[ ParsedConfig::NO_CACHE => $noCache ]
			)
		);
	}
}
