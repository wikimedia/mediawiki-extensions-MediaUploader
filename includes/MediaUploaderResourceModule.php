<?php

namespace MediaWiki\Extension\MediaUploader;

use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\ResourceLoader\FileModule;

/**
 * Class representing the 'ext.uploadWizard' ResourceLoader module.
 */
class MediaUploaderResourceModule extends FileModule {

	/** @var RawConfig */
	private $rawConfig;

	/**
	 * @param array $options Options from resource module definition
	 * @param RawConfig $rawConfig
	 *
	 * @internal use MediaUploaderResourceModuleFactory
	 */
	public function __construct( array $options, RawConfig $rawConfig ) {
		parent::__construct( $options );
		$this->rawConfig = $rawConfig;
	}

	/**
	 * Returns messages required by this module.
	 *
	 * Uses the global MediaUploader config to determine which license messages should
	 * be loaded.
	 *
	 * @return array
	 */
	public function getMessages() {
		$licenseMessages = [];

		// Add messages used directly by licenses
		foreach ( $this->rawConfig->getSetting( 'licenses', [] ) as $value ) {
			foreach ( [ 'msg', 'explainMsg' ] as $mKey ) {
				if ( isset( $value[$mKey] ) ) {
					$licenseMessages[] = $value[$mKey];
				}
			}
		}

		$licensing = $this->rawConfig->getSetting( 'licensing', [] );

		return array_unique( array_merge(
			parent::getMessages(),
			$licenseMessages,
			$this->getMessagesForLicenseGroups( $licensing, 'ownWork' ),
			$this->getMessagesForLicenseGroups( $licensing, 'thirdParty' ),
			$this->rawConfig->getSetting( 'additionalMessages', [] )
		) );
	}

	/**
	 * Returns an array of messages necessary to display all license groups of type $type.
	 *
	 * @param array $licensingConfig
	 * @param string $type Either 'ownWork' or 'thirdParty'
	 *
	 * @return string[]
	 */
	private function getMessagesForLicenseGroups(
		array $licensingConfig,
		string $type
	): array {
		if ( !isset( $licensingConfig[$type]['licenseGroups'] ) ) {
			return [];
		}

		$messages = [];
		foreach ( $licensingConfig[$type]['licenseGroups'] as $licenseGroup ) {
			if ( isset( $licenseGroup['head'] ) ) {
				$messages[] = $licenseGroup['head'];
			}
			if ( isset( $licenseGroup['subhead'] ) ) {
				$messages[] = $licenseGroup['subhead'];
			}
		}

		return $messages;
	}
}
