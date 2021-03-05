<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Hooks;

use CampaignContent;
use MediaWiki\Extension\MediaUploader\Hooks\CampaignContentHooks;
use MediaWikiUnitTestCase;
use Title;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Hooks\CampaignContentHooks
 */
class CampaignContentHooksTest extends MediaWikiUnitTestCase {

	public function provideContentModelCanBeUsedOn() : iterable {
		yield 'Campaign content model, Campaign: namespace' => [
			CampaignContent::MODEL_NAME, true, true
		];

		yield 'Campaign content model, other namespace' => [
			CampaignContent::MODEL_NAME, false, false
		];

		yield 'other content model, Campaign: namespace' => [
			'OtherModel', true, false
		];

		yield 'other content model, other namespace' => [
			'OtherModel', false, true
		];
	}

	/**
	 * @param string $contentModel
	 * @param bool $inCampaignNamespace
	 * @param bool $expected
	 *
	 * @dataProvider provideContentModelCanBeUsedOn
	 */
	public function testContentModelCanBeUsedOn(
		string $contentModel,
		bool $inCampaignNamespace,
		bool $expected
	) {
		$hooks = new CampaignContentHooks();
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'inNamespace' )
			->with( NS_CAMPAIGN )
			->willReturn( $inCampaignNamespace );

		$ok = true;
		$this->assertSame(
			$expected,
			$hooks->onContentModelCanBeUsedOn( $contentModel, $title, $ok ),
			'onContentModelCanBeUsedOn()'
		);
		$this->assertSame( $expected, $ok, '&$ok parameter' );
	}

	public function provideCodeEditorGetPageLanguage() : iterable {
		yield 'title not in Campaign namespace' => [ false, null ];
		yield 'title in Campaign namespace' => [ true, 'json' ];
	}

	/**
	 * @param bool $inCampaignNamespace
	 * @param string|null $expectedLang
	 *
	 * @dataProvider provideCodeEditorGetPageLanguage
	 */
	public function testCodeEditorGetPageLanguage(
		bool $inCampaignNamespace,
		?string $expectedLang
	) {
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'inNamespace' )
			->with( NS_CAMPAIGN )
			->willReturn( $inCampaignNamespace );

		$lang = null;
		$this->assertTrue(
			CampaignContentHooks::onCodeEditorGetPageLanguage( $title, $lang ),
			'onCodeEditorGetPageLanguage()'
		);
		$this->assertSame(
			$expectedLang,
			$lang,
			'&$lang parameter'
		);
	}
}
