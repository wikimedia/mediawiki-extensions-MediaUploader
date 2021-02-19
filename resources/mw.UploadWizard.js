/**
 * Object that represents the entire multi-step Upload Wizard
 */
( function ( uw ) {

	mw.UploadWizard = function ( config ) {
		var maxSimPref;

		this.api = this.getApi( { ajax: { timeout: 0 } } );

		// making a sort of global for now, should be done by passing in config or fragments of config
		// when needed elsewhere
		mw.UploadWizard.config = config;
		// Shortcut for local references
		this.config = config;

		this.steps = {};

		maxSimPref = mw.user.options.get( 'upwiz_maxsimultaneous' );

		if ( maxSimPref !== 'default' ) {
			if ( maxSimPref > 0 ) {
				config.maxSimultaneousConnections = maxSimPref;
			} else {
				config.maxSimultaneousConnections = 1;
			}
		}

		this.maxSimultaneousConnections = config.maxSimultaneousConnections;

		if ( mw.loader.getState( 'ext.uls.mediawiki' ) !== null ) {
			mw.loader.load( 'ext.uls.mediawiki' );
		}
	};

	mw.UploadWizard.DEBUG = true;

	mw.UploadWizard.userAgent = 'UploadWizard';

	mw.UploadWizard.prototype = {
		/**
		 * Create the basic interface to make an upload in this div
		 *
		 * @param {string} selector
		 */
		createInterface: function ( selector ) {
			this.ui = new uw.ui.Wizard( selector );

			this.initialiseSteps().then( function ( steps ) {
				// "select" the first step - highlight, make it visible, hide all others
				steps.tutorial.load( [] );
			} );
		},

		/**
		 * Initialise the steps in the wizard
		 *
		 * @return {jQuery.Promise}
		 */
		initialiseSteps: function () {
			var self = this,
				steps = {};

			steps.tutorial = new uw.controller.Tutorial( this.api, this.config );
			steps.file = new uw.controller.Upload( this.api, this.config );
			steps.deeds = new uw.controller.Deed( this.api, this.config );
			steps.details = new uw.controller.Details( this.api, this.config );
			steps.thanks = new uw.controller.Thanks( this.api, this.config );

			steps.tutorial.setNextStep( steps.file );

			steps.file.setPreviousStep( steps.tutorial );
			steps.file.setNextStep( steps.deeds );

			steps.deeds.setPreviousStep( steps.file );
			steps.deeds.setNextStep( steps.details );

			steps.details.setPreviousStep( steps.deeds );
			steps.details.setNextStep( steps.thanks );

			// thanks doesn't need a "previous" step, there's no undoing uploads!
			steps.thanks.setNextStep( steps.file );

			return $.Deferred().resolve( steps ).promise()
				.always( function ( steps ) {
					self.steps = steps;
					self.ui.initialiseSteps( steps );
				} );
		},

		/**
		 * mw.Api's ajax calls are not very consistent in their error handling.
		 * As long as the response comes back, the response will be fine: it'll
		 * get rejected with the error details there. However, if no response
		 * comes back for whatever reason, things can get confusing.
		 * I'll monkeypatch around such cases so that we can always rely on the
		 * error response the way we want it to be.
		 *
		 * TODO: Instead of this monkeypatching, we could call api.getErrorMessage()
		 * in the error handlers to get nice messages.
		 *
		 * @param {Object} options
		 * @return {mw.Api}
		 */
		getApi: function ( options ) {
			var api = new mw.Api( options );

			api.ajax = function ( parameters, ajaxOptions ) {
				var original, override;

				$.extend( parameters, {
					errorformat: 'html',
					errorlang: mw.config.get( 'wgUserLanguage' ),
					errorsuselocal: 1,
					formatversion: 2
				} );

				original = mw.Api.prototype.ajax.apply( this, [ parameters, ajaxOptions ] );

				// we'll attach a default error handler that makes sure error
				// output is always, reliably, in the same format
				override = original.then(
					null, // done handler - doesn't need overriding
					function ( code, result ) { // fail handler
						var response = { errors: [ {
							code: code,
							html: result.textStatus || mw.message( 'api-clientside-error-invalidresponse' ).parse()
						} ] };

						if ( result.errors && result.errors[ 0 ] ) {
							// in case of success-but-has-errors, we have a valid result
							response = result;
						} else if ( result && result.textStatus === 'timeout' ) {
							// in case of $.ajax.fail(), there is no response json
							response.errors[ 0 ].html = mw.message( 'api-clientside-error-timeout' ).parse();
						} else if ( result && result.textStatus === 'parsererror' ) {
							response.errors[ 0 ].html = mw.message( 'api-error-parsererror' ).parse();
						} else if ( code === 'http' && result && result.xhr && result.xhr.status === 0 ) {
							// failed to even connect to server
							response.errors[ 0 ].html = mw.message( 'api-clientside-error-noconnect' ).parse();
						}

						return $.Deferred().reject( code, response, response );
					}
				);

				/*
				 * After attaching (.then) our error handler, a new promise is
				 * returned. The original promise had an 'abort' method, which
				 * we'll also want to make use of...
				 */
				return override.promise( { abort: original.abort } );
			};

			return api;
		}
	};

	/**
	 * Get the own work and third party licensing deeds if they are needed.
	 *
	 * @static
	 * @since 1.2
	 * @param {mw.UploadWizardUpload[]} uploads
	 * @param {Object} config The UW config object.
	 * @return {mw.deed.Abstract[]}
	 */
	mw.UploadWizard.getLicensingDeeds = function ( uploads, config ) {
		var deed, api,
			deeds = {},
			doOwnWork = false,
			doThirdParty = false;

		api = this.prototype.getApi( { ajax: { timeout: 0 } } );

		if ( config.licensing.ownWorkDefault === 'choice' ) {
			doOwnWork = doThirdParty = true;
		} else if ( config.licensing.ownWorkDefault === 'own' ) {
			doOwnWork = true;
		} else {
			doThirdParty = true;
		}

		if ( doOwnWork ) {
			deed = new uw.deed.OwnWork( config, uploads, api );
			deeds[ deed.name ] = deed;
		}
		if ( doThirdParty ) {
			deed = new uw.deed.ThirdParty( config, uploads, api );
			deeds[ deed.name ] = deed;
		}

		return deeds;
	};

	/**
	 * Helper method to put a thumbnail somewhere.
	 *
	 * @param {string|jQuery} selector String representing a jQuery selector, or a jQuery object
	 * @param {HTMLCanvasElement|HTMLImageElement|null} image
	 */
	mw.UploadWizard.placeThumbnail = function ( selector, image ) {
		if ( image === null ) {
			$( selector ).addClass( 'mwe-upwiz-file-preview-broken' );
			return;
		}

		$( selector )
			.css( { background: 'none' } )
			.prepend(
				$( '<a>' )
					.addClass( 'mwe-upwiz-thumbnail-link' )
					.append( image )
			);
	};

}( mw.uploadWizard ) );
