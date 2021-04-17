<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Hooks;

use Content;
use IContextSource;
use IDatabase;
use LinksUpdate;
use ManualLogEntry;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Hooks\CampaignHooks;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;
use WikitextContent;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Hooks\CampaignHooks
 */
class CampaignHooksTest extends MediaWikiUnitTestCase {

	private const DUMMY_CAMPAIGN_NAME = 'Dummy';

	public function testArticleDeleteComplete_notCampaignNS() {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleNotInCampaignNamespace() );

		$hooks = $this->getCampaignHooks();

		$this->assertTrue(
			$hooks->onArticleDeleteComplete(
				$wikiPage,
				$this->createNoOpMock( User::class ),
				'',
				123,
				$this->createNoOpMock( Content::class ),
				$this->createNoOpMock( ManualLogEntry::class ),
				10
			),
			'onArticleDeleteComplete()'
		);
	}

	public function testArticleDeleteComplete_campaignNS() {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleInCampaignNamespace() );

		$dbw = $this->createMock( IDatabase::class );
		$dbw->expects( $this->once() )
			->method( 'onTransactionPreCommitOrIdle' )
			->willReturnCallback(
				function ( $callback ) {
					$callback();
				}
			);

		// Should be called by $callback passed to onTransactionCommitOrIdle
		$dbw->expects( $this->once() )
			->method( 'delete' )
			->with(
				'uw_campaigns',
				[ 'campaign_name' => self::DUMMY_CAMPAIGN_NAME ]
			);

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->expects( $this->once() )
			->method( 'getConnection' )
			->with( DB_MASTER )
			->willReturn( $dbw );

		$hooks = $this->getCampaignHooks( $loadBalancer );

		$this->assertTrue(
			$hooks->onArticleDeleteComplete(
				$wikiPage,
				$this->createNoOpMock( User::class ),
				'',
				123,
				$this->createNoOpMock( Content::class ),
				$this->createNoOpMock( ManualLogEntry::class ),
				10
			),
			'onArticleDeleteComplete()'
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
				$this->createNoOpMock( User::class ),
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
				$this->createNoOpMock( User::class ),
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
				$this->createNoOpMock( User::class ),
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
				$this->createNoOpMock( User::class ),
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

		$this->assertTrue(
			$hooks->onLinksUpdateComplete( $linksUpdate, null ),
			'onLinksUpdateComplete()'
		);
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

		$this->assertTrue(
			$hooks->onLinksUpdateComplete( $linksUpdate, null ),
			'onLinksUpdateComplete()'
		);
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

		$this->assertTrue(
			$hooks->onLinksUpdateComplete( $linksUpdate, null ),
			'onLinksUpdateComplete()'
		);
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

		$this->assertTrue(
			$hooks->onLinksUpdateComplete( $linksUpdate, null ),
			'onLinksUpdateComplete()'
		);
	}

	public function provideDoCampaignUpdate() : iterable {
		yield 'enabled' => [ [ 'enabled' => true ], 1 ];
		yield 'disabled' => [ [ 'enabled' => false ], 0 ];
		yield 'no data' => [ null, 0 ];
	}

	/**
	 * @param array|null $data
	 * @param int $expectedEnabled
	 *
	 * @dataProvider provideDoCampaignUpdate
	 */
	public function testDoCampaignUpdate( ?array $data, int $expectedEnabled ) {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleInCampaignNamespace() );

		$content = $this->createMock( CampaignContent::class );
		$content->expects( $this->once() )
			->method( 'getData' )
			->willReturn( Status::newGood( $data ) );

		$dbw = $this->createMock( IDatabase::class );
		$dbw->expects( $this->once() )
			->method( 'upsert' )
			->with(
				'uw_campaigns',
				[
					'campaign_name' => self::DUMMY_CAMPAIGN_NAME,
					'campaign_enabled' => $expectedEnabled
				],
				'campaign_name',
				[ 'campaign_enabled' => $expectedEnabled ]
			);

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->expects( $this->once() )
			->method( 'getConnection' )
			->with( DB_MASTER )
			->willReturn( $dbw );

		$hooks = $this->getCampaignHooks( $loadBalancer );

		$hooks->doCampaignUpdate( $wikiPage, $content );
	}

	public function testArticleDelete_configAnchor() {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleForGlobalConfigAnchor() );

		$status = $this->createMock( Status::class );
		$status->expects( $this->once() )
			->method( 'fatal' )
			->with( 'mwe-upwiz-global-config-anchor' );

		$hooks = $this->getCampaignHooks();

		$error = '';
		$reason = '';
		$this->assertFalse(
			$hooks->onArticleDelete(
				$wikiPage,
				$this->createNoOpMock( User::class ),
				$reason,
				$error,
				$status,
				false
			),
			'onArticleDelete()'
		);
	}

	public function testArticleDelete_notConfigAnchor() {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( $this->getTitleNotInCampaignNamespace() );

		$status = $this->createNoOpMock( Status::class );

		$hooks = $this->getCampaignHooks();

		$error = '';
		$reason = '';
		$this->assertTrue(
			$hooks->onArticleDelete(
				$wikiPage,
				$this->createNoOpMock( User::class ),
				$reason,
				$error,
				$status,
				false
			),
			'onArticleDelete()'
		);
	}

	/**
	 * Returns a CampaignHooks object for testing.
	 * Null arguments will be changed to be no-op mocks.
	 *
	 * @param ILoadBalancer|null $loadBalancer
	 * @param ConfigCacheInvalidator|null $cacheInvalidator
	 *
	 * @return CampaignHooks
	 */
	private function getCampaignHooks(
		ILoadBalancer $loadBalancer = null,
		ConfigCacheInvalidator $cacheInvalidator = null
	) : CampaignHooks {
		return new CampaignHooks(
			$loadBalancer ?:
				$this->createNoOpMock( ILoadBalancer::class ),
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
				function ( LinkTarget $target ) : bool {
					return $target->getNamespace() === NS_CAMPAIGN &&
						$target->getDBkey() === CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY;
				}
			);

		return $title;
	}

	/**
	 * Returns User:MediaUploader
	 *
	 * @return UserIdentity|MockObject
	 */
	private function getMediaUploaderUser() {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getName' )
			->willReturn( 'MediaUploader' );
		$user->method( 'isRegistered' )
			->willReturn( true );

		return $user;
	}

	/**
	 * Returns some other (non-system) user
	 *
	 * @return UserIdentity|MockObject
	 */
	private function getOtherUser() {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getName' )
			->willReturn( 'DummyUser' );
		$user->method( 'isRegistered' )
			->willReturn( true );

		return $user;
	}
}
