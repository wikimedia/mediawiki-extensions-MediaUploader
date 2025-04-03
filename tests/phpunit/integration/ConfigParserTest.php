<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Extension\MediaUploader\Config\ConfigParser;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use RequestContext;

/**
 * @group Upload
 * @group Database
 * @group medium
 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigParser
 *
 * @see \MediaWiki\Extension\MediaUploader\Tests\Unit\Config\ConfigParserTest for unit tests
 */
class ConfigParserTest extends MediaWikiIntegrationTestCase {

	private const PAGE_1_TITLE = 'MU_test_page_1';
	private const PAGE_1_CONTENT = 'content1';
	private const PAGE_2_TITLE = 'MU_test_page_2';
	private const PAGE_2_CONTENT = 'content2';

	private function getConfigParser( array $configToParse ): ConfigParser {
		$configParserFactory = MediaUploaderServices::getConfigParserFactory();
		$requestContext = RequestContext::getMain();

		return $configParserFactory->newConfigParser(
			$configToParse,
			ParserOptions::newFromContext( $requestContext )
		);
	}

	public function testParse() {
		$configParser = $this->getConfigParser(
			[
				'title' => '{{NAMESPACE}}',
				'description' => '{{PAGENAME}}'
			]
		);
		$expectedConfig = [
			'title' => 'Special',
			'description' => 'MediaUploader'
		];

		$parsedConfig = $configParser->getParsedConfig();
		$this->assertIsArray( $parsedConfig, 'parsed config' );
		$this->assertArrayEquals(
			$expectedConfig,
			$parsedConfig,
			false,
			true,
			'parsed config'
		);
	}

	public function testParseWithTemplates() {
		// Edit the page twice to ensure different revision and page ids
		$this->editPage( self::PAGE_1_TITLE, 'dummy' );
		$status = $this->editPage( self::PAGE_1_TITLE, self::PAGE_1_CONTENT );
		$page1Id = $status->value['revision-record']->getPageId();
		$page1RevId = $status->value['revision-record']->getId();

		$status = $this->editPage( self::PAGE_2_TITLE, self::PAGE_2_CONTENT );
		$page2Id = $status->value['revision-record']->getPageId();
		$page2RevId = $status->value['revision-record']->getId();

		$configParser = $this->getConfigParser(
			[
				'title' => '{{:' . self::PAGE_1_TITLE . '}}',
				'description' => '{{:' . self::PAGE_2_TITLE . '}}'
			]
		);
		$expectedConfig = [
			'title' => self::PAGE_1_CONTENT,
			'description' => self::PAGE_2_CONTENT
		];
		$expectedTemplates = [
			0 => [
				self::PAGE_1_TITLE => [
					$page1Id,
					$page1RevId
				],
				self::PAGE_2_TITLE => [
					$page2Id,
					$page2RevId
				],
			]
		];

		$parsedConfig = $configParser->getParsedConfig();
		$this->assertIsArray( $parsedConfig, 'parsed config' );
		$this->assertArrayEquals(
			$expectedConfig,
			$parsedConfig,
			false,
			true,
			'parsed config'
		);

		$usedTemplates = $configParser->getTemplates();
		$this->assertIsArray( $usedTemplates, 'used templates' );
		$this->assertArrayEquals(
			$expectedTemplates,
			$usedTemplates,
			true,
			true,
			'used templates'
		);
	}
}
