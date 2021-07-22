<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use BagOStuff;
use FormatJson;
use MediaWiki\Extension\MediaUploader\Campaign\Validator;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWikiUnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @ingroup Upload
 * @ingroup medium
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\Validator
 */
class ValidatorTest extends MediaWikiUnitTestCase {

	/**
	 * Checks whether the campaign schema itself is:
	 * - a valid YAML file
	 * - a valid JSON Schema definition
	 */
	public function testCampaignSchemaSchema() {
		// Parse the schema file, this should not throw any exception
		$schema = Yaml::parseFile(
			MU_SCHEMA_DIR . 'campaign.yaml',
			Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP
		);

		$validator = new \JsonSchema\Validator();
		$validator->validate(
			$schema,
			[ '$ref' => 'file://' . MU_SCHEMA_DIR . 'json-schema-draft-4.json' ]
		);

		$this->assertTrue(
			$validator->isValid(),
			// Print validation errors
			FormatJson::encode( $validator->getErrors(), true )
		);
	}

	public function provideValidate(): iterable {
		// Basic cases
		yield 'simplest valid campaign' => [
			[ 'enabled' => true ],
			[],
			true
		];

		yield 'empty campaign' => [
			[],
			[],
			false
		];

		yield 'invalid property' => [
			[
				'enabled' => true,
				'someProperty' => [],
			],
			[],
			false
		];

		yield 'general settings' => [
			[
				'enabled' => true,
				'title' => 'some campaign',
				'description' => 'desc',
				'start' => '01-01-2020',
				'end' => '31-01-2020',
			],
			[],
			true
		];

		// Licensing
		yield 'licensing.ownWork referencing undefined licenses' => [
			[
				'enabled' => true,
				'licensing' => [
					'ownWork' => [
						'defaults' => 'lic1',
						'licenses' => [ 'lic1', 'lic2' ],
					]
				]
			],
			[],
			false
		];

		yield 'licensing.ownWork referencing defined licenses' => [
			[
				'enabled' => true,
				'licensing' => [
					'ownWork' => [
						'defaults' => 'lic1',
						'licenses' => [ 'lic1', 'lic2' ],
					]
				]
			],
			[ 'lic1' => [], 'lic2' => [] ],
			true
		];

		yield 'licensing.ownWork.defaults is an array' => [
			[
				'enabled' => true,
				'licensing' => [
					'ownWork' => [
						'defaults' => [ 'lic1' ],
						'licenses' => [ 'lic1', 'lic2' ],
					]
				]
			],
			[ 'lic1' => [], 'lic2' => [] ],
			true
		];

		yield 'licensing.thirdParty referencing undefined licenses' => [
			[
				'enabled' => true,
				'licensing' => [
					'thirdParty' => [
						'defaults' => 'lic1',
						'licenseGroups' => [
							[
								'licenses' => [ 'lic1', 'lic2' ]
							]
						]
					]
				]
			],
			[],
			false
		];

		yield 'licensing.thirdParty referencing defined licenses' => [
			[
				'enabled' => true,
				'licensing' => [
					'thirdParty' => [
						'defaults' => 'lic1',
						'licenseGroups' => [
							[
								'head' => 'head',
								'subhead' => 'subhead',
								'licenses' => [ 'lic1', 'lic2' ]
							]
						]
					]
				]
			],
			[ 'lic1' => [], 'lic2' => [] ],
			true
		];

		yield 'licensing.thirdParty.defaults is an array' => [
			[
				'enabled' => true,
				'licensing' => [
					'thirdParty' => [
						'defaults' => [ 'lic1', 'lic2' ],
						'licenseGroups' => [
							[
								'head' => 'head',
								'subhead' => 'subhead',
								'licenses' => [ 'lic1', 'lic2' ]
							]
						]
					]
				]
			],
			[ 'lic1' => [], 'lic2' => [] ],
			true
		];

		// (before|while|after)Active modifiers
		yield 'beforeActive' => [
			[
				'enabled' => false,
				'beforeActive' => [
					'display' => [
						'headerLabel' => 'header',
						'homeButton' => [ 'label' => 'Home' ],
					]
				]
			],
			[],
			true
		];

		yield 'whileActive' => [
			[
				'enabled' => false,
				'whileActive' => [
					'display' => [
						'thanksLabel' => 'header',
						'homeButton' => [ 'label' => 'Home' ],
					],
					'autoAdd' => [
						'categories' => [ 'Some category' ],
						'wikitext' => 'auto-added WT',
					]
				]
			],
			[],
			true
		];

		yield 'afterActive' => [
			[
				'enabled' => false,
				'afterActive' => [
					'display' => [
						'headerLabel' => 'header',
						'beginButton' => [ 'label' => 'Home' ],
					]
				]
			],
			[],
			true
		];
	}

	/**
	 * This is not a very "unit" test, as it tests the integration with the
	 * JsonSchema library as well. It seemed like a sensible choice to me,
	 * the test is about verifying if validation works as a whole.
	 *
	 * @param array $toValidate Campaign config to validate
	 * @param array $licensesSetting The 'licenses' setting of the global config
	 * @param bool $expectedIsValid
	 *
	 * @dataProvider provideValidate
	 */
	public function testValidate(
		array $toValidate,
		array $licensesSetting,
		bool $expectedIsValid
	) {
		$rawConfig = $this->createMock( RawConfig::class );
		$rawConfig->expects( $this->once() )
			->method( 'getSetting' )
			->with( 'licenses' )
			->willReturn( $licensesSetting );

		$cache = $this->createMock( BagOStuff::class );
		$cache->expects( $this->once() )
			->method( 'makeKey' )
			->with( 'mediauploader', 'campaign-schema' )
			->willReturn( 'dummy-key' );
		$cache->expects( $this->once() )
			->method( 'get' )
			->with( 'dummy-key' )
			->willReturn( false );

		$validator = new Validator( $rawConfig, $cache );

		$status = $validator->validate( $toValidate );

		if ( $expectedIsValid ) {
			$this->assertEmpty(
				$status->getErrors(),
				// print the validation errors
				FormatJson::encode( $status->getErrors(), true )
			);
		} else {
			$this->assertNotEmpty( $status->getErrors(), 'validate()->getErrors()' );
		}

		$this->assertSame(
			$expectedIsValid,
			$status->isGood(),
			'validate()->isGood()'
		);
	}
}
