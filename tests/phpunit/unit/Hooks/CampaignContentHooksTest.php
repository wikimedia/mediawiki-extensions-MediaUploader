<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Hooks;

use EditPage;
use ExtensionRegistry;
use MediaWiki\Extension\MediaUploader\Hooks\CampaignContentHooks;
use MediaWikiUnitTestCase;
use OutputPage;
use Title;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Hooks\CampaignContentHooks
 */
class CampaignContentHooksTest extends MediaWikiUnitTestCase {

	public static function provideContentModelCanBeUsedOn(): iterable {
		yield 'Campaign content model, Campaign: namespace' => [
			CONTENT_MODEL_CAMPAIGN, true, true
		];

		yield 'Campaign content model, other namespace' => [
			CONTENT_MODEL_CAMPAIGN, false, false
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
		$hooks = new CampaignContentHooks(
			$this->createNoOpMock( ExtensionRegistry::class )
		);
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

	public static function provideCodeEditorGetPageLanguage(): iterable {
		yield 'title not in Campaign namespace' => [ false, null ];
		yield 'title in Campaign namespace' => [ true, 'yaml' ];
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

	public function testShowEditFormInitial_noCodeEditor() {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->expects( $this->once() )
			->method( 'isLoaded' )
			->with( 'CodeEditor' )
			->willReturn( false );

		$hooks = new CampaignContentHooks( $extensionRegistry );

		$this->assertTrue(
			$hooks->onEditPage__showEditForm_initial(
				$this->createNoOpMock( EditPage::class ),
				$this->createNoOpMock( OutputPage::class )
			),
			'onEditPage__showEditForm_initial()'
		);
	}

	public function testShowEditFormInitial_notInCampaignNamespace() {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->expects( $this->once() )
			->method( 'isLoaded' )
			->with( 'CodeEditor' )
			->willReturn( true );

		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'getNamespace' )
			->willReturn( NS_MEDIAWIKI );

		$editPage = $this->createMock( EditPage::class );
		$editPage->expects( $this->once() )
			->method( 'getContextTitle' )
			->willReturn( $title );

		$hooks = new CampaignContentHooks( $extensionRegistry );

		$this->assertTrue(
			$hooks->onEditPage__showEditForm_initial(
				$editPage,
				$this->createNoOpMock( OutputPage::class )
			),
			'onEditPage__showEditForm_initial()'
		);
	}

	public function testShowEditFormInitial_valid() {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->expects( $this->once() )
			->method( 'isLoaded' )
			->with( 'CodeEditor' )
			->willReturn( true );

		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'getNamespace' )
			->willReturn( NS_CAMPAIGN );

		$editPage = $this->createMock( EditPage::class );
		$editPage->expects( $this->once() )
			->method( 'getContextTitle' )
			->willReturn( $title );

		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.mediaUploader.campaignEditor' );

		$hooks = new CampaignContentHooks( $extensionRegistry );

		$this->assertTrue(
			$hooks->onEditPage__showEditForm_initial(
				$editPage,
				$outputPage
			),
			'onEditPage__showEditForm_initial()'
		);
	}
}
