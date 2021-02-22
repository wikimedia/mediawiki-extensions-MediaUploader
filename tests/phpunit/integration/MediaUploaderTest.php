<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\MediaUploader\Special\MediaUploader;
use SpecialPageTestBase;
use UserBlockedError;

/**
 * @group Database
 */
class MediaUploaderTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new MediaUploader();
	}

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Special\MediaUploader::isUserUploadAllowed
	 * @dataProvider provideIsUserUploadAllowedForBlockedUser
	 * @param bool $sitewide The block is a sitewide block
	 * @param bool $expectException A UserBlockedError is expected
	 */
	public function testIsUserUploadAllowedForBlockedUser( $sitewide, $expectException ) {
		$this->setMwGlobals( [
			'wgBlockDisablesLogin' => false,
			'wgEnableUploads' => true,
		] );

		$user = $this->getTestUser()->getUser();
		$block = new DatabaseBlock( [
			'expiry' => 'infinite',
			'sitewide' => $sitewide,
		] );
		$block->setTarget( $user );
		$block->setBlocker( $this->getTestSysop()->getUser() );
		$block->insert();

		$caughtException = false;
		try {
			$this->executeSpecialPage( '', null, null, $user );
		} catch ( UserBlockedError $e ) {
			$caughtException = true;
		}

		$block->delete();

		$this->assertSame( $expectException, $caughtException );
	}

	public function provideIsUserUploadAllowedForBlockedUser() {
		return [
			'User with sitewide block is blocked from uploading' => [ true, true ],
			'User with partial block is allowed to upload' => [ false, false ],
		];
	}

}
