<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\Validator;
use MediaWiki\Page\PageReferenceValue;
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

		$content->setServices( $validator );

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

	public function provideInvalidSyntax(): iterable {
		yield 'invalid YAML' => [ '[[[[' ];
		yield 'invalid JSON' => [ '{"enabled":false' ];
	}

	/**
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::isValid
	 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::getValidationStatus
	 *
	 * @dataProvider provideInvalidSyntax
	 */
	public function testIsValid_invalidSyntax( string $text ) {
		$content = new CampaignContent( $text );

		$content->setServices(
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

		$content->setServices( $validator );

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

	public function provideNewCampaignRecord(): iterable {
		yield 'Invalid markup' => [
			'[', false, CampaignRecord::CONTENT_INVALID_FORMAT, null
		];
		yield 'Valid markup, but violates the schema' => [
			'- a', false, CampaignRecord::CONTENT_INVALID_SCHEMA, [ 'a' ]
		];
		yield 'Partially valid, has the "enabled" option set' => [
			"enabled: true\naaaa: aaaa",
			true,
			CampaignRecord::CONTENT_INVALID_SCHEMA,
			[ 'enabled' => true, 'aaaa' => 'aaaa' ]
		];
		yield 'All valid, enabled' => [
			"enabled: true",
			true,
			CampaignRecord::CONTENT_VALID,
			[ 'enabled' => true ]
		];
		yield 'All valid, disabled' => [
			"enabled: false",
			false,
			CampaignRecord::CONTENT_VALID,
			[ 'enabled' => false ]
		];
	}

	/**
	 * @param string $contentText
	 * @param bool $expectedEnabled
	 * @param int $expectedValidity
	 * @param array|null $expectedContent
	 *
	 * @covers       \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent::newCampaignRecord
	 * @dataProvider provideNewCampaignRecord
	 */
	public function testNewCampaignRecord(
		string $contentText,
		bool $expectedEnabled,
		int $expectedValidity,
		?array $expectedContent
	) {
		if ( $expectedValidity === CampaignRecord::CONTENT_INVALID_FORMAT ) {
			$validator = $this->createNoOpMock( Validator::class );
		} else {
			$status = $expectedValidity === CampaignRecord::CONTENT_VALID
				? Status::newGood()
				: Status::newFatal( 'dummy-message' );

			$validator = $this->createMock( Validator::class );
			$validator->expects( $this->once() )
				->method( 'validate' )
				->willReturn( $status );
		}

		$content = new CampaignContent( $contentText );
		$content->setServices( $validator );
		$pageRef = PageReferenceValue::localReference( NS_CAMPAIGN, 'dummy' );

		$record = $content->newCampaignRecord( $pageRef, 123 );

		$this->assertSame(
			123,
			$record->getPageId(),
			'CampaignRecord::getPageId()'
		);
		$this->assertSame(
			$pageRef,
			$record->getPage(),
			'CampaignRecord::getPage()'
		);
		$this->assertSame(
			$expectedEnabled,
			$record->isEnabled(),
			'CampaignRecord::isEnabled()'
		);
		$this->assertSame(
			$expectedValidity,
			$record->getValidity(),
			'CampaignRecord::getValidity()'
		);

		if ( $expectedContent === null ) {
			$this->assertNull(
				$record->getContent(),
				'CampaignRecord::getContent()'
			);
		} else {
			$this->assertArrayEquals(
				$expectedContent,
				$record->getContent(),
				false,
				true,
				'CampaignRecord::getContent()'
			);
		}
	}

	public function testOverrideValidationStatus_invalidSchema() {
		$validator = $this->createMock( Validator::class );
		$validator->expects( $this->once() )
			->method( 'validate' )
			->willReturn( Status::newFatal( 'dummy message' ) );

		$content = new CampaignContent( 'garbled: input' );
		$content->setServices( $validator );
		$pageRef = PageReferenceValue::localReference( NS_CAMPAIGN, 'dummy' );

		// We are a system user, so override the checks
		$content->overrideValidationStatus();

		// The content object should pretend everything is okay
		$this->assertTrue(
			$content->getData()->isGood(),
			'CampaignContent::getData()'
		);
		$this->assertTrue(
			$content->getValidationStatus()->isGood(),
			'CampaignContent::getValidationStatus()'
		);

		// Make a CampaignRecord. It should bear the real validation status.
		$record = $content->newCampaignRecord( $pageRef, 123 );

		$this->assertSame(
			CampaignRecord::CONTENT_INVALID_SCHEMA,
			$record->getValidity(),
			'CampaignRecord::getValidity()'
		);
	}

	public function testOverrideValidationStatus_valid() {
		$validator = $this->createMock( Validator::class );
		$validator->expects( $this->once() )
			->method( 'validate' )
			->willReturn( Status::newGood() );

		$content = new CampaignContent( 'enabled: true' );
		$content->setServices( $validator );
		$pageRef = PageReferenceValue::localReference( NS_CAMPAIGN, 'dummy' );

		// We are a system user, so override the checks
		$content->overrideValidationStatus();

		$record = $content->newCampaignRecord( $pageRef );

		$this->assertSame(
			CampaignRecord::CONTENT_VALID,
			$record->getValidity(),
			'CampaignRecord::getValidity()'
		);
		$this->assertArrayEquals(
			[ 'enabled' => true ],
			$record->getContent(),
			false,
			true,
			'CampaignRecord::getContent()'
		);
		$this->assertTrue(
			$record->isEnabled(),
			'CampaignRecord::isEnabled()'
		);
	}
}
