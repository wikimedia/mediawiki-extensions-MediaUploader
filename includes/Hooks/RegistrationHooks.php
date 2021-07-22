<?php

namespace MediaWiki\Extension\MediaUploader\Hooks;

use MediaWiki\ChangeTags\Hook\ChangeTagsAllowedAddHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\User\Hook\UserGetReservedNamesHook;

/**
 * Hooks loosely related to extension registration.
 */
class RegistrationHooks implements
	UserGetReservedNamesHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	ChangeTagsAllowedAddHook
{
	/**
	 * Change tags used in the extension.
	 */
	public const CHANGE_TAGS = [
		'uploadwizard',
		'uploadwizard-flickr',
	];

	/**
	 * Lists tags used by UploadWizard (via ListDefinedTags,
	 * ListExplicitlyDefinedTags & ChangeTagsListActive hooks)
	 *
	 * @param string[] &$tags
	 *
	 * @return bool true
	 */
	public function onListDefinedTags( &$tags ): bool {
		$tags = array_merge( $tags, self::CHANGE_TAGS );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsAllowedAdd( &$allowedTags, $addTags, $user ): bool {
		return $this->onListDefinedTags( $allowedTags );
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsListActive( &$tags ): bool {
		return $this->onListDefinedTags( $tags );
	}

	/**
	 * Reserves the 'MediaUploader' username.
	 *
	 * @param array &$reservedUsernames
	 *
	 * @return bool true
	 */
	public function onUserGetReservedNames( &$reservedUsernames ): bool {
		$reservedUsernames[] = 'MediaUploader';
		return true;
	}
}
