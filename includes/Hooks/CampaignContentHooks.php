<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\MediaUploader\Hooks;

use EditPage;
use ExtensionRegistry;
use MediaWiki\Content\Hook\ContentModelCanBeUsedOnHook;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use OutputPage;
use Title;

/**
 * Hooks related directly to the campaign content model.
 */
class CampaignContentHooks implements
	ContentModelCanBeUsedOnHook,
	EditPage__showEditForm_initialHook
{
	/** @var ExtensionRegistry */
	private $extensionRegistry;

	/**
	 * Factory method to inject ExtensionRegistry.
	 *
	 * @return static
	 */
	public static function newFromGlobalState(): self {
		return new static( ExtensionRegistry::getInstance() );
	}

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 */
	public function __construct( ExtensionRegistry $extensionRegistry ) {
		$this->extensionRegistry = $extensionRegistry;
	}

	/**
	 * 'Campaign' content model must be used in, and only in, the 'Campaign' namespace.
	 *
	 * @param string $contentModel
	 * @param Title $title
	 * @param bool &$ok
	 * @return bool
	 */
	public function onContentModelCanBeUsedOn( $contentModel, $title, &$ok ): bool {
		$isCampaignModel = $contentModel === CONTENT_MODEL_CAMPAIGN;
		$isCampaignNamespace = $title->inNamespace( NS_CAMPAIGN );
		if ( $isCampaignModel !== $isCampaignNamespace ) {
			$ok = false;
			return false;
		}

		return true;
	}

	/**
	 * Load an additional module when editing campaigns with CodeEditor.
	 *
	 * @param EditPage $editor
	 * @param OutputPage $out
	 *
	 * @return void
	 */
	public function onEditPage__showEditForm_initial( $editor, $out ) {
		if ( $this->extensionRegistry->isLoaded( 'CodeEditor' ) &&
			$editor->getContextTitle()->getNamespace() === NS_CAMPAIGN
		) {
			$out->addModules( 'ext.mediaUploader.campaignEditor' );
		}
	}

	/**
	 * Declares YAML as the code editor language for Campaign: pages.
	 * This hook only runs if the CodeEditor extension is enabled.
	 *
	 * @param Title $title
	 * @param string|null &$lang Page language.
	 *
	 * @return void
	 */
	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang ) {
		if ( $title->inNamespace( NS_CAMPAIGN ) ) {
			$lang = 'yaml';
		}
	}
}
