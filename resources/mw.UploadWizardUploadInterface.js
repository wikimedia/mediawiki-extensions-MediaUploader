( function ( uw ) {
	/**
	 * Create an interface fragment corresponding to a file input, suitable for Upload Wizard.
	 *
	 * @class mw.UploadWizardUploadInterface
	 * @constructor
	 * @param {mw.UploadWizardUpload} upload
	 */
	mw.UploadWizardUploadInterface = function MWUploadWizardUploadInterface( upload ) {
		var ui = this;

		OO.EventEmitter.call( this );

		this.upload = upload;

		// May need to collaborate with the particular upload type sometimes
		// for the interface, as well as the uploadwizard. OY.
		this.$div = $( '<div>' ).addClass( 'mediauploader-file' );

		this.isFilled = false;

		this.statusMessage = new OO.ui.MessageWidget( { inline: true } );
		this.statusMessage.toggle( false );
		this.$spinner = $.createSpinner( { size: 'small', type: 'inline' } );
		this.$spinner.hide();
		this.$indicator = $( '<div>' ).addClass( 'mediauploader-file-indicator' ).append(
			this.$spinner,
			this.statusMessage.$element
		);

		this.$visibleFilenameDiv = $( '<div>' ).addClass( 'mediauploader-visible-file' ).append(
			this.$indicator,
			$( '<div>' ).addClass( 'mediauploader-visible-file-filename' ).append(
				$( '<div>' ).addClass( 'mediauploader-file-preview' ),
				$( '<div>' ).addClass( 'mediauploader-file-texts' ).append(
					$( '<div>' ).addClass( 'mediauploader-visible-file-filename-text' ),
					$( '<div>' ).addClass( 'mediauploader-file-status-line' ).append(
						$( '<div>' ).addClass( 'mediauploader-file-status' )
					)
				)
			)
		);

		this.removeCtrl = new OO.ui.ButtonWidget( {
			label: mw.message( 'mediauploader-remove' ).text(),
			title: mw.message( 'mediauploader-remove-upload' ).text(),
			flags: 'destructive',
			icon: 'trash',
			framed: false
		} ).on( 'click', function () {
			ui.emit( 'upload-removed' );
		} );

		this.$visibleFilenameDiv.find( '.mediauploader-file-status-line' )
			.append( this.removeCtrl.$element );

		this.$form = $( '<form>' )
			.addClass( 'mediauploader-form' )
			.append( this.$visibleFilenameDiv );

		this.$div.append( this.$form );

		// this.progressBar = ( no progress bar for individual uploads yet )
		// we bind to the ui div since .off() doesn't work for non-DOM objects
		// TODO Convert this to an OO.EventEmitter, and use OOjs events
		this.$div.on( 'transportProgressEvent', function () {
			ui.showTransportProgress();
		} );
	};

	OO.mixinClass( mw.UploadWizardUploadInterface, OO.EventEmitter );

	/**
	 * Change the graphic indicator at the far end of the row for this file
	 *
	 * @param {string} [status] Either a OO.ui.MessageWidget type (error/success/...) or 'progress'.
	 *  Omit to hide the indicator
	 */
	mw.UploadWizardUploadInterface.prototype.showIndicator = function ( status ) {
		this.$spinner.hide();
		this.statusMessage.toggle( false );

		if ( status === 'progress' ) {
			this.$spinner.show();
		} else if ( status ) {
			this.statusMessage.toggle( true ).setType( status );
		}
		this.$indicator.toggleClass( 'mediauploader-file-indicator-visible', !!status );
	};

	/**
	 * Set the status line for this upload with an internationalized message string.
	 *
	 * @param {string} msgKey Key for the message
	 * @param {Array} [args] Array of values, in case any need to be fed to the image.
	 */
	mw.UploadWizardUploadInterface.prototype.setStatus = function ( msgKey, args ) {
		// get the status line for our upload
		var $status = this.$div.find( '.mediauploader-file-status' );
		// eslint-disable-next-line mediawiki/msg-doc
		$status.msg( msgKey, args || [] ).show();
	};

	/**
	 * Set status line directly with a string
	 *
	 * @param {string} html
	 */
	mw.UploadWizardUploadInterface.prototype.setStatusString = function ( html ) {
		this.$div.find( '.mediauploader-file-status' ).html( html ).show();
	};

	/**
	 * Set additional status information
	 *
	 * @param {jQuery} [$status] If not given or null, additional status is cleared
	 */
	mw.UploadWizardUploadInterface.prototype.setAdditionalStatus = function ( $status ) {
		if ( this.$additionalStatus ) {
			this.$additionalStatus.remove();
		}
		this.$additionalStatus = $status;
		if ( this.$additionalStatus ) {
			this.$div.find( '.mediauploader-file-status' ).after( this.$additionalStatus );
		}
	};

	/**
	 * Clear the status line for this upload (hide it, in case there are paddings and such which offset other things.)
	 */
	mw.UploadWizardUploadInterface.prototype.clearStatus = function () {
		this.$div.find( '.mediauploader-file-status' ).hide();
		this.setAdditionalStatus( null );
	};

	/**
	 * Put the visual state of an individual upload into "progress"
	 *
	 * @param {number} fraction The fraction of progress. Float between 0 and 1
	 */
	mw.UploadWizardUploadInterface.prototype.showTransportProgress = function () {
		// if fraction available, update individual progress bar / estimates, etc.
		this.showIndicator( 'progress' );
		this.setStatus( 'mediauploader-uploading' );
		this.setAdditionalStatus( null );
	};

	/**
	 * Show that upload is transported
	 */
	mw.UploadWizardUploadInterface.prototype.showStashed = function () {
		this.showIndicator( 'success' );
		this.setStatus( 'mediauploader-stashed-upload' );
		this.setAdditionalStatus( null );
	};

	/**
	 * Show that transport has failed
	 *
	 * @param {string} code Error code from API
	 * @param {string} html Error message
	 * @param {jQuery} [$additionalStatus]
	 */
	mw.UploadWizardUploadInterface.prototype.showError = function ( code, html, $additionalStatus ) {
		this.showIndicator( 'error' );
		this.setStatusString( html );
		this.setAdditionalStatus( $additionalStatus );
	};

	/**
	 * Run this when the value of the file input has changed and we know it's acceptable -- this
	 * will update interface to show as much info as possible, including preview.
	 * n.b. in older browsers we only will know the filename
	 *
	 * @param {Object} imageinfo
	 * @param {File} file
	 */
	mw.UploadWizardUploadInterface.prototype.fileChangedOk = function ( imageinfo, file ) {
		var statusItems = [];

		this.updateFilename();

		// set the status string - e.g. "256 Kb, 100 x 200"
		if ( imageinfo && imageinfo.width && imageinfo.height ) {
			statusItems.push( imageinfo.width + '\u00d7' + imageinfo.height );
		}

		if ( file && file.size ) {
			statusItems.push( uw.units.bytes( file.size ) );
		}

		this.clearStatus();
		this.setStatusString( statusItems.join( ' \u00b7 ' ) );
	};

	/**
	 * Display thumbnail preview.
	 *
	 * @return {jQuery.Promise} Promise resolved when the thumbnail is displayed or when displaying it
	 *     fails
	 */
	mw.UploadWizardUploadInterface.prototype.showThumbnail = function () {
		var $preview = this.$div.find( '.mediauploader-file-preview' ),
			deferred = $.Deferred();
		// This must match the CSS dimensions of .mediauploader-file-preview
		this.upload.getThumbnail( 120, 120 ).done( function ( thumb ) {
			mw.UploadWizard.placeThumbnail( $preview, thumb );
			deferred.resolve();
		} );
		return deferred.promise();
	};

	/**
	 * this does two things:
	 *   1 ) since the file input has been hidden with some clever CSS ( to avoid x-browser styling issues ),
	 *       update the visible filename
	 *
	 *   2 ) update the underlying "title" which we are targeting to add to mediawiki.
	 *       TODO silently fix to have unique filename? unnecessary at this point...
	 */
	mw.UploadWizardUploadInterface.prototype.updateFilename = function () {
		var path = this.upload.getFilename();

		// visible filename
		this.$form.find( '.mediauploader-visible-file-filename-text' )
			.text( path );

		if ( !this.isFilled ) {
			this.isFilled = true;
			this.$div.addClass( 'filled' );
		}
	};

}( mw.uploadWizard ) );
