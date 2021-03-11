<?php
/**
 * Upload Campaign Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup UploadWizard
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

use MediaWiki\Extension\MediaUploader\Config\ParsedConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Linker\LinkTarget;

/**
 * Represents the configuration of an Upload Campaign
 */
class CampaignContent extends JsonContent {

	/**
	 * DB key of the page where the global config is anchored.
	 * The page is always in the Campaign: namespace.
	 *
	 * This page records the templates used by the global config, which allows
	 * the config to be reparsed when any of the used templates change.
	 */
	public const GLOBAL_CONFIG_ANCHOR_DBKEY = '-';

	/**
	 * The name of this content model.
	 */
	public const MODEL_NAME = 'Campaign';

	public static function getGlobalConfigAnchorLinkTarget() : LinkTarget {
		return new TitleValue( NS_CAMPAIGN, self::GLOBAL_CONFIG_ANCHOR_DBKEY );
	}

	public function __construct( $text ) {
		parent::__construct( $text, self::MODEL_NAME );
	}

	/**
	 * Checks user input JSON to make sure that it produces a valid campaign object
	 *
	 * @throws JsonSchemaException If invalid.
	 * @return bool True if valid.
	 */
	public function validate() {
		$campaign = $this->getJsonData();
		if ( !is_array( $campaign ) ) {
			throw new JsonSchemaException( 'eventlogging-invalid-json' );
		}

		// FIXME: CampaignContent MUST NOT rely on merging with the global config for verifying
		//  its validity. Campaign schema should be restructured to represent the ACTUAL schema
		//  of a campaign specification, not the schema of the specification merged with the
		//  global config. Until that is fixed, campaign schema validation is commented out.
		/* $schema = include __DIR__ . '/CampaignSchema.php';

		// Only validate fields we care about
		$campaignFields = array_keys( $schema['properties'] );

		$fullConfig = UploadWizardConfig::getConfig();

		$defaultCampaignConfig = [];

		foreach ( $fullConfig as $key => $value ) {
			if ( in_array( $key, $campaignFields ) ) {
				$defaultCampaignConfig[$key] = $value;
			}
		}

		$mergedConfig = UploadWizardConfig::arrayReplaceSanely( $defaultCampaignConfig, $campaign );
		return EventLogging::schemaValidate( $mergedConfig, $schema ); */
		return true;
	}

	/**
	 * @return bool Whether content is valid JSON Schema.
	 */
	public function isValid() {
		try {
			return parent::isValid() && $this->validate();
		} catch ( JsonSchemaException $e ) {
			return false;
		}
	}

	/**
	 * Override to generate appropriate ParserOutput.
	 *
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput &$output
	 */
	protected function fillParserOutput(
		Title $title,
		$revId,
		ParserOptions $options,
		$generateHtml,
		ParserOutput &$output
	) {
		if ( $title->getDBkey() === self::GLOBAL_CONFIG_ANCHOR_DBKEY ) {
			// Handle the case of the global config anchor.
			// We ignore config cache as this function may have been called by a
			// recursive LinksUpdate, which means there are probably some templates
			// that this config depends on that have changed. It's also possible
			// that this was caused by a null edit by GlobalConfigAnchorUpdateJob,
			// but then it still is safer to reparse the config than rely on cache
			// that may be out of date.
			$configFactory = MediaUploaderServices::getConfigFactory();
			$config = $configFactory->newGlobalConfig(
				$options->getUser(),
				$options->getUserLangObj(),
				[],
				true
			);

			$this->registerTemplates( $config, $output );

			if ( $generateHtml ) {
				$output->setText(
					wfMessage( 'mwe-upwiz-global-config-anchor' )->parseAsBlock()
				);
			}
			return;
		}

		// Handle a regular campaign.
		// Here we also ignore the cache, as there's no way to tell whether
		// it's just someone viewing the page and parser cache has expired, or there
		// was an actual edit or a LinksUpdate. We can't defer this until later
		// (like PageSaveComplete or LinksUpdateComplete), because we can't modify
		// ParserOutput at those points. We need an up-to-date list of templates here
		// and now.
		$campaign = UploadWizardCampaign::newFromTitle(
			$title,
			[],
			$this,
			true
		);

		if ( $generateHtml ) {
			$formatter = new CampaignPageFormatter( $campaign );
			$output->setText( $formatter->generateReadHtml() );
		}

		$this->registerTemplates( $campaign->getConfig(), $output );

		// Add some styles
		$output->addModuleStyles( 'ext.uploadWizard.uploadCampaign.display' );
	}

	/**
	 * Registers templates used in a ParsedConfig with a ParserOutput.
	 *
	 * @param ParsedConfig $parsedConfig
	 * @param ParserOutput $parserOutput
	 */
	private function registerTemplates(
		ParsedConfig $parsedConfig,
		ParserOutput $parserOutput
	) : void {
		// FIXME: should we be registering other stuff??
		foreach ( $parsedConfig->getTemplates() as $ns => $templates ) {
			foreach ( $templates as $dbk => $ids ) {
				$title = Title::makeTitle( $ns, $dbk );
				$parserOutput->addTemplate( $title, $ids[0], $ids[1] );
			}
		}
	}

	/**
	 * Deprecated in JsonContent but still useful here because we need to merge the schema's data
	 * with a config array
	 *
	 * @return array|null
	 */
	public function getJsonData() {
		return FormatJson::decode( $this->getNativeData(), true );
	}
}
