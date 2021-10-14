<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use MWException;
use Status;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TextContent;
use TitleValue;

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

	public const MODEL_ID = 'Campaign';

	public static function getGlobalConfigAnchorLinkTarget(): LinkTarget {
		return new TitleValue( NS_CAMPAIGN, self::GLOBAL_CONFIG_ANCHOR_DBKEY );
	}

	/** @var Validator */
	private $validator;

	/** @var Status */
	private $yamlParse;

	/** @var Status */
	private $realYamlParse;

	/** @var Status */
	private $validationStatus;

	/** @var Status */
	private $realValidationStatus;

	/** @var bool Whether the services were initialized */
	private $initializedServices = false;

	/**
	 * CampaignContent constructor.
	 *
	 * See CampaignContentHandler::preSaveTransform for a usage of the second and
	 * third arguments.
	 *
	 * @param string $text
	 *
	 * @throws MWException
	 */
	public function __construct( string $text ) {
		parent::__construct( $text, CONTENT_MODEL_CAMPAIGN );
	}

	/**
	 * Make a copy of this content instance with new text.
	 *
	 * This carries on the services and validation statuses.
	 *
	 * @param string $text
	 *
	 * @return CampaignContent
	 * @throws MWException
	 */
	public function copyWithNewText( string $text ): CampaignContent {
		$content = new CampaignContent( $text );

		// Carry on the validation statuses
		$content->yamlParse = $this->yamlParse;
		$content->validationStatus = $this->validationStatus;
		$content->realYamlParse = $this->realYamlParse;
		$content->realValidationStatus = $this->realValidationStatus;

		// And the services as well
		$content->setServices( $this->validator );
		return $content;
	}

	/**
	 * Overrides the parsing and schema checks. Should only be used when saving an edit by the system user.
	 */
	public function overrideValidationStatus() {
		$this->realValidationStatus = $this->getValidationStatus();
		$this->realYamlParse = $this->getData();
		$this->yamlParse = Status::newGood();
		$this->validationStatus = $this->yamlParse;
	}

	/**
	 * Set services for unit testing purposes.
	 *
	 * @param Validator|null $validator
	 */
	public function setServices( Validator $validator = null ) {
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
			MediaUploaderServices::getCampaignValidator()
		);
	}

	/**
	 * Checks user input YAML to make sure that it produces a valid campaign object.
	 *
	 * @return Status
	 */
	public function getValidationStatus(): Status {
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
	 * Returns the data contained on the page in array representation.
	 * The value is wrapped in the Status object.
	 *
	 * The data is guaranteed to come from a syntactically valid YAML, but may
	 * not validate against the schema. Use isValid() to check if it does.
	 *
	 * @return Status
	 */
	public function getData(): Status {
		if ( $this->yamlParse ) {
			return $this->yamlParse;
		}

		try {
			$data = Yaml::parse(
				$this->getText(),
				Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
			);
			if ( is_array( $data ) ) {
				$this->yamlParse = Status::newGood( $data );
			} else {
				$this->yamlParse = Status::newFatal(
					'mediauploader-yaml-parse-error',
					'unknown error'
				);
			}
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
	 * @param PageReference $page
	 * @param int|null $pageId
	 *
	 * @return CampaignRecord
	 */
	public function newCampaignRecord( PageReference $page, int $pageId = null ): CampaignRecord {
		$yamlParse = $this->realYamlParse ?: $this->getData();
		if ( !$yamlParse->isGood() ) {
			$validity = CampaignRecord::CONTENT_INVALID_FORMAT;
		} else {
			$status = $this->realValidationStatus ?: $this->getValidationStatus();
			if ( !$status->isGood() ) {
				$validity = CampaignRecord::CONTENT_INVALID_SCHEMA;
			} else {
				$validity = CampaignRecord::CONTENT_VALID;
			}
		}

		$content = $yamlParse->getValue();
		// Content can be null, when YAML is invalid and we're force-saving
		// with the system user. Fall back to empty array, so that the config
		// factory doesn't do a backflip.
		if ( $content === null && $validity === CampaignRecord::CONTENT_VALID ) {
			$content = [];
		}

		return new CampaignRecord(
			$pageId,
			( $content ?: [] )['enabled'] ?? false,
			$validity,
			$content,
			$page
		);
	}
}
