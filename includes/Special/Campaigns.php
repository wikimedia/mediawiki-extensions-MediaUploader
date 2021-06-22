<?php

namespace MediaWiki\Extension\MediaUploader\Special;

use Html;
use LogicException;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignException;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use SpecialPage;

class Campaigns extends SpecialPage {

	/** @var CampaignStore */
	private $campaignStore;

	/** @var ConfigFactory */
	private $configFactory;

	public function __construct(
		CampaignStore $campaignStore,
		ConfigFactory $configFactory
	) {
		parent::__construct( 'Campaigns' );

		$this->campaignStore = $campaignStore;
		$this->configFactory = $configFactory;
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$start = $request->getIntOrNull( 'start' );
		$limit = 50;

		$queryBuilder = $this->campaignStore->newSelectQueryBuilder()
			// TODO: we show all campaigns, add an option to filter by enabled.
			//  Also display whether the campaign is enabled or not.
			//->whereEnabled( true )
			->orderByIdAsc()
			->option( 'LIMIT', $limit + 1 );

		if ( $start !== null ) {
			$queryBuilder->where( "campaign_page_id > $start" );
		}

		$this->getOutput()->setPageTitle( $this->msg( 'mwe-upload-campaigns-list-title' ) );
		$this->getOutput()->addModules( 'ext.uploadWizard.uploadCampaign.list' );
		$this->getOutput()->addHTML( '<dl>' );

		$curCount = 0;
		$lastId = null;
		$records = $queryBuilder->fetchCampaignRecords( CampaignStore::SELECT_TITLE );

		foreach ( $records as $record ) {
			$curCount++;

			if ( $curCount > $limit ) {
				// We've got an extra element. Paginate!
				$lastId = $record->getPageId();
				break;
			}

			$this->getOutput()->addHTML( $this->getHtmlForCampaign( $record ) );
		}
		$this->getOutput()->addHTML( '</dl>' );

		// Pagination links!
		if ( $lastId !== null ) {
			$this->getOutput()->addHTML( $this->getHtmlForPagination( $lastId ) );
		}
	}

	/**
	 * @param CampaignRecord $record
	 *
	 * @return string
	 */
	private function getHtmlForCampaign( CampaignRecord $record ) : string {
		$title = $record->getTitle();
		if ( $title === null ) {
			// Should never happen. The 'if' is here to make Phan happy.
			throw new LogicException( 'Title for Campaign was expected to be set.' );
		}

		try {
			$campaignConfig = $this->configFactory->newCampaignConfig(
				$this->getUser(),
				$this->getLanguage(),
				$record,
				$title
			);
		} catch ( InvalidCampaignException $ex ) {
			// Display an error
			return Html::rawElement(
				'dt',
				[],
				Html::Element(
					'a',
					[ 'href' => $title->getLocalURL() ],
					$ex->getMessage()
				)
			);
		}

		return Html::rawElement(
			'dt',
			[],
			Html::rawElement(
				'a',
				[ 'href' => $title->getLocalURL() ],
				$campaignConfig->getSetting(
					'title',
					// Escape the raw title
					htmlspecialchars( $title->getText() )
				)
			)
		) .
		Html::rawElement(
			'dd',
			[],
			$campaignConfig->getSetting( 'description', '' )
		);
	}

	/**
	 * @param int $firstId
	 *
	 * @return string
	 */
	private function getHtmlForPagination( $firstId ) {
		$nextHref = $this->getPageTitle()->getLocalURL( [ 'start' => $firstId ] );
		return Html::rawElement( 'div',
			[ 'id' => 'mwe-upload-campaigns-pagination' ],
			Html::element( 'a',
				[ 'href' => $nextHref ],
				$this->msg( 'mwe-upload-campaigns-pagination-next' )->text()
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'media';
	}
}
