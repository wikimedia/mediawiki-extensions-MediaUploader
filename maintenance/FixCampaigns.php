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

class FixCampaigns extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MediaUploader' );
		$this->addDescription(
			"Inspects and attempts to fix broken campaign definitions.\n" .
			"This is especially useful if you are migrating from UploadWizard.\n" .
			"Optionally prettifies all campaign definitions."
		);
		$this->addOption(
			'dry-run',
			'Do not fix found issues, just list them.'
		);
		$this->addOption(
			'prettify',
			'Pretty-print all campaign definitions in YAML.'
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

		$this->output( "Fixing MediaUploader campaigns...\n" );
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

			$this->fixCampaign( $content, $page );
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
	private function fixCampaign( CampaignContent $content, WikiPage $page ) {
		$status = $content->getValidationStatus();
		$titleText = $page->getTitle()->getPrefixedText();
		$dryRun = $this->getOption( 'dry-run' );
		$valid = true;
		$toFixManual = 0;
		$fixed = 0;

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

			if ( $error['message'] !== 'mediauploader-schema-validation-error' ) {
				// If it's not a schema error, we cannot attempt to fix it
				$this->output( "ERROR: This must be fixed manually.\n" );
				$toFixManual++;
				$valid = false;
				continue;
			}
			if ( $dryRun ) {
				$this->output( "SKIP (DRY RUN)\n" );
				continue;
			}

			// Get the more detailed schema validator error
			$sError = $error['params'][2];
			if ( $sError['constraint'] === 'additionalProp' ) {
				// This is unfortunately only given as part of the message
				preg_match(
					'/The property (.+?) is not defined/',
					$sError['message'],
					$propMatch
				);
				if ( $this->removeProperty( $data, $sError['property'], $propMatch[1] ) ) {
					$this->output( "FIXED\n" );
					$fixed++;
				} else {
					$this->output( "ERROR: Fix failed.\n" );
					$toFixManual++;
				}
			} else {
				$this->output( "ERROR: This must be fixed manually.\n" );
				$toFixManual++;
			}
		}

		if ( $dryRun ) {
			return;
		}

		if ( $valid && ( $fixed || $this->getOption( 'prettify' ) ) ) {
			// Save changes
			$text = Yaml::dump(
				$data,
				10,
				2,
				Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE
			);
			// Symfony for some reason prefers to start objects in a new line following
			// a dash (-). This is contrary to my preference and it seems most YAML
			// style guides out there, so this regex removes the extra newline.
			$text = preg_replace(
				'/^( *)-\n +([^-])/m',
				'$1- $2',
				$text
			);
			$updater = $page->newPageUpdater( MediaUploaderServices::getSystemUser() );
			$content = new CampaignContent( $text );
			$updater->setContent( SlotRecord::MAIN, $content );
			$key = $fixed ? 'fixed' : 'prettified';
			$comment = CommentStoreComment::newUnsavedComment(
				new Message(
					'mediauploader-fix-campaign-comment-' . $key,
					[ $fixed, $toFixManual ]
				)
			);
			$updater->saveRevision( $comment, EDIT_UPDATE );
			$this->output(
				"Saved changes. Fixed $fixed issue(s), " .
				"$toFixManual left to fix manually.\n"
			);
		} else {
			// Perform a null edit on the campaign, just in case
			$updater = $page->newPageUpdater( MediaUploaderServices::getSystemUser() );
			$updater->setContent( SlotRecord::MAIN, $content );
			$comment = CommentStoreComment::newUnsavedComment( '' );
			$updater->setOriginalRevisionId( $page->getRevisionRecord()->getId() );
			$updater->saveRevision( $comment, EDIT_UPDATE );

			$this->output( 'Performed a null edit.' );
		}
	}

	/**
	 * Remove an invalid property at given path.
	 *
	 * @param mixed &$data The content of the campaign.
	 * @param string $path Path to object.
	 * @param string $prop Name of the property to remove.
	 *
	 * @return bool Whether the removal was successful.
	 */
	private function removeProperty( &$data, string $path, string $prop ): bool {
		$cursor =& $data;
		$path = str_replace( [ '[', ']' ], '.', $path );

		foreach ( explode( '.', $path ) as $key ) {
			if ( strlen( $key ) === 0 ) {
				// We may have produced empty keys by the ugly substitution above
				continue;
			}

			if ( is_object( $cursor ) ) {
				$cursor =& $cursor->$key;
			} else {
				if ( !array_key_exists( $key, $cursor ) ) {
					// Something went wrong with the path
					return false;
				}
				$cursor =& $cursor[$key];
			}
		}

		unset( $cursor->$prop );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'fix MediaUploader campaigns';
	}
}

$maintClass = FixCampaigns::class;

require_once RUN_MAINTENANCE_IF_MAIN;
