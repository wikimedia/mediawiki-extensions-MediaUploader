<?php

use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStats;
use MediaWiki\Extension\MediaUploader\Config\CampaignParsedConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;

/**
 * Helper class to produce formatted HTML output for Campaigns
 */
class CampaignPageFormatter {

	/** @var CampaignRecord */
	private $record;

	/** @var CampaignParsedConfig */
	private $config;

	/** @var CampaignStats */
	private $campaignStats;

	/** @var RequestContext */
	private $context;

	public function __construct(
		CampaignRecord $campaignRecord,
		CampaignParsedConfig $config
	) {
		$this->record = $campaignRecord;
		$this->config = $config;

		// TODO: use DI
		$this->campaignStats = MediaUploaderServices::getCampaignStats();
		// No way to get rid of this for now.
		// Blockers: ImageGalleryBase and message localization.
		$this->context = RequestContext::getMain();
	}

	/**
	 * @param ParserOutput $output
	 *
	 * @return void
	 * @throws MWException
	 */
	public function fillParserOutput( ParserOutput $output ): void {
		$campaignTitle = $this->config->getSetting(
			'title',
			$this->record->getTitle()->getText()
		);
		$campaignDescription = $this->config->getSetting( 'description', '' );
		$trackingCat = Title::newFromText(
			$this->record->getTrackingCategoryName( $this->config ),
			NS_CATEGORY
		);
		$campaignViewMoreLink = $trackingCat ? $trackingCat->getFullURL() : '';

		$gallery = ImageGalleryBase::factory( 'packed-hover', $this->context );
		$gallery->setWidths( '180' );
		$gallery->setHeights( '180' );
		$gallery->setShowBytes( false );

		$outputPage = $this->context->getOutput();
		$outputPage->setCdnMaxage(
			$this->config->getSetting( 'campaignSquidMaxAge' )
		);
		$outputPage->enableOOUI();

		$stats = $this->campaignStats->getStatsForRecord( $this->record ) ?? [];
		$images = $stats['uploadedMedia'] ?? [];

		if ( $this->context->getUser()->isAnon() ) {
			$urlParams = [ 'returnto' => $this->record->getTitle()->getPrefixedText() ];
			$createAccountUrl = Skin::makeSpecialUrlSubpage( 'Userlogin', 'signup', $urlParams );
			$uploadLink = new OOUI\ButtonWidget( [
				'label' => $this->context->msg( 'mwe-upwiz-campaign-create-account-button' )->text(),
				'flags' => [ 'progressive', 'primary' ],
				'href' => $createAccountUrl
			] );
		} else {
			$uploadUrl = Skin::makeSpecialUrl(
				'MediaUploader', [ 'campaign' => $this->record->getTitle()->getDBkey() ]
			);
			$uploadLink = new OOUI\ButtonWidget( [
				'label' => $this->context->msg( 'mwe-upwiz-campaign-upload-button' )->text(),
				'flags' => [ 'progressive', 'primary' ],
				'href' => $uploadUrl
			] );
		}

		if ( $images === [] ) {
			$body = Html::element(
				'div',
				[ 'id' => 'mw-campaign-no-uploads-yet' ],
				$this->context->msg( 'mwe-upwiz-campaign-no-uploads-yet' )->plain()
			);
		} else {
			foreach ( $images as $image ) {
				$gallery->add( Title::newFromText( $image, NS_FILE ) );
			}

			$body =
				Html::rawElement( 'div', [ 'id' => 'mw-campaign-images' ], $gallery->toHTML() ) .
				Html::rawElement( 'a',
					[ 'id' => 'mw-campaign-view-all', 'href' => $campaignViewMoreLink ],
					Html::rawElement(
						'span',
						[ 'class' => 'mw-campaign-chevron mw-campaign-float-left' ], '&nbsp;'
					) .
					$this->context->msg( 'mwe-upwiz-campaign-view-all-media' )->escaped() .
					Html::rawElement(
						'span',
						[ 'class' => 'mw-campaign-chevron mw-campaign-float-right' ], '&nbsp;'
					)
				);
		}

		$contributorsCount = $stats['contributorsCount'] ?? 0;
		$campaignExpensiveStats =
			Html::rawElement( 'div', [ 'class' => 'mw-campaign-number-container' ],
				Html::element( 'div', [ 'class' => 'mw-campaign-number' ],
					$this->context->getLanguage()->formatNum( $contributorsCount ) ) .
				Html::element( 'span',
					[ 'class' => 'mw-campaign-number-desc' ],
					$this->context->msg( 'mwe-upwiz-campaign-contributors-count-desc' )
					->numParams( $contributorsCount )
					->text()
				)
			);

		$uploadCount = $stats['uploadedMediaCount'] ?? 0;
		$result =
			Html::rawElement( 'div', [ 'id' => 'mw-campaign-container' ],
				Html::rawElement( 'div', [ 'id' => 'mw-campaign-header' ],
					Html::rawElement( 'div', [ 'id' => 'mw-campaign-primary-info' ],
						// No need to escape these, since they are just parsed wikitext
						// Any stripping that needed to be done should've been done by the parser
						Html::rawElement( 'p', [ 'id' => 'mw-campaign-title' ], $campaignTitle ) .
						Html::rawElement( 'p', [ 'id' => 'mw-campaign-description' ], $campaignDescription ) .
					$uploadLink
					) .
					Html::rawElement( 'div', [ 'id' => 'mw-campaign-numbers' ],
						$campaignExpensiveStats .
						Html::rawElement( 'div', [ 'class' => 'mw-campaign-number-container' ],
							Html::element( 'div', [ 'class' => 'mw-campaign-number' ],
								$this->context->getLanguage()->formatNum( $uploadCount )
							) .
							Html::element( 'span',
								[ 'class' => 'mw-campaign-number-desc' ],
								$this->context->msg( 'mwe-upwiz-campaign-media-count-desc' )
								->numParams( $uploadCount )
								->text()
							)
						)
					)
				) .
				$body
			);
		$output->setText( $result );
	}
}
