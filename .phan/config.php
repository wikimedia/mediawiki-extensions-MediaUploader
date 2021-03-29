<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Namespace constants
$cfg['file_list'][] = 'defines.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		// Include only the YAML library. The JSON Schema library is also
		// included in dev requirements for core, which causes duplicate
		// definition errors in Phan.
		'vendor/symfony/yaml',
		'../../extensions/EventLogging',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'vendor/symfony/yaml',
		'../../extensions/EventLogging',
	]
);

return $cfg;
