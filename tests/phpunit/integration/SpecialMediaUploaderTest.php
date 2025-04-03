<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\UserBlockTarget;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Extension\MediaUploader\Special\MediaUploader;
use MediaWiki\MainConfigNames;
use SpecialPageTestBase;
use UserBlockedError;

/**
 * @group Database
 */
class SpecialMediaUploaderTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new MediaUploader(
			MediaUploaderServices::getRawConfig(),
			MediaUploaderServices::getConfigFactory(),
			MediaUploaderServices::getCampaignStore(),
			$services->getUserOptionsLookup()
		);
	}

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Special\MediaUploader::isUserUploadAllowed
	 * @dataProvider provideIsUserUploadAllowedForBlockedUser
	 * @param bool $sitewide The block is a sitewide block
	 * @param bool $expectException A UserBlockedError is expected
	 */
	public function testIsUserUploadAllowedForBlockedUser( bool $sitewide, bool $expectException ) {
		$this->overrideConfigValues( [
			MainConfigNames::BlockDisablesLogin => false,
			MainConfigNames::EnableUploads => true,
		] );

		$user = $this->getTestUser()->getUser();
		$block = new DatabaseBlock( [
			'expiry' => 'infinite',
			'sitewide' => $sitewide,
		] );
		$target = new UserBlockTarget( $user );
		$block->setTarget( $target );
		$block->setBlocker( $this->getTestSysop()->getUser() );

		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$blockStore->insertBlock( $block );

		if ( $expectException ) {
			$this->expectException( UserBlockedError::class );
		}

		try {
			$this->executeSpecialPage( '', null, null, $user );
			$this->addToAssertionCount( 1 );
		} finally {
			$blockStore->deleteBlock( $block );
		}
	}

	public static function provideIsUserUploadAllowedForBlockedUser() {
		return [
			'User with sitewide block is blocked from uploading' => [ true, true ],
			'User with partial block is allowed to upload' => [ false, false ],
		];
	}

}
