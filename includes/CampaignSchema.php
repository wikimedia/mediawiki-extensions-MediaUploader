<?php

use MediaWiki\Extension\MediaUploader\MediaUploaderServices;

return [
	"type" => "object",
	"id" => "#campaignnode",
	"required" => true,
	"properties" => [
		"title" => [
			"type" => "string"
		],
		"description" => [
			"type" => "string"
		],
		"enabled" => [
			"type" => "boolean",
			"required" => true,
		],
		"start" => [
			"type" => "string",
		],
		"end" => [
			"type" => "string",
		],
		"autoAdd" => [
			"type" => "object",
			"properties" => [
				"categories" => [
					"type" => "array",
					"items" => [
						[
							"type" => "string"
						]
					]
				],
				"wikitext" => [
					"type" => "string"
				]
			]
		],
		"fields" => [
			"type" => "array",
			"items" => [
				[
					"type" => "object",
					"properties" => [
						"wikitext" => [
							"type" => "string"
						],
						"label" => [
							"type" => "string"
						],
						"maxLength" => [
							"type" => "integer"
						],
						"initialValue" => [
							"type" => "string"
						],
						"required" => [
							"type" => "boolean"
						],
						"type" => [
							"type" => "string"
						],
						"options" => [
							"type" => "object",
							"properties" => [],
							"additionalProperties" => true
						]
					]
				]
			]
		],
		"defaults" => [
			"type" => "object",
			"properties" => [
				"alt" => [
					"type" => "number"
				],
				"categories" => [
					"type" => "array",
					"items" => [
						[
							"type" => "string"
						]
					]
				],
				"description" => [
					"type" => "string"
				],
				"lat" => [
					"type" => "number"
				],
				"lon" => [
					"type" => "number"
				],
			]
		],
		"display" => [
			"type" => "object",
			"properties" => [
				"headerLabel" => [
					"type" => "string"
				],
				"thanksLabel" => [
					"type" => "string"
				],
				"homeButton" => [
					"type" => "object",
					"properties" => [
						"label" => [
							"type" => "string"
						],
						"target" => [
							"type" => "string"
						]
					]
				],
				"beginButton" => [
					"type" => "object",
					"properties" => [
						"label" => [
							"type" => "string"
						],
						"target" => [
							"type" => "string"
						]
					]
				],
			]
		],
		"licensing" => [
			"type" => "object",
			"properties" => [
				"defaultType" => [
					"type" => "string"
				],
				"ownWorkDefault" => [
					"type" => "string"
				],
				"ownWork" => [
					"type" => "object",
					"properties" => [
						"default" => [
							"type" => "string",
							// TODO: Why. Please do it sanely somewhere else. Please.
							"enum" => array_keys(
								MediaUploaderServices::getRawConfig()->getSetting( 'licenses' )
							)
						],
						"licenses" => [
							"type" => "array",
							"items" => [
								[
									"type" => "string",
									"enum" => array_keys(
										MediaUploaderServices::getRawConfig()->getSetting( 'licenses' )
									)
								]
							]

						],
						"template" => [
							"type" => "string"
						],
						"type" => [
							"type" => "string"
						]
					]
				],
				"thirdParty" => [
					"type" => "object",
					"properties" => [
						"defaults" => [
							"type" => "string",
							"enum" => array_keys(
								MediaUploaderServices::getRawConfig()->getSetting( 'licenses' )
							)
						],
						"licenseGroups" => [
							"type" => "array",
							"items" => [
								[
									"type" => "object",
									"properties" => [
										"head" => [
											"type" => "string"
										],
										"licenses" => [
											"type" => "array",
											"items" => [
												[
													"type" => "string",
													"enum" => array_keys(
														MediaUploaderServices::getRawConfig()->getSetting(
															'licenses'
														)
													)
												]
											]

										],
										"subhead" => [
											"type" => "string"
										]
									]
								]
							]

						],
						"type" => [
							"type" => "string"
						]
					]
				]
			]
		],
		"tutorial" => [
			"type" => "object",
			"properties" => [
				"enabled" => [
					"type" => "boolean"
				],
				"skip" => [
					"type" => "boolean"
				],
				"wikitext" => [
					"type" => "string"
				]
			]
		],
		"whileActive" => [
			"type" => "object",
			"properties" => [
				"display" => [
					"type" => "object",
					"properties" => [
						"headerLabel" => [
							"type" => "string"
						],
						"thanksLabel" => [
							"type" => "string"
						],
					],
				],

				"autoAdd" => [
					"type" => "object",
					"properties" => [
						"categories" => [
							"type" => "array",
							"items" => [
								[
									"type" => "string"
								],
							],
						],
						"wikitext" => [
							"type" => "string"
						],
					],
				],
			],
		],
		"beforeActive" => [
			"type" => "object",
			"properties" => [
				"display" => [
					"type" => "object",
					"properties" => [
						"headerLabel" => [
							"type" => "string"
						],
						"thanksLabel" => [
							"type" => "string"
						],
					],
				],
			],
		],
		"afterActive" => [
			"type" => "object",
			"properties" => [
				"display" => [
					"type" => "object",
					"properties" => [
						"headerLabel" => [
							"type" => "string"
						],
						"thanksLabel" => [
							"type" => "string"
						],
					],
				],
			],
		],
	]
];
