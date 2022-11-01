<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Maintenance\MigrateCampaigns;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Title;

/**
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\Extension\MediaUploader\Maintenance\MigrateCampaigns
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContentHandler
 */
class MaintenanceMigrateCampaignsTest extends MaintenanceBaseTestCase {

	private const CHANGE_NONE = 1;
	private const CHANGE_PRETTY = 2;

	private const FIXABLE_YAML = <<<'YAML'
enabled: true
bad: bad
worse: worse
display:
  headerLabel: valid label
  bad: [ 'a', 'b' ]
YAML;

	private const INVALID_YAML = '{';

	private const VALID_YAML = '{ enabled: true }';

	public function testNothingToFix() {
		// The global config anchor should be ignored
		$this->makeCampaign(
			CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY,
			self::FIXABLE_YAML
		);

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();
		$this->expectOutputRegex( '/\s*/' );
	}

	public function testFixable() {
		$this->makeCampaign( 'Fixable', self::FIXABLE_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->assertChange( 'Fixable', self::CHANGE_PRETTY );

		// Re-run the script to check if the issues were really fixed
		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex(
			'/(ERROR: This must be fixed manually.*){3}Issues to fix: 3/s'
		);
		$this->deleteCampaign( 'Fixable' );
	}

	public function testFixableDryRun() {
		$this->makeCampaign( 'Fixable', self::FIXABLE_YAML );

		$this->maintenance->loadWithArgv( [ '--force', '--dry-run' ] );
		$this->maintenance->execute();

		$this->assertChange( 'Fixable', self::CHANGE_NONE );
		$this->deleteCampaign( 'Fixable' );
	}

	public function testInvalid() {
		$this->makeCampaign( 'Invalid', self::INVALID_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/Cannot process this campaign/' );
		$this->assertChange( 'Invalid', self::CHANGE_NONE );
		$this->deleteCampaign( 'Invalid' );
	}

	public function testValidPrettify() {
		$this->makeCampaign( 'Valid', self::VALID_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/Saved changes\./' );
		$this->assertChange( 'Valid', self::CHANGE_PRETTY );
		$this->deleteCampaign( 'Valid' );
	}

	public function testMultiple() {
		$this->makeCampaign( 'Fixable', self::FIXABLE_YAML );
		$this->makeCampaign( 'Valid', self::VALID_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		// Two campaigns should be present in output
		$this->expectOutputRegex( '/----.*----/s' );

		$this->assertChange( 'Fixable', self::CHANGE_PRETTY );
		$this->deleteCampaign( 'Fixable' );
		$this->assertChange( 'Valid', self::CHANGE_PRETTY );
		$this->deleteCampaign( 'Valid' );
	}

	/**
	 * Creates a campaign page for testing.
	 *
	 * @param string $name
	 * @param string $content
	 */
	private function makeCampaign( string $name, string $content ): void {
		$title = Title::newFromText( $name, NS_CAMPAIGN );
		$this->editPage(
			$title,
			new CampaignContent( $content ),
			'creating campaign',
			NS_CAMPAIGN,
			MediaUploaderServices::getSystemUser()
		);
	}

	private function assertChange( string $name, int $changeType ): void {
		$wpFactory = $this->getServiceContainer()->getWikiPageFactory();

		$title = Title::newFromText( $name, NS_CAMPAIGN );
		$page = $wpFactory->newFromLinkTarget( $title );
		$revision = $page->getRevisionRecord();

		if ( $changeType === self::CHANGE_NONE ) {
			$this->assertSame(
				'creating campaign',
				$revision->getComment()->text,
				'$revision->getComment()->text'
			);
			return;
		} else {
			$this->assertSame(
				'MediaUploader',
				$revision->getUser()->getName(),
				'$revision->getUser()->getName()'
			);
			$this->assertNotSame(
				'creating campaign',
				$revision->getComment()->text,
				'$revision->getComment()->text'
			);
		}

		// Check the edit comment
		$commentMessage = $revision->getComment()->message;
		$this->assertSame(
			'mediauploader-fix-campaign-comment-prettified',
			$commentMessage->getKey(),
			'Message::getKey()'
		);
	}

	private function deleteCampaign( string $name ): void {
		$wpFactory = $this->getServiceContainer()->getWikiPageFactory();
		$title = Title::newFromText( $name, NS_CAMPAIGN );
		$page = $wpFactory->newFromLinkTarget( $title );
		$this->deletePage( $page );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass(): string {
		return MigrateCampaigns::class;
	}
}
