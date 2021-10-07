<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Maintenance\FixCampaigns;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Title;

/**
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\Extension\MediaUploader\Maintenance\FixCampaigns
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContent
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignContentHandler
 */
class MaintenanceFixCampaignsTest extends MaintenanceBaseTestCase {

	private const CHANGE_NONE = 1;
	private const CHANGE_PRETTY = 2;
	private const CHANGE_FIX = 3;

	private const FIXABLE_YAML = <<<'YAML'
enabled: true
bad: bad
worse: worse
display:
  headerLabel: valid label
  bad: [ 'a', 'b' ]
fields:
  - wikitext: some text
    bad: bad
YAML;

	private const INVALID_YAML = '{';

	private const VALID_YAML = '{ enabled: true }';

	private const UNFIXABLE_YAML = <<<'YAML'
enabled: true
bad: bad
worse: worse
display:
  headerLabel: valid label
  bad: [ 'a', 'b' ]
fields:
  - wikitext: some text
    bad: bad
    # type mismatch
    maxLength: aaa
YAML;

	public function testNothingToFix() {
		// The global config anchor should be ignored
		$this->makeCampaign(
			CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY,
			self::FIXABLE_YAML
		);

		$this->maintenance->loadWithArgv( [ '--force', '--prettify' ] );
		$this->maintenance->execute();
		$this->expectOutputRegex( '/\s*/' );
	}

	public function testFixable() {
		$this->makeCampaign( 'Fixable', self::FIXABLE_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->assertChange( 'Fixable', self::CHANGE_FIX, 4 );

		// Re-run the script to check if the issues were really fixed
		$this->maintenance->loadWithArgv( [ '--force', '--prettify' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex(
			'/Fixed 4 issue\(s\), 0 left to fix manually.*'
			. 'Fixed 0 issue\(s\), 0 left to fix manually/s'
		);
		$this->deleteCampaign( 'Fixable' );
	}

	public function testFixableDryRun() {
		$this->makeCampaign( 'Fixable', self::FIXABLE_YAML );

		$this->maintenance->loadWithArgv( [ '--force', '--dry-run' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/SKIP \(DRY RUN\)/' );
		$this->assertChange( 'Fixable', self::CHANGE_NONE );
		$this->deleteCampaign( 'Fixable' );
	}

	public function testInvalid() {
		$this->makeCampaign( 'Invalid', self::INVALID_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/ERROR: This must be fixed manually/' );
		$this->assertChange( 'Invalid', self::CHANGE_NONE );
		$this->deleteCampaign( 'Invalid' );
	}

	public function testValidNoPrettify() {
		$this->makeCampaign( 'Valid', self::VALID_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/VALID/' );
		$this->assertChange( 'Valid', self::CHANGE_NONE );
		$this->deleteCampaign( 'Valid' );
	}

	public function testValidPrettify() {
		$this->makeCampaign( 'Valid', self::VALID_YAML );

		$this->maintenance->loadWithArgv( [ '--force', '--prettify' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/Saved changes\. Fixed 0 issue\(s\)/' );
		$this->assertChange( 'Valid', self::CHANGE_PRETTY );
		$this->deleteCampaign( 'Valid' );
	}

	public function testUnfixable() {
		$this->makeCampaign( 'Unfixable', self::UNFIXABLE_YAML );

		$this->maintenance->loadWithArgv( [ '--force' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/Fixed 4 issue\(s\), 1 left to fix manually/' );
		$this->assertChange( 'Unfixable', self::CHANGE_FIX, 4, 1 );
		$this->deleteCampaign( 'Unfixable' );
	}

	public function testMultiple() {
		$this->makeCampaign( 'Fixable', self::FIXABLE_YAML );
		$this->makeCampaign( 'Unfixable', self::UNFIXABLE_YAML );
		$this->makeCampaign( 'Valid', self::VALID_YAML );

		$this->maintenance->loadWithArgv( [ '--force', '--prettify' ] );
		$this->maintenance->execute();

		// Three campaigns should be present in output
		$this->expectOutputRegex( '/----.*----.*----/s' );

		$this->assertChange( 'Fixable', self::CHANGE_FIX, 4 );
		$this->deleteCampaign( 'Fixable' );
		$this->assertChange( 'Unfixable', self::CHANGE_FIX, 4, 1 );
		$this->deleteCampaign( 'Unfixable' );
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

	private function assertChange(
		string $name,
		int $changeType,
		int $fixed = 0,
		int $toFix = 0
	): void {
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
		$key = $changeType === self::CHANGE_FIX ? 'fixed' : 'prettified';
		$commentMessage = $revision->getComment()->message;
		$this->assertSame(
			'mediauploader-fix-campaign-comment-' . $key,
			$commentMessage->getKey(),
			'Message::getKey()'
		);
		$this->assertSame(
			$fixed,
			$commentMessage->getParams()[0],
			'Message::getParams()[0]'
		);
		$this->assertSame(
			$toFix,
			$commentMessage->getParams()[1],
			'Message::getParams()[1]'
		);
	}

	private function deleteCampaign( string $name ): void {
		$wpFactory = $this->getServiceContainer()->getWikiPageFactory();
		$title = Title::newFromText( $name, NS_CAMPAIGN );
		$page = $wpFactory->newFromLinkTarget( $title );
		$page->doDeleteArticleReal( '', $this->getTestSysop()->getUser() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass(): string {
		return FixCampaigns::class;
	}
}
