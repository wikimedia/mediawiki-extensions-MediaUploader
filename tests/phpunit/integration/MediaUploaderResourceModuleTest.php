<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderResourceModule;
use MediaWikiIntegrationTestCase;

/**
 * This should be a unit test case, but someone bright decided to introduce
 * the service container into the ResourceLoader\FileModule class, without
 * using DI. Thank you, Wikimedia, for your efforts to make my life simpler.
 *
 * TODO: change it to a unit test when it becomes possible.
 *
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\MediaUploaderResourceModule
 */
class MediaUploaderResourceModuleTest extends MediaWikiIntegrationTestCase {

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
					[
						'msg' => 'mediauploader-license-cc-by-nc-nd-2.0',
						'icons' => [ 'cc-by', 'cc-nc', 'cc-nd' ],
						'url' => '//creativecommons.org/licenses/by-nc-nd/2.0/',
						'languageCodePrefix' => 'deed.',
						'wikitext' => '{{subst:int:mediauploader-license-cc-by-nc-nd-2.0' .
							'||//creativecommons.org/licenses/by-nc-nd/2.0/}}',
						'explainMsg' => 'mediauploader-source-ownwork-cc-by-nc-nd-explain'
					],
					'fal' => [
						'msg' => 'mediauploader-license-fal',
						'wikitext' => '{{subst:int:mediauploader-license-fal}}'
					],
					'gfdl' => [
						'msg' => 'mediauploader-license-gfdl',
						'wikitext' => '{{subst:int:mediauploader-license-gfdl}}'
					],
					'none' => [
						'msg' => 'mediauploader-license-none',
						'wikitext' => '{{subst:int:mediauploader-license-none-text}}'
					],
				]
			],
			[
				'mediauploader-license-cc-by-nc-nd-2.0',
				'mediauploader-source-ownwork-cc-by-nc-nd-explain',
				'mediauploader-license-fal',
				'mediauploader-license-gfdl',
				'mediauploader-license-none',
			]
		];

		yield '"licensing" setting' => [
			[
				// Taken from the default config, edited for brevity
				'licensing' => [
					'thirdParty' => [
						'type' => 'radio',
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
						'type' => 'radio',
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
			]
		];

		yield '"licensing" setting, multiple "ownWork" license groups' => [
			[
				// The slightly trickier case where "ownWork" has multiple license
				// groups. This example has been taken from nonsa.pl config and
				// edited for brevity.
				'licensing' => [
					'ownWork' => [
						'type' => 'checkbox',
						'template' => 'self',
						'defaults' => 'cc-by-sa-3.0',
						'licenseGroups' => [
							[
								'head' => 'mediauploader-license-self-free-head',
								'subhead' => 'mediauploader-license-self-free-subhead',
								'licenses' => [
									'cc-by-sa-4.0',
									'cc-by-sa-3.0'
								]
							],
							[
								'head' => 'mediauploader-license-self-copydown-head',
								'subhead' => 'mediauploader-license-self-copydown-subhead',
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
			]
		];

		yield 'all settings combined' => [
			[
				'additionalMessages' => [ 'k1', 'k2' ],
				'licenses' => [
					[
						'msg' => 'mediauploader-license-cc-by-nc-nd-2.0',
						'icons' => [ 'cc-by', 'cc-nc', 'cc-nd' ],
						'url' => '//creativecommons.org/licenses/by-nc-nd/2.0/',
						'languageCodePrefix' => 'deed.',
						'wikitext' => '{{subst:int:mediauploader-license-cc-by-nc-nd-2.0' .
							'||//creativecommons.org/licenses/by-nc-nd/2.0/}}',
						'explainMsg' => 'mediauploader-source-ownwork-cc-by-nc-nd-explain'
					],
					'fal' => [
						'msg' => 'mediauploader-license-fal',
						'wikitext' => '{{subst:int:mediauploader-license-fal}}'
					],
					'gfdl' => [
						'msg' => 'mediauploader-license-gfdl',
						'wikitext' => '{{subst:int:mediauploader-license-gfdl}}'
					],
					'none' => [
						'msg' => 'mediauploader-license-none',
						'wikitext' => '{{subst:int:mediauploader-license-none-text}}'
					],
				],
				'licensing' => [
					'thirdParty' => [
						'type' => 'radio',
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
						'type' => 'choice',
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
				'mediauploader-license-cc-by-nc-nd-2.0',
				'mediauploader-source-ownwork-cc-by-nc-nd-explain',
				'mediauploader-license-fal',
				'mediauploader-license-gfdl',
				'mediauploader-license-none',
				// Head and subhead messages
				'mediauploader-license-cc-head',
				'mediauploader-license-cc-subhead',
				'mediauploader-license-custom-head',
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
