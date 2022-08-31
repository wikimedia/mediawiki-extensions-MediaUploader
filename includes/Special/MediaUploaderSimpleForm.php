<?php
/**
 * This upload form is used at Special:MediaUploader
 *
 * @file
 * @ingroup SpecialPage
 * @ingroup Upload
 */

namespace MediaWiki\Extension\MediaUploader\Special;

use UploadForm;

/**
 * This is a hack on UploadForm, to make one that works from MediaUploader when JS is not available.
 *
 * @codeCoverageIgnore
 */
class MediaUploaderSimpleForm extends UploadForm {

	/**
	 * Normally, UploadForm adds its own Javascript.
	 * We wish to prevent this, because we want to control the case where we have Javascript.
	 * So, we make the addUploadJS a no-op.
	 */
	protected function addUploadJS() {
	}
}
