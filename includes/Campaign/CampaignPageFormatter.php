<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use ImageGalleryBase;
use MediaWiki\Extension\MediaUploader\Config\CampaignParsedConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\Html\Html;
use MediaWiki\Skin\SkinComponentUtils;
use MediaWiki\Title\Title;
use MWException;
use ParserOutput;
use RequestContext;

/**
 * Helper class to produce formatted HTML output for campaigns
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
		$campaignTitle = Title::makeTitle(
			NS_CAMPAIGN,
			$this->record->getPage()->getDBkey()
		);
		$campaignName = $this->config->getSetting(
			'title',
			$campaignTitle->getText()
		);

		$campaignDescription = $this->config->getSetting( 'description', '' );
		$trackingCat = Title::newFromText(
			$this->record->getTrackingCategoryName( $this->config ),
			NS_CATEGORY
		);
		$campaignViewMoreLink = $trackingCat ? $trackingCat->getFullURL() : '';

		$outputPage = $this->context->getOutput();
		$outputPage->setCdnMaxage(
			$this->config->getSetting( 'campaignCdnMaxAge' )
		);
		$outputPage->enableOOUI();

		$stats = $this->campaignStats->getStatsForRecord( $this->record ) ?? [];
		$images = $stats['uploadedMedia'] ?? [];

		if ( $this->context->getUser()->isAnon() ) {
			$urlParams = [ 'returnto' => $campaignTitle->getPrefixedText() ];
			$createAccountUrl = SkinComponentUtils::makeSpecialUrlSubpage( 'Userlogin', 'signup', $urlParams );
			$uploadLink = new \OOUI\ButtonWidget( [
				'label' => $this->context->msg( 'mediauploader-campaign-create-account-button' )->text(),
				'flags' => [ 'progressive', 'primary' ],
				'href' => $createAccountUrl
			] );
		} else {
			$uploadUrl = SkinComponentUtils::makeSpecialUrl(
				'MediaUploader', [ 'campaign' => $this->record->getPage()->getDBkey() ]
			);
			$uploadLink = new \OOUI\ButtonWidget( [
				'label' => $this->context->msg( 'mediauploader-campaign-upload-button' )->text(),
				'flags' => [ 'progressive', 'primary' ],
				'href' => $uploadUrl
			] );
		}

		if ( $images === [] ) {
			$body = Html::element(
				'div',
				[ 'id' => 'mw-campaign-no-uploads-yet' ],
				$this->context->msg( 'mediauploader-campaign-no-uploads-yet' )->plain()
			);
		} else {
			$gallery = ImageGalleryBase::factory( 'packed-hover', $this->context );
			$gallery->setShowBytes( false );

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
					$this->context->msg( 'mediauploader-campaign-view-all-media' )->escaped() .
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
					$this->context->msg( 'mediauploader-campaign-contributors-count-desc' )
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
						Html::rawElement( 'p', [ 'id' => 'mw-campaign-title' ], $campaignName ) .
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
								$this->context->msg( 'mediauploader-campaign-media-count-desc' )
								->numParams( $uploadCount )
								->text()
							)
						)
					)
				) .
				$body
			);
		$output->setContentHolderText( $result );
	}
}
