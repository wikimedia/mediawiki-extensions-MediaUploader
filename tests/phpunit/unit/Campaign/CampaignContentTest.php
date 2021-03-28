<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWikiUnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent
 */
class CampaignContentTest extends MediaWikiUnitTestCase {

	public function testGetData_validYaml() {
		$content = new CampaignContent( 'enabled: true' );

		$status = $content->getData();

		$this->assertTrue( $status->isGood(), 'Status::isGood()' );
		$this->assertArrayEquals(
			[ 'enabled' => true ],
			$status->getValue(),
			false,
			true,
			'Status::getValue()'
		);
	}

	/**
	 * getData() should use the YAML parser option that disallows parsing
	 * PHP objects. No funny business allowed here.
	 */
	public function testGetData_unsafeYaml() {
		$toParse = '!php/object \'O:8:"stdClass":1:{s:4:"prop";s:3:"val";}\'';

		// First, ensure the unsafe string does not throw an exception when parsed
		// without additional options.
		Yaml::parse( $toParse );

		$content = new CampaignContent( $toParse );

		$status = $content->getData();

		$this->assertFalse( $status->isGood(), 'Status::isGood()' );
		$errors = $status->getErrors();
		$this->assertCount( 1, $errors, 'number of errors' );
		$this->assertSame(
			'mediauploader-yaml-parse-error',
			$errors[0]['message'],
			"'message' key of the first error in the array"
		);
	}
}
