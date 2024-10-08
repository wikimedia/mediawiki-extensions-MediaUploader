<?php

namespace MediaWiki\Extension\MediaUploader\Hooks;

use DatabaseUpdater;
use MediaWiki\ChangeTags\Hook\ChangeTagsAllowedAddHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Extension\MediaUploader\Maintenance\MigrateCampaigns;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\User\Hook\UserGetReservedNamesHook;

/**
 * Hooks loosely related to extension registration.
 */
class RegistrationHooks implements
	UserGetReservedNamesHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	ChangeTagsAllowedAddHook,
	LoadExtensionSchemaUpdatesHook
{
	/**
	 * Change tags used in the extension.
	 */
	public const CHANGE_TAGS = [ 'uploadwizard' ];

	/**
	 * Sets up constants.
	 */
	public static function registerExtension(): void {
		require_once dirname( __DIR__, 2 ) . '/defines.php';
	}

	/**
	 * Lists tags used by MediaUploader (via ListDefinedTags,
	 * ListExplicitlyDefinedTags & ChangeTagsListActive hooks)
	 *
	 * @param string[] &$tags
	 *
	 * @return void
	 */
	public function onListDefinedTags( &$tags ) {
		$tags = array_merge( $tags, self::CHANGE_TAGS );
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsAllowedAdd( &$allowedTags, $addTags, $user ) {
		$this->onListDefinedTags( $allowedTags );
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsListActive( &$tags ) {
		$this->onListDefinedTags( $tags );
	}

	/**
	 * Reserves the 'MediaUploader' username.
	 *
	 * @param array &$reservedUsernames
	 *
	 * @return void
	 */
	public function onUserGetReservedNames( &$reservedUsernames ) {
		$reservedUsernames[] = 'MediaUploader';
	}

	/**
	 * @param DatabaseUpdater $updater
	 *
	 * @return void
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$path = dirname( __DIR__, 2 ) . '/sql';

		$updater->addExtensionTable( 'mu_campaign', "$path/$type/tables-generated.sql" );

		$updater->addPostDatabaseUpdateMaintenance( MigrateCampaigns::class );
	}
}
