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
					'pd-textlogo' => [
						'msg' => 'mwe-upwiz-license-pd-textlogo',
						'templates' => [ 'trademarked', 'PD-textlogo' ]
					],
					'attribution' => [
						'msg' => 'mwe-upwiz-license-attribution'
					],
					'gfdl' => [
						'msg' => 'mwe-upwiz-license-gfdl',
						'templates' => [ 'GFDL' ]
					],
				]
			],
			[
				'mwe-upwiz-license-pd-textlogo',
				'mwe-upwiz-license-attribution',
				'mwe-upwiz-license-gfdl',
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
								'head' => 'mwe-upwiz-license-cc-head',
								'subhead' => 'mwe-upwiz-license-cc-subhead',
								'licenses' => [ 'cc-by-sa-4.0' ]
							],
							[
								'head' => 'mwe-upwiz-license-flickr-head',
								'subhead' => 'mwe-upwiz-license-flickr-subhead',
								'prependTemplates' => [ 'flickrreview' ],
								'licenses' => [ 'cc-by-sa-2.0' ]
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
				'mwe-upwiz-license-cc-head',
				'mwe-upwiz-license-cc-subhead',
				'mwe-upwiz-license-flickr-head',
				'mwe-upwiz-license-flickr-subhead',
				// Default license assertions for own work uploads
				'mwe-upwiz-source-ownwork-assert-cc-by-sa-4.0',
				'mwe-upwiz-source-ownwork-cc-by-sa-4.0-explain',
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
								'head' => 'mwe-upwiz-license-self-free-head',
								'subhead' => 'mwe-upwiz-license-self-free-subhead',
								'template' => 'self',
								'licenses' => [
									'cc-by-sa-4.0',
									'cc-by-sa-3.0'
								]
							],
							[
								'head' => 'mwe-upwiz-license-self-copydown-head',
								'subhead' => 'mwe-upwiz-license-self-copydown-subhead',
								'template' => 'self',
								'licenses' => [ 'cc-by-sa-4.0-copydown' ]
							],
						],
					],
				],
			],
			[
				// Head and subhead messages
				'mwe-upwiz-license-self-free-head',
				'mwe-upwiz-license-self-free-subhead',
				'mwe-upwiz-license-self-copydown-head',
				'mwe-upwiz-license-self-copydown-subhead',
				// Default license assertions for own work uploads
				'mwe-upwiz-source-ownwork-assert-cc-by-sa-4.0',
				'mwe-upwiz-source-ownwork-cc-by-sa-4.0-explain',
				'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0',
				'mwe-upwiz-source-ownwork-cc-by-sa-3.0-explain',
				'mwe-upwiz-source-ownwork-assert-cc-by-sa-4.0-copydown',
				'mwe-upwiz-source-ownwork-cc-by-sa-4.0-copydown-explain',
			]
		];

		yield 'all settings combined' => [
			[
				'additionalMessages' => [ 'k1', 'k2' ],
				'licenses' => [
					'pd-textlogo' => [
						'msg' => 'mwe-upwiz-license-pd-textlogo',
						'templates' => [ 'trademarked', 'PD-textlogo' ]
					],
					'attribution' => [
						'msg' => 'mwe-upwiz-license-attribution'
					],
					'gfdl' => [
						'msg' => 'mwe-upwiz-license-gfdl',
						'templates' => [ 'GFDL' ]
					],
				],
				'licensing' => [
					'thirdParty' => [
						'type' => 'or',
						'defaults' => 'cc-by-sa-4.0',
						'licenseGroups' => [
							[
								'head' => 'mwe-upwiz-license-cc-head',
								'subhead' => 'mwe-upwiz-license-cc-subhead',
								'licenses' => [ 'cc-by-sa-4.0' ]
							],
							[
								'head' => 'mwe-upwiz-license-flickr-head',
								'subhead' => 'mwe-upwiz-license-flickr-subhead',
								'prependTemplates' => [ 'flickrreview' ],
								'licenses' => [ 'cc-by-sa-2.0' ]
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
				'mwe-upwiz-license-pd-textlogo',
				'mwe-upwiz-license-attribution',
				'mwe-upwiz-license-gfdl',
				// Head and subhead messages
				'mwe-upwiz-license-cc-head',
				'mwe-upwiz-license-cc-subhead',
				'mwe-upwiz-license-flickr-head',
				'mwe-upwiz-license-flickr-subhead',
				// Default license assertions for own work uploads
				'mwe-upwiz-source-ownwork-assert-cc-by-sa-4.0',
				'mwe-upwiz-source-ownwork-cc-by-sa-4.0-explain',
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
