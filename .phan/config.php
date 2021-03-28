<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Namespace constants
$cfg['file_list'][] = 'defines.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'vendor',
		'../../extensions/EventLogging',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'vendor',
		'../../extensions/EventLogging',
	]
);

return $cfg;
