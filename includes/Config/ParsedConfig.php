<?php

namespace MediaWiki\Extension\MediaUploader\Config;

use Language;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserOptionsLookup;
use User;
use WANObjectCache;

/**
 * Abstract parsed config.
 */
abstract class ParsedConfig extends ConfigBase {

	/**
	 * @internal
	 */
	public const NO_CACHE = 'NoConfigCache';

	/**
	 * @internal Only for use by ConfigFactory
	 */
	public const CONSTRUCTOR_OPTIONS = [ self::NO_CACHE ];

	/** @var array */
	protected $parsedConfig;

	/** @var array */
	protected $usedTemplates;

	/** @var WANObjectCache */
	protected $cache;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var ConfigCacheInvalidator */
	protected $invalidator;

	/** @var Language */
	protected $language;

	/** @var User */
	protected $user;

	/** @var ServiceOptions */
	private $options;

	/**
	 * @param WANObjectCache $cache
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param ConfigCacheInvalidator $cacheInvalidator
	 * @param Language $language
	 * @param User $user
	 * @param ServiceOptions $options
	 */
	protected function __construct(
		WANObjectCache $cache,
		UserOptionsLookup $userOptionsLookup,
		ConfigCacheInvalidator $cacheInvalidator,
		Language $language,
		User $user,
		ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->cache = $cache;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->invalidator = $cacheInvalidator;
		$this->language = $language;
		$this->user = $user;
		$this->options = $options;
	}

	/**
	 * Returns the key used to store the parsed config.
	 *
	 * @param string ...$additionalComponents Additional cache key components
	 *
	 * @return string
	 */
	final protected function makeCacheKey( string ...$additionalComponents ) : string {
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
	 * Retrieves the parsed config from cache, if available.
	 * Otherwise, re-parses the config, stores it in cache and sets the
	 * $parsedConfig and $usedTemplates fields.
	 *
	 * @param bool $noCache Whether to bypass cache entirely.
	 *  No reads or writes to cache should be made.
	 */
	abstract protected function initialize( bool $noCache ) : void;

	/**
	 * @inheritDoc
	 */
	public function getConfigArray() : array {
		if ( $this->parsedConfig === null ) {
			$this->initialize(
				$this->options->get( self::NO_CACHE )
			);
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
			$this->initialize(
				$this->options->get( self::NO_CACHE )
			);
		}

		return $this->usedTemplates;
	}
}
