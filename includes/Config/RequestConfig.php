<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use Collator;
use FormatJson;
use Language;
use LanguageCode;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Title\Title;
use WANObjectCache;

/**
 * Config with fields dependent on the specific request.
 *
 * This means things like the current language can be taken into account.
 *
 * @internal Only for use by GlobalParsedConfig and CampaignParsedConfig.
 */
class RequestConfig extends ConfigBase {

	/**
	 * Raw config's value enhanced with request-dependent settings.
	 *
	 * @var array|null
	 */
	private $config = null;

	/** @var WANObjectCache */
	protected $cache;

	/** @var Language */
	private $language;

	/** @var RawConfig */
	private $rawConfig;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/**
	 * @param WANObjectCache $cache
	 * @param LanguageNameUtils $languageNameUtils
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param Language $language
	 * @param RawConfig $rawConfig
	 *
	 * @internal Only for use by ConfigFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		LanguageNameUtils $languageNameUtils,
		LinkBatchFactory $linkBatchFactory,
		Language $language,
		RawConfig $rawConfig
	) {
		$this->cache = $cache;
		$this->language = $language;
		$this->rawConfig = $rawConfig;
		$this->languageNameUtils = $languageNameUtils;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * Returns the unparsed configuration array with all dynamically
	 * generated defaults applied.
	 */
	public function getConfigArray(): array {
		if ( $this->config !== null ) {
			return $this->config;
		}

		// Set request-dependent defaults
		$this->config = $this->rawConfig->getConfigWithAdditionalDefaults( [
			'languages' => $this->getTemplateLanguages(),
		] );

		return $this->config;
	}

	public function getConfigHash(): string {
		return md5( FormatJson::encode( $this->getConfigArray() ) );
	}

	/**
	 * Generates the 'languages' setting's default value.
	 * TODO: Execute this only if the wizard is configured with multi-language fields.
	 */
	private function getTemplateLanguages(): array {
		// We need to get a list of languages for the description dropdown.
		// Increase the 'version' number in the options below if this logic or format changes.
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey(
				'mediauploader',
				'language-templates',
				$this->language->getCode()
			),
			$this->cache::TTL_DAY,
			function () {
				$languages = [];

				// First, get a list of languages we support.
				$baseLangs = $this->languageNameUtils->getLanguageNames(
					$this->language->getCode(),
					LanguageNameUtils::ALL
				);

				// We need to take into account languageTemplateFixups
				$languageFixups = $this->rawConfig->getSetting(
					'languageTemplateFixups',
					[]
				);

				// Use LinkBatch to make this a little bit more faster.
				// It works because $title->exists (below) will use LinkCache.
				$linkBatch = $this->linkBatchFactory->newLinkBatch();
				foreach ( $baseLangs as $code => $name ) {
					$fixedCode = $languageFixups[$code] ?? $code;
					if ( is_string( $fixedCode ) && $fixedCode !== '' ) {
						$title = Title::makeTitle(
							NS_TEMPLATE,
							Title::capitalize( $fixedCode, NS_TEMPLATE )
						);
						$linkBatch->addObj( $title );
					}
				}
				$linkBatch->execute();

				// Then, check that there's a template for each one.
				foreach ( $baseLangs as $code => $name ) {
					$fixedCode = $languageFixups[$code] ?? $code;
					if ( is_string( $fixedCode ) && $fixedCode !== '' ) {
						$title = Title::makeTitle(
							NS_TEMPLATE,
							Title::capitalize( $fixedCode, NS_TEMPLATE )
						);
						if ( $title->exists() ) {
							// If there is, then it's in the final picks!
							$languages[$code] = $name;
						}
					}
				}

				// Skip the duplicate deprecated language codes if the new one is okay to use.
				foreach ( LanguageCode::getDeprecatedCodeMapping() as $oldKey => $newKey ) {
					if ( isset( $languages[$newKey] ) && isset( $languages[$oldKey] ) ) {
						unset( $languages[$oldKey] );
					}
				}

				// Sort the list by the language name.
				if ( class_exists( Collator::class ) ) {
					// If a specific collation is not available for the user's language,
					// this falls back to a generic 'root' one.
					$collator = Collator::create( $this->language->getCode() );
					$collator->asort( $languages );
				} else {
					natcasesort( $languages );
				}

				// Fallback just in case
				return $languages ?: [ 'en' => 'English' ];
			},
			[
				'version' => 1,
			]
		);
	}
}
