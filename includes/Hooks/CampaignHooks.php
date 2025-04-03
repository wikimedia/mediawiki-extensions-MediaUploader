<?php

namespace MediaWiki\Extension\MediaUploader\Hooks;

use Content;
use DeferredUpdates;
use IContextSource;
use ManualLogEntry;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\MovePageIsValidMoveHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\PageDeleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use StatusValue;
use TitleValue;
use User;
use Wikimedia\Assert\PreconditionException;
use WikiPage;

/**
 * Hooks related to handling events happening on pages in the Campaign: namespace.
 */
class CampaignHooks implements
	EditFilterMergedContentHook,
	LinksUpdateCompleteHook,
	MovePageIsValidMoveHook,
	PageDeleteCompleteHook,
	PageDeleteHook,
	PageSaveCompleteHook
{

	/** @var CampaignStore */
	private $campaignStore;

	/** @var ConfigCacheInvalidator */
	private $cacheInvalidator;

	public function __construct(
		CampaignStore $campaignStore,
		ConfigCacheInvalidator $cacheInvalidator
	) {
		$this->campaignStore = $campaignStore;
		$this->cacheInvalidator = $cacheInvalidator;
	}

	/**
	 * Deletes entries from mu_campaign table when a Campaign is deleted
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $deleter
	 * @param string $reason
	 * @param int $pageID
	 * @param RevisionRecord $deletedRev
	 * @param ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount
	 *
	 * @return void
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		if ( $page->getNamespace() !== NS_CAMPAIGN ) {
			return;
		}

		$this->campaignStore->deleteCampaignByPageId( $pageID );
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
	): bool {
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
	 * @return void
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		if ( !$linksUpdate->getTitle()->inNamespace( NS_CAMPAIGN ) ) {
			return;
		}

		// Invalidate global config cache.
		if ( $this->isGlobalConfigAnchor( $linksUpdate->getTitle() ) ) {
			// Ignore edits by MediaUploader itself.
			// The cache was invalidated recently anyway.
			if ( !$this->isMagicUser( $linksUpdate->getTriggeringUser() ) ) {
				$this->cacheInvalidator->invalidate();
			}
			return;
		}

		$this->cacheInvalidator->invalidate( $linksUpdate->getTitle()->getDBkey() );
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
	 * @return void
	 */
	public function onPageSaveComplete(
		$wikiPage, $userIdentity, $summary, $flags, $revisionRecord, $editResult
	) {
		$content = $wikiPage->getContent();
		if ( !$content instanceof CampaignContent
			|| $this->isGlobalConfigAnchor( $wikiPage->getTitle() )
		) {
			return;
		}

		DeferredUpdates::addCallableUpdate(
			function () use ( $wikiPage, $content ) {
				$this->doCampaignUpdate( $wikiPage, $content );
			}
		);
	}

	/**
	 * Performs the actual campaign data update after the campaign page is saved.
	 *
	 * @param WikiPage $wikiPage
	 * @param CampaignContent $content
	 */
	public function doCampaignUpdate( WikiPage $wikiPage, CampaignContent $content ): void {
		$campaignRecord = $content->newCampaignRecord( $wikiPage, $wikiPage->getId() );
		$this->campaignStore->upsertCampaign( $campaignRecord );
	}

	/**
	 * Prevent the global config anchor from being deleted.
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $deleter
	 * @param string $reason
	 * @param StatusValue $status
	 * @param bool $suppress
	 *
	 * @return bool
	 */
	public function onPageDelete(
		ProperPageIdentity $page, Authority $deleter, string $reason, StatusValue $status, bool $suppress
	): bool {
		if ( $this->isGlobalConfigAnchor( TitleValue::newFromPage( $page ) ) ) {
			$status->fatal( 'mediauploader-global-config-anchor' );
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
	 * @return void
	 */
	public function onMovePageIsValidMove( $oldTitle, $newTitle, $status ) {
		if ( $this->isGlobalConfigAnchor( $oldTitle ) ||
			$this->isGlobalConfigAnchor( $newTitle )
		) {
			$status->fatal( 'mediauploader-global-config-anchor' );
		}
	}

	/**
	 * Checks whether $linkTarget is of the global config anchor page.
	 *
	 * @param LinkTarget $linkTarget
	 *
	 * @return bool
	 */
	private function isGlobalConfigAnchor( LinkTarget $linkTarget ): bool {
		return $linkTarget->isSameLinkAs(
			CampaignContent::getGlobalConfigAnchorLinkTarget()
		);
	}

	/**
	 * Checks whether $identity is of the "magic" built-in MediaUploader user.
	 *
	 * @param UserIdentity|null $identity
	 *
	 * @return bool
	 */
	private function isMagicUser( ?UserIdentity $identity ): bool {
		if ( $identity === null ) {
			return false;
		}
		try {
			$identity->assertWiki( UserIdentity::LOCAL );
		} catch ( PreconditionException ) {
			return false;
		}
		return $identity->isRegistered() && $identity->getName() === 'MediaUploader';
	}
}
