<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use Language;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use ParserFactory;
use ParserOptions;
use SpecialPage;
use Title;

/**
 * Constructs ConfigParser objects.
 */
class ConfigParserFactory {

	/** @var ParserFactory */
	private $parserFactory;

	/**
	 * ConfigParserFactory constructor.
	 *
	 * @param ParserFactory $parserFactory
	 *
	 * @internal Only for use by ServiceWiring
	 */
	public function __construct( ParserFactory $parserFactory ) {
		$this->parserFactory = $parserFactory;
	}

	/**
	 * @param array $rawConfig
	 * @param UserIdentity $user
	 * @param Language $language
	 * @param LinkTarget|null $linkTarget This should correspond to the campaign's
	 *  page title, or Special:MediaUploader in case it's not a campaign. Default
	 *  is Special:MediaUploader.
	 *
	 * @return ConfigParser
	 */
	public function newConfigParser(
		array $rawConfig,
		UserIdentity $user,
		Language $language,
		LinkTarget $linkTarget = null
	): ConfigParser {
		if ( $linkTarget === null ) {
			$title = SpecialPage::getTitleFor( 'MediaUploader' );
		} else {
			$title = Title::newFromLinkTarget( $linkTarget );
		}

		$parserOptions = ParserOptions::newFromUserAndLang( $user, $language );
		$parserOptions->setTargetLanguage( $language );
		$parserOptions->setInterfaceMessage( true );

		return new ConfigParser(
			$title,
			$this->parserFactory,
			$parserOptions,
			$rawConfig
		);
	}
}
