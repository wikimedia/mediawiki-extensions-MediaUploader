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

/**
 * Represents the configuration of an Upload Campaign
 */
class CampaignContent extends JsonContent {

	public function __construct( $text ) {
		parent::__construct( $text, 'Campaign' );
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
	 * Override getParserOutput, since we require $title to generate our output
	 * @param Title $title
	 * @param int|null $revId
	 * @param ParserOptions|null $options
	 * @param bool $generateHtml
	 * @return ParserOutput
	 */
	public function getParserOutput( Title $title,
		$revId = null,
		ParserOptions $options = null, $generateHtml = true
	) {
		$po = new ParserOutput();
		$campaign = UploadWizardCampaign::newFromTitle(
			$title,
			[],
			$this
		);

		if ( $generateHtml ) {
			$po->setText( $this->generateHtml( $campaign ) );
		}

		// Register template usage
		// FIXME: should we be registering other stuff??
		foreach ( $campaign->getConfig()->getTemplates() as $ns => $templates ) {
			foreach ( $templates as $dbk => $ids ) {
				$title = Title::makeTitle( $ns, $dbk );
				$po->addTemplate( $title, $ids[0], $ids[1] );
			}
		}

		// Add some styles
		$po->addModuleStyles( 'ext.uploadWizard.uploadCampaign.display' );

		return $po;
	}

	public function generateHtml( $campaign ) {
		$formatter = new CampaignPageFormatter( $campaign );

		return $formatter->generateReadHtml();
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
