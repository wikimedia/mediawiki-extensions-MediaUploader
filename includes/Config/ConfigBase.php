<?php

namespace MediaWiki\Extension\MediaUploader\Config;

/**
 * Abstract base for all config classes. Provides a config retrieval interface
 * and protected utility functions.
 */
abstract class ConfigBase {
	public const LIC_OWN_WORK = 'ownWork';
	public const LIC_THIRD_PARTY = 'thirdParty';

	/**
	 * Returns the entire configuration array.
	 *
	 * @return array
	 */
	abstract public function getConfigArray(): array;

	/**
	 * Returns a specific setting of the configuration array.
	 *
	 * @param string $key
	 * @param mixed|null $default Default value if $key is not found in the array
	 *
	 * @return mixed $default if the key does not exist.
	 */
	public function getSetting( string $key, $default = null ) {
		return $this->getConfigArray()[$key] ?? $default;
	}

	/**
	 * Get a list of available licenses for a given deed type (own work or third party)
	 * from the config.
	 *
	 * @param string $type one of ConfigBase::LIC_* constants
	 *
	 * @return array
	 */
	public function getAvailableLicenses( string $type ): array {
		$licensing = $this->getSetting( 'licensing', [] )[$type] ?? [];
		$licenses = $licensing['licenses'] ?? [];

		foreach ( $licensing['licenseGroups'] ?? [] as $group ) {
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
	private function isAssoc( array $array ): bool {
		return (bool)count( array_filter( array_keys( $array ), 'is_string' ) );
	}

	/**
	 * Same functionality as array_merge_recursive, but sanely
	 * It treats 'normal' integer indexed arrays as scalars, and does
	 * not recurse into them. Associative arrays are recursed into.
	 *
	 * Null values in the second array will result in unset keys.
	 *
	 * @param array $array
	 * @param array $array1
	 *
	 * @return array Yet another array, sanely replacing contents of $array with $array1
	 */
	final protected function arrayReplaceSanely( array $array, array $array1 ): array {
		$newArray = [];

		foreach ( $array as $key => $value ) {
			if ( array_key_exists( $key, $array1 ) ) {
				$value1 = $array1[$key];
				if ( $value1 === null ) {
					// Special case: if the new array has this value as null, unset it entirely.
					// This is useful for removing parts of the config in campaigns.
					continue;
				} if ( is_array( $value ) && is_array( $value1 ) && $this->isAssoc( $value ) ) {
					$newArray[$key] = $this->arrayReplaceSanely( $value, $value1 );
				} else {
					$newArray[$key] = $value1;
				}
			} else {
				$newArray[$key] = $value;
			}
		}

		return array_merge( $newArray, array_diff_key( $array1, $array ) );
	}
}
