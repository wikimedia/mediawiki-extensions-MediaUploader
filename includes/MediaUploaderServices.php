<?php

namespace MediaWiki\Extension\MediaUploader;

use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\GlobalParsedConfig;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\MediaWikiServices;
use RequestContext;

class MediaUploaderServices {

	/**
	 * @param MediaWikiServices|null $services
	 * @param string $name
	 *
	 * @return mixed
	 */
	private static function getService( ?MediaWikiServices $services, string $name ) {
		if ( $services === null ) {
			$services = MediaWikiServices::getInstance();
		}
		return $services->getService( 'MediaUploader' . $name );
	}

	public static function getConfigFactory( MediaWikiServices $services = null ) : ConfigFactory {
		return self::getService( $services, 'ConfigFactory' );
	}

	public static function getConfigParserFactory( MediaWikiServices $services = null ) : ConfigParserFactory {
		return self::getService( $services, 'ConfigParserFactory' );
	}

	public static function getRawConfig( MediaWikiServices $services = null ) : RawConfig {
		return self::getService( $services, 'RawConfig' );
	}

	/**
	 * @return GlobalParsedConfig
	 *
	 * @deprecated Temporary method, will be removed when all code moves to DI.
	 *  Use getConfigFactory()->newGlobalConfig() instead.
	 */
	public static function getGlobalParsedConfig() : GlobalParsedConfig {
		$context = RequestContext::getMain();

		return self::getConfigFactory()->newGlobalConfig(
			$context->getUser(),
			$context->getLanguage()
		);
	}
}
