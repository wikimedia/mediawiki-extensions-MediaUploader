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
	 * Deed step controller.
	 *
	 * @class
	 * @extends uw.controller.Step
	 * @param {mw.Api} api
	 * @param {Object} config UploadWizard config object.
	 */
	uw.controller.Deed = function UWControllerDeed( api, config ) {
		uw.controller.Step.call(
			this,
			new uw.ui.Deed(),
			api,
			config
		);

		this.stepName = 'deeds';

		this.deeds = {};
	};

	OO.inheritClass( uw.controller.Deed, uw.controller.Step );

	uw.controller.Deed.prototype.moveNext = function () {
		var
			deedController = this,
			valid, fields, validityPromises;

		if ( !this.deedChooser ) {
			uw.controller.Step.prototype.moveNext.call( this );
			return;
		}

		valid = this.deedChooser.valid();
		if ( valid ) {
			fields = this.deedChooser.deed.getFields();
			validityPromises = fields.map( function ( fieldLayout ) {
				// Update any error/warning messages
				return fieldLayout.checkValidity( true );
			} );
			if ( validityPromises.length === 1 ) {
				// validityPromises will hold all promises for all uploads;
				// adding a bogus promise (no warnings & errors) to
				// ensure $.when always resolves with an array of multiple
				// results (if there's just 1, it would otherwise have just
				// that one's arguments, instead of a multi-dimensional array
				// of upload warnings & failures)
				validityPromises.push( $.Deferred().resolve( [], [] ).promise() );
			}

			$.when.apply( $, validityPromises ).then( function () {
				// `arguments` will be an array of all fields, with their warnings & errors
				// e.g. `[[something], []], [[], [something]]` for 2 fields, where the first one has
				// a warning and the last one an error

				// TODO Handle warnings with a confirmation dialog

				var i;
				for ( i = 0; i < arguments.length; i++ ) {
					if ( arguments[ i ][ 1 ].length ) {
						// One of the fields has errors; refuse to proceed!
						return;
					}
				}

				uw.controller.Step.prototype.moveNext.call( deedController );
			} );
		}
	};

	uw.controller.Deed.prototype.unload = function () {
		var deedController = this;
		uw.controller.Step.prototype.unload.call( this );

		Object.keys( this.deeds ).forEach( function ( name ) {
			deedController.deeds[ name ].unload();
		} );
	};

	/**
	 * Move to this step.
	 *
	 * @param {mw.UploadWizardUpload[]} uploads
	 */
	uw.controller.Deed.prototype.load = function ( uploads ) {
		var customDeed, previousDeed;

		uw.controller.Step.prototype.load.call( this, uploads );

		// grab a serialized copy of previous deeds' details (if any)
		if ( this.deedChooser ) {
			previousDeed = this.deedChooser.getSerialized();
		}

		this.deeds = mw.UploadWizard.getLicensingDeeds( this.uploads, this.config );

		// if we have multiple uploads, also give them the option to set
		// licenses individually
		if ( this.uploads.length > 1 ) {
			customDeed = new uw.deed.Custom( this.config );
			this.deeds[ customDeed.name ] = customDeed;
		}

		this.deedChooser = new mw.UploadWizardDeedChooser(
			this.config,
			'#mediauploader-deeds',
			this.deeds,
			this.uploads
		);

		$( '<div>' )
			.insertBefore( this.deedChooser.$selector.find( '.mediauploader-deed-ownwork' ) )
			.msg( 'mediauploader-deeds-macro-prompt', this.uploads.length, mw.user );

		uploads.forEach( function ( upload ) {
			// Add previews and details to the DOM
			upload.deedPreview = new uw.ui.DeedPreview( upload );
		} );

		this.deedChooser.onLayoutReady();

		// restore the previous input (if any) for all deeds
		if ( previousDeed ) {
			this.deedChooser.setSerialized( previousDeed );
		}
	};

	/**
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Deed.prototype.removeUpload = function ( upload ) {
		uw.controller.Step.prototype.removeUpload.call( this, upload );

		if ( upload.deedPreview ) {
			upload.deedPreview.remove();
		}
	};

}( mw.uploadWizard ) );
