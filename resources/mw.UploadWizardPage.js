/*
 * This script is run on [[Special:MediaUploader]].
 * Configures and creates an interface for uploading files in multiple steps, hence "wizard".
 *
 * Tries to transform Javascript globals dumped on us by the PHP code into a more
 * compact configuration, owned by the MediaUploader created.
 */

// Create UploadWizard
( function () {

	function isCompatible() {
		var
			profile = $.client.profile(),
			// Firefox < 7.0 sends an empty string as filename for Blobs in FormData.
			// requests. https://bugzilla.mozilla.org/show_bug.cgi?id=649150
			badFormDataBlobs = profile.name === 'firefox' && profile.versionNumber < 7;

		return !!(
			window.FileReader &&
			window.FormData &&
			window.File &&
			window.File.prototype.slice &&
			!badFormDataBlobs
		);
	}

	mw.UploadWizardPage = function () {

		var uploadWizard,
			config = mw.config.get( 'MediaUploaderConfig' );

		// Default configuration value that cannot be removed
		config.maxUploads = config.maxUploads || 10;

		// Remove the initial spinner
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.mwe-first-spinner' ).remove();

		// eslint-disable-next-line no-jquery/no-global-selector
		if ( $( '#upload-wizard' ).length === 0 ) {
			mw.log( 'MediaUploader is disabled, nothing to do.' );
			return;
		}

		if ( !isCompatible() ) {
			// Display the same error message as for grade-C browsers
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.mediauploader-unavailable' ).show();
			return;
		}

		uploadWizard = new mw.UploadWizard( config );
		uploadWizard.createInterface( '#upload-wizard' );
	};

	$( function () {
		// show page.
		mw.UploadWizardPage();
	} );

}() );
