<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use ParserFactory;
use ParserOptions;

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
	 * @param ParserOptions $parserOptions
	 * @param PageReference|null $pageRef This should correspond to the campaign's
	 *  page title, or Special:MediaUploader in case it's not a campaign. Default
	 *  is Special:MediaUploader.
	 *
	 * @return ConfigParser
	 */
	public function newConfigParser(
		array $rawConfig,
		ParserOptions $parserOptions,
		?PageReference $pageRef = null
	): ConfigParser {
		$pageRef ??= PageReferenceValue::localReference( NS_SPECIAL, 'MediaUploader' );

		$parserOptions->setInterfaceMessage( true );
		return new ConfigParser(
			$pageRef,
			$this->parserFactory,
			$parserOptions,
			$rawConfig
		);
	}
}
