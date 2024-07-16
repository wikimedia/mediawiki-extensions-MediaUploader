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
		];

		$hooks = new RegistrationHooks();

		$tags = $original;
		$hooks->onListDefinedTags( $tags );
		$this->assertArrayEquals(
			$expected,
			$tags,
			false,
			false,
			'onListDefinedTags()'
		);

		$tags = $original;
		$hooks->onChangeTagsAllowedAdd(
			$tags,
			[],
			$this->createNoOpMock( User::class )
		);
		$this->assertArrayEquals(
			$expected,
			$tags,
			false,
			false,
			'onChangeTagsAllowedAdd()'
		);

		$tags = $original;
		$hooks->onChangeTagsListActive( $tags );
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

		$hooks->onUserGetReservedNames( $usernames );
		$this->assertArrayEquals(
			$expected,
			$usernames,
			false,
			false,
			'onUserGetReservedNames()'
		);
	}
}
