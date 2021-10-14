<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use MediaWiki\Page\PageReference;
use Parser;
use ParserFactory;
use ParserOptions;
use ParserOutput;
use Title;

/**
 * Class responsible for parsing wikitext in MediaUploader's config.
 * This applies both to the main "global" config and campaigns.
 */
class ConfigParser {

	/** @var ParserFactory */
	private $parserFactory;

	/** @var ParserOptions */
	private $parserOptions;

	/** @var Title */
	private $pageRef;

	/** @var array */
	private $unparsedConfig;

	/** @var array|null */
	private $parsedConfig = null;

	/** @var array */
	private $templates = [];

	/**
	 * @param PageReference $pageRef campaign page or title of Special:MediaUploader
	 *   if not parsing a campaign.
	 * @param ParserFactory $parserFactory
	 * @param ParserOptions $parserOptions
	 * @param array $configToParse
	 *
	 * @internal Only for use by ConfigParserFactory
	 */
	public function __construct(
		PageReference $pageRef,
		ParserFactory $parserFactory,
		ParserOptions $parserOptions,
		array $configToParse
	) {
		$this->pageRef = $pageRef;
		$this->parserFactory = $parserFactory;
		$this->parserOptions = $parserOptions;
		$this->unparsedConfig = $configToParse;
	}

	/**
	 * Returns the parsed config array.
	 *
	 * @return array
	 */
	public function getParsedConfig(): array {
		if ( $this->parsedConfig === null ) {
			$this->parseConfig();
		}
		return $this->parsedConfig;
	}

	/**
	 * Returns the templates used in this config
	 *
	 * @return array [ ns => [ dbk => [ page_id, rev_id ] ] ]
	 */
	public function getTemplates(): array {
		if ( $this->parsedConfig === null ) {
			$this->parseConfig();
		}
		return $this->templates;
	}

	/**
	 * Does the actual parsing work.
	 *
	 * @return void
	 */
	private function parseConfig(): void {
		$parsedConfig = [];
		foreach ( $this->unparsedConfig as $key => $value ) {
			switch ( $key ) {
				case 'title':
				case 'description':
					$parsedConfig[$key] = $this->parseValue( $value );
					break;
				case 'display':
					foreach ( $value as $option => $optionValue ) {
						if ( is_array( $optionValue ) ) {
							$parsedConfig['display'][$option] = $this->parseArrayValues(
								$optionValue,
								[ 'label' ]
							);
						} else {
							$parsedConfig['display'][$option] = $this->parseValue( $optionValue );
						}
					}
					break;
				case 'tutorial':
					if ( ( $value['enabled'] ?? true ) && ( $value['wikitext'] ?? false ) ) {
						// Parse tutorial wikitext
						$parsedConfig['tutorial'] = [
							'enabled' => true,
							'skip' => $value['skip'] ?? false,
							'html' => $this->parseValue( $value['wikitext'] ),
						];
					} else {
						// The tutorial is not present or is disabled
						$parsedConfig['tutorial'] = [
							'enabled' => false,
							'skip' => true,
							'html' => '',
						];
					}
					break;
				case 'fields':
					$parsedConfig['fields'] = [];
					foreach ( $value as $field ) {
						$parsedConfig['fields'][] = $this->parseArrayValues(
							$field,
							[ 'label', 'options' ]
						);
					}
					break;
				case 'whileActive':
				case 'afterActive':
				case 'beforeActive':
					if ( array_key_exists( 'display', $value ) ) {
						$value['display'] = $this->parseArrayValues( $value['display'] );
					}
					$parsedConfig[$key] = $value;
					break;
				default:
					$parsedConfig[$key] = $value;
					break;
			}
		}

		$this->parsedConfig = $parsedConfig;
	}

	/**
	 * Parses the values in an associative array as wikitext
	 *
	 * @param array $array
	 * @param array|null $forKeys Array of keys whose values should be parsed
	 *
	 * @return array
	 */
	private function parseArrayValues( array $array, $forKeys = null ): array {
		$parsed = [];
		foreach ( $array as $key => $value ) {
			if ( $forKeys !== null ) {
				if ( in_array( $key, $forKeys ) ) {
					if ( is_array( $value ) ) {
						$parsed[$key] = $this->parseArrayValues( $value );
					} else {
						$parsed[$key] = $this->parseValue( $value );
					}
				} else {
					$parsed[$key] = $value;
				}
			} else {
				$parsed[$key] = $this->parseValue( $value );
			}
		}
		return $parsed;
	}

	/**
	 * Parses a wikitext fragment to HTML
	 *
	 * @param string $value Wikitext to parse
	 *
	 * @return string HTML
	 */
	private function parseValue( string $value ): string {
		$output = $this->parserFactory->create()->parse(
			$value, $this->pageRef, $this->parserOptions
		);
		$parsed = $output->getText( [
			'enableSectionEditLinks' => false,
		] );

		$this->updateTemplates( $output );

		return Parser::stripOuterParagraph( $parsed );
	}

	/**
	 * Update internal list of templates used in parsing this config
	 *
	 * @param ParserOutput $parserOutput
	 *
	 * @return void
	 */
	private function updateTemplates( ParserOutput $parserOutput ): void {
		$templateIds = $parserOutput->getTemplateIds();
		foreach ( $parserOutput->getTemplates() as $ns => $templates ) {
			foreach ( $templates as $dbk => $id ) {
				$this->templates[$ns][$dbk] = [ $id, $templateIds[$ns][$dbk] ];
			}
		}
	}
}
