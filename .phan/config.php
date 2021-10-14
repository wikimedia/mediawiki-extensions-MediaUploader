<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Namespace constants
$cfg['file_list'][] = 'defines.php';

// Don't add libraries to static analysis lists.
// The JSON Schema library is also included in dev requirements for core,
// which causes duplicate definition errors in Phan.
// Symfony's YAML is included with MW only starting with 1.38.

return $cfg;
