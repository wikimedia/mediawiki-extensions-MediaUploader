<?php

namespace MediaWiki\Extension\MediaUploader\Hooks;

use MediaWiki\Extension\MediaUploader\Config\ConfigBase;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use User;

/**
 * General MediaUploader hooks.
 */
class Hooks implements GetPreferencesHook {
	/** @var RawConfig */
	private $config;

	/**
	 * @param RawConfig $rawConfig
	 */
	public function __construct( RawConfig $rawConfig ) {
		$this->config = $rawConfig;
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 *
	 * @return true
	 */
	public function onGetPreferences( $user, &$preferences ) {
		// User preference to skip the licensing tutorial, provided it's not globally disabled
		if ( $this->config->getSetting( 'tutorial' ) ) {
			$preferences['upwiz_skiptutorial'] = [
				'type' => 'check',
				'label-message' => 'mediauploader-prefs-skiptutorial',
				'section' => 'uploads/mediauploader-interface'
			];
		}

		$preferences['upwiz_licensename'] = [
			'type' => 'text',
			'label-message' => 'mediauploader-prefs-license-name',
			'help-message' => 'mediauploader-prefs-license-name-help',
			'section' => 'uploads/mediauploader-licensing'
		];

		$licenses = [];
		$licenseTypes = [
			[ 'configKey' => ConfigBase::LIC_OWN_WORK, 'msgKey' => 'ownwork' ],
			[ 'configKey' => ConfigBase::LIC_THIRD_PARTY, 'msgKey' => 'thirdparty' ],
		];
		$hasCustom = false;

		foreach ( $licenseTypes as $lType ) {
			foreach ( $this->config->getAvailableLicenses( $lType['configKey'] ) as $license ) {
				if ( $license === 'custom' ) {
					$hasCustom = true;
					continue;
				}

				$lMsg = $this->getLicenseMessage( $license ) ?: '';
				$lKey = wfMessage( 'mediauploader-prefs-license-' . $lType['msgKey'] )
					->rawParams( $lMsg )->escaped();
				$lValue = htmlspecialchars(
					$lType['configKey'] . '-' . $license, ENT_QUOTES, 'UTF-8', false
				);
				$licenses[$lKey] = $lValue;
			}
		}

		$licenses = array_merge(
			[ wfMessage( 'mediauploader-prefs-def-license-def' )->escaped() => 'default' ],
			$licenses
		);

		if ( $hasCustom ) {
			// The "custom license" option must be last, otherwise the text referring to "following
			// wikitext" and "last option above" makes no sense.
			$licenseMessage = $this->getLicenseMessage( 'custom' ) ?: '';
			$licenseKey = wfMessage( 'mediauploader-prefs-license-thirdparty' )
				->rawParams( $licenseMessage )->escaped();
			$licenses[$licenseKey] = 'thirdparty-custom';
		}

		$preferences['upwiz_deflicense'] = [
			'type' => 'radio',
			'label-message' => 'mediauploader-prefs-def-license',
			'section' => 'uploads/mediauploader-licensing',
			'options' => $licenses
		];

		if ( $hasCustom ) {
			$preferences['upwiz_deflicense_custom'] = [
				'type' => 'text',
				'label-message' => 'mediauploader-prefs-def-license-custom',
				'help-message' => 'mediauploader-prefs-def-license-custom-help',
				'section' => 'uploads/mediauploader-licensing',
			];
		}

		// Setting for maximum number of simultaneous uploads (always lower than the server-side config)
		if ( ( $this->config->getSetting( 'maxSimultaneousConnections', 0 ) ) > 1 ) {
			// Hack to make the key and value the same otherwise options are added wrongly.
			$range = range( 0, $this->config->getSetting( 'maxSimultaneousConnections' ) );
			$range[0] = 'default';

			$preferences['upwiz_maxsimultaneous'] = [
				'type' => 'select',
				'label-message' => 'mediauploader-prefs-maxsimultaneous-upload',
				'section' => 'uploads/mediauploader-experimental',
				'options' => $range
			];
		}

		return true;
	}

	/**
	 * Helper to return the parsed text of a license message.
	 *
	 * @param string $licenseName
	 *
	 * @return string|null
	 */
	private function getLicenseMessage( string $licenseName ): ?string {
		$license = $this->config->getSetting( 'licenses', [] )[$licenseName] ?? null;
		if ( $license === null ) {
			return null;
		}

		if ( array_key_exists( 'url', $license ) ) {
			return wfMessage( $license['msg'], '', $license['url'] )->parse();
		} else {
			return wfMessage( $license['msg'] )->parse();
		}
	}
}
