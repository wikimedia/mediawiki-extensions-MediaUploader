( function ( uw ) {

	var NS_FILE = mw.config.get( 'wgNamespaceIds' ).file,
		byteLength = require( 'mediawiki.String' ).byteLength;

	/**
	 * A title field in UploadWizard's "Details" step form.
	 *
	 * @class uw.TitleDetailsWidget
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} [config]
	 */
	uw.TitleDetailsWidget = function UWTitleDetailsWidget( config ) {
		config = config || {};
		uw.TitleDetailsWidget.parent.call( this, config );

		this.config = config;
		// We wouldn't want or use any of mw.widgets.TitleInputWidget functionality.
		this.titleInput = new OO.ui.TextInputWidget( {
			classes: [ 'mwe-title', 'mediauploader-titleDetailsWidget-title' ],
			maxLength: config.maxLength,
			disabled: this.config.disabled
		} );

		// Aggregate 'change' event (with delay)
		this.titleInput.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

		this.$element.addClass( 'mediauploader-titleDetailsWidget' );
		this.$element.append(
			this.titleInput.$element
		);
	};
	OO.inheritClass( uw.TitleDetailsWidget, uw.DetailsWidget );

	/**
	 * Reliably turn input into a MediaWiki title that is located in the 'File:' namespace.
	 * Also applies file-specific checks ($wgIllegalFileChars).
	 *
	 *     var title = uw.TitleDetailsWidget.static.makeTitleInFileNS( 'filename.ext' );
	 *
	 * @static
	 * @param {string} filename Desired file name; optionally with 'File:' namespace prefixed
	 * @return {mw.Title|null}
	 */
	uw.TitleDetailsWidget.static.makeTitleInFileNS = function ( filename ) {
		var
			mwTitle = mw.Title.newFromText( filename, NS_FILE ),
			illegalFileChars = new RegExp( '[' + mw.config.get( 'wgIllegalFileChars', '' ) + ']' );
		if ( mwTitle && mwTitle.getNamespaceId() !== NS_FILE ) {
			// Force file namespace
			mwTitle = mw.Title.makeTitle( NS_FILE, filename );
		}
		if ( mwTitle && ( illegalFileChars.test( mwTitle.getMainText() ) || mwTitle.fragment !== null ) ) {
			// Consider the title invalid if it contains characters disallowed in file names
			mwTitle = null;
		}
		return mwTitle;
	};

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.pushPending = function () {
		this.titleInput.pushPending();
	};

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.popPending = function () {
		this.titleInput.popPending();
	};

	/**
	 * Get a mw.Title object for current value.
	 *
	 * @return {mw.Title|null}
	 */
	uw.TitleDetailsWidget.prototype.getTitle = function () {
		var value, extRegex, cleaned, title;
		value = this.titleInput.getValue().trim();
		if ( !value ) {
			return null;
		}

		if ( this.config.extension ) {
			extRegex = new RegExp( '\\.' + this.extension + '$', 'i' );
			cleaned = value.replace( extRegex, '' ).replace( /\.+$/g, '' ).trim();
			cleaned = cleaned + '.' + this.config.extension;
		} else {
			cleaned = value;
		}
		title = uw.TitleDetailsWidget.static.makeTitleInFileNS( cleaned );
		return title;
	};

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.getWarnings = function () {
		var warnings = [];
		this.getEmptyWarning( this.titleInput.getValue().trim() === '', warnings );

		return $.Deferred().resolve( warnings ).promise();
	};

	/**
	 * @return {jQuery.Promise}
	 */
	uw.TitleDetailsWidget.prototype.getErrors = function () {
		var
			errors = [],
			value = this.titleInput.getValue().trim(),
			processDestinationCheck = this.processDestinationCheck,
			title = this.getTitle(),
			// title length is dependent on DB column size and is bytes rather than characters
			length = byteLength( value );

		if ( this.config.required && value === '' ) {
			errors.push( mw.message( 'mediauploader-error-blank' ) );
			return $.Deferred().resolve( errors ).promise();
		}

		if ( !this.config.required && value === '' ) {
			return $.Deferred().resolve( [] ).promise();
		}

		if ( length !== 0 && this.config.minLength && length < this.config.minLength ) {
			errors.push( mw.message( 'mediauploader-error-title-too-short', this.config.minLength ) );
			return $.Deferred().resolve( errors ).promise();
		}

		if ( this.config.maxLength && length > this.config.maxLength ) {
			errors.push( mw.message( 'mediauploader-error-title-too-long', this.config.maxLength ) );
			return $.Deferred().resolve( errors ).promise();
		}

		if ( !title ) {
			errors.push( mw.message( 'mediauploader-error-title-invalid' ) );
			return $.Deferred().resolve( errors ).promise();
		}

		return mw.DestinationChecker.checkTitle( title.getPrefixedText() )
			.then( function ( result ) {
				var moreErrors = processDestinationCheck( result );
				if ( result.blacklist.unavailable ) {
					// We don't have a title blacklist, so just check for some likely undesirable patterns.
					moreErrors = moreErrors.concat(
						mw.QuickTitleChecker.checkTitle( title.getNameText() ).map( function ( errorCode ) {
							// Messages that can be used here:
							// * mediauploader-error-title-invalid
							// * mediauploader-error-title-senselessimagename
							// * mediauploader-error-title-thumbnail
							// * mediauploader-error-title-extension
							return mw.message( 'mediauploader-error-title-' + errorCode );
						} )
					);
				}
				return moreErrors;
			} )
			.then( function ( moreErrors ) {
				return [].concat( errors, moreErrors );
			}, function () {
				return $.Deferred().resolve( errors );
			} );
	};

	/**
	 * Process the result of a destination filename check, return array of mw.Messages objects
	 * representing errors.
	 *
	 * @private
	 * @param {Object} result Result to process, output from mw.DestinationChecker
	 * @return {mw.Message[]} Error messages
	 */
	uw.TitleDetailsWidget.prototype.processDestinationCheck = function ( result ) {
		var messageParams, errors, titleString;

		if ( result.unique.isUnique && result.blacklist.notBlacklisted && !result.unique.isProtected ) {
			return [];
		}

		// Something is wrong with this title.
		errors = [];

		try {
			titleString = result.unique.title || result.title;
			titleString = uw.TitleDetailsWidget.static.makeTitleInFileNS( titleString ).getPrefixedText();
		} catch ( e ) {
			// Unparseable result? This shouldn't happen, we checked for that earlier...
			errors.push( mw.message( 'mediauploader-error-title-invalid' ) );
			return errors;
		}

		if ( !result.unique.isUnique ) {
			// result is NOT unique
			if ( result.unique.href ) {
				errors.push( mw.message(
					'mediauploader-fileexists-replace-on-page',
					titleString,
					$( '<a>' ).attr( { href: result.unique.href, target: '_blank' } )
				) );
			} else {
				errors.push( mw.message( 'mediauploader-fileexists-replace-no-link', titleString ) );
			}
		} else if ( result.unique.isProtected ) {
			errors.push( mw.message( 'mediauploader-error-title-protected' ) );
		} else {
			mw.messages.set( result.blacklist.blacklistMessage, result.blacklist.blacklistReason );
			messageParams = [
				'mediauploader-blacklisted-details',
				titleString,
				function () {
					// eslint-disable-next-line mediawiki/msg-doc
					mw.errorDialog( $( '<div>' ).msg( result.blacklist.blacklistMessage ) );
				}
			];

			// feedback request for titleblacklist
			if ( mw.UploadWizard.config.blacklistIssuesPage !== undefined && mw.UploadWizard.config.blacklistIssuesPage !== '' ) {
				messageParams[ 0 ] = 'mediauploader-blacklisted-details-feedback';
				messageParams.push( function () {
					var feedback = new mw.Feedback( {
						title: new mw.Title( mw.UploadWizard.config.blacklistIssuesPage ),
						dialogTitleMessageKey: 'mediauploader-feedback-title'
					} );
					feedback.launch( {
						message: mw.message( 'mediauploader-feedback-blacklist-line-intro', result.blacklist.blacklistLine ).text(),
						subject: mw.message( 'mediauploader-feedback-blacklist-subject', titleString ).text()
					} );
				} );
			}

			errors.push( mw.message.apply( mw, messageParams ) );
		}

		return errors;
	};

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.getWikiText = function () {
		return this.titleInput.getValue().trim();
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.TitleDetailsWidget.prototype.getSerialized = function () {
		return {
			title: this.titleInput.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {string} serialized.language Title language code
	 * @param {string} serialized.title Title text
	 */
	uw.TitleDetailsWidget.prototype.setSerialized = function ( serialized ) {
		this.titleInput.setValue( serialized.title );
	};

}( mw.uploadWizard ) );
