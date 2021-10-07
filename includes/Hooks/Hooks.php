<?php

namespace MediaWiki\Extension\MediaUploader\Hooks;

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
				'label-message' => 'mwe-upwiz-prefs-skiptutorial',
				'section' => 'uploads/upwiz-interface'
			];
		}

		$preferences['upwiz_licensename'] = [
			'type' => 'text',
			'label-message' => 'mwe-upwiz-prefs-license-name',
			'help-message' => 'mwe-upwiz-prefs-license-name-help',
			'section' => 'uploads/upwiz-licensing'
		];

		if ( $this->config->getSetting( 'enableLicensePreference' ) ) {
			$licenses = [];

			$licensingOptions = $this->config->getSetting( 'licensing' );

			$ownWork = $licensingOptions['ownWork'];
			foreach ( $ownWork['licenses'] as $license ) {
				$licenseMessage = $this->getLicenseMessage( $license ) ?: '';
				$licenseKey = wfMessage( 'mwe-upwiz-prefs-license-own' )
					->rawParams( $licenseMessage )->escaped();
				$licenseValue = htmlspecialchars( 'ownwork-' . $license, ENT_QUOTES, 'UTF-8', false );
				$licenses[$licenseKey] = $licenseValue;
			}

			$thirdParty = $this->config->getThirdPartyLicenses();
			$hasCustom = false;
			foreach ( $thirdParty as $license ) {
				if ( $license !== 'custom' ) {
					$licenseMessage = $this->getLicenseMessage( $license ) ?: '';
					$licenseKey = wfMessage( 'mwe-upwiz-prefs-license-thirdparty' )
						->rawParams( $licenseMessage )->escaped();
					$licenseValue = htmlspecialchars( 'thirdparty-' . $license, ENT_QUOTES, 'UTF-8', false );
					$licenses[$licenseKey] = $licenseValue;
				} else {
					$hasCustom = true;
				}
			}

			$licenses = array_merge(
				[
					wfMessage( 'mwe-upwiz-prefs-def-license-def' )->escaped() => 'default'
				],
				$licenses
			);

			if ( $hasCustom ) {
				// The "custom license" option must be last, otherwise the text referring to "following
				// wikitext" and "last option above" makes no sense.
				$licenseMessage = $this->getLicenseMessage( 'custom' ) ?: '';
				$licenseKey = wfMessage( 'mwe-upwiz-prefs-license-thirdparty' )
					->rawParams( $licenseMessage )->escaped();
				$licenses[$licenseKey] = 'thirdparty-custom';
			}

			$preferences['upwiz_deflicense'] = [
				'type' => 'radio',
				'label-message' => 'mwe-upwiz-prefs-def-license',
				'section' => 'uploads/upwiz-licensing',
				'options' => $licenses
			];

			if ( $hasCustom ) {
				$preferences['upwiz_deflicense_custom'] = [
					'type' => 'text',
					'label-message' => 'mwe-upwiz-prefs-def-license-custom',
					'help-message' => 'mwe-upwiz-prefs-def-license-custom-help',
					'section' => 'uploads/upwiz-licensing',
				];
			}
		}

		// Setting for maximum number of simultaneous uploads (always lower than the server-side config)
		if ( ( $this->config->getSetting( 'maxSimultaneousConnections', 0 ) ) > 1 ) {
			// Hack to make the key and value the same otherwise options are added wrongly.
			$range = range( 0, $this->config->getSetting( 'maxSimultaneousConnections' ) );
			$range[0] = 'default';

			$preferences['upwiz_maxsimultaneous'] = [
				'type' => 'select',
				'label-message' => 'mwe-upwiz-prefs-maxsimultaneous-upload',
				'section' => 'uploads/upwiz-experimental',
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
