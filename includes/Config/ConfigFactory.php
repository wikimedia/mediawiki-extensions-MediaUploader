<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use CampaignContent;
use Language;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserOptionsLookup;
use User;
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

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var RawConfig */
	private $rawGlobalConfig;

	/** @var ConfigParserFactory */
	private $configParserFactory;

	/**
	 * @param WANObjectCache $cache
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param LanguageNameUtils $languageNameUtils
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param RawConfig $rawGlobalConfig
	 * @param ConfigParserFactory $configParserFactory
	 *
	 * @internal Only for use by ServiceWiring
	 */
	public function __construct(
		WANObjectCache $cache,
		UserOptionsLookup $userOptionsLookup,
		LanguageNameUtils $languageNameUtils,
		LinkBatchFactory $linkBatchFactory,
		RawConfig $rawGlobalConfig,
		ConfigParserFactory $configParserFactory
	) {
		$this->cache = $cache;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->languageNameUtils = $languageNameUtils;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->rawGlobalConfig = $rawGlobalConfig;
		$this->configParserFactory = $configParserFactory;
	}

	/**
	 * @param Language $language
	 *
	 * @return RequestConfig
	 */
	private function newRequestConfig( Language $language ) : RequestConfig {
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
	 * @param User $user
	 * @param Language $language
	 * @param array $urlOverrides URL parameter overrides in the form of an
	 *   associative array. Use with caution and do not pass unvalidated user
	 *   input.
	 *
	 * @return GlobalParsedConfig
	 */
	public function newGlobalConfig(
		User $user,
		Language $language,
		array $urlOverrides = []
	) : GlobalParsedConfig {
		return new GlobalParsedConfig(
			$this->cache,
			$this->userOptionsLookup,
			$language,
			$user,
			$this->configParserFactory,
			$this->newRequestConfig( $language ),
			$urlOverrides
		);
	}

	/**
	 * Returns the parsed config of a campaign.
	 *
	 * @param User $user
	 * @param Language $language
	 * @param CampaignContent $campaignContent
	 * @param LinkTarget $campaignLinkTarget
	 * @param array $urlOverrides URL parameter overrides in the form of an
	 *   associative array. Use with caution and do not pass unvalidated user
	 *   input.
	 *
	 * @return CampaignParsedConfig
	 */
	public function newCampaignConfig(
		User $user,
		Language $language,
		CampaignContent $campaignContent,
		LinkTarget $campaignLinkTarget,
		array $urlOverrides = []
	) : CampaignParsedConfig {
		return new CampaignParsedConfig(
			$this->cache,
			$this->userOptionsLookup,
			$language,
			$user,
			$this->configParserFactory,
			$this->newRequestConfig( $language ),
			$urlOverrides,
			$campaignContent,
			$campaignLinkTarget
		);
	}
}
