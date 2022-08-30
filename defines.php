<?php

// Constants
if ( !defined( 'NS_CAMPAIGN' ) ) {
	define( 'NS_CAMPAIGN', 460 );
	define( 'NS_CAMPAIGN_TALK', 461 );
}

define( 'CONTENT_MODEL_CAMPAIGN', 'Campaign' );
// Not approved yet (Aug 2022), but looks like it will be the one:
// https://www.ietf.org/archive/id/draft-ietf-httpapi-yaml-mediatypes-00.html
define( 'CONTENT_FORMAT_CAMPAIGN', 'application/yaml' );
define( 'MU_SCHEMA_DIR', __DIR__ . '/schemas/' );
