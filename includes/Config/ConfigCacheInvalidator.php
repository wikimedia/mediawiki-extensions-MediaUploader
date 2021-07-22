<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use WANObjectCache;

/**
 * A relatively lightweight class intended for use when the config cache has to be
 * explicitly invalidated.
 */
class ConfigCacheInvalidator {

	/** @var WANObjectCache */
	private $cache;

	/**
	 * @param WANObjectCache $cache
	 */
	public function __construct( WANObjectCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Returns the key used to store the last time the config cache was invalidated.
	 *
	 * @param string ...$additionalComponents Additional cache key components
	 *
	 * @return string
	 */
	public function makeInvalidateTimestampKey( string ...$additionalComponents ): string {
		return $this->cache->makeKey(
			'mediauploader',
			'parsed-config',
			'invalidate',
			...$additionalComponents
		);
	}

	/**
	 * Invalidate the cache for the corresponding config, in all languages and genders.
	 *
	 * Does so by simply writing a new invalidate timestamp to cache.
	 * Since this invalidate timestamp is checked on every read, the cached entries
	 * for the config will be regenerated the next time there is a read.
	 *
	 * @param string ...$additionalComponents
	 */
	public function invalidate( string ...$additionalComponents ): void {
		$this->cache->touchCheckKey(
			$this->makeInvalidateTimestampKey( ...$additionalComponents )
		);
	}
}
