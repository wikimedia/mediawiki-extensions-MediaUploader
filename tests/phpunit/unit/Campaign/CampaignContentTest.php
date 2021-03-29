<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\Validator;
use MediaWikiUnitTestCase;
use Status;
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

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::isValid
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::getValidationStatus
	 */
	public function testIsValid_valid() {
		$content = new CampaignContent( 'enabled: true' );

		$validator = $this->createMock( Validator::class );
		$validator->expects( $this->once() )
			->method( 'validate' )
			->willReturn( Status::newGood() );

		$content->setServices( null, $validator );

		$this->assertTrue(
			$content->getValidationStatus()->isGood(),
			'first call: getValidationStatus()->isGood()'
		);

		// Call the method twice to ensure the campaign is validated only once
		$this->assertTrue(
			$content->getValidationStatus()->isGood(),
			'second call: getValidationStatus()->isGood()'
		);

		$this->assertTrue( $content->isValid(), 'isValid()' );
	}

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::isValid
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::getValidationStatus
	 */
	public function testIsValid_invalidSyntax() {
		// Try to parse some very invalid YAML
		$content = new CampaignContent( '[[[[' );

		$content->setServices(
			null,
			$this->createNoOpMock( Validator::class )
		);

		$status = $content->getValidationStatus();
		$this->assertFalse( $status->isGood(), 'Status::isGood()' );

		$errors = $status->getErrors();
		$this->assertCount( 1, $errors, 'number of errors' );
		$this->assertSame(
			'mediauploader-yaml-parse-error',
			$errors[0]['message'],
			"'message' key of the first error in the array"
		);

		$this->assertFalse( $content->isValid(), 'isValid()' );
	}

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::isValid
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::getValidationStatus
	 */
	public function testIsValid_invalidSchema() {
		// This is valid YAML, but it violates the campaign schema
		$content = new CampaignContent( '- a' );

		$validator = $this->createMock( Validator::class );
		$validator->expects( $this->once() )
			->method( 'validate' )
			->willReturn( Status::newFatal( 'dummy-message' ) );

		$content->setServices( null, $validator );

		$status = $content->getValidationStatus();
		$this->assertFalse( $status->isGood(), 'Status::isGood()' );

		$errors = $status->getErrors();
		$this->assertCount( 1, $errors, 'number of errors' );
		$this->assertSame(
			'dummy-message',
			$errors[0]['message'],
			"'message' key of the first error in the array"
		);

		$this->assertFalse( $content->isValid(), 'isValid()' );
	}
}
