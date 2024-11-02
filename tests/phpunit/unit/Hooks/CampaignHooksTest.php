<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Hooks;

use IContextSource;
use ManualLogEntry;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Hooks\CampaignHooks;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Title;
use User;
use WikiPage;
use WikitextContent;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Hooks\CampaignHooks
 */
class CampaignHooksTest extends MediaWikiUnitTestCase {

	private const DUMMY_CAMPAIGN_NAME = 'Dummy';
	private const DUMMY_CAMPAIGN_ID = 1234;

	public function testPageDeleteComplete_notCampaignNS() {
		$page = $this->createMock( ProperPageIdentity::class );
		$page->expects( $this->atLeastOnce() )
			->method( 'getNamespace' )
			->willReturn( 1 );

		$hooks = $this->getCampaignHooks();

		$hooks->onPageDeleteComplete(
			$page,
			$this->createNoOpMock( Authority::class ),
			'',
			123,
			$this->createNoOpMock( RevisionRecord::class ),
			$this->createNoOpMock( ManualLogEntry::class ),
			10
		);
	}

	public function testPageDeleteComplete_campaignNS() {
		$page = $this->createMock( ProperPageIdentity::class );
		$page->expects( $this->atLeastOnce() )
			->method( 'getNamespace' )
			->willReturn( NS_CAMPAIGN );

		$campaignStore = $this->createMock( CampaignStore::class );
		$campaignStore->expects( $this->once() )
			->method( 'deleteCampaignByPageId' )
			->with( self::DUMMY_CAMPAIGN_ID );

		$hooks = $this->getCampaignHooks( $campaignStore );

		$hooks->onPageDeleteComplete(
			$page,
			$this->createNoOpMock( Authority::class ),
			'',
			self::DUMMY_CAMPAIGN_ID,
			$this->createNoOpMock( RevisionRecord::class ),
			$this->createNoOpMock( ManualLogEntry::class ),
			10
		);
	}

	public function testEditFilterMergedContent_notCampaignNS() {
		$context = $this->createMock( IContextSource::class );
		$context->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleNotInCampaignNamespace() );

		$hooks = $this->getCampaignHooks();

		$this->assertTrue(
			$hooks->onEditFilterMergedContent(
				$context,
				$this->createNoOpMock( WikitextContent::class ),
				$this->createNoOpMock( Status::class ),
				'',
				$this->getOtherUser(),
				false
			),
			'onEditFilterMergedContent()'
		);
	}

	public function testEditFilterMergedContent_globalConfigAnchor() {
		$context = $this->createMock( IContextSource::class );
		$context->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleForGlobalConfigAnchor() );

		$hooks = $this->getCampaignHooks();

		$this->assertTrue(
			$hooks->onEditFilterMergedContent(
				$context,
				$this->createNoOpMock( CampaignContent::class ),
				$this->createNoOpMock( Status::class ),
				'',
				$this->getOtherUser(),
				false
			),
			'onEditFilterMergedContent()'
		);
	}

	public function testEditFilterMergedContent_valid() {
		$context = $this->createMock( IContextSource::class );
		$context->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleInCampaignNamespace() );

		$content = $this->createMock( CampaignContent::class );
		$content->expects( $this->once() )
			->method( 'getValidationStatus' )
			->willReturn( Status::newGood() );

		$status = Status::newGood();

		$hooks = $this->getCampaignHooks();

		$this->assertTrue(
			$hooks->onEditFilterMergedContent(
				$context,
				$content,
				$status,
				'',
				$this->getOtherUser(),
				false
			),
			'onEditFilterMergedContent()'
		);
		$this->assertTrue( $status->isGood() );
	}

	public function testEditFilterMergedContent_invalid() {
		$context = $this->createMock( IContextSource::class );
		$context->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleInCampaignNamespace() );

		$validationStatus = Status::newFatal( 'dummy-code' );

		$content = $this->createMock( CampaignContent::class );
		$content->expects( $this->once() )
			->method( 'getValidationStatus' )
			->willReturn( $validationStatus );

		$status = Status::newGood();

		$hooks = $this->getCampaignHooks();

		$this->assertFalse(
			$hooks->onEditFilterMergedContent(
				$context,
				$content,
				$status,
				'',
				$this->getOtherUser(),
				false
			),
			'onEditFilterMergedContent()'
		);
		$this->assertTrue( $status->hasMessage( 'dummy-code' ) );
	}

	public function testLinksUpdateComplete_notCampaign() {
		$linksUpdate = $this->createMock( LinksUpdate::class );
		$linksUpdate->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleNotInCampaignNamespace() );

		$hooks = $this->getCampaignHooks();

		$hooks->onLinksUpdateComplete( $linksUpdate, null );
	}

	/**
	 * Tests the case where LinksUpdate is triggered by an edit by the "magic"
	 * MediaUploader user. The global config cache should not be invalidated.
	 */
	public function testLinksUpdateComplete_configAnchorMagicUser() {
		$linksUpdate = $this->createMock( LinksUpdate::class );
		$linksUpdate->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleForGlobalConfigAnchor() );
		$linksUpdate->expects( $this->atLeastOnce() )
			->method( 'getTriggeringUser' )
			->willReturn( $this->getMediaUploaderUser() );

		$hooks = $this->getCampaignHooks();

		$hooks->onLinksUpdateComplete( $linksUpdate, null );
	}

	/**
	 * Tests the case where LinksUpdate on the global config anchor page is
	 * triggered by a different user. This should cause global config cache
	 * invalidation.
	 */
	public function testLinksUpdateComplete_configAnchorNotMagicUser() {
		$linksUpdate = $this->createMock( LinksUpdate::class );
		$linksUpdate->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleForGlobalConfigAnchor() );
		$linksUpdate->expects( $this->atLeastOnce() )
			->method( 'getTriggeringUser' )
			->willReturn( $this->getOtherUser() );

		$invalidator = $this->createMock( ConfigCacheInvalidator::class );
		$invalidator->expects( $this->once() )
			->method( 'invalidate' );

		$hooks = $this->getCampaignHooks( null, $invalidator );

		$hooks->onLinksUpdateComplete( $linksUpdate, null );
	}

	public function testLinksUpdateComplete_campaign() {
		$linksUpdate = $this->createMock( LinksUpdate::class );
		$linksUpdate->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleInCampaignNamespace() );

		$invalidator = $this->createMock( ConfigCacheInvalidator::class );
		$invalidator->expects( $this->once() )
			->method( 'invalidate' )
			->with( self::DUMMY_CAMPAIGN_NAME );

		$hooks = $this->getCampaignHooks( null, $invalidator );

		$hooks->onLinksUpdateComplete( $linksUpdate, null );
	}

	public function testDoCampaignUpdate() {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->expects( $this->once() )
			->method( 'getId' )
			->willReturn( 123 );

		$record = $this->createNoOpMock( CampaignRecord::class );

		$content = $this->createMock( CampaignContent::class );
		$content->expects( $this->once() )
			->method( 'newCampaignRecord' )
			->willReturn( $record );

		$campaignStore = $this->createMock( CampaignStore::class );
		$campaignStore->expects( $this->once() )
			->method( 'upsertCampaign' )
			->with( $record );

		$hooks = $this->getCampaignHooks( $campaignStore );

		$hooks->doCampaignUpdate( $wikiPage, $content );
	}

	public function testPageDelete_configAnchor() {
		$page = $this->createMock( ProperPageIdentity::class );
		$page->expects( $this->once() )
			->method( 'getNamespace' )
			->willReturn( NS_CAMPAIGN );
		$page->expects( $this->once() )
			->method( 'getDBkey' )
			->willReturn( CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY );

		$status = $this->createMock( Status::class );
		$status->expects( $this->once() )
			->method( 'fatal' )
			->with( 'mediauploader-global-config-anchor' );

		$hooks = $this->getCampaignHooks();

		$reason = '';
		$this->assertFalse(
			$hooks->onPageDelete(
				$page,
				$this->createNoOpMock( Authority::class ),
				$reason,
				$status,
				false
			),
			'onPageDelete()'
		);
	}

	public function testArticleDelete_notConfigAnchor() {
		$page = $this->createMock( ProperPageIdentity::class );
		$page->expects( $this->once() )
			->method( 'getNamespace' )
			->willReturn( 0 );
		$page->expects( $this->once() )
			->method( 'getDBkey' )
			->willReturn( 'some other page' );

		$status = $this->createNoOpMock( Status::class );

		$hooks = $this->getCampaignHooks();

		$reason = '';
		$this->assertTrue(
			$hooks->onPageDelete(
				$page,
				$this->createNoOpMock( Authority::class ),
				$reason,
				$status,
				false
			),
			'onPageDelete()'
		);
	}

	/**
	 * Returns a CampaignHooks object for testing.
	 * Null arguments will be changed to be no-op mocks.
	 *
	 * @param CampaignStore|null $campaignStore
	 * @param ConfigCacheInvalidator|null $cacheInvalidator
	 *
	 * @return CampaignHooks
	 */
	private function getCampaignHooks(
		?CampaignStore $campaignStore = null,
		?ConfigCacheInvalidator $cacheInvalidator = null
	): CampaignHooks {
		return new CampaignHooks(
			$campaignStore ?:
				$this->createNoOpMock( CampaignStore::class ),
			$cacheInvalidator ?:
				$this->createNoOpMock( ConfigCacheInvalidator::class )
		);
	}

	/**
	 * @return MockObject|Title
	 */
	private function getTitleNotInCampaignNamespace() {
		$title = $this->createMock( Title::class );
		$title->method( 'inNamespace' )
			->with( NS_CAMPAIGN )
			->willReturn( false );
		$title->method( 'isSameLinkAs' )
			->willReturn( false );

		return $title;
	}

	/**
	 * Returns Title for page Campaign:Dummy
	 *
	 * @return MockObject|Title
	 */
	private function getTitleInCampaignNamespace() {
		$title = $this->createMock( Title::class );

		$title->method( 'inNamespace' )
			->with( NS_CAMPAIGN )
			->willReturn( true );

		$title->method( 'getDBkey' )
			->willReturn( self::DUMMY_CAMPAIGN_NAME );

		$title->method( 'isSameLinkAs' )
			->willReturn( false );

		$title->method( 'getId' )
			->willReturn( self::DUMMY_CAMPAIGN_ID );

		return $title;
	}

	/**
	 * Returns Title for the global config anchor page.
	 *
	 * @return MockObject|Title
	 */
	private function getTitleForGlobalConfigAnchor() {
		$title = $this->createMock( Title::class );

		$title->method( 'inNamespace' )
			->with( NS_CAMPAIGN )
			->willReturn( true );

		$title->method( 'getDBkey' )
			->willReturn( CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY );

		$title->method( 'isSameLinkAs' )
			->willReturnCallback(
				static function ( LinkTarget $target ): bool {
					return $target->getNamespace() === NS_CAMPAIGN &&
						$target->getDBkey() === CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY;
				}
			);

		return $title;
	}

	/**
	 * Returns User:MediaUploader
	 *
	 * @return User|MockObject
	 */
	private function getMediaUploaderUser() {
		$user = $this->createMock( User::class );
		$user->method( 'getName' )
			->willReturn( 'MediaUploader' );
		$user->method( 'isRegistered' )
			->willReturn( true );

		return $user;
	}

	/**
	 * Returns some other (non-system) user
	 *
	 * @return User|MockObject
	 */
	private function getOtherUser() {
		$user = $this->createMock( User::class );
		$user->method( 'getName' )
			->willReturn( 'DummyUser' );
		$user->method( 'isRegistered' )
			->willReturn( true );

		return $user;
	}
}
