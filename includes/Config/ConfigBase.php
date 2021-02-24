<?php

namespace MediaWiki\Extension\MediaUploader\Config;

/**
 * Abstract base for all config classes. Provides a config retrieval interface
 * and protected utility functions.
 */
abstract class ConfigBase {

	/**
	 * Returns the entire configuration array.
	 *
	 * @return array
	 */
	abstract public function getConfigArray() : array;

	/**
	 * Returns a specific setting of the configuration array.
	 *
	 * @param string $key
	 * @param mixed $default Default value if $key is not found in the array
	 *
	 * @return mixed $default if the key does not exist.
	 */
	public function getSetting( string $key, $default = null ) {
		return $this->getConfigArray()[$key] ?? $default;
	}

	/**
	 * Get a list of available third party licenses from the config.
	 *
	 * @return array
	 */
	public function getThirdPartyLicenses() : array {
		$licensing = $this->getSetting( 'licensing', [] );
		$thirdParty = $licensing['thirdParty'] ?? [];
		$licenses = [];

		foreach ( $thirdParty['licenseGroups'] ?? [] as $group ) {
			$licenses = array_merge( $licenses, $group['licenses'] );
		}

		return array_unique( $licenses );
	}

	/**
	 * Returns true if any of the keys of an array is a string
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	private function isAssoc( array $array ) : bool {
		return (bool)count( array_filter( array_keys( $array ), 'is_string' ) );
	}

	/**
	 * Same functionality as array_merge_recursive, but sanely
	 * It treats 'normal' integer indexed arrays as scalars, and does
	 * not recurse into them. Associative arrays are recursed into.
	 *
	 * @param array $array
	 * @param array $array1
	 *
	 * @return array Yet another array, sanely replacing contents of $array with $array1
	 */
	final protected function arrayReplaceSanely( array $array, array $array1 ) : array {
		$newArray = [];

		foreach ( $array as $key => $value ) {
			if ( array_key_exists( $key, $array1 ) ) {
				if ( is_array( $value ) && $this->isAssoc( $array[$key] ) ) {
					$newArray[$key] = $this->arrayReplaceSanely( $array[$key], $array1[$key] );
				} else {
					$newArray[$key] = $array1[$key];
				}
			} else {
				$newArray[$key] = $array[$key];
			}
		}
		$newArray = array_merge( $newArray, array_diff_key( $array1, $array ) );
		return $newArray;
	}
}
