<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit;

use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderResourceModule;
use MediaWikiUnitTestCase;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\MediaUploaderResourceModule
 */
class MediaUploaderResourceModuleTest extends MediaWikiUnitTestCase {

	public function provideGetMessages(): iterable {
		yield 'empty config' => [
			[],
			[]
		];

		yield '"additionalMessages" setting' => [
			[ 'additionalMessages' => [ 'k1', 'k2' ] ],
			[ 'k1', 'k2' ]
		];

		yield '"licenses" setting' => [
			[
				// Taken from the default config
				'licenses' => [
					'pd-old' => [
						'msg' => 'mediauploader-license-pd-old',
						'templates' => [ 'PD-old' ]
					],
					'attribution' => [
						'msg' => 'mediauploader-license-attribution'
					],
					'gfdl' => [
						'msg' => 'mediauploader-license-gfdl',
						'templates' => [ 'GFDL' ]
					],
				]
			],
			[
				'mediauploader-license-pd-old',
				'mediauploader-license-attribution',
				'mediauploader-license-gfdl',
			]
		];

		yield '"licensing" setting' => [
			[
				// Taken from the default config, edited for brevity
				'licensing' => [
					'thirdParty' => [
						'type' => 'or',
						'defaults' => 'cc-by-sa-4.0',
						'licenseGroups' => [
							[
								'head' => 'mediauploader-license-cc-head',
								'subhead' => 'mediauploader-license-cc-subhead',
								'licenses' => [ 'cc-by-sa-4.0' ]
							],
							[
								'head' => 'mediauploader-license-custom-head',
								'special' => 'custom',
								'licenses' => [ 'custom' ],
							],
						]
					],
					'ownWork' => [
						'type' => 'or',
						'template' => 'self',
						'defaults' => 'cc-by-sa-4.0',
						'licenses' => [ 'cc-by-sa-4.0' ]
					],
				],
			],
			[
				// Head and subhead messages
				'mediauploader-license-cc-head',
				'mediauploader-license-cc-subhead',
				'mediauploader-license-custom-head',
				// Default license assertions for own work uploads
				'mediauploader-source-ownwork-assert-cc-by-sa-4.0',
				'mediauploader-source-ownwork-cc-by-sa-4.0-explain',
			]
		];

		yield '"licensing" setting, multiple "ownWork" license groups' => [
			[
				// The slightly trickier case where "ownWork" has multiple license
				// groups. This example has been taken from nonsa.pl config and
				// edited for brevity.
				'licensing' => [
					'ownWork' => [
						'type' => 'or',
						'template' => 'self',
						'defaults' => 'cc-by-sa-3.0',
						'licenseGroups' => [
							[
								'head' => 'mediauploader-license-self-free-head',
								'subhead' => 'mediauploader-license-self-free-subhead',
								'template' => 'self',
								'licenses' => [
									'cc-by-sa-4.0',
									'cc-by-sa-3.0'
								]
							],
							[
								'head' => 'mediauploader-license-self-copydown-head',
								'subhead' => 'mediauploader-license-self-copydown-subhead',
								'template' => 'self',
								'licenses' => [ 'cc-by-sa-4.0-copydown' ]
							],
						],
					],
				],
			],
			[
				// Head and subhead messages
				'mediauploader-license-self-free-head',
				'mediauploader-license-self-free-subhead',
				'mediauploader-license-self-copydown-head',
				'mediauploader-license-self-copydown-subhead',
				// Default license assertions for own work uploads
				'mediauploader-source-ownwork-assert-cc-by-sa-4.0',
				'mediauploader-source-ownwork-cc-by-sa-4.0-explain',
				'mediauploader-source-ownwork-assert-cc-by-sa-3.0',
				'mediauploader-source-ownwork-cc-by-sa-3.0-explain',
				'mediauploader-source-ownwork-assert-cc-by-sa-4.0-copydown',
				'mediauploader-source-ownwork-cc-by-sa-4.0-copydown-explain',
			]
		];

		yield 'all settings combined' => [
			[
				'additionalMessages' => [ 'k1', 'k2' ],
				'licenses' => [
					'pd-old' => [
						'msg' => 'mediauploader-license-pd-old',
						'templates' => [ 'PD-old' ]
					],
					'attribution' => [
						'msg' => 'mediauploader-license-attribution'
					],
					'gfdl' => [
						'msg' => 'mediauploader-license-gfdl',
						'templates' => [ 'GFDL' ]
					],
				],
				'licensing' => [
					'thirdParty' => [
						'type' => 'or',
						'defaults' => 'cc-by-sa-4.0',
						'licenseGroups' => [
							[
								'head' => 'mediauploader-license-cc-head',
								'subhead' => 'mediauploader-license-cc-subhead',
								'licenses' => [ 'cc-by-sa-4.0' ]
							],
							[
								'head' => 'mediauploader-license-custom-head',
								'special' => 'custom',
								'licenses' => [ 'custom' ],
							],
						]
					],
					'ownWork' => [
						'type' => 'or',
						'template' => 'self',
						'defaults' => 'cc-by-sa-4.0',
						'licenses' => [ 'cc-by-sa-4.0' ]
					],
				],
			],
			[
				// Additional messages
				'k1',
				'k2',
				// Licenses
				'mediauploader-license-pd-old',
				'mediauploader-license-attribution',
				'mediauploader-license-gfdl',
				// Head and subhead messages
				'mediauploader-license-cc-head',
				'mediauploader-license-cc-subhead',
				'mediauploader-license-custom-head',
				// Default license assertions for own work uploads
				'mediauploader-source-ownwork-assert-cc-by-sa-4.0',
				'mediauploader-source-ownwork-cc-by-sa-4.0-explain',
			]
		];
	}

	/**
	 * @param array $rawConfigValue
	 * @param array $expectedMessages
	 *
	 * @dataProvider provideGetMessages
	 */
	public function testGetMessages(
		array $rawConfigValue,
		array $expectedMessages
	) {
		$rawConfig = $this->createMock( RawConfig::class );
		$rawConfig->method( 'getSetting' )
			->willReturnCallback(
				static function ( $key, $default = null ) use ( $rawConfigValue ) {
					return $rawConfigValue[$key] ?? $default;
				}
			);

		// Message keys defined in extension.json
		$staticKeys = [ 'static-key-1', 'static-key-2' ];

		$module = new MediaUploaderResourceModule(
			[ 'messages' => $staticKeys ],
			$rawConfig
		);

		$this->assertArrayEquals(
			array_merge( $expectedMessages, $staticKeys ),
			$module->getMessages(),
			false,
			false,
			'getMessages()'
		);
	}
}