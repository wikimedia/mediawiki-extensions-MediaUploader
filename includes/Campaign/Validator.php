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
	public function validate( array $object ): Status {
		$cacheKey = $this->localServerCache->makeKey(
			'mediauploader',
			'campaign-schema'
		);
		$schema = $this->localServerCache->getWithSetCallback(
			$cacheKey,
			$this->localServerCache::TTL_MINUTE * 5,
			function (): stdClass {
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
	private function makeSchema(): stdClass {
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
	 * @param array $array
	 * @param stdClass $schema
	 *
	 * @return Status
	 */
	private function doValidate( array $array, stdClass $schema ): Status {
		$validator = new \JsonSchema\Validator();
		$objectStatus = self::arrayToObject( $array );

		if ( !$objectStatus->isGood() ) {
			return $objectStatus;
		}

		$object = $objectStatus->getValue();
		$validator->validate( $object, $schema );

		if ( $validator->isValid() ) {
			return Status::newGood();
		}

		$status = new Status();
		foreach ( $validator->getErrors() as $error ) {
			$status->fatal(
				'mediauploader-schema-validation-error',
				$error['property'],
				$error['message'],
				// Pass the inner error with all the details
				$error
			);
		}

		return $status;
	}

	/**
	 * Converts an array to PHP object, while restricting the array's
	 * maximum depth to 8.
	 *
	 * @param array $array
	 *
	 * @return Status
	 */
	private static function arrayToObject( array $array ): Status {
		// Encode with max depth of 8
		// This should be enough for campaign configs.
		$json = json_encode( $array, 0, 8 );

		$code = json_last_error();
		if ( $code === JSON_ERROR_NONE ) {
			return Status::newGood( (object)json_decode( $json ) );
		} elseif ( $code === JSON_ERROR_DEPTH ) {
			return Status::newFatal( 'json-error-depth' );
		} else {
			return Status::newFatal( 'json-error-unknown', $code );
		}
	}
}
