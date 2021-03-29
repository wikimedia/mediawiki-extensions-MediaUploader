<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use JobQueueGroup;
use Language;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignContentException;
use MediaWiki\Extension\MediaUploader\Config\CampaignParsedConfig;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use TitleValue;
use WANObjectCache;

/**
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigFactory
 */
class ConfigFactoryTest extends MediaWikiUnitTestCase {

	/**
	 * @return ConfigFactory
	 */
	private function getConfigFactory() : ConfigFactory {
		return new ConfigFactory(
			$this->createNoOpMock( WANObjectCache::class ),
			$this->createNoOpMock( UserOptionsLookup::class ),
			$this->createNoOpMock( LanguageNameUtils::class ),
			$this->createNoOpMock( LinkBatchFactory::class ),
			$this->createNoOpMock( JobQueueGroup::class ),
			$this->createNoOpMock( RawConfig::class ),
			$this->createNoOpMock( ConfigParserFactory::class ),
			$this->createNoOpMock( ConfigCacheInvalidator::class )
		);
	}

	public function testNewCampaignConfig_invalidContent() {
		$this->expectException( InvalidCampaignContentException::class );
		$this->expectExceptionMessageMatches( '/Camp name/' );

		$factory = $this->getConfigFactory();

		$content = $this->createMock( CampaignContent::class );
		$content->expects( $this->once() )
			->method( 'isValid' )
			->willReturn( false );

		$factory->newCampaignConfig(
			$this->createNoOpMock( UserIdentity::class ),
			$this->createNoOpMock( Language::class ),
			$content,
			new TitleValue( NS_CAMPAIGN, 'Camp name' )
		);
	}

	public function testNewCampaignConfig_validContent() {
		$factory = $this->getConfigFactory();

		$content = $this->createMock( CampaignContent::class );
		$content->expects( $this->once() )
			->method( 'isValid' )
			->willReturn( true );

		$this->assertInstanceOf(
			CampaignParsedConfig::class,
			$factory->newCampaignConfig(
				$this->createNoOpMock( UserIdentity::class ),
				$this->createNoOpMock( Language::class ),
				$content,
				new TitleValue( NS_CAMPAIGN, 'Camp name' )
			)
		);
	}
}
