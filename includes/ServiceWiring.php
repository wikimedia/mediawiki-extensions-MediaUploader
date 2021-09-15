<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStats;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Campaign\Validator;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'MediaUploaderCampaignStats' => static function ( MediaWikiServices $services ): CampaignStats {
		return new CampaignStats(
			$services->getDBLoadBalancer(),
			$services->getMainWANObjectCache(),
			MediaUploaderServices::getRawConfig( $services )
		);
	},

	'MediaUploaderCampaignStore' => static function ( MediaWikiServices $services ): CampaignStore {
		return new CampaignStore( $services->getDBLoadBalancer() );
	},

	'MediaUploaderCampaignValidator' => static function ( MediaWikiServices $services ): Validator {
		return new Validator(
			MediaUploaderServices::getRawConfig( $services ),
			$services->getLocalServerObjectCache()
		);
	},

	'MediaUploaderConfigCacheInvalidator' => static function ( MediaWikiServices $services ): ConfigCacheInvalidator {
		return new ConfigCacheInvalidator(
			$services->getMainWANObjectCache()
		);
	},

	'MediaUploaderConfigFactory' => static function ( MediaWikiServices $services ): ConfigFactory {
		return new ConfigFactory(
			$services->getMainWANObjectCache(),
			$services->getUserOptionsLookup(),
			$services->getLanguageNameUtils(),
			$services->getContentLanguage(),
			$services->getLinkBatchFactory(),
			$services->getJobQueueGroup(),
			MediaUploaderServices::getRawConfig( $services ),
			MediaUploaderServices::getConfigParserFactory( $services ),
			MediaUploaderServices::getConfigCacheInvalidator( $services )
		);
	},

	'MediaUploaderConfigParserFactory' => static function ( MediaWikiServices $services ): ConfigParserFactory {
		return new ConfigParserFactory( $services->getParserFactory() );
	},

	'MediaUploaderRawConfig' => static function ( MediaWikiServices $services ): RawConfig {
		return new RawConfig(
			new ServiceOptions(
				RawConfig::CONSTRUCTOR_OPTIONS,
				[ 'PersistDuringRequest' => true ],
				$services->getMainConfig()
			)
		);
	},
];
