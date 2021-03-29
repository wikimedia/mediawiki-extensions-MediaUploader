<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use BagOStuff;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use Status;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Campaign schema validator.
 */
class Validator {

	/** @var RawConfig */
	private $rawConfig;

	/** @var BagOStuff */
	private $localServerCache;

	/**
	 * @param RawConfig $rawConfig
	 * @param BagOStuff $localServerCache
	 */
	public function __construct(
		RawConfig $rawConfig,
		BagOStuff $localServerCache
	) {
		$this->rawConfig = $rawConfig;
		$this->localServerCache = $localServerCache;
	}

	/**
	 * Validates an object against campaign JSON Schema.
	 *
	 * @param array $object The campaign to validate. Must be in an associative array form.
	 *
	 * @return Status with validation errors (if any)
	 */
	public function validate( array $object ) : Status {
		$cacheKey = $this->localServerCache->makeKey(
			'mediauploader',
			'campaign-schema'
		);
		$schema = $this->localServerCache->getWithSetCallback(
			$cacheKey,
			$this->localServerCache::TTL_MINUTE * 5,
			function () : stdClass {
				return $this->makeSchema();
			}
		);

		return $this->doValidate( $object, $schema );
	}

	/**
	 * Reads the schema from the YAML file and fills in the required gaps.
	 *
	 * @return stdClass
	 */
	private function makeSchema() : stdClass {
		$schema = Yaml::parseFile(
			MU_SCHEMA_DIR . 'campaign.yaml',
			Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
		);
		$schema->definitions->licenseName->enum = array_keys(
			$this->rawConfig->getSetting( 'licenses' )
		);

		return $schema;
	}

	/**
	 * Does the actual validation work.
	 *
	 * @param array $object
	 * @param stdClass $schema
	 *
	 * @return Status
	 */
	private function doValidate( array $object, stdClass $schema ) : Status {
		$validator = new \JsonSchema\Validator();
		$objectObject = $validator::arrayToObjectRecursive( $object );

		$validator->validate( $objectObject, $schema );

		if ( $validator->isValid() ) {
			return Status::newGood();
		}

		$status = new Status();
		foreach ( $validator->getErrors() as $error ) {
			$status->fatal(
				'mediauploader-schema-validation-error',
				$error['property'],
				$error['message']
			);
		}

		return $status;
	}
}
