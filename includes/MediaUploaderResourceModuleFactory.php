<?php

namespace MediaWiki\Extension\MediaUploader;

/**
 * Factory for MediaUploaderResourceModule.
 */
class MediaUploaderResourceModuleFactory {

	public static function factory( array $options ): MediaUploaderResourceModule {
		return new MediaUploaderResourceModule(
			$options,
			MediaUploaderServices::getRawConfig()
		);
	}
}
