<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Config\GlobalConfigAnchorUpdateJob;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Title;
use WikiPage;

/**
 * @group Upload
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\MediaUploader\Config\GlobalConfigAnchorUpdateJob
 */
class GlobalConfigAnchorUpdateJobTest extends MediaWikiIntegrationTestCase {

	public function testCreateAnchorPageOnFirstUse() {
		// This ensures the anchor page does not exist
		$this->getNonexistingTestPage( Title::newFromLinkTarget(
			CampaignContent::getGlobalConfigAnchorLinkTarget()
		) );

		// Do the actual test
		$this->doTest( true );
	}

	public function testNullEditAnchorPageOnConfigChange() {
		$contentHandler = $this->getServiceContainer()
			->getContentHandlerFactory()
			->getContentHandler( CampaignContent::MODEL_ID );

		// Edit the anchor page to ensure it exists
		$this->editPage(
			CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY,
			$contentHandler->makeEmptyContent(),
			'',
			NS_CAMPAIGN,
			$this->getTestSysop()->getUser()
		);

		// Do the actual test
		$this->doTest( false );
	}

	private function doTest( bool $isNew ) {
		$hookCalls = 0;

		$this->setTemporaryHook(
			'PageSaveComplete',
			function (
				WikiPage $wikiPage,
				UserIdentity $userIdentity,
				string $summary,
				int $flags,
				RevisionRecord $revisionRecord,
				EditResult $editResult
			) use ( &$hookCalls, $isNew ) {
				$hookCalls++;

				$this->assertSame(
					NS_CAMPAIGN,
					$wikiPage->getTitle()->getNamespace(),
					'namespace of WikiPage passed to PageSaveComplete matches'
				);
				$this->assertSame(
					CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY,
					$wikiPage->getTitle()->getDBkey(),
					'DB key of WikiPage passed to PageSaveComplete matches'
				);
				$this->assertSame(
					'MediaUploader',
					$userIdentity->getName(),
					'the edit was performed by the MediaUploader user'
				);

				if ( $isNew ) {
					$this->assertTrue(
						$editResult->isNew(),
						'EditResult::isNew()'
					);
				} else {
					$this->assertTrue(
						$editResult->isNullEdit(),
						'EditResult::isNullEdit()'
					);
				}
			}
		);

		$job = new GlobalConfigAnchorUpdateJob( [] );
		$this->assertTrue(
			$job->run(),
			'GlobalConfigAnchorUpdateJob::run()'
		);

		$this->assertSame(
			1,
			$hookCalls,
			'PageSaveComplete hook was invoked exactly once'
		);
	}
}
