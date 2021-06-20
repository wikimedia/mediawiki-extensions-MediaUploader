<?php

namespace MediaWiki\Extension\MediaUploader\Hooks;

use Content;
use DeferredUpdates;
use IContextSource;
use LinksUpdate;
use ManualLogEntry;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\MovePageIsValidMoveHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Page\Hook\ArticleDeleteHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\UserIdentity;
use Status;
use Title;
use User;
use Wikimedia\Assert\PreconditionException;
use WikiPage;

/**
 * Hooks related to handling events happening on pages in the Campaign: namespace.
 */
class CampaignHooks implements
	PageSaveCompleteHook,
	LinksUpdateCompleteHook,
	ArticleDeleteCompleteHook,
	EditFilterMergedContentHook,
	ArticleDeleteHook,
	MovePageIsValidMoveHook
{

	/** @var CampaignStore */
	private $campaignStore;

	/** @var ConfigCacheInvalidator */
	private $cacheInvalidator;

	/**
	 * @param CampaignStore $campaignStore
	 * @param ConfigCacheInvalidator $cacheInvalidator
	 */
	public function __construct(
		CampaignStore $campaignStore,
		ConfigCacheInvalidator $cacheInvalidator
	) {
		$this->campaignStore = $campaignStore;
		$this->cacheInvalidator = $cacheInvalidator;
	}

	/**
	 * Deletes entries from mu_campaign table when a Campaign is deleted
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $reason
	 * @param int $id
	 * @param Content|null $content
	 * @param ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount
	 *
	 * @return bool
	 */
	public function onArticleDeleteComplete(
		$wikiPage, $user, $reason, $id, $content, $logEntry, $archivedRevisionCount
	) : bool {
		if ( !$wikiPage->getTitle()->inNamespace( NS_CAMPAIGN ) ) {
			return true;
		}

		$this->campaignStore->deleteCampaignByPageId( $id );
		return true;
	}

	/**
	 * Validates that the revised contents of a campaign are valid YAML.
	 * If not valid, rejects edit with error message.
	 *
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 *
	 * @return bool
	 */
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) : bool {
		if ( !$context->getTitle()->inNamespace( NS_CAMPAIGN )
			|| !$content instanceof CampaignContent
		) {
			return true;
		}

		if ( $this->isGlobalConfigAnchor( $context->getTitle() ) ) {
			// There's no need to validate the anchor's contents, it doesn't
			// matter anyway.
			return true;
		}

		if ( MediaUploaderServices::isSystemUser( $user ) ) {
			return true;
		}

		$status->merge( $content->getValidationStatus() );

		return $status->isOK();
	}

	/**
	 * Invalidates the cache for a campaign when any of its dependents are edited. The
	 * 'dependents' are tracked by entries in the templatelinks table, which are inserted
	 * by CampaignContent.
	 *
	 * This is usually run via the Job Queue mechanism.
	 *
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket
	 *
	 * @return bool
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) : bool {
		if ( !$linksUpdate->getTitle()->inNamespace( NS_CAMPAIGN ) ) {
			return true;
		}

		// Invalidate global config cache.
		if ( $this->isGlobalConfigAnchor( $linksUpdate->getTitle() ) ) {
			// Ignore edits by MediaUploader itself.
			// The cache was invalidated recently anyway.
			if ( !$this->isMagicUser( $linksUpdate->getTriggeringUser() ) ) {
				$this->cacheInvalidator->invalidate();
			}
			return true;
		}

		$this->cacheInvalidator->invalidate( $linksUpdate->getTitle()->getDBkey() );

		return true;
	}

	/**
	 * Sets up appropriate entries in the uc_campaigns table for each Campaign
	 * Acts everytime a page in the NS_CAMPAIGN namespace is saved
	 *
	 * The real update is done in doCampaignUpdate
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 *
	 * @return bool
	 */
	public function onPageSaveComplete(
		$wikiPage, $userIdentity, $summary, $flags, $revisionRecord, $editResult
	) : bool {
		$content = $wikiPage->getContent();
		if ( !$content instanceof CampaignContent
			|| $this->isGlobalConfigAnchor( $wikiPage->getTitle() )
		) {
			return true;
		}

		DeferredUpdates::addCallableUpdate(
			function () use ( $wikiPage, $content ) {
				$this->doCampaignUpdate( $wikiPage, $content );
			}
		);

		return true;
	}

	/**
	 * Performs the actual campaign data update after the campaign page is saved.
	 *
	 * @param WikiPage $wikiPage
	 * @param CampaignContent $content
	 */
	public function doCampaignUpdate( WikiPage $wikiPage, CampaignContent $content ) : void {
		$campaignRecord = $content->newCampaignRecord( $wikiPage->getId() );
		$this->campaignStore->upsertCampaign( $campaignRecord );
	}

	/**
	 * Prevent the global config anchor from being deleted.
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string &$reason
	 * @param string &$error
	 * @param Status &$status
	 * @param bool $suppress
	 *
	 * @return bool
	 */
	public function onArticleDelete(
		WikiPage $wikiPage, User $user, &$reason, &$error, Status &$status, $suppress
	) : bool {
		if ( $this->isGlobalConfigAnchor( $wikiPage->getTitle() ) ) {
			$status->fatal( 'mwe-upwiz-global-config-anchor' );
			return false;
		}

		return true;
	}

	/**
	 * Prevent the global config anchor from being moved.
	 *
	 * @param Title $oldTitle
	 * @param Title $newTitle
	 * @param Status $status
	 *
	 * @return bool
	 */
	public function onMovePageIsValidMove( $oldTitle, $newTitle, $status ) : bool {
		if ( $this->isGlobalConfigAnchor( $oldTitle ) ||
			$this->isGlobalConfigAnchor( $newTitle )
		) {
			$status->fatal( 'mwe-upwiz-global-config-anchor' );
		}
		return true;
	}

	/**
	 * Checks whether $linkTarget is of the global config anchor page.
	 *
	 * @param LinkTarget $linkTarget
	 *
	 * @return bool
	 */
	private function isGlobalConfigAnchor( LinkTarget $linkTarget ) : bool {
		return $linkTarget->isSameLinkAs(
			CampaignContent::getGlobalConfigAnchorLinkTarget()
		);
	}

	/**
	 * Checks whether $identity is of the "magic" built-in MediaUploader user.
	 *
	 * @param UserIdentity $identity
	 *
	 * @return bool
	 */
	private function isMagicUser( UserIdentity $identity ) : bool {
		try {
			$identity->assertWiki( UserIdentity::LOCAL );
		} catch ( PreconditionException $ex ) {
			return false;
		}
		return $identity->isRegistered() && $identity->getName() === 'MediaUploader';
	}
}
