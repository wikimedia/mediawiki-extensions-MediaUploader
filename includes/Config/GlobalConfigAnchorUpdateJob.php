<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use CommentStoreComment;
use GenericParameterJob;
use Job;
use JobSpecification;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContentHandler;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\MediaWikiServices;
use MWException;

/**
 * Job for updating the global config anchor page (Campaign:-).
 *
 * It refreshes the list of templates used by the anchor to ensure future recursive
 * LinksUpdates will invalidate the global config cache.
 */
class GlobalConfigAnchorUpdateJob extends Job implements GenericParameterJob {

	public const NAME = 'globalConfigAnchorUpdate';

	/**
	 * Returns a JobSpecification for this job.
	 *
	 * @return JobSpecification
	 */
	public static function newSpec() : JobSpecification {
		return new JobSpecification( self::NAME, [] );
	}

	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( self::NAME, $params );
	}

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function run() : bool {
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$anchor = $wikiPageFactory->newFromLinkTarget(
			CampaignContent::getGlobalConfigAnchorLinkTarget()
		);

		$content = $anchor->getContent();
		if ( !$content ) {
			$contentHandler = new CampaignContentHandler();
			$content = $contentHandler->makeEmptyContent();
		}

		$pageUpdater = $anchor->newPageUpdater(
			MediaUploaderServices::getSystemUser()
		);
		$pageUpdater->setContent( 'main', $content );

		$lastRevision = $anchor->getRevisionRecord();
		if ( $lastRevision ) {
			$lastRevisionId = $lastRevision->getId();
			if ( $lastRevisionId ) {
				// This is needed so that the edit is correctly marked as 'null'
				$pageUpdater->setOriginalRevisionId( $lastRevisionId );
			}
		}

		$pageUpdater->saveRevision( CommentStoreComment::newUnsavedComment( '' ) );

		return true;
	}
}
