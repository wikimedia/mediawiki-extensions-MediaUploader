/*
 * This file is part of the MediaWiki extension MediaUploader.
 *
 * MediaUploader is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaUploader is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaUploader.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw ) {
	/**
	 * Represents the UI for the wizard's Upload step.
	 *
	 * @class uw.ui.Upload
	 * @extends uw.ui.Step
	 * @constructor
	 * @param {Object} config UploadWizard config object.
	 */
	uw.ui.Upload = function UWUIUpload( config ) {
		var upload = this;

		this.config = config;

		uw.ui.Step.call(
			this,
			'file'
		);

		this.$addFileContainer = $( '<div>' )
			.attr( 'id', 'mediauploader-add-file-container' )
			.addClass( 'mediauploader-add-files-0' );

		this.$uploadCtrl = $( '<div>' )
			.attr( 'id', 'mediauploader-upload-ctrls' )
			.addClass( 'mediauploader-file ui-helper-clearfix' )
			.append( this.$addFileContainer );

		this.addFile = new OO.ui.SelectFileWidget( {
			classes: [ 'mediauploader-add-file' ],
			multiple: true,
			showDropTarget: true,
			button: {
				label: mw.message( 'mediauploader-add-file-0-free' ).text(),
				flags: [ 'progressive', 'primary' ]
			}
		} );

		this.$addFileContainer.append( this.addFile.$element );

		this.nextStepButtonAllOk = new OO.ui.ButtonWidget( {
			label: mw.message( 'mediauploader-next-file' ).text(),
			flags: [ 'progressive', 'primary' ]
		} ).on( 'click', function () {
			upload.emit( 'next-step' );
		} );

		this.retryButtonSomeFailed = new OO.ui.ButtonWidget( {
			label: mw.message( 'mediauploader-file-retry' ).text(),
			flags: [ 'progressive' ]
		} ).on( 'click', function () {
			upload.hideEndButtons();
			upload.emit( 'retry' );
		} );

		this.nextStepButtonSomeFailed = new OO.ui.ButtonWidget( {
			label: mw.message( 'mediauploader-next-file-despite-failures' ).text(),
			flags: [ 'progressive', 'primary' ]
		} ).on( 'click', function () {
			upload.emit( 'next-step' );
		} );

		this.retryButtonAllFailed = new OO.ui.ButtonWidget( {
			label: mw.message( 'mediauploader-file-retry' ).text(),
			flags: [ 'progressive' ]
		} ).on( 'click', function () {
			upload.hideEndButtons();
			upload.emit( 'retry' );
		} );

		this.$fileList = $( '<div>' )
			.attr( 'id', 'mediauploader-filelist' )
			.addClass( 'ui-corner-all' );

		this.$progress = $( '<div>' )
			.attr( 'id', 'mediauploader-progress' )
			.addClass( 'ui-helper-clearfix' );

		this.addPreviousButton();
		this.addNextButton();
	};

	OO.inheritClass( uw.ui.Upload, uw.ui.Step );

	uw.ui.Upload.prototype.showProgressBar = function () {
		this.$progress.show();
	};

	/**
	 * Updates the interface based on the number of uploads.
	 *
	 * @param {boolean} haveUploads Whether there are any uploads at all.
	 * @param {boolean} fewerThanMax Whether we can add more uploads.
	 */
	uw.ui.Upload.prototype.updateFileCounts = function ( haveUploads, fewerThanMax ) {
		this.$fileList.toggleClass( 'mediauploader-filled-filelist', haveUploads );
		this.$addFileContainer.toggleClass( 'mediauploader-add-files-0', !haveUploads );

		this.setAddButtonText( haveUploads );

		if ( haveUploads ) {
			// we have uploads ready to go, so allow us to proceed
			this.$addFileContainer.add( this.$buttons ).show();

			// fix the rounded corners on file elements.
			// we want them to be rounded only when their edge touched the top or bottom of the filelist.
			this.$fileListings = this.$fileList.find( '.filled' );

			this.$visibleFileListings = this.$fileListings.find( '.mediauploader-visible-file' );
			this.$visibleFileListings.removeClass( 'ui-corner-top ui-corner-bottom' );
			this.$visibleFileListings.first().addClass( 'ui-corner-top' );
			this.$visibleFileListings.last().addClass( 'ui-corner-bottom' );

			// eslint-disable-next-line no-jquery/no-sizzle
			this.$fileListings.filter( ':odd' ).addClass( 'odd' );
			// eslint-disable-next-line no-jquery/no-sizzle
			this.$fileListings.filter( ':even' ).removeClass( 'odd' );
		} else {
			this.hideEndButtons();
		}

		this.addFile.setDisabled( !fewerThanMax );
	};

	/**
	 * Changes the initial centered invitation button to something like "add another file"
	 *
	 * @param {boolean} more
	 */
	uw.ui.Upload.prototype.setAddButtonText = function ( more ) {
		var msg = 'mediauploader-add-file-';

		if ( more ) {
			msg += 'n';
		} else {
			msg += '0-free';
		}

		// Messages that can be used here:
		// * mediauploader-add-file-0-free
		// * mediauploader-add-file-n
		this.addFile.selectButton.setLabel( mw.message( msg ).text() );
	};

	uw.ui.Upload.prototype.load = function ( uploads ) {
		var ui = this;

		uw.ui.Step.prototype.load.call( this, uploads );

		if ( uploads.length === 0 ) {
			this.$fileList.removeClass( 'mediauploader-filled-filelist' );
		}

		this.$div.prepend(
			$( '<div>' )
				.attr( 'id', 'mediauploader-files' )
				.append(
					this.$fileList,
					this.$uploadCtrl
				)
		);

		this.addFile.on( 'change', function ( files ) {
			ui.emit( 'files-added', files );
			ui.addFile.setValue( null );
		} );
	};

	uw.ui.Upload.prototype.displayUploads = function ( uploads ) {
		var thumbPromise,
			$uploadInterfaceDivs = $( [] );

		uploads.forEach( function ( upload ) {
			// We'll attach all interfaces to the DOM at once rather than one-by-one, for better
			// performance
			$uploadInterfaceDivs = $uploadInterfaceDivs.add( upload.ui.$div );
		} );

		// Attach all interfaces to the DOM
		this.$fileList.append( $uploadInterfaceDivs );

		// Display thumbnails, but not all at once because they're somewhat expensive to generate.
		// This will wait for each thumbnail to be complete before starting the next one.
		thumbPromise = $.Deferred().resolve();
		uploads.forEach( function ( upload ) {
			thumbPromise = thumbPromise.then( function () {
				var deferred = $.Deferred();
				setTimeout( function () {
					if ( this.movedFrom ) {
						// We're no longer displaying any of these thumbnails, stop
						deferred.reject();
					}
					upload.ui.showThumbnail().done( function () {
						deferred.resolve();
					} );
				} );
				return deferred.promise();
			} );
		} );
	};

	uw.ui.Upload.prototype.addNextButton = function () {
		var ui = this;

		this.nextButtonPromise.done( function () {
			ui.$buttons.append(
				$( '<div>' )
					.addClass( 'mediauploader-file-next-all-ok mediauploader-file-endchoice' )
					.append(
						new OO.ui.HorizontalLayout( {
							items: [
								new OO.ui.LabelWidget( {
									label: mw.message( 'mediauploader-file-all-ok' ).text()
								} ),
								ui.nextStepButtonAllOk
							]
						} ).$element
					)
			);

			ui.$buttons.append(
				$( '<div>' )
					.addClass( 'mediauploader-file-next-some-failed mediauploader-file-endchoice' )
					.append(
						new OO.ui.HorizontalLayout( {
							items: [
								new OO.ui.LabelWidget( {
									label: mw.message( 'mediauploader-file-some-failed' ).text()
								} ),
								ui.retryButtonSomeFailed,
								ui.nextStepButtonSomeFailed
							]
						} ).$element
					)
			);

			ui.$buttons.append(
				$( '<div>' )
					.addClass( 'mediauploader-file-next-all-failed mediauploader-file-endchoice' )
					.append(
						new OO.ui.HorizontalLayout( {
							items: [
								new OO.ui.LabelWidget( {
									label: mw.message( 'mediauploader-file-all-failed' ).text()
								} ),
								ui.retryButtonAllFailed
							]
						} ).$element
					)
			);

			ui.$buttons.append( ui.$progress );
		} );
	};

	/**
	 * Hide the buttons for moving to the next step.
	 */
	uw.ui.Upload.prototype.hideEndButtons = function () {
		this.$div
			.find( '.mediauploader-buttons .mediauploader-file-endchoice' )
			.hide();
	};

	/**
	 * Shows an error dialog informing the user that some uploads have been omitted
	 * since they went over the max files limit.
	 *
	 * @param {number} filesUploaded The number of files that have been attempted to upload
	 */
	uw.ui.Upload.prototype.showTooManyFilesError = function ( filesUploaded ) {
		mw.errorDialog(
			mw.message(
				'mediauploader-too-many-files-text',
				this.config.maxUploads,
				filesUploaded
			).text(),
			mw.message( 'mediauploader-too-many-files' ).text()
		);
	};

	/**
	 * Shows an error dialog informing the user that an upload omitted because
	 * it is too large.
	 *
	 * @param {number} maxSize The max upload file size
	 * @param {number} size The actual upload file size
	 */
	uw.ui.Upload.prototype.showFileTooLargeError = function ( maxSize, size ) {
		mw.errorDialog(
			mw.message(
				'mediauploader-file-too-large-text',
				uw.units.bytes( maxSize ),
				uw.units.bytes( size )
			).text(),
			mw.message( 'mediauploader-file-too-large' ).text()
		);
	};

	/**
	 * @param {string} filename
	 * @param {string} extension
	 */
	uw.ui.Upload.prototype.showBadExtensionError = function ( filename, extension ) {
		var $errorMessage = $( '<p>' ).msg( 'mediauploader-upload-error-bad-filename-extension', extension );
		this.showFilenameError( $errorMessage );
	};

	uw.ui.Upload.prototype.showMissingExtensionError = function () {
		var $errorMessage = $( '<p>' ).msg( 'mediauploader-upload-error-bad-filename-no-extension' );
		this.showFilenameError(
			$( '<div>' ).append(
				$errorMessage,
				$( '<p>' ).msg( 'mediauploader-allowed-filename-extensions' ),
				$( '<blockquote>' ).append( $( '<tt>' ).append(
					mw.UploadWizard.config.fileExtensions.join( ' ' )
				) )
			)
		);
	};

	/**
	 * @param {string} filename
	 * @param {string} basename
	 */
	uw.ui.Upload.prototype.showDuplicateError = function ( filename, basename ) {
		this.showFilenameError( $( '<p>' ).msg( 'mediauploader-upload-error-duplicate-filename-error', basename ) );
	};

	/**
	 * @param {string} filename
	 */
	uw.ui.Upload.prototype.showUnparseableFilenameError = function ( filename ) {
		this.showFilenameError( mw.message( 'mediauploader-unparseable-filename', filename ).escaped() );
	};

	/**
	 * Shows an error dialog informing the user that an upload has been omitted
	 * over its filename.
	 *
	 * @param {jQuery|string} message The error message
	 */
	uw.ui.Upload.prototype.showFilenameError = function ( message ) {
		mw.errorDialog( message );
	};

}( mw.uploadWizard ) );
