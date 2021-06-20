<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWikiIntegrationTestCase;

/**
 * Besides testing the whole service shebang, this also tests some basic code
 * paths in the services themselves.
 *
 * @group Upload
 * @group Database
 * @group medium
 * @covers \MediaWiki\Extension\MediaUploader\MediaUploaderServices
 */
class MediaUploaderServicesTest extends MediaWikiIntegrationTestCase {

	public function testGetRawConfig() {
		$this->expectNotToPerformAssertions();

		$rawConfig = MediaUploaderServices::getRawConfig();

		// Call some functions to ensure no exceptions are thrown
		$rawConfig->getConfigArray();
		$rawConfig->getConfigWithAdditionalDefaults( [ 'test' => 'test' ] );
		$rawConfig->getSetting( 'test' );
	}

	public function testGetConfigParserFactory() {
		$this->expectNotToPerformAssertions();

		$configParserFactory = MediaUploaderServices::getConfigParserFactory();

		$configParser = $configParserFactory->newConfigParser(
			[],
			$this->getTestUser()->getUser(),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);
		$configParser->getParsedConfig();
	}

	public function testGetConfigFactory() {
		$this->expectNotToPerformAssertions();

		$configFactory = MediaUploaderServices::getConfigFactory();

		$gConfig = $configFactory->newGlobalConfig(
			$this->getTestUser()->getUser(),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);
		$gConfig->getConfigArray();
	}

	public function testGetCampaignStore() {
		$this->expectNotToPerformAssertions();

		$campaignStore = MediaUploaderServices::getCampaignStore();
		$campaignStore->newSelectQueryBuilder();
	}
}
