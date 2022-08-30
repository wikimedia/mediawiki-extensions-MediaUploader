<?php
/**
 * MediaUploader configuration
 * Do not modify this file, instead use LocalSettings.php and set:
 * $wgMediaUploaderConfig['name'] = 'value';
 */
return [
	// MediaUploader has an internal debug flag
	'debug' => false,

	// The default campaign to use.
	'defaultCampaign' => '',

	// Enable or disable the default upload license user preference
	'enableLicensePreference' => true,

	// Number of seconds to cache Campaign pages in squid, for anon users
	'campaignSquidMaxAge' => 10 * 60,

	// Number of seconds to cache Campaign stats
	// Currently affects: uploaded media list and contributors count
	'campaignStatsMaxAge' => 60,

	// File extensions acceptable in this wiki
	// The default is $wgFileExtensions if $wgCheckFileExtensions is enabled.
	// Initialized in RawConfig.php
	'fileExtensions' => null,

	// Settings about things that get automatically (and silently) added to uploads
	'autoAdd' => [
		// Categories to automatically (and silently) add all uploaded images into.
		'categories' => [],

		// WikiText to automatically (and silently) add to all uploaded images.
		'wikitext' => '',
	],

	// If the user didn't add categories, or removed the default categories, add this wikitext.
	// Use this to indicate that some human should categorize this file.
	// Does not consider autoAdd.categories, which are hidden.
	'missingCategoriesWikiText' => '',

	'display' => [
		// wikitext to display above the MediaUploader UI.
		'headerLabel' => '',

		// wikitext to display on top of the "use" page.
		// When not provided, the message mediauploader-thanks-intro will be used.
		'thanksLabel' => '',
	],

	// Settings for the tutorial to be shown.
	// Empty array if we want to skip
	'tutorial' => [
		// Set to false to hide the tutorial step entirely
		'enabled' => true,

		// Set to true to skip the tutorial by default
		'skip' => false,

		// Wikitext to be displayed in the tutorial step.
		// When set to a falsy value, the tutorial step will be hidden entirely.
		// The default is a message explaining how to configure the tutorial.
		// The parsed tutorial HTML is later placed in the 'html' property.
		'wikitext' => '{{int:mediauploader-default-tutorial-text}}',
	],

	// Tracking categories for various scenarios
	// Category names should be specified without the namespace prefix and with
	// underscores instead of spaces.
	'trackingCategory' => [
		// Category added no matter what
		// Default to none because we don't know what categories
		// exist or not on local wikis.
		// Do not uncomment this line, set
		// $wgMediaUploaderConfig['trackingCategory']['all']
		// to your favourite category name.

		// 'all' => '',

		// Tracking category added for campaigns. $1 is replaced with campaign page name
		// Changing this to an invalid value will prevent MediaUploader from collecting
		// statistics, such as the total number of uploads and contributors in a campaign.
		'campaign' => 'Uploaded_via_Campaign:$1'
	],

	// TODO: add link to a documentation page about this
	'fields' => [
		'title' => [
			'order' => 0,
			'type' => 'title',
			'label' => '{{MediaWiki:mediauploader-title}}',
			'help' => '{{MediaWiki:mediauploader-tooltip-title}}',
			'required' => 'required',
			'autoFill' => true,
			'minLength' => 5,
			'maxLength' => 240,
		],
		'description' => [
			'order' => 1,
			'type' => 'textarea',
			'label' => '{{MediaWiki:mediauploader-description}}',
			'help' => '{{MediaWiki:mediauploader-tooltip-description}}',
			'required' => 'required',
			'autoFill' => true,
			'minLength' => 5,
			'maxLength' => 10000,
		],
		// The license field is special: it is only used when the user uploads multiple
		// files and wants to choose a license for each of them separately.
		// All options besides 'order', 'label' and 'help' will be ignored here.
		'license' => [
			'order' => 2,
			'type' => 'license',
			'label' => '{{MediaWiki:mediauploader-copyright-info}}',
		],
		'date' => [
			'order' => 3,
			'type' => 'date',
			'label' => '{{MediaWiki:mediauploader-date-created}}',
			'help' => '{{MediaWiki:mediauploader-tooltip-date}}',
			'required' => 'recommended',
			'autoFill' => true,
		],
		'categories' => [
			'order' => 4,
			'type' => 'categories',
			'label' => '{{MediaWiki:mediauploader-categories}}',
			'help' => '{{MediaWiki:mediauploader-tooltip-categories}}',
			'required' => 'recommended',
		],
		'location' => [
			'order' => 5,
			'type' => 'location',
			'label' => '{{MediaWiki:mediauploader-location}}',
			'help' => '{{MediaWiki:mediauploader-tooltip-location}}',
			// Other available fields: altitude, heading
			'fields' => [ 'latitude', 'longitude' ],
			'auxiliary' => true,
			'autoFill' => true,
		],
		'other' => [
			'order' => 6,
			'type' => 'text',
			'label' => '{{MediaWiki:mediauploader-other}}',
			'help' => '{{MediaWiki:mediauploader-tooltip-other}}',
			'maxLength' => 10000,
			'auxiliary' => true,
		],
	],

	// How to transform the data from the fields into something useful
	'content' => [
		// The key of the field with the title of the uploaded file. It must be of type 'title'.
		'titleField' => 'title',
		// The field with the caption that will be used by default for the uploaded file.
		// It must be one of the types: text, textarea, singlelang, multilang
		'captionField' => 'description',
		// Wikitext template for building uploaded file description pages from user-provided information.
		// Parameters are passed as pseudo-template arguments – {{{param_name}}}. The name of the parameter corresponds
		// to the name of the field in the form. See the documentation for more information on this.
		// Save transformations ({{subst: }}) are supported.
		// When unspecified, the 'mediauploader-default-content-wikitext' message will be used instead.
		'wikitext' => '',
		// Wikitext to prepend before the 'wikitext' field. Useful in campaigns, when you want to include some extra
		// information.
		'prepend' => '',
		// Wikitext to append after the 'wikitext' field.
		'append' => '',
	],

	// 'languages' is a list of languages and codes, for use in the description step.
	// By default initialized to a list of all available languages that have corresponding
	// templates (in ISO 646 language codes). Additionally, the languageTemplateFixups
	// setting is taken into account (see below).
	// Initialized in RequestConfig.php
	'languages' => [],

	// The MediaUploader allows users to provide file descriptions in multiple languages. For each description, the user
	// can choose the language. The MediaUploader wraps each description in a "language template". A language template
	// is by default assumed to be a template with a name corresponding to the ISO 646 code of the language. For
	// instance, Template:en for English, or Template:fr for French.
	// If this is not the case for some or all or your wiki's language templates, this map can be used to define the
	// template names to be used. Keys are ISO 646 language codes, values are template names.
	'languageTemplateFixups' => [],

	// 'licenses' is a list of licenses you could possibly use elsewhere, for instance in
	// licensing['ownWork'] or licensing['thirdParty'].
	// It just describes what licenses go with what wikitext, and how to display them in
	// a menu of license choices. There probably isn't any reason to delete any entry here.
	// The 'wikitext' field tells the uploader how to mark this license on the file description page.
	// By default, this is just the name of the license, but if you want, you can put template names here.
	// See also the 'licensing' section, it allows you to wrap this wikitext into more complex stuff.
	// TODO: write down the docs about the optional parameters: assertMsg, explainMsg
	//
	// Messages used here:
	// * mediauploader-license-cc-by-4.0
	// * mediauploader-license-cc-by-3.0
	// * mediauploader-license-cc-by-2.5
	// * mediauploader-license-cc-by-2.0
	// * mediauploader-license-cc-by-nc-4.0
	// * mediauploader-license-cc-by-nc-3.0
	// * mediauploader-license-cc-by-nc-2.5
	// * mediauploader-license-cc-by-nc-2.0
	// * mediauploader-license-cc-by-nd-4.0
	// * mediauploader-license-cc-by-nd-3.0
	// * mediauploader-license-cc-by-nd-2.5
	// * mediauploader-license-cc-by-nd-2.0
	// * mediauploader-license-cc-by-nc-nd-4.0
	// * mediauploader-license-cc-by-nc-nd-3.0
	// * mediauploader-license-cc-by-nc-nd-2.5
	// * mediauploader-license-cc-by-nc-nd-2.0
	// * mediauploader-license-cc-by-nc-sa-4.0
	// * mediauploader-license-cc-by-nc-sa-3.0
	// * mediauploader-license-cc-by-nc-sa-2.5
	// * mediauploader-license-cc-by-nc-sa-2.0
	// * mediauploader-license-cc-by-sa-4.0
	// * mediauploader-license-cc-by-sa-3.0
	// * mediauploader-license-cc-by-sa-2.5
	// * mediauploader-license-cc-by-sa-2.0
	'licenses' => ( static function () {
		// Generate Creative Commons license variants
		$ccLicenses = [];
		foreach ( [ '2.0', '2.5', '3.0', '4.0' ] as $ccVer ) {
			foreach ( [ 'by', 'by-nc', 'by-nd', 'by-nc-nd', 'by-nc-sa', 'by-sa' ] as $ccType ) {
				$ccLicenses["cc-$ccType-$ccVer"] = [
					'msg' => "mediauploader-license-cc-$ccType-$ccVer",
					'icons' => array_map(
						static function ( $t ) {
							return "cc-$t";
						},
						explode( '-', $ccType )
					),
					'url' => "//creativecommons.org/licenses/$ccType/$ccVer/",
					'languageCodePrefix' => 'deed.',
					'wikitext' => "{{subst:int:mediauploader-license-cc-$ccType-$ccVer" .
						"||//creativecommons.org/licenses/$ccType/$ccVer/}}",
					'explainMsg' => "mediauploader-source-ownwork-cc-$ccType-explain"
				];
			}
		}
		return $ccLicenses;
	} )() + [
		'cc-zero' => [
			'msg' => 'mediauploader-license-cc-zero',
			'icons' => [ 'cc-zero' ],
			'url' => '//creativecommons.org/publicdomain/zero/1.0/',
			'languageCodePrefix' => 'deed.',
			'wikitext' => '{{subst:int:mediauploader-license-cc-zero||//creativecommons.org/publicdomain/zero/1.0/}}'
		],
		'fal' => [
			'msg' => 'mediauploader-license-fal',
			'wikitext' => '{{subst:int:mediauploader-license-fal}}'
		],
		'pd-old' => [
			'msg' => 'mediauploader-license-pd-old',
			'wikitext' => '{{subst:int:mediauploader-license-pd-old}}'
		],
		'pd-ineligible' => [
			'msg' => 'mediauploader-license-pd-ineligible',
			'wikitext' => '{{subst:int:mediauploader-license-pd-ineligible}}'
		],
		'attribution' => [
			'msg' => 'mediauploader-license-attribution',
			'wikitext' => '{{subst:int:mediauploader-license-attribution}}'
		],
		'gfdl' => [
			'msg' => 'mediauploader-license-gfdl',
			'wikitext' => '{{subst:int:mediauploader-license-gfdl}}'
		],
		'beerware' => [
			'msg' => 'mediauploader-license-beerware',
			'explainMsg' => 'mediauploader-source-ownwork-beerware-explain',
			'url' => 'https://fedoraproject.org/wiki/Licensing/Beerware',
			'wikitext' => '{{subst:int:mediauploader-license-beerware}}'
		],
		'wtfpl' => [
			'msg' => 'mediauploader-license-wtfpl',
			'explainMsg' => 'mediauploader-source-ownwork-wtfpl-explain',
			'url' => 'http://www.wtfpl.net/about/',
			'wikitext' => '{{subst:int:mediauploader-license-wtfpl}}'
		],
		'copyright' => [
			'msg' => 'mediauploader-license-copyright',
			'icons' => [ 'copyright' ],
			'wikitext' => '{{subst:int:mediauploader-license-copyright}}'
		],
		'none' => [
			'msg' => 'mediauploader-license-none',
			'wikitext' => '{{subst:int:mediauploader-license-none-text}}'
		],
		'custom' => [
			'msg' => 'mediauploader-license-custom',
			'wikitext' => ''
		],
		'generic' => [
			'msg' => 'mediauploader-license-generic',
			'wikitext' => '{{subst:int:mediauploader-license-generic|1}}'
		]
	],

	'licensing' => [
		// Default license type.
		// Possible values: ownWork, thirdParty, choice.
		'defaultType' => 'choice',

		// Which license type options should be shown?
		// Possible values: ownWork, thirdParty.
		'showTypes' => [ 'ownWork', 'thirdParty' ],

		// radio button selection of some licenses
		'ownWork' => [
			// License formatting fields:
			//  - licenseWikitext – wraps the wikitext of ONE license, $1 is the license. '$1' by default.
			//  - licenseSeparator – used for joining several licenses wrapped by 'licenseWikitext'. ' ' by default
			//  - wrapper – wraps the list of licenses. $1 – licenses, $2 – number of licenses. '$1' by default.

			// Possible values: radio, checkbox
			'type' => 'radio',
			'wrapper' => '{{subst:int:mediauploader-content-license-ownwork|$2}} $1',
			// Either a name of a single license or an array of them
			'defaults' => 'cc-by-sa-4.0',
			'licenses' => [
				'cc-by-sa-4.0',
				'cc-by-sa-3.0',
				'cc-by-4.0',
				'cc-by-3.0',
				'cc-zero'
			]
		],

		'thirdParty' => [
			'type' => 'radio',
			// Either a name of a single license or an array of them
			'defaults' => 'cc-by-sa-4.0',
			'licenseGroups' => [
				[
					'head' => 'mediauploader-license-cc-head',
					'subhead' => 'mediauploader-license-cc-subhead',
					'licenses' => [
						'cc-by-sa-4.0',
						'cc-by-sa-3.0',
						'cc-by-sa-2.5',
						'cc-by-4.0',
						'cc-by-3.0',
						'cc-by-2.5',
						'cc-zero'
					]
				],
				[
					'head' => 'mediauploader-license-custom-head',
					'special' => 'custom',
					'licenses' => [ 'custom' ],
				],
				[
					'head' => 'mediauploader-license-none-head',
					'licenses' => [ 'none' ]
				],
			]
		]
	],

	// Additional messages to be loaded with MediaUploader
	// This is only useful if your campaigns define custom licenses or license groups.
	// MediaUploader has no way of knowing about them when loading the global config,
	// so you will have to list them manually in this setting.
	'additionalMessages' => [],

	// Max author string length
	'maxAuthorLength' => 10000,

	// Min author string length
	'minAuthorLength' => 1,

	// Max source string length
	'maxSourceLength' => 10000,

	// Min source string length
	'minSourceLength' => 5,

	// Max number of simultaneous upload requests
	'maxSimultaneousConnections' => 3,

	// Max number of uploads for a given form
	// Only '*' (everyone) and 'mass-upload' (users with this user right) keys are allowed
	'maxUploads' => [
		'*' => 50,
		'mass-upload' => 500,
	],

	// Max file size that is allowed by PHP (may be higher/lower than MediaWiki file size limit).
	// When using chunked uploading, these limits can be ignored.
	// The default is UploadBase::getMaxPhpUploadSize()
	// Initialized in RawConfig.php
	'maxPhpUploadSize' => null,

	// Max file size that is allowed by MediaWiki. This limit can never be ignored.
	// The default is UploadBase::getMaxUploadSize( 'file' )
	// Initialized in RawConfig.php
	'maxMwUploadSize' => null,

	// Minimum length of custom wikitext for a license, if used.
	'minCustomLicenseLength' => 5,

	// Maximum length of custom wikitext for a license
	'maxCustomLicenseLength' => 10000,

	// Link to page where users can leave feedback or bug reports.
	// Defaults to MediaUploader's bug tracker.
	// If you want to use a wiki page, set this to a falsy value,
	// and set feedbackPage to the name of the wiki page.
	'feedbackLink' => '',

	// [deprecated] Wiki page for leaving Upload Wizard feedback,
	// for example 'Commons:Upload wizard feedback'
	'feedbackPage' => '',

	// Title of page for alternative uploading form, e.g.:
	//   'altUploadForm' => 'Special:Upload',
	//
	// If different pages are required for different languages,
	// supply an object mapping user language code to page. For a catch-all
	// page for all languages not explicitly configured, use 'default'. For instance:
	//   array(
	//		'default'	=> 'Commons:Upload',
	//		'de'		=> 'Commons:Hochladen'
	//	 );
	'altUploadForm' => '',

	// Wiki page that lists alternative ways to upload
	'alternativeUploadToolsPage' => 'Commons:Upload_tools',

	// Wiki page for reporting issues with the blacklist
	'blacklistIssuesPage' => '',

	// When using chunked upload, what size, in bytes, should each chunk be?
	'chunkSize' => 5 * 1024 * 1024,

	// Should feature to copy metadata across a batch of uploads be enabled?
	'copyMetadataFeature' => true,
];
