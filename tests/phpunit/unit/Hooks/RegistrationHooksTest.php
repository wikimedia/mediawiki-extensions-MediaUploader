<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Hooks;

use MediaWiki\Extension\MediaUploader\Hooks\RegistrationHooks;
use MediaWikiUnitTestCase;
use User;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Hooks\RegistrationHooks
 */
class RegistrationHooksTest extends MediaWikiUnitTestCase {

	public function testChangeTagsHooks() {
		$original = [ 'dummy' ];
		$expected = [
			'dummy',
			'uploadwizard',
			'uploadwizard-flickr'
		];

		$hooks = new RegistrationHooks();

		$tags = $original;
		$this->assertTrue(
			$hooks->onListDefinedTags( $tags ),
			'onListDefinedTags'
		);
		$this->assertArrayEquals(
			$expected,
			$tags,
			false,
			false,
			'onListDefinedTags()'
		);

		$tags = $original;
		$this->assertTrue(
			$hooks->onChangeTagsAllowedAdd(
				$tags,
				[],
				$this->createNoOpMock( User::class )
			),
			'onChangeTagsAllowedAdd()'
		);
		$this->assertArrayEquals(
			$expected,
			$tags,
			false,
			false,
			'onChangeTagsAllowedAdd()'
		);

		$tags = $original;
		$this->assertTrue(
			$hooks->onChangeTagsListActive( $tags ),
			'onChangeTagsListActive()'
		);
		$this->assertArrayEquals(
			$expected,
			$tags,
			false,
			false,
			'onChangeTagsListActive()'
		);
	}

	public function testUserGetReservedNames() {
		$usernames = [ 'Dummy username' ];
		$expected = [ 'Dummy username', 'MediaUploader' ];

		$hooks = new RegistrationHooks();

		$this->assertTrue(
			$hooks->onUserGetReservedNames( $usernames ),
			'onUserGetReservedNames()'
		);
		$this->assertArrayEquals(
			$expected,
			$usernames,
			false,
			false,
			'onUserGetReservedNames()'
		);
	}
}
