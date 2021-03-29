<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use CampaignPageFormatter;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ParsedConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Linker\LinkTarget;
use ParserOptions;
use ParserOutput;
use Status;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TextContent;
use Title;
use TitleValue;
use UploadWizardCampaign;
use User;

/**
 * Represents the configuration of an Upload Campaign
 */
class CampaignContent extends TextContent {

	/**
	 * DB key of the page where the global config is anchored.
	 * The page is always in the Campaign: namespace.
	 *
	 * This page records the templates used by the global config, which allows
	 * the config to be reparsed when any of the used templates change.
	 */
	public const GLOBAL_CONFIG_ANCHOR_DBKEY = '-';

	public static function getGlobalConfigAnchorLinkTarget() : LinkTarget {
		return new TitleValue( NS_CAMPAIGN, self::GLOBAL_CONFIG_ANCHOR_DBKEY );
	}

	/** @var ConfigFactory */
	private $configFactory;

	/** @var Validator */
	private $validator;

	/** @var Status */
	private $yamlParse;

	/** @var Status */
	private $validationStatus;

	/** @var bool Whether the services were initialized */
	private $initializedServices = false;

	public function __construct( $text ) {
		parent::__construct( $text, CONTENT_MODEL_CAMPAIGN );
	}

	/**
	 * Set services for unit testing purposes.
	 *
	 * @param ConfigFactory|null $configFactory
	 * @param Validator|null $validator
	 */
	public function setServices(
		ConfigFactory $configFactory = null,
		Validator $validator = null
	) {
		$this->configFactory = $configFactory;
		$this->validator = $validator;
		$this->initializedServices = true;
	}

	/**
	 * Initialize services from global state.
	 */
	private function initServices() {
		if ( $this->initializedServices ) {
			return;
		}

		$this->setServices(
			MediaUploaderServices::getConfigFactory(),
			MediaUploaderServices::getCampaignValidator()
		);
	}

	/**
	 * Checks user input YAML to make sure that it produces a valid campaign object.
	 *
	 * @return Status
	 */
	public function getValidationStatus() : Status {
		$this->initServices();

		if ( $this->validationStatus ) {
			return $this->validationStatus;
		}

		// First, check if the syntax is valid
		$yamlParse = $this->getData();
		if ( !$yamlParse->isGood() ) {
			$this->validationStatus = $yamlParse;
			return $this->validationStatus;
		}

		$this->validationStatus = $this->validator->validate(
			$yamlParse->getValue()
		);

		return $this->validationStatus;
	}

	/**
	 * @return bool Whether content validates against campaign JSON Schema.
	 */
	public function isValid() {
		return $this->getValidationStatus()->isGood();
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
		$this->initServices();

		if ( $title->getDBkey() === self::GLOBAL_CONFIG_ANCHOR_DBKEY ) {
			// Handle the case of the global config anchor.
			// We ignore config cache as this function may have been called by a
			// recursive LinksUpdate, which means there are probably some templates
			// that this config depends on that have changed. It's also possible
			// that this was caused by a null edit by GlobalConfigAnchorUpdateJob,
			// but then it still is safer to reparse the config than rely on cache
			// that may be out of date.
			$config = $this->configFactory->newGlobalConfig(
				$options->getUserIdentity(),
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
		try {
			$campaign = UploadWizardCampaign::newFromTitle(
				$title,
				[],
				$this,
				true
			);
		} catch ( InvalidCampaignContentException $e ) {
			// TODO: handle this somehow, show a proper error message
			throw $e;
		}

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
	 * Returns the data contained on the page in array representation.
	 * The value is wrapped in the Status object.
	 *
	 * The data is guaranteed to come from a syntactically valid YAML, but may
	 * not validate against the schema. Use isValid() to check if it does.
	 *
	 * @return Status
	 */
	public function getData() : Status {
		if ( $this->yamlParse ) {
			return $this->yamlParse;
		}

		try {
			$this->yamlParse = Status::newGood( Yaml::parse(
				$this->getText(),
				Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
			) );
			return $this->yamlParse;
		}
		catch ( ParseException $e ) {
			return Status::newFatal(
				'mediauploader-yaml-parse-error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Normalizes line endings before saving.
	 *
	 * @param Title $title
	 * @param User $user
	 * @param ParserOptions $popts
	 *
	 * @return CampaignContent
	 */
	public function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		if ( !$this->isValid() ) {
			return $this;
		}

		return new static( self::normalizeLineEndings( $this->getText() ) );
	}
}
