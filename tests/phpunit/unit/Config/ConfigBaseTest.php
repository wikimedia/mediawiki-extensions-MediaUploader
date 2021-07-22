<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use MediaWiki\Extension\MediaUploader\Config\ConfigBase;
use MediaWikiUnitTestCase;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigBase
 */
class ConfigBaseTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigBase::getSetting
	 */
	public function testGetSetting() {
		$config = $this->getMockForAbstractClass( ConfigBase::class );
		$config->expects( $this->exactly( 3 ) )
			->method( 'getConfigArray' )
			->willReturn( [
				'key1' => 'value1',
				'key2' => 'value2',
			] );

		$this->assertSame(
			'value2',
			$config->getSetting( 'key2' ),
			'the correct setting value is returned'
		);
		$this->assertNull(
			$config->getSetting( 'key3' ),
			'null is returned for unknown key'
		);
		$this->assertSame(
			'default',
			$config->getSetting( 'key3', 'default' ),
			'specified default value is returned for unknown key'
		);
	}

	public function testGetThirdPartyLicenses() {
		$config = $this->getMockForAbstractClass( ConfigBase::class );
		$config->expects( $this->atLeastOnce() )
			->method( 'getConfigArray' )
			->willReturn( [
				'licensing' => [
					'ownWork' => [ 'something' ],
					'thirdParty' => [
						'type' => 'or',
						'defaults' => 'cc-by-sa-4.0',
						'licenseGroups' => [
							[ 'licenses' => [ 'a', 'b' ] ],
							[ 'licenses' => [ 'b', 'c' ] ],
							[ 'licenses' => [ 'd', 'a' ] ],
						]
					],
				]
			] );

		$this->assertArrayEquals(
			[ 'a', 'b', 'c', 'd' ],
			$config->getThirdPartyLicenses(),
			false,
			false,
			'getThirdPartyLicenses()'
		);
	}

	public function provideArrayReplaceSanely(): iterable {
		yield 'no replacements to be made' => [
			[ 'key' => [ 'key' => 'value' ] ],
			[],
			[ 'key' => [ 'key' => 'value' ] ]
		];

		yield 'replacement in 1D associative array' => [
			[ 'key' => 'value', 'k2' => 'value2' ],
			[ 'k2' => [ 'v1', 'v2' ] ],
			[ 'key' => 'value', 'k2' => [ 'v1', 'v2' ] ]
		];

		yield 'multiple replacements in 1D associative array' => [
			[ 'key' => 'value', 'k2' => 'value2', 'k3' => 'value3' ],
			[ 'k2' => [ 'v1', 'v2' ], 'k3' => 'v3' ],
			[ 'key' => 'value', 'k2' => [ 'v1', 'v2' ], 'k3' => 'v3' ]
		];

		yield 'replacement in nested associative array' => [
			[
				'assoc' => [
					'k1' => [ 'v1' => [ 'k11' => 'v11' ], 'k12' => 'v12' ],
					'k2' => 0,
					'k3' => 'test',
					'test'
				],
				'ordered' => [
					'a',
					'b'
				],
				'test'
			],
			[
				'assoc' => [
					'k1' => [ 'v1' => [ 'k11' => 'repl' ] ],
					'k3' => 'repl'
				]
			],
			[
				'assoc' => [
					'k1' => [ 'v1' => [ 'k11' => 'repl' ], 'k12' => 'v12' ],
					'k2' => 0,
					'k3' => 'repl',
					'test'
				],
				'ordered' => [
					'a',
					'b'
				],
				'test'
			],
		];
	}

	/**
	 * @dataProvider provideArrayReplaceSanely
	 *
	 * @param array $array
	 * @param array $array1
	 * @param array $expected
	 */
	public function testArrayReplaceSanely( array $array, array $array1, array $expected ) {
		$config = new class extends ConfigBase {
			public function getConfigArray(): array {
				return [];
			}

			public function testArrayReplaceSanely( $array, $array1 ) {
				return $this->arrayReplaceSanely( $array, $array1 );
			}
		};

		$this->assertArrayEquals(
			$expected,
			$config->testArrayReplaceSanely( $array, $array1 ),
			false,
			true,
			'merged array matches expectation'
		);
	}
}
