<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'MediaUploaderConfigCacheInvalidator' => function ( MediaWikiServices $services ) : ConfigCacheInvalidator {
		return new ConfigCacheInvalidator(
			$services->getMainWANObjectCache()
		);
	},

	'MediaUploaderConfigFactory' => function ( MediaWikiServices $services ) : ConfigFactory {
		return new ConfigFactory(
			$services->getMainWANObjectCache(),
			$services->getUserOptionsLookup(),
			$services->getLanguageNameUtils(),
			$services->getLinkBatchFactory(),
			JobQueueGroup::singleton(),
			MediaUploaderServices::getRawConfig( $services ),
			MediaUploaderServices::getConfigParserFactory( $services ),
			MediaUploaderServices::getConfigCacheInvalidator( $services )
		);
	},

	'MediaUploaderConfigParserFactory' => function ( MediaWikiServices $services ) : ConfigParserFactory {
		return new ConfigParserFactory( $services->getParserFactory() );
	},

	'MediaUploaderRawConfig' => function ( MediaWikiServices $services ) : RawConfig {
		return new RawConfig(
			new ServiceOptions(
				RawConfig::CONSTRUCTOR_OPTIONS,
				[ 'PersistDuringRequest' => true ],
				$services->getMainConfig()
			)
		);
	},
];
