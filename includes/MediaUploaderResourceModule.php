<?php

namespace MediaWiki\Extension\MediaUploader;

use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use ResourceLoaderFileModule;

/**
 * Class representing the 'ext.uploadWizard' ResourceLoader module.
 */
class MediaUploaderResourceModule extends ResourceLoaderFileModule {

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

		foreach ( $this->rawConfig->getSetting( 'licenses', [] ) as $key => $value ) {
			if ( isset( $value['msg'] ) ) {
				$licenseMessages[] = $value['msg'];
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
		if ( !isset( $licensingConfig[$type] ) ) {
			return [];
		}
		$config = $licensingConfig[$type]; // shorthand

		$messages = [];
		if ( $type === 'ownWork' && isset( $config['licenses'] ) ) {
			$messages = $this->getMessagesForDefaultLicenses( $config['licenses'] );
		}

		if ( !isset( $config['licenseGroups'] ) ) {
			return $messages;
		}
		foreach ( $config['licenseGroups'] as $licenseGroup ) {
			if ( isset( $licenseGroup['head'] ) ) {
				$messages[] = $licenseGroup['head'];
			}
			if ( isset( $licenseGroup['subhead'] ) ) {
				$messages[] = $licenseGroup['subhead'];
			}
			if ( $type === 'ownWork' && isset( $licenseGroup['licenses'] ) ) {
				$messages = array_merge(
					$messages,
					$this->getMessagesForDefaultLicenses(
						$licenseGroup['licenses']
					)
				);
			}
		}

		return $messages;
	}

	/**
	 * Returns an array of messages necessary to display license assertion messages.
	 * These are used when a user selects a license to be their default.
	 *
	 * @param string[] $licenses
	 *
	 * @return string[]
	 */
	private function getMessagesForDefaultLicenses( array $licenses ): array {
		$messages = [];
		foreach ( $licenses as $license ) {
			$messages[] = 'mwe-upwiz-source-ownwork-assert-' . $license;
			$messages[] = 'mwe-upwiz-source-ownwork-' . $license . '-explain';
		}

		return $messages;
	}
}
