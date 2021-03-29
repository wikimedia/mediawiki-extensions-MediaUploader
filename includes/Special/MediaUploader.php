<?php
/**
 * Special:MediaUploader
 *
 * Easy to use multi-file upload page.
 *
 * @file
 * @ingroup SpecialPage
 * @ingroup Upload
 */

namespace MediaWiki\Extension\MediaUploader\Special;

use BitmapHandler;
use ChangeTags;
use DerivativeContext;
use Html;
use LogicException;
use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignContentException;
use MediaWiki\Extension\MediaUploader\Config\ConfigFactory;
use MediaWiki\Extension\MediaUploader\Config\ParsedConfig;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\Hooks\RegistrationHooks;
use MediaWiki\Widget\SpinnerWidget;
use PermissionsError;
use SpecialPage;
use Title;
use UploadBase;
use UploadFromUrl;
use UploadWizardCampaign;
use User;
use UserBlockedError;

class MediaUploader extends SpecialPage {
	/**
	 * The name of the upload wizard campaign, or null when none is specified.
	 *
	 * @var string|null
	 */
	private $campaign = null;

	/** @var ParsedConfig|null */
	private $loadedConfig = null;

	/** @var ConfigFactory */
	private $configFactory;

	/** @var RawConfig */
	private $rawConfig;

	public function __construct(
		RawConfig $rawConfig,
		ConfigFactory $configFactory
	) {
		parent::__construct( 'MediaUploader', 'upload' );

		$this->configFactory = $configFactory;
		$this->rawConfig = $rawConfig;
	}

	/**
	 * Replaces default execute method
	 * Checks whether uploading enabled, user permissions okay,
	 * @param string|null $subPage subpage, e.g. the "foo" in Special:MediaUploader/foo.
	 */
	public function execute( $subPage ) {
		// side effects: if we can't upload, will print error page to wgOut
		// and return false
		if ( !( $this->isUploadAllowed() && $this->isUserUploadAllowed( $this->getUser() ) ) ) {
			return;
		}

		$this->setHeaders();
		$this->outputHeader();

		$req = $this->getRequest();

		$urlOverrides = [];
		$urlArgs = [ 'description', 'lat', 'lon', 'alt' ];

		foreach ( $urlArgs as $arg ) {
			$value = $req->getText( $arg );
			if ( $value ) {
				$urlOverrides['defaults'][$arg] = $value;
			}
		}

		$categories = $req->getText( 'categories' );
		if ( $categories ) {
			$urlOverrides['defaults']['categories'] = explode( '|', $categories );
		}

		$fields = $req->getArray( 'fields' );

		# Support id and id2 for field0 and field1
		# Legacy support for old URL structure. They override fields[]
		if ( $req->getText( 'id' ) ) {
			$fields[0] = $req->getText( 'id' );
		}

		if ( $req->getText( 'id2' ) ) {
			$fields[1] = $req->getText( 'id2' );
		}

		if ( $fields ) {
			foreach ( $fields as $index => $value ) {
				$urlOverrides['fields'][$index]['initialValue'] = $value;
			}
		}

		$this->loadConfig( $urlOverrides );

		$out = $this->getOutput();

		// fallback for non-JS
		$out->addHTML( '<div class="mwe-upwiz-unavailable">' );
		$out->addHTML( '<p class="errorbox">' . $this->msg( 'mwe-upwiz-unavailable' )->parse() . '</p>' );
		// create a simple form for non-JS fallback, which targets the old Special:Upload page.
		// at some point, if we completely subsume its functionality, change that to point here again,
		// but then we'll need to process non-JS uploads in the same way Special:Upload does.
		$derivativeContext = new DerivativeContext( $this->getContext() );
		$derivativeContext->setTitle( SpecialPage::getTitleFor( 'Upload' ) );
		$simpleForm = new MediaUploaderSimpleForm( [], $derivativeContext, $this->getLinkRenderer() );
		$simpleForm->show();
		$out->addHTML( '</div>' );

		// global javascript variables
		$this->addJsVars( $subPage );

		// dependencies (css, js)
		$out->addModules( 'ext.uploadWizard.page' );
		$out->addModuleStyles( 'ext.uploadWizard.page.styles' );
		// load spinner styles early
		$out->addModuleStyles( 'jquery.spinner.styles' );

		// where the uploadwizard will go
		// TODO import more from MediaUploader's createInterface call.
		$out->addHTML( $this->getWizardHtml() );
	}

	/**
	 * Handles the campaign parameter and loads the appropriate config.
	 *
	 * @param array $urlOverrides
	 */
	protected function loadConfig( array $urlOverrides ) {
		$campaignName = $this->getRequest()->getVal( 'campaign' );
		if ( $campaignName === null ) {
			$campaignName = $this->rawConfig->getSetting( 'defaultCampaign' );
		}

		if ( $campaignName !== null && $campaignName !== '' ) {
			try {
				$campaign = UploadWizardCampaign::newFromName(
					$campaignName,
					$urlOverrides
				);

				if ( $campaign === null ) {
					$this->displayError(
						$this->msg( 'mwe-upwiz-error-nosuchcampaign', $campaignName )->text()
					);
				} elseif ( !$campaign->isEnabled() ) {
					$this->displayError(
						$this->msg( 'mwe-upwiz-error-campaigndisabled', $campaignName )->text()
					);
				} else {
					$this->campaign = $campaignName;
					$this->loadedConfig = $campaign->getConfig();
				}
			} catch ( InvalidCampaignContentException $e ) {
				$this->displayError( $e->getMessage() );
			}
		}

		// This is not a campaign or the campaign failed to load
		// Either way, we fall back to the global config
		if ( $this->loadedConfig === null ) {
			$this->loadedConfig = $this->configFactory->newGlobalConfig(
				$this->getUser(),
				$this->getLanguage(),
				$urlOverrides
			);
		}
	}

	/**
	 * Display an error message.
	 *
	 * @since 1.2
	 *
	 * @param string $message
	 */
	protected function displayError( $message ) {
		$this->getOutput()->addHTML( Html::element(
			'span',
			[ 'class' => 'errorbox' ],
			$message
		) . '<br /><br /><br />' );
	}

	/**
	 * Adds some global variables for our use, as well as initializes the MediaUploader
	 *
	 * TODO This should be factored out somewhere so that MediaUploader can be included
	 *  dynamically.
	 *
	 * @param string $subPage subpage, e.g. the "foo" in Special:MediaUploader/foo
	 */
	public function addJsVars( $subPage ) {
		$config = $this->loadedConfig->getConfigArray();

		if ( array_key_exists( 'trackingCategory', $config ) ) {
			if ( array_key_exists( 'campaign', $config['trackingCategory'] ) ) {
				if ( $this->campaign !== null ) {
					$config['trackingCategory']['campaign'] = str_replace(
						'$1',
						$this->campaign,
						$config['trackingCategory']['campaign']
					);
				} else {
					unset( $config['trackingCategory']['campaign'] );
				}
			}
		}
		// UploadFromUrl parameter set to true only if the user is allowed to upload a file
		// from a URL which we need to check in our Javascript implementation.
		if ( UploadFromUrl::isEnabled() && UploadFromUrl::isAllowed( $this->getUser() ) === true ) {
			$config['UploadFromUrl'] = true;
		} else {
			$config['UploadFromUrl'] = false;
		}

		// Get the user's default license. This will usually be 'default', but
		// can be a specific license like 'ownwork-cc-zero'.
		$userDefaultLicense = $this->getUser()->getOption( 'upwiz_deflicense' );

		if ( $userDefaultLicense !== 'default' ) {
			$licenseParts = explode( '-', $userDefaultLicense, 2 );
			$userLicenseType = $licenseParts[0];
			$userDefaultLicense = $licenseParts[1];

			// Determine if the user's default license is valid for this campaign
			switch ( $config['licensing']['ownWorkDefault'] ) {
				case "own":
					$defaultInAllowedLicenses = in_array(
						$userDefaultLicense, $config['licensing']['ownWork']['licenses']
					);
					break;
				case "notown":
					$defaultInAllowedLicenses = in_array(
						$userDefaultLicense, $this->loadedConfig->getThirdPartyLicenses()
					);
					break;
				case "choice":
					$defaultInAllowedLicenses = ( in_array(
							$userDefaultLicense, $config['licensing']['ownWork']['licenses']
						) ||
						in_array( $userDefaultLicense, $this->loadedConfig->getThirdPartyLicenses() ) );
					break;
				default:
					throw new LogicException( 'Bad ownWorkDefault config' );
			}

			if ( $defaultInAllowedLicenses ) {
				if ( $userLicenseType === 'ownwork' ) {
					$userLicenseGroup = 'ownWork';
				} else {
					$userLicenseGroup = 'thirdParty';
				}
				$config['licensing'][$userLicenseGroup]['defaults'] = [ $userDefaultLicense ];
				$config['licensing']['defaultType'] = $userLicenseType;

				if ( $userDefaultLicense === 'custom' ) {
					$config['licenses']['custom']['defaultText'] =
						$this->getUser()->getOption( 'upwiz_deflicense_custom' );
				}
			}
		}

		// add an 'uploadwizard' tag, but only if it'll be allowed
		$status = ChangeTags::canAddTagsAccompanyingChange(
			RegistrationHooks::CHANGE_TAGS,
			$this->getUser()
		);
		$config['CanAddTags'] = $status->isOK();

		// Upload comment should be localized with respect to the wiki's language
		$config['uploadComment'] = [
			'ownWork' => $this->msg( 'mwe-upwiz-upload-comment-own-work' )
				->inContentLanguage()->plain(),
			'thirdParty' => $this->msg( 'mwe-upwiz-upload-comment-third-party' )
				->inContentLanguage()->plain()
		];

		// maxUploads and maxFlickrUploads depend on the user's rights
		$canMassUpload = $this->getUser()->isAllowed( 'mass-upload' );
		$config['maxUploads'] = $this->getMaxUploads(
			$config['maxUploads'],
			$canMassUpload,
			50
		);
		$config['maxFlickrUploads'] = $this->getMaxUploads(
			$config['maxFlickrUploads'],
			$canMassUpload,
			4
		);

		$bitmapHandler = new BitmapHandler();
		$this->getOutput()->addJsConfigVars(
			[
				'UploadWizardConfig' => $config,
				'wgFileCanRotate' => $bitmapHandler->canRotate(),
			]
		);
	}

	/**
	 * Returns the value for maxUploads and maxFlickrUpload settings, based on
	 * whether the user has the mass-upload user right.
	 *
	 * @param mixed $setting
	 * @param bool $canMassUpload
	 * @param int $default
	 *
	 * @return mixed
	 */
	private function getMaxUploads( $setting, bool $canMassUpload, int $default ) {
		if ( is_array( $setting ) ) {
			if ( $canMassUpload && in_array( 'mass_upload', $setting ) ) {
				return $setting['mass-upload'];
			} else {
				return $setting['*'] ?? $default;
			}
		}
		return $setting;
	}

	/**
	 * Check if anyone can upload (or if other sitewide config prevents this)
	 * Side effect: will print error page to wgOut if cannot upload.
	 * @return bool -- true if can upload
	 */
	private function isUploadAllowed() {
		// Check uploading enabled
		if ( !UploadBase::isEnabled() ) {
			$this->getOutput()->showErrorPage( 'uploaddisabled', 'uploaddisabledtext' );
			return false;
		}

		// Check whether we actually want to allow changing stuff
		$this->checkReadOnly();

		// we got all the way here, so it must be okay to upload
		return true;
	}

	/**
	 * Check if the user can upload
	 * Side effect: will print error page to wgOut if cannot upload.
	 * @param User $user
	 * @throws PermissionsError
	 * @throws UserBlockedError
	 * @return bool -- true if can upload
	 */
	private function isUserUploadAllowed( User $user ) {
		// Check permissions
		$permissionRequired = UploadBase::isAllowed( $user );
		if ( $permissionRequired !== true ) {
			throw new PermissionsError( $permissionRequired );
		}

		// Check blocks
		if ( $user->isBlockedFromUpload() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Global blocks
		if ( $user->isBlockedGlobally() ) {
			throw new UserBlockedError( $user->getGlobalBlock() );
		}

		// we got all the way here, so it must be okay to upload
		return true;
	}

	/**
	 * Return the basic HTML structure for the entire page
	 * Will be enhanced by the javascript to actually do stuff
	 * @return string html
	 * @suppress SecurityCheck-XSS The documentation of $config['display']['headerLabel'] says,
	 *   it is wikitext, but all *label are used as html
	 */
	protected function getWizardHtml() {
		$config = $this->loadedConfig->getConfigArray();

		if ( array_key_exists(
			'display', $config ) && array_key_exists( 'headerLabel', $config['display'] )
		) {
			$this->getOutput()->addHTML( $config['display']['headerLabel'] );
		}

		if ( array_key_exists( 'fallbackToAltUploadForm', $config )
			&& array_key_exists( 'altUploadForm', $config )
			&& $config['altUploadForm'] != ''
			&& $config[ 'fallbackToAltUploadForm' ]
		) {
			$linkHtml = '';
			$altUploadForm = Title::newFromText( $config[ 'altUploadForm' ] );
			if ( $altUploadForm instanceof Title ) {
				$linkHtml = Html::rawElement( 'p', [ 'style' => 'text-align: center;' ],
					Html::element( 'a', [ 'href' => $altUploadForm->getLocalURL() ],
						$config['altUploadForm']
					)
				);
			}

			return Html::rawElement(
				'div',
				[],
				Html::element(
					'p',
					[ 'style' => 'text-align: center' ],
					$this->msg( 'mwe-upwiz-extension-disabled' )->text()
				) . $linkHtml
			);
		}

		// TODO move this into UploadWizard.js or some other javascript resource so the upload wizard
		// can be dynamically included ( for example the add media wizard )
		// @codingStandardsIgnoreStart
		return '<div id="upload-wizard" class="upload-section">' .
			'<div id="mwe-upwiz-tutorial-html" style="display:none;">' .
				$config['tutorial']['html'] .
			'</div>' .
			'<div class="mwe-first-spinner">' .
				new SpinnerWidget( [ 'size' => 'large' ] ) .
			'</div>' .
		'</div>';
		// @codingStandardsIgnoreEnd
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'media';
	}
}
