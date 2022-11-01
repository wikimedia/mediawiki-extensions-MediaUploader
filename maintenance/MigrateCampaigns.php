<?php

namespace MediaWiki\Extension\MediaUploader\Maintenance;

use CommentStoreComment;
use LoggedUpdateMaintenance;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ExistingPageRecord;
use MediaWiki\Revision\SlotRecord;
use Message;
use Symfony\Component\Yaml\Yaml;
use WikiPage;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";

class MigrateCampaigns extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MediaUploader' );
		$this->addDescription(
			"Inspects campaign definitions carried over from UploadWizard and\n" .
			"converts them to YAML.\n"
		);
		$this->addOption(
			'dry-run',
			'Do not convert campaign pages to YAML, just list the issues.'
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$services = MediaWikiServices::getInstance();
		$pageStore = $services->getPageStore();
		$wikiPageFactory = $services->getWikiPageFactory();

		$dbr = $this->getDB( DB_REPLICA );

		$this->output( "Inspecting MediaUploader campaigns...\n" );
		$pageRecords = $pageStore->newSelectQueryBuilder( $dbr )
			->whereNamespace( NS_CAMPAIGN )
			->fetchPageRecords();

		/** @var ExistingPageRecord $record */
		foreach ( $pageRecords as $record ) {
			if ( $record->isRedirect() ) {
				continue;
			}
			if ( $record->getDBkey() === CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY ) {
				continue;
			}

			$page = $wikiPageFactory->newFromTitle( $record );
			$content = $page->getContent();
			if ( !( $content instanceof CampaignContent ) ) {
				continue;
			}

			$this->doCampaign( $content, $page );
		}

		$this->output( "\n\n" );
		return true;
	}

	/**
	 * Applies fixes/prettification to a campaign.
	 *
	 * @param CampaignContent $content
	 * @param WikiPage $page
	 */
	private function doCampaign( CampaignContent $content, WikiPage $page ) {
		$status = $content->getValidationStatus();
		$titleText = $page->getTitle()->getPrefixedText();
		$dryRun = $this->getOption( 'dry-run' );
		$toFix = 0;

		$this->output( "\n\n---- $titleText\n" );

		if ( $status->isGood() ) {
			$this->output( "VALID\n" );
		}

		$data = null;
		if ( $content->getData()->isGood() ) {
			// Parse the YAML again, but this time use objects to avoid
			// PHP's array type ambiguity (associative vs list).
			$data = Yaml::parse(
				$content->getText(),
				Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
				| Yaml::PARSE_OBJECT_FOR_MAP
			);
		}

		foreach ( $status->getErrors() as $error ) {
			$this->output(
				wfMessage( $error['message'], ...$error['params'] )->text() . "\n"
			);

			$this->output( "ERROR: This must be fixed manually.\n" );
			$toFix++;
		}

		if ( $toFix ) {
			$this->output( "Issues to fix: $toFix" );
		}

		if ( $dryRun ) {
			return;
		}

		if ( $data ) {
			// Save changes
			$text = Yaml::dump(
				$data,
				10,
				2,
				Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE
			);
			// Symfony for some reason prefers to start objects in a new line following
			// a dash (-). This is contrary to my preference, and it seems most YAML
			// style guides out there, so this regex removes the extra newline.
			$text = preg_replace(
				'/^( *)-\n +([^-])/m',
				'$1- $2',
				$text
			);
			$updater = $page->newPageUpdater( MediaUploaderServices::getSystemUser() );
			$content = new CampaignContent( $text );
			$updater->setContent( SlotRecord::MAIN, $content );
			$comment = CommentStoreComment::newUnsavedComment(
				new Message( 'mediauploader-fix-campaign-comment-prettified' )
			);
			$updater->saveRevision( $comment, EDIT_UPDATE );
			$this->output( "Saved changes.\n" );
		} else {
			$this->output( "Cannot process this campaign.\n" );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'migrate MediaUploader campaigns';
	}
}

$maintClass = MigrateCampaigns::class;

require_once RUN_MAINTENANCE_IF_MAIN;
