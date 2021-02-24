<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'MediaUploaderConfigFactory' => function ( MediaWikiServices $services ) : ConfigFactory {
		return new ConfigFactory(
			$services->getMainWANObjectCache(),
			$services->getUserOptionsLookup(),
			$services->getLanguageNameUtils(),
			$services->getLinkBatchFactory(),
			MediaUploaderServices::getRawConfig( $services ),
			MediaUploaderServices::getConfigParserFactory( $services )
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
