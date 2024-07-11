<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use Content;
use Html;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\BaseCampaignException;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ParsedConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use ParserOutput;
use TextContentHandler;
use Title;

/**
 * Content handler for campaign pages.
 */
class CampaignContentHandler extends TextContentHandler {

	/** @var ConfigFactory */
	private $configFactory;

	/**
	 * @param string $modelId
	 * @param ConfigFactory $configFactory
	 */
	public function __construct( $modelId, ConfigFactory $configFactory ) {
		parent::__construct( $modelId, [ CONTENT_FORMAT_CAMPAIGN ] );
		$this->configFactory = $configFactory;
	}

	/**
	 * @return class-string<CampaignContent>
	 */
	protected function getContentClass() {
		return CampaignContent::class;
	}

	/**
	 * @return CampaignContent
	 */
	public function makeEmptyContent() {
		return new CampaignContent( 'enabled: false' );
	}

	/**
	 * Normalizes line endings before saving.
	 *
	 * @param Content $content
	 * @param PreSaveTransformParams $pstParams
	 *
	 * @return CampaignContent
	 */
	public function preSaveTransform( Content $content, PreSaveTransformParams $pstParams ): Content {
		/** @var CampaignContent $content */
		'@phan-var CampaignContent $content';
		// Allow the system user to bypass format and schema checks
		if ( MediaUploaderServices::isSystemUser( $pstParams->getUser() ) ) {
			$content->overrideValidationStatus();
		}

		if ( !$content->isValid() ) {
			return $content;
		}

		return $content->copyWithNewText(
			CampaignContent::normalizeLineEndings( $content->getText() )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function fillParserOutput(
		Content $content, ContentParseParams $cpoParams, ParserOutput &$output
	) {
		/** @var CampaignContent $content */
		'@phan-var CampaignContent $content';
		$page = $cpoParams->getPage();
		if ( $page->getDBkey() === CampaignContent::GLOBAL_CONFIG_ANCHOR_DBKEY ) {
			// Handle the case of the global config anchor.
			// We ignore config cache as this function may have been called by a
			// recursive LinksUpdate, which means there are probably some templates
			// that this config depends on that have changed. It's also possible
			// that this was caused by a null edit by GlobalConfigAnchorUpdateJob,
			// but then it still is safer to reparse the config than rely on cache
			// that may be out of date.
			$config = $this->configFactory->newGlobalConfig(
				$cpoParams->getParserOptions(),
				[],
				true
			);

			$this->registerTemplates( $config, $output );

			if ( $cpoParams->getGenerateHtml() ) {
				$output->setText(
					wfMessage( 'mediauploader-global-config-anchor' )->parseAsBlock()
				);
			}
			return;
		}

		// Handle a regular campaign.
		$record = $content->newCampaignRecord( $cpoParams->getPage() );

		try {
			// Page ref and content will always be set, no need to check that.
			$record->assertValid( $cpoParams->getPage()->getDBkey() );
		} catch ( BaseCampaignException $e ) {
			$output->setText( Html::errorBox( $e->getMessage() ) );
			return;
		}

		// Here we also ignore the cache, as there's no way to tell whether
		// it's just someone viewing the page and parser cache has expired, or there
		// was an actual edit or a LinksUpdate. We can't defer this until later
		// (like PageSaveComplete or LinksUpdateComplete), because we can't modify
		// ParserOutput at those points. We need an up-to-date list of templates here
		// and now.
		$campaignConfig = $this->configFactory->newCampaignConfig(
			$cpoParams->getParserOptions(),
			$record,
			$page,
			[],
			true
		);

		if ( $cpoParams->getGenerateHtml() ) {
			$formatter = new CampaignPageFormatter( $record, $campaignConfig );
			$formatter->fillParserOutput( $output );
		}

		$this->registerTemplates( $campaignConfig, $output );

		// Add some styles
		$output->addModuleStyles( [ 'ext.uploadWizard.uploadCampaign.display' ] );
	}

	/**
	 * Registers templates used in a ParsedConfig with a ParserOutput.
	 *
	 * @param ParsedConfig $parsedConfig
	 * @param ParserOutput $parserOutput
	 */
	private function registerTemplates(
		ParsedConfig $parsedConfig,
		ParserOutput $parserOutput
	): void {
		// FIXME: should we be registering other stuff??
		foreach ( $parsedConfig->getTemplates() as $ns => $templates ) {
			foreach ( $templates as $dbk => $ids ) {
				$title = Title::makeTitle( $ns, $dbk );
				$parserOutput->addTemplate( $title, $ids[0], $ids[1] );
			}
		}
	}
}
