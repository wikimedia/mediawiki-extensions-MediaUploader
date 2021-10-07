<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use MediaWiki\Extension\MediaUploader\Config\ConfigParser;
use MediaWikiUnitTestCase;
use Parser;
use ParserFactory;
use ParserOptions;
use ParserOutput;
use Title;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser
 *
 * @see \MediaWiki\Extension\MediaUploader\Tests\Integration\ConfigParserTest for integration tests
 */
class ConfigParserTest extends MediaWikiUnitTestCase {

	private const PARSED_TEXT = 'Dummy parsed text.';

	/**
	 * Convenience function asserting that values returned by getTemplates() and
	 * getParsedConfig() are correct.
	 *
	 * @param array $expectedConfig
	 * @param array $actualConfig
	 * @param array $expectedTemplates
	 * @param array $actualTemplates
	 */
	private function assertResults(
		array $expectedConfig,
		array $actualConfig,
		array $expectedTemplates,
		array $actualTemplates
	) {
		$this->assertIsArray( $actualConfig, 'parsed config' );
		$this->assertArrayEquals(
			$expectedConfig,
			$actualConfig,
			false,
			true,
			'parsed config'
		);

		$this->assertIsArray( $actualTemplates, 'used templates' );
		$this->assertArrayEquals(
			$expectedTemplates,
			$actualTemplates,
			true,
			true,
			'used templates'
		);
	}

	/**
	 * Ensures that parsing is done only once per object instantiation.
	 *
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser::getTemplates
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser::getParsedConfig
	 */
	public function testParserInvokedOnlyOnce() {
		// setup mocks
		$toParse = [ 'description' => "'''Description!'''" ];

		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->expects( $this->once() )
			->method( 'getText' )
			->willReturn( self::PARSED_TEXT );
		$parserOutput->expects( $this->once() )
			->method( 'getTemplateIds' )
			->willReturn( [] );
		$parserOutput->expects( $this->once() )
			->method( 'getTemplates' )
			->willReturn( [] );

		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->once() )
			->method( 'parse' )
			->willReturn( $parserOutput );

		$parserFactory = $this->createMock( ParserFactory::class );
		$parserFactory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $parser );

		$parserOptions = $this->createNoOpMock( ParserOptions::class );
		$title = $this->createNoOpMock( Title::class );

		// test
		$configParser = new ConfigParser(
			$title,
			$parserFactory,
			$parserOptions,
			$toParse
		);

		$parsedConfig = $configParser->getParsedConfig();
		$this->assertSame(
			$parsedConfig,
			$configParser->getParsedConfig(),
			'the same parsed config is returned on consecutive calls'
		);
		$usedTemplates = $configParser->getTemplates();
		$this->assertSame(
			$usedTemplates,
			$configParser->getTemplates(),
			'the same used templates array is returned on consecutive calls'
		);

		$this->assertResults(
			[ 'description' => self::PARSED_TEXT ],
			$parsedConfig,
			[],
			$usedTemplates
		);
	}

	public function provideParse() {
		yield 'title and description are parsed' => [
			[
				'title' => 'Title to parse',
				'description' => 'Description to parse'
			],
			[
				'title' => self::PARSED_TEXT,
				'description' => self::PARSED_TEXT
			]
		];

		yield 'display section' => [
			[
				'display' => [
					'thanksLabel' => 'toParse',
					'homeButton' => [
						'label' => 'toParse',
						'target' => 'noparse'
					],
					'headerLabel' => 'toParse'
				],
			],
			[
				'display' => [
					'thanksLabel' => self::PARSED_TEXT,
					'homeButton' => [
						'label' => self::PARSED_TEXT,
						'target' => 'noparse'
					],
					'headerLabel' => self::PARSED_TEXT
				],
			]
		];

		yield 'tutorial enabled, wikitext present' => [
			[
				'tutorial' => [
					'enabled' => true,
					'skip' => true,
					'wikitext' => 'toParse'
				]
			],
			[
				'tutorial' => [
					'enabled' => true,
					'skip' => true,
					'html' => self::PARSED_TEXT
				]
			]
		];

		yield 'tutorial enabled, wikitext not present' => [
			[
				'tutorial' => [
					'enabled' => true,
					'skip' => false,
					'wikitext' => ''
				]
			],
			[
				'tutorial' => [
					'enabled' => false,
					'skip' => true,
					'html' => ''
				]
			]
		];

		yield 'tutorial disabled, wikitext present' => [
			[
				'tutorial' => [
					'enabled' => false,
					'skip' => false,
					'wikitext' => 'toParse'
				]
			],
			[
				'tutorial' => [
					'enabled' => false,
					'skip' => true,
					'html' => ''
				]
			]
		];

		yield 'fields section' => [
			[
				'fields' => [
					[
						'label' => 'toParse',
						'type' => 'select',
						'options' => [
							'v1' => 'toParse1',
							'v2' => 'toParse2'
						]
					],
					[
						'label' => 'toParse',
						'maxLength' => 25,
						'type' => 'text'
					]
				]
			],
			[
				'fields' => [
					[
						'label' => self::PARSED_TEXT,
						'type' => 'select',
						'options' => [
							'v1' => self::PARSED_TEXT,
							'v2' => self::PARSED_TEXT
						]
					],
					[
						'label' => self::PARSED_TEXT,
						'maxLength' => 25,
						'type' => 'text'
					]
				]
			]
		];

		yield '(while|after|before)Active' => [
			[
				'whileActive' => [
					'display' => [
						'headerLabel' => 'toParse',
						'thanksLabel' => 'toParse'
					],
					'dummyKey' => 'noparse'
				],
				'beforeActive' => [
					'display' => [
						'headerLabel' => 'toParse'
					],
					'dummyKey' => 'noparse'
				],
				'afterActive' => [
					'display' => [
						'headerLabel' => 'toParse'
					],
					'dummyKey' => 'noparse'
				]
			],
			[
				'whileActive' => [
					'display' => [
						'headerLabel' => self::PARSED_TEXT,
						'thanksLabel' => self::PARSED_TEXT
					],
					'dummyKey' => 'noparse'
				],
				'beforeActive' => [
					'display' => [
						'headerLabel' => self::PARSED_TEXT
					],
					'dummyKey' => 'noparse'
				],
				'afterActive' => [
					'display' => [
						'headerLabel' => self::PARSED_TEXT
					],
					'dummyKey' => 'noparse'
				]
			]
		];

		$otherSectionsTestArray = [
			'defaults' => [
				'caption' => 'Test caption'
			],
			'licenses' => [
				'cc-by-sa-4.0' => [
					'msg' => 'mediauploader-license-cc-by-sa-4.0',
					'icons' => [ 'cc-by', 'cc-sa' ],
					'url' => '//creativecommons.org/licenses/by-sa/4.0/',
					'languageCodePrefix' => 'deed.'
				]
			],
			'minDescriptionLength' => 5,
			'alternativeUploadToolsPage' => 'Commons:Upload_tools',
		];

		yield 'other sections are not parsed' => [
			$otherSectionsTestArray,
			$otherSectionsTestArray
		];

		yield 'multiple sections' => [
			[
				'title' => 'Title to parse',
				'description' => 'Description to parse',
				'defaults' => [
					'caption' => 'Test caption'
				],
				'fields' => [
					[
						'label' => 'toParse',
						'type' => 'select',
						'options' => [
							'v1' => 'toParse1',
							'v2' => 'toParse2'
						]
					],
					[
						'label' => 'toParse',
						'maxLength' => 25,
						'type' => 'text'
					]
				]
			],
			[
				'title' => self::PARSED_TEXT,
				'description' => self::PARSED_TEXT,
				'defaults' => [
					'caption' => 'Test caption'
				],
				'fields' => [
					[
						'label' => self::PARSED_TEXT,
						'type' => 'select',
						'options' => [
							'v1' => self::PARSED_TEXT,
							'v2' => self::PARSED_TEXT
						]
					],
					[
						'label' => self::PARSED_TEXT,
						'maxLength' => 25,
						'type' => 'text'
					]
				]
			]
		];
	}

	/**
	 * A lot of config parsing cases trying to cover as many branches as possible.
	 *
	 * @dataProvider provideParse
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser::parseConfig
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser::parseArrayValues
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser::parseValue
	 *
	 * @param array $toParse
	 * @param array $expectedConfig
	 */
	public function testParse( array $toParse, array $expectedConfig ) {
		// setup mocks
		$parserOutput = $this->createMock( ParserOutput::class );

		// ParserOutput here always returns the same text, which is to simplify the test
		// a bit. Integration tests cover this a bit better.
		$parserOutput->method( 'getText' )
			->willReturn( self::PARSED_TEXT );
		$parserOutput->method( 'getTemplateIds' )
			->willReturn( [] );
		$parserOutput->method( 'getTemplates' )
			->willReturn( [] );

		$parser = $this->createMock( Parser::class );
		$parser->method( 'parse' )
			->willReturn( $parserOutput );

		$parserFactory = $this->createMock( ParserFactory::class );
		$parserFactory->method( 'create' )
			->willReturn( $parser );

		$parserOptions = $this->createNoOpMock( ParserOptions::class );
		$title = $this->createNoOpMock( Title::class );

		// test
		$configParser = new ConfigParser(
			$title,
			$parserFactory,
			$parserOptions,
			$toParse
		);

		$parsedConfig = $configParser->getParsedConfig();
		$usedTemplates = $configParser->getTemplates();
		$this->assertResults(
			$expectedConfig,
			$parsedConfig,
			[],
			$usedTemplates
		);
	}

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser::getTemplates
	 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser::updateTemplates
	 */
	public function testUsedTemplates() {
		// setup mocks
		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->method( 'getText' )
			->willReturn( self::PARSED_TEXT );

		$parserOutput->method( 'getTemplates' )
			->willReturnOnConsecutiveCalls(
				[],
				[
					0 => [
						'Test page' => 1,
						'Test 2' => 2
					]
				],
				[
					0 => [
						'Test 3' => 3
					],
					1 => [
						'Test1' => 4,
						'Test2' => 5
					]
				]
			);

		$parserOutput->method( 'getTemplateIds' )
			->willReturnOnConsecutiveCalls(
				[],
				[
					0 => [
						'Test page' => 123,
						'Test 2' => 124
					]
				],
				[
					0 => [
						'Test 3' => 125
					],
					1 => [
						'Test1' => 126,
						'Test2' => 127
					]
				]
			);

		$expectedTemplates = [
			0 => [
				'Test page' => [ 1, 123 ],
				'Test 2' => [ 2, 124 ],
				'Test 3' => [ 3, 125 ]
			],
			1 => [
				'Test1' => [ 4, 126 ],
				'Test2' => [ 5, 127 ]
			]
		];

		$parser = $this->createMock( Parser::class );
		$parser->method( 'parse' )
			->willReturn( $parserOutput );

		$parserFactory = $this->createMock( ParserFactory::class );
		$parserFactory->method( 'create' )
			->willReturn( $parser );

		$parserOptions = $this->createNoOpMock( ParserOptions::class );
		$title = $this->createNoOpMock( Title::class );

		$toParse = [
			'title' => 'test title',
			'description' => 'test description',
			'display' => [
				'headerLabel' => 'test header'
			]
		];
		$expectedConfig = [
			'title' => self::PARSED_TEXT,
			'description' => self::PARSED_TEXT,
			'display' => [
				'headerLabel' => self::PARSED_TEXT
			]
		];

		// test
		$configParser = new ConfigParser(
			$title,
			$parserFactory,
			$parserOptions,
			$toParse
		);

		$parsedConfig = $configParser->getParsedConfig();
		$usedTemplates = $configParser->getTemplates();
		$this->assertResults(
			$expectedConfig,
			$parsedConfig,
			$expectedTemplates,
			$usedTemplates
		);
	}
}
