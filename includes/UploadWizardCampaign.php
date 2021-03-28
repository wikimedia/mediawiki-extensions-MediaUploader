<?php

use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Config\CampaignParsedConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\MediaWikiServices;

/**
 * Class that represents a single upload campaign.
 * An upload campaign is stored as a row in the uw_campaigns table,
 * and its configuration is stored in the Campaign: namespace
 *
 * This class is 'readonly' - to modify the campaigns, please
 * edit the appropriate Campaign: namespace page
 *
 * TODO: Don't get too emotionally attached to this class. It should be
 *  rewritten to support DI.
 *
 * @file
 * @ingroup Upload
 *
 * @license GPL-2.0-or-later
 * @author Yuvi Panda <yuvipanda@gmail.com>
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class UploadWizardCampaign {

	/**
	 * The Title representing the current campaign
	 *
	 * @var Title|null
	 */
	protected $title = null;

	/** @var CampaignParsedConfig */
	private $config;

	/** @var CampaignContent */
	private $content;

	/**
	 * @param string $name
	 * @param array $urlOverrides
	 *
	 * @return UploadWizardCampaign|null
	 */
	public static function newFromName(
		string $name,
		array $urlOverrides = []
	) : ?UploadWizardCampaign {
		$campaignTitle = Title::makeTitleSafe( NS_CAMPAIGN, $name );

		return self::newFromTitle( $campaignTitle, $urlOverrides );
	}

	/**
	 * @param Title $title
	 * @param array $urlOverrides
	 * @param CampaignContent|null $content
	 * @param bool $noConfigCache Whether to ignore config cache
	 *
	 * @return UploadWizardCampaign|null
	 */
	public static function newFromTitle(
		Title $title,
		array $urlOverrides = [],
		CampaignContent $content = null,
		bool $noConfigCache = false
	) : ?self {
		if ( !$title->exists() ) {
			return null;
		}

		if ( $content === null ) {
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
			$content = $wikiPageFactory->newFromTitle( $title )->getContent();

			if ( !$content instanceof CampaignContent ) {
				return null;
			}
		}

		return new self( $title, $content, $urlOverrides, $noConfigCache );
	}

	/**
	 * Use factory methods instead.
	 *
	 * @param Title $title
	 * @param CampaignContent $content
	 * @param array $urlOverrides
	 * @param bool $noConfigCache
	 */
	private function __construct(
		Title $title,
		CampaignContent $content,
		array $urlOverrides,
		bool $noConfigCache
	) {
		$requestContext = RequestContext::getMain();
		$configFactory = MediaUploaderServices::getConfigFactory();

		$this->config = $configFactory->newCampaignConfig(
			$requestContext->getUser(),
			$requestContext->getLanguage(),
			$content,
			$title,
			$urlOverrides,
			$noConfigCache
		);

		$this->title = $title;
		$this->content = $content;
	}

	/**
	 * Returns name of current campaign
	 *
	 * @return string
	 */
	public function getName() {
		return $this->title->getDBkey();
	}

	public function getTitle() {
		return $this->title;
	}

	/**
	 * @return CampaignParsedConfig
	 */
	public function getConfig() : CampaignParsedConfig {
		return $this->config;
	}

	/**
	 * Whether this campaign is enabled.
	 *
	 * @return bool
	 */
	public function isEnabled() : bool {
		return $this->config->getSetting( 'enabled' );
	}

	/**
	 * TODO: What is... going on here?!
	 *  restructure this to make some sense
	 *  remove redundant code from MediaUploader::addJsVars
	 *
	 * @return Title
	 */
	public function getTrackingCategory() : Title {
		$trackingCats = $this->config->getSetting( 'trackingCategory' );
		return Title::makeTitleSafe(
			NS_CATEGORY, str_replace( '$1', $this->getName(), $trackingCats['campaign'] )
		);
	}

	public function getUploadedMediaCount() {
		return Category::newFromTitle( $this->getTrackingCategory() )->getFileCount();
	}

	public function getTotalContributorsCount() {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;

		return $cache->getWithSetCallback(
			$cache->makeKey( 'uploadwizard-campaign-contributors-count', $this->getName() ),
			$this->config->getSetting( 'campaignStatsMaxAge' ),
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $fname ) {
				$dbr = wfGetDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );

				$actorQuery = ActorMigration::newMigration()->getJoin( 'img_user' );

				$result = $dbr->select(
					[ 'categorylinks', 'page', 'image' ] + $actorQuery['tables'],
					[ 'count' => 'COUNT(DISTINCT ' . $actorQuery['fields']['img_user'] . ')' ],
					[ 'cl_to' => $this->getTrackingCategory()->getDBkey(), 'cl_type' => 'file' ],
					$fname,
					[
						'USE INDEX' => [ 'categorylinks' => 'cl_timestamp' ]
					],
					[
						'page' => [ 'INNER JOIN', 'cl_from=page_id' ],
						'image' => [ 'INNER JOIN', 'page_title=img_name' ]
					] + $actorQuery['joins']
				);

				return $result->current()->count;
			}
		);
	}

	/**
	 * @param int $limit
	 *
	 * @return Title[]
	 */
	public function getUploadedMedia( $limit = 24 ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select(
			[ 'categorylinks', 'page' ],
			[ 'cl_from', 'page_namespace', 'page_title' ],
			[ 'cl_to' => $this->getTrackingCategory()->getDBkey(), 'cl_type' => 'file' ],
			__METHOD__,
			[
				'ORDER BY' => 'cl_timestamp DESC',
				'LIMIT' => $limit,
				'USE INDEX' => [ 'categorylinks' => 'cl_timestamp' ]
			],
			[ 'page' => [ 'INNER JOIN', 'cl_from=page_id' ] ]
		);

		$images = [];
		foreach ( $result as $row ) {
			$images[] = Title::makeTitle( $row->page_namespace, $row->page_title );
		}

		return $images;
	}
}
