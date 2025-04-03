<?php

namespace MediaWiki\Extension\MediaUploader;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignStats;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Campaign\Validator;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use User;

class MediaUploaderServices {

	/**
	 * @param MediaWikiServices|null $services
	 * @param string $name
	 *
	 * @return mixed
	 */
	private static function getService( ?MediaWikiServices $services, string $name ) {
		$services ??= MediaWikiServices::getInstance();
		return $services->getService( 'MediaUploader' . $name );
	}

	public static function getCampaignStore( ?MediaWikiServices $services = null ): CampaignStore {
		return self::getService( $services, 'CampaignStore' );
	}

	public static function getCampaignStats( ?MediaWikiServices $services = null ): CampaignStats {
		return self::getService( $services, 'CampaignStats' );
	}

	public static function getCampaignValidator( ?MediaWikiServices $services = null ): Validator {
		return self::getService( $services, 'CampaignValidator' );
	}

	public static function getConfigFactory( ?MediaWikiServices $services = null ): ConfigFactory {
		return self::getService( $services, 'ConfigFactory' );
	}

	public static function getConfigParserFactory( ?MediaWikiServices $services = null ): ConfigParserFactory {
		return self::getService( $services, 'ConfigParserFactory' );
	}

	public static function getConfigCacheInvalidator( ?MediaWikiServices $services = null ): ConfigCacheInvalidator {
		return self::getService( $services, 'ConfigCacheInvalidator' );
	}

	public static function getRawConfig( ?MediaWikiServices $services = null ): RawConfig {
		return self::getService( $services, 'RawConfig' );
	}

	/**
	 * Returns the system (MediaUploader) user used for maintenance tasks.
	 */
	public static function getSystemUser(): User {
		return User::newSystemUser( 'MediaUploader', [ 'steal' => true ] );
	}

	/**
	 * Checks whether a given user is the system (MediaUploader) user.
	 *
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public static function isSystemUser( UserIdentity $user ): bool {
		return $user->getName() === 'MediaUploader';
	}
}
