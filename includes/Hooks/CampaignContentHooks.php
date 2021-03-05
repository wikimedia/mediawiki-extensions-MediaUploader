<?php

namespace MediaWiki\Extension\MediaUploader\Hooks;

use CampaignContent;
use MediaWiki\Content\Hook\ContentModelCanBeUsedOnHook;
use Title;

/**
 * Hooks related directly to the campaign content model.
 */
class CampaignContentHooks implements ContentModelCanBeUsedOnHook {

	/**
	 * 'Campaign' content model must be used in, and only in, the 'Campaign' namespace.
	 *
	 * @param string $contentModel
	 * @param Title $title
	 * @param bool &$ok
	 * @return bool
	 */
	public function onContentModelCanBeUsedOn( $contentModel, $title, &$ok ) : bool {
		$isCampaignModel = $contentModel === CampaignContent::MODEL_NAME;
		$isCampaignNamespace = $title->inNamespace( NS_CAMPAIGN );
		if ( $isCampaignModel !== $isCampaignNamespace ) {
			$ok = false;
			return false;
		}

		return true;
	}

	/**
	 * Declares JSON as the code editor language for Campaign: pages.
	 * This hook only runs if the CodeEditor extension is enabled.
	 *
	 * @param Title $title
	 * @param string|null &$lang Page language.
	 *
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang ) : bool {
		if ( $title->inNamespace( NS_CAMPAIGN ) ) {
			$lang = 'json';
		}
		return true;
	}
}
