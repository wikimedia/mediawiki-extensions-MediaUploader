<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use MediaWikiUnitTestCase;

// HACK: Extension-defined NS_* constants appear not to be loaded in unit tests,
// so we include them manually.
require_once dirname( __DIR__, 4 ) . '/defines.php';

/**
 * Base class for config unit tests.
 */
abstract class ConfigUnitTestCase extends MediaWikiUnitTestCase {

	/**
	 * Asserts that $expectedSubmap is a submap of $config.
	 *
	 * @param array $expectedSubmap
	 * @param array $config
	 */
	final protected function assertConfigSubmap( array $expectedSubmap, array $config ) {
		foreach ( $expectedSubmap as $k => $v ) {
			$this->assertArrayHasKey(
				$k,
				$config,
				"configuration array contains key '$k'"
			);
			if ( is_array( $v ) ) {
				$this->assertArrayEquals(
					$v,
					$config[$k],
					false,
					true,
					"config subarray at key '$k' has correct value"
				);
			} else {
				$this->assertSame(
					$v,
					$config[$k],
					"config value at key '$k' has correct value"
				);
			}
		}
	}
}
