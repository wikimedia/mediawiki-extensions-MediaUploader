<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use MediaWiki\Config\ServiceOptions;
use UploadBase;

/**
 * Represents the "raw" global config, no settings are parsed.
 * This class depends solely on configuration settings and can be safely used
 * in e.g. load.php with no RequestContext.
 *
 * This class is much "lighter" than GlobalParsedConfig or CampaignParsedConfig and should
 * be used whenever possible.
 *
 * Configuration is loaded from the $wgMediaUploaderConfig variable. In case
 * this variable isn't set, it falls back to $wgUploadWizardConfig.
 */
class RawConfig extends ConfigBase {

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CheckFileExtensions',
		'FileExtensions',
		'MediaUploaderConfig',
		// For backwards compatibility
		'UploadWizardConfig',
		// Whether to persist the merged config for the duration of the request.
		// Should be always true. Can be disabled for unit testing.
		'PersistDuringRequest',
		// Return value of UploadBase::getMaxUploadSize( 'file' )
		// Included here to avoid breaking unit tests – the aforementioned
		// static method calls the service container, ugh.
		'FileMaxUploadSize',
	];

	/**
	 * Holds the global config merged with default settings for the duration
	 * of the request.
	 *
	 * @var array|null
	 */
	private static $mergedGlobalConfig = null;

	/** @var ServiceOptions */
	private $options;

	/**
	 * @param ServiceOptions $options
	 *
	 * @internal For use by ServiceWiring
	 */
	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	/**
	 * Returns the default global config, from MediaUploader.config.php.
	 */
	private function getDefaultConfig(): array {
		$configPath = dirname( __DIR__, 2 ) . '/MediaUploader.config.php';
		$config = is_file( $configPath ) ? include $configPath : [];

		// Initialize settings dependent on other config variables
		return [
			'fileExtensions' =>
				$this->options->get( 'CheckFileExtensions' ) ?
					$this->options->get( 'FileExtensions' ) :
					null,
			'maxPhpUploadSize' => UploadBase::getMaxPhpUploadSize(),
			'maxMwUploadSize' => $this->options->get( 'FileMaxUploadSize' ),
		] + $config;
	}

	/**
	 * @inheritDoc
	 */
	public function getConfigArray(): array {
		if (
			self::$mergedGlobalConfig === null ||
			!$this->options->get( 'PersistDuringRequest' )
		) {
			self::$mergedGlobalConfig = $this->mergeConfigs(
				$this->getDefaultConfig()
			);
		}

		return self::$mergedGlobalConfig;
	}

	/**
	 * Returns the specified default config merged with configs specified
	 * locally (MediaUploaderConfig or UploadWizardConfig).
	 *
	 * @param array $defaults
	 *
	 * @return array
	 */
	private function mergeConfigs( array $defaults ): array {
		$muConfig = $this->options->get( 'MediaUploaderConfig' );
		if ( $muConfig ) {
			return $this->arrayReplaceSanely( $defaults, $muConfig );
		} else {
			return $this->arrayReplaceSanely(
				$defaults,
				$this->options->get( 'UploadWizardConfig' )
			);
		}
	}

	/**
	 * Returns the raw config with additional default settings included.
	 * These additional defaults should depend on the request. We do the merge
	 * here and not in ParsedConfig to maintain the proper array merging order.
	 *
	 * @param array $defaults
	 *
	 * @return array
	 * @internal For use by ParsedConfig
	 */
	public function getConfigWithAdditionalDefaults( array $defaults ): array {
		return $this->mergeConfigs( $defaults + $this->getDefaultConfig() );
	}
}
