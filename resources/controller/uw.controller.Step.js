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
	 * Represents a step in the wizard.
	 *
	 * @class
	 * @abstract
	 * @param {uw.ui.Step} ui The UI object that controls this step.
	 * @param {mw.Api} api
	 * @param {Object} config UploadWizard config object.
	 */
	uw.controller.Step = function UWControllerStep( ui, api, config ) {
		const step = this;

		OO.EventEmitter.call( this );

		/**
		 * @property {Object} config
		 */
		this.config = config;
		/**
		 * @property {mw.Api} api
		 */
		this.api = api;

		this.ui = ui;

		this.uploads = [];

		// children are expected to override this with the actual step name
		this.stepName = new Error( 'Undefined stepName' );

		/**
		 * Upload object event handlers to be bound on load & unbound on unload.
		 * This is an object literal where the keys are callback names, and
		 * values all callback. These callbacks will be called with the
		 * controller as content (`this`), and the upload as first argument.
		 * This'll effectively be:
		 * `upload.on( <key>, <value>.bind( this, upload ) );`
		 *
		 * @property {Object}
		 */
		this.uploadHandlers = {
			'remove-upload': this.removeUpload
		};

		this.ui.on( 'next-step', () => {
			step.moveNext();
		} );

		this.ui.on( 'previous-step', () => {
			step.movePrevious();
		} );

		/**
		 * @property {uw.controller.Step} nextStep
		 * The next step in the process.
		 */
		this.nextStep = null;

		/**
		 * @property {uw.controller.Step} previousStep
		 * The previous step in the process.
		 */
		this.previousStep = null;
	};

	OO.mixinClass( uw.controller.Step, OO.EventEmitter );

	/**
	 * Set the next step in the process.
	 *
	 * @param {uw.controller.Step} step
	 */
	uw.controller.Step.prototype.setNextStep = function ( step ) {
		this.nextStep = step;
		this.ui.enableNextButton();
	};

	/**
	 * Set the previous step in the process.
	 *
	 * @param {uw.controller.Step} step
	 */
	uw.controller.Step.prototype.setPreviousStep = function ( step ) {
		this.previousStep = step;
		this.ui.enablePreviousButton();
	};

	/**
	 * Initialize this step.
	 *
	 * @param {mw.UploadWizardUpload[]} uploads List of uploads being carried forward.
	 */
	uw.controller.Step.prototype.load = function ( uploads ) {
		const step = this;

		this.emit( 'load' );

		this.uploads = uploads || [];

		// prevent the window from being closed as long as we have data
		this.allowCloseWindow = mw.confirmCloseWindow( {
			test: step.hasData.bind( this )
		} );

		this.uploads.forEach( ( upload ) => {
			upload.state = step.stepName;

			step.bindUploadHandlers( upload );
		} );

		this.ui.load( uploads );
	};

	/**
	 * Cleanup this step.
	 */
	uw.controller.Step.prototype.unload = function () {
		const step = this;

		this.uploads.forEach( ( upload ) => {
			step.unbindUploadHandlers( upload );
		} );

		this.allowCloseWindow.release();
		this.ui.unload();

		this.emit( 'unload' );
	};

	/**
	 * Move to the next step.
	 */
	uw.controller.Step.prototype.moveNext = function () {
		this.unload();

		if ( this.nextStep ) {
			this.nextStep.load( this.uploads );
		}
	};

	/**
	 * Move to the previous step.
	 */
	uw.controller.Step.prototype.movePrevious = function () {
		this.unload();

		if ( this.previousStep ) {
			this.previousStep.load( this.uploads );
		}
	};

	/**
	 * Attaches controller-specific upload event handlers.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Step.prototype.bindUploadHandlers = function ( upload ) {
		const controller = this;

		Object.keys( this.uploadHandlers ).forEach( ( event ) => {
			const callback = controller.uploadHandlers[ event ];
			upload.on( event, callback, [ upload ], controller );
		} );
	};

	/**
	 * Removes controller-specific upload event handlers.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Step.prototype.unbindUploadHandlers = function ( upload ) {
		const controller = this;

		Object.keys( this.uploadHandlers ).forEach( ( event ) => {
			const callback = controller.uploadHandlers[ event ];
			upload.off( event, callback, controller );
		} );
	};

	/**
	 * Check if upload is able to be put through this step's changes.
	 *
	 * @return {boolean}
	 */
	uw.controller.Step.prototype.canTransition = function () {
		return true;
	};

	/**
	 * Figure out what to do and what options to show after the uploads have stopped.
	 * Uploading has stopped for one of the following reasons:
	 * 1) The user removed all uploads before they completed, in which case we are at upload.length === 0. We should start over and allow them to add new ones
	 * 2) All succeeded - show link to next step
	 * 3) Some failed, some succeeded - offer them the chance to retry the failed ones or go on to the next step
	 * 4) All failed -- have to retry, no other option
	 * In principle there could be other configurations, like having the uploads not all in error or stashed state, but
	 * we trust that this hasn't happened.
	 *
	 * For uploads that have succeeded, now is the best time to add the relevant previews and details to the DOM
	 * in the right order.
	 *
	 * @return {boolean} Whether all of the uploads are in a successful state.
	 */
	uw.controller.Step.prototype.showNext = function () {
		let okCount = this.getUploadStatesCount( this.finishState ),
			$buttons;

		// abort if all uploads have been removed
		if ( this.uploads.length === 0 ) {
			return false;
		}

		this.updateProgressBarCount( okCount );

		$buttons = this.ui.$div.find( '.mediauploader-buttons' );
		$buttons.show();

		$buttons.find( '.mediauploader-file-next-all-ok' ).hide();
		$buttons.find( '.mediauploader-file-next-some-failed' ).hide();
		$buttons.find( '.mediauploader-file-next-all-failed' ).hide();

		if ( okCount === this.uploads.length ) {
			$buttons.find( '.mediauploader-file-next-all-ok' ).show();
			return true;
		}

		if ( this.getUploadStatesCount( [ 'error', 'recoverable-error' ] ) === this.uploads.length ) {
			$buttons.find( '.mediauploader-file-next-all-failed' ).show();
		} else if ( this.getUploadStatesCount( 'transporting' ) === 0 ) {
			$buttons.find( '.mediauploader-file-next-some-failed' ).show();
		}

		return false;
	};

	/**
	 * @param {string|string[]} states List of upload states we want the count for
	 * @return {number}
	 */
	uw.controller.Step.prototype.getUploadStatesCount = function ( states ) {
		let count = 0;

		// normalize to array of states, even though input can be 1 string
		states = Array.isArray( states ) ? states : [ states ];

		this.uploads.forEach( ( upload ) => {
			if ( states.includes( upload.state ) ) {
				count++;
			}
		} );

		return count;
	};

	/**
	 * Function used by some steps to update progress bar for the whole
	 * batch of uploads.
	 */
	uw.controller.Step.prototype.updateProgressBarCount = function () {};

	/**
	 * Check if this step has data, to test if the window can be close (i.e. if
	 * content is going to be lost)
	 *
	 * @return {boolean}
	 */
	uw.controller.Step.prototype.hasData = function () {
		return this.uploads.length !== 0;
	};

	/**
	 * Add an upload.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Step.prototype.addUpload = function ( upload ) {
		this.uploads.push( upload );
	};

	/**
	 * Remove an upload.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Step.prototype.removeUpload = function ( upload ) {
		// remove the upload from the uploads array
		const index = this.uploads.indexOf( upload );
		if ( index !== -1 ) {
			this.uploads.splice( index, 1 );
		}

		// let the upload object cleanup itself!
		upload.remove();
	};

	/**
	 * Remove multiple uploads.
	 *
	 * @param {mw.UploadWizardUpload[]} uploads
	 */
	uw.controller.Step.prototype.removeUploads = function ( uploads ) {
		let i,
			// clone the array of uploads, just to be sure it's not a reference
			// to this.uploads, which will be modified (and we can't have that
			// while we're looping it)
			copy = uploads.slice();

		for ( i = 0; i < copy.length; i++ ) {
			this.removeUpload( copy[ i ] );
		}
	};

	/**
	 * Clear out uploads that are in error mode, perhaps before proceeding to the next step
	 */
	uw.controller.Step.prototype.removeErrorUploads = function () {
		// We must not remove items from an array while iterating over it with $.each (it causes the
		// next item to be skipped). Find and queue them first, then remove them.
		const toRemove = [];
		this.uploads.forEach( ( upload ) => {
			if ( upload.state === 'error' || upload.state === 'recoverable-error' ) {
				toRemove.push( upload );
			}
		} );

		this.removeUploads( toRemove );
	};

}( mw.uploadWizard ) );
