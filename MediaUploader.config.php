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

	// Enable or disable campaignstats that are expensive to compute
	'campaignExpensiveStatsEnabled' => true,

	// Number of seconds to cache Campaign stats
	// Currently affects: Contributors count for each campaign
	'campaignStatsMaxAge' => 60,

	// File extensions acceptable in this wiki
	// The default is $wgFileExtensions if $wgCheckFileExtensions is enabled.
	// Initialized in RawConfig.php
	'fileExtensions' => null,

	// Flickr details
	// Flickr API is SSL-only as of June 27th, 2014:
	// http://code.flickr.net/2014/04/30/flickr-api-going-ssl-only-on-june-27th-2014/
	'flickrApiUrl' => 'https://api.flickr.com/services/rest/?',

	// you should probably replace this with your own
	'flickrApiKey' => 'aeefff139445d825d4460796616f9349',

	// name of wiki page with blacklist of Flickr users
	'flickrBlacklistPage' => '',

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
		// wikitext to display above the UploadWizard UI.
		'headerLabel' => '',

		// wikitext to display on top of the "use" page.
		// When not provided, the message mwe-upwiz-thanks-intro will be used.
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
		'wikitext' => '{{int:mwe-upwiz-default-tutorial-text}}',
	],

	// Tracking categories for various scenarios
	'trackingCategory' => [
		// Category added no matter what
		// Default to none because we don't know what categories
		// exist or not on local wikis.
		// Do not uncomment this line, set
		// $wgUploadWizardConfig['trackingCategory']['all']
		// to your favourite category name.

		// 'all' => '',

		// Tracking category added for campaigns. $1 is replaced with campaign page name
		'campaign' => 'Uploaded via Campaign:$1'
	],

	'fields' => [
		// Field via which an ID can be provided.
		[
			// When non empty, this field will be shown, and $1 will be replaced by it's value.
			'wikitext' => '',

			// Label text to display with the field. Is parsed as wikitext.
			'label' => '',

			// The maximum length of the id field.
			'maxLength' => 25,

			// Initial value for the id field.
			'initialValue' => '',

			// Set to true if this field is required
			'required' => false,

			// Define the type of widget that will be rendered,
			// pick between text and select
			'type' => "text",

			// If the type above is select, provide a dictionary of
			// value -> label associations to display as options
			'options' => [ /* 'value' => 'label' */ ]
		]
	],

	'defaults' => [
		// Categories to list by default in the list of cats to add.
		'categories' => [],

		// Initial value for the description field.
		'description' => '',

		// These values are commented out by default, so they can be undefined
		// Define them here if you want defaults.
		// This is required, because the JsonSchema for these defines them to be type number
		// But we can't have them to be NULL, because that's not a number.
		// This is a technical limitation of JsonSchema, I think.

		//// Initial value for the latitude field.
		//'lat' => 0,

		//// Initial value for the longitude field.
		//'lon' => 0,

		//// Initial value for the altitude field. (unused)
		//'alt' => 0,

		//// Initial value for the heading field.
		//'heading' => 0,
	],

	// 'languages' is a list of languages and codes, for use in the description step.
	// By default initialized to a list of all available languages that have corresponding
	// templates (in ISO 646 language codes). Additionally, the languageTemplateFixups
	// setting is taken into account (see below).
	// Initialized in RequestConfig.php
	'languages' => [],

	// The UploadWizard allows users to provide file descriptions in multiple languages. For each description, the user
	// can choose the language. The UploadWizard wraps each description in a "language template". A language template is
	// by default assumed to be a template with a name corresponding to the ISO 646 code of the language. For instance,
	// Template:en for English, or Template:fr for French.
	// If this is not the case for some or all or your wiki's language templates, this map can be used to define the
	// template names to be used. Keys are ISO 646 language codes, values are template names.
	'languageTemplateFixups' => [],

	// 'licenses' is a list of licenses you could possibly use elsewhere, for instance in
	// licensesOwnWork or licensesThirdParty.
	// It just describes what licenses go with what wikitext, and how to display them in
	// a menu of license choices. There probably isn't any reason to delete any entry here.
	// Under normal circumstances, the license name is the name of the wikitext template to insert.
	// For those that aren't, there is a "templates" property.
	'licenses' => [
		'cc-by-sa-4.0' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-4.0',
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/4.0/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0',
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-gfdl' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-gfdl',
			'templates' => [ 'GFDL', 'cc-by-sa-3.0' ],
			'icons' => [ 'cc-by', 'cc-sa' ]
		],
		'cc-by-sa-3.0-at' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-at',
			'templates' => [ 'cc-by-sa-3.0-at' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/at/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-de' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-de',
			'templates' => [ 'cc-by-sa-3.0-de' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/de/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-ee' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-ee',
			'templates' => [ 'cc-by-sa-3.0-ee' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/ee/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-es' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-es',
			'templates' => [ 'cc-by-sa-3.0-es' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/es/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-hr' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-hr',
			'templates' => [ 'cc-by-sa-3.0-hr' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/hr/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-lu' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-lu',
			'templates' => [ 'cc-by-sa-3.0-lu' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/lu/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-nl' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-nl',
			'templates' => [ 'cc-by-sa-3.0-nl' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/nl/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-no' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-no',
			'templates' => [ 'cc-by-sa-3.0-no' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/no/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-pl' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-pl',
			'templates' => [ 'cc-by-sa-3.0-pl' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/pl/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-3.0-ro' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-3.0-ro',
			'templates' => [ 'cc-by-sa-3.0-ro' ],
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/3.0/ro/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-4.0' => [
			'msg' => 'mwe-upwiz-license-cc-by-4.0',
			'icons' => [ 'cc-by' ],
			'url' => '//creativecommons.org/licenses/by/4.0/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-3.0' => [
			'msg' => 'mwe-upwiz-license-cc-by-3.0',
			'icons' => [ 'cc-by' ],
			'url' => '//creativecommons.org/licenses/by/3.0/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-zero' => [
			'msg' => 'mwe-upwiz-license-cc-zero',
			'icons' => [ 'cc-zero' ],
			'url' => '//creativecommons.org/publicdomain/zero/1.0/',
			'languageCodePrefix' => 'deed.'
		],
		'own-pd' => [
			'msg' => 'mwe-upwiz-license-own-pd',
			'icons' => [ 'cc-zero' ],
			'templates' => [ 'cc-zero' ]
		],
		'cc-by-sa-2.5' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-2.5',
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/2.5/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-2.5' => [
			'msg' => 'mwe-upwiz-license-cc-by-2.5',
			'icons' => [ 'cc-by' ],
			'url' => '//creativecommons.org/licenses/by/2.5/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-sa-2.0' => [
			'msg' => 'mwe-upwiz-license-cc-by-sa-2.0',
			'icons' => [ 'cc-by', 'cc-sa' ],
			'url' => '//creativecommons.org/licenses/by-sa/2.0/',
			'languageCodePrefix' => 'deed.'
		],
		'cc-by-2.0' => [
			'msg' => 'mwe-upwiz-license-cc-by-2.0',
			'icons' => [ 'cc-by' ],
			'url' => '//creativecommons.org/licenses/by/2.0/',
			'languageCodePrefix' => 'deed.'
		],
		'fal' => [
			'msg' => 'mwe-upwiz-license-fal',
			'templates' => [ 'FAL' ]
		],
		'pd-old-100' => [
			'msg' => 'mwe-upwiz-license-pd-old-100',
			'templates' => [ 'PD-old-100' ]
		],
		'pd-old' => [
			'msg' => 'mwe-upwiz-license-pd-old',
			'templates' => [ 'PD-old' ]
		],
		'pd-art' => [
			'msg' => 'mwe-upwiz-license-pd-art-70',
			'templates' => [ 'PD-Art|PD-old-70' ],
			'url' => '//commons.wikimedia.org/wiki/Commons:Licensing#Material_in_the_public_domain',
		],
		'pd-us' => [
			'msg' => 'mwe-upwiz-license-pd-us',
			'templates' => [ 'PD-US-expired' ]
		],
		'pd-old-70-expired' => [
			'msg' => 'mwe-upwiz-license-pd-old-70-1923',
			'templates' => [ 'PD-old-70-expired' ],
		],
		'pd-usgov' => [
			'msg' => 'mwe-upwiz-license-pd-usgov',
			'templates' => [ 'PD-USGov' ]
		],
		'pd-usgov-nasa' => [
			'msg' => 'mwe-upwiz-license-pd-usgov-nasa',
			'templates' => [ 'PD-USGov-NASA' ]
		],
		'pd-ineligible' => [
			'msg' => 'mwe-upwiz-license-pd-ineligible'
		],
		'pd-textlogo' => [
			'msg' => 'mwe-upwiz-license-pd-textlogo',
			'templates' => [ 'trademarked', 'PD-textlogo' ]
		],
		'attribution' => [
			'msg' => 'mwe-upwiz-license-attribution'
		],
		'gfdl' => [
			'msg' => 'mwe-upwiz-license-gfdl',
			'templates' => [ 'GFDL' ]
		],
		'none' => [
			'msg' => 'mwe-upwiz-license-none',
			'templates' => [ 'subst:uwl' ]
		],
		'custom' => [
			'msg' => 'mwe-upwiz-license-custom',
			'templates' => [ 'subst:Custom license marker added by UW' ]
		],
		'generic' => [
			'msg' => 'mwe-upwiz-license-generic',
			'templates' => [ 'Generic' ]
		]
	],

	'licensing' => [
		// Default license type.
		// Possible values: ownwork, thirdparty, choice.
		'defaultType' => 'choice',

		// Should the own work option be shown, and if not, what option should be set?
		// Possible values:  own, notown, choice.
		'ownWorkDefault' => 'choice',

		// radio button selection of some licenses
		'ownWork' => [
			'type' => 'or',
			'template' => 'self',
			'defaults' => 'cc-by-sa-4.0',
			'licenses' => [
				'cc-by-sa-4.0',
				'cc-by-sa-3.0',
				'cc-by-4.0',
				'cc-by-3.0',
				'cc-zero'
			]
		],

		// checkbox selection of all licenses
		'thirdParty' => [
			'type' => 'or',
			'defaults' => 'cc-by-sa-4.0',
			'licenseGroups' => [
				[
					// This should be a list of all CC licenses we can reasonably expect to find around the web
					'head' => 'mwe-upwiz-license-cc-head',
					'subhead' => 'mwe-upwiz-license-cc-subhead',
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
					// n.b. as of April 2011, Flickr still uses CC 2.0 licenses.
					// The White House also has an account there, hence the Public Domain US Government license
					'head' => 'mwe-upwiz-license-flickr-head',
					'subhead' => 'mwe-upwiz-license-flickr-subhead',
					'prependTemplates' => [ 'flickrreview' ],
					'licenses' => [
						'cc-by-sa-2.0',
						'cc-by-2.0',
						'pd-usgov',
					]
				],
				[
					'head' => 'mwe-upwiz-license-public-domain-usa-head',
					'subhead' => 'mwe-upwiz-license-public-domain-usa-subhead',
					'licenses' => [
						'pd-us',
						'pd-art',
					]
				],
				[
					// omitted navy because it is believed only MultiChil uses it heavily. Could add it back
					'head' => 'mwe-upwiz-license-usgov-head',
					'licenses' => [
						'pd-usgov',
						'pd-usgov-nasa'
					]
				],
				[
					'head' => 'mwe-upwiz-license-custom-head',
					'special' => 'custom',
					'licenses' => [ 'custom' ],
				],
				[
					'head' => 'mwe-upwiz-license-none-head',
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

	// Max file title string length
	'maxTitleLength' => 240,

	// Min file title string length
	'minTitleLength' => 5,

	// Max file description length
	'maxDescriptionLength' => 10000,

	// Min file description length
	'minDescriptionLength' => 5,

	// Max length for other file information:
	'maxOtherInformationLength' => 10000,

	// Max number of simultaneous upload requests
	'maxSimultaneousConnections' => 3,

	// Max number of uploads for a given form
	// Only '*' (everyone) and 'mass-upload' (users with this user right) keys are allowed
	'maxUploads' => [
		'*' => 50,
		'mass-upload' => 500,
	],

	// Max number of files that can be imported from Flickr at one time (T236341)
	// Note that these numbers should always be equal to or less than the maxUploads above.
	// Only '*' (everyone) and 'mass-upload' (users with this user right) keys are allowed
	'maxFlickrUploads' => [
		'*' => 4,
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
	// It is 6 because at minimum it needs four chars for opening and closing
	// braces, then two chars for a license, e.g. {{xx}}
	'minCustomLicenseLength' => 6,

	// Maximum length of custom wikitext for a license
	'maxCustomLicenseLength' => 10000,

	// License template custom licenses should transclude (if any)
	// This is the prefixed db key (e.g. Template:License_template_tag), or
	// false to disable this check
	'customLicenseTemplate' => false,

	// Link to page where users can leave feedback or bug reports.
	// Defaults to UploadWizard's bug tracker.
	// If you want to use a wiki page, set this to a falsy value,
	// and set feedbackPage to the name of the wiki page.
	'feedbackLink' => '',

	// [deprecated] Wiki page for leaving Upload Wizard feedback,
	// for example 'Commons:Upload wizard feedback'
	'feedbackPage' => '',

	// Link to page containing a list of categories that the user can use for uploaded files.
	// Shown on the Details stage, above the category selection field.
	'allCategoriesLink' => 'https://commons.wikimedia.org/wiki/Commons:Categories',

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

	// Should we pester the user with a confirmation step when submitting a file without assigning it
	// to any categories?
	'enableCategoryCheck' => true,
];
