<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use Language;
use MediaWiki\User\UserOptionsLookup;
use User;
use WANObjectCache;

/**
 * Abstract parsed config.
 */
abstract class ParsedConfig extends ConfigBase {

	/** @var array */
	protected $parsedConfig;

	/** @var array */
	protected $usedTemplates;

	/** @var WANObjectCache */
	protected $cache;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var Language */
	protected $language;

	/** @var User */
	protected $user;

	/**
	 * @param WANObjectCache $cache
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param Language $language
	 * @param User $user
	 */
	protected function __construct(
		WANObjectCache $cache,
		UserOptionsLookup $userOptionsLookup,
		Language $language,
		User $user
	) {
		$this->cache = $cache;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->language = $language;
		$this->user = $user;
	}

	/**
	 * Returns the key used to store the parsed config.
	 *
	 * @param string[] $additionalComponents Additional cache key components
	 *
	 * @return string
	 */
	final protected function makeCacheKey( array $additionalComponents = [] ) : string {
		$gender = $this->userOptionsLookup->getOption( $this->user, 'gender' );
		return $this->cache->makeKey(
			'mediauploader',
			'parsed-config',
			$this->language->getCode(),
			$gender,
			...$additionalComponents
		);
	}

	/**
	 * Returns the key used to store the last time the config cache was invalidated.
	 *
	 * @param array $additionalComponents Additional cache key components
	 *
	 * @return string
	 */
	final protected function makeInvalidateTimestampKey( array $additionalComponents = [] ) : string {
		return $this->cache->makeKey(
			'mediauploader',
			'parsed-config',
			'invalidate',
			...$additionalComponents
		);
	}

	/**
	 * Retrieves the parsed config from cache, if available.
	 * Otherwise, re-parses the config, stores it in cache and sets the
	 * $parsedConfig and $usedTemplates fields.
	 */
	abstract protected function initialize() : void;

	/**
	 * @inheritDoc
	 */
	public function getConfigArray() : array {
		if ( $this->parsedConfig === null ) {
			$this->initialize();
		}

		return $this->parsedConfig;
	}

	/**
	 * Returns the templates used in this config
	 *
	 * @return array [ ns => [ dbKey => [ page_id, rev_id ] ] ]
	 */
	public function getTemplates() : array {
		if ( $this->usedTemplates === null ) {
			$this->initialize();
		}

		return $this->usedTemplates;
	}

	/**
	 * Invalidate the cache for this config, in all languages and genders.
	 *
	 * Does so by simply writing a new invalidate timestamp to cache.
	 * Since this invalidate timestamp is checked on every read, the cached entries
	 * for the campaign will be regenerated the next time there is a read.
	 */
	abstract public function invalidateCache() : void;
}
