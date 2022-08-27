/*
 * This file is part of the MediaWiki extension UploadWizard.
 *
 * UploadWizard is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * UploadWizard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UploadWizard.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw ) {
	/**
	 * Set up the form and deed object for the deed option that says these uploads are all the user's own work.
	 *
	 * @class uw.deed.OwnWork
	 * @constructor
	 * @param {Object} config The UW config
	 * @param {mw.UploadWizardUpload[]} uploads Array of uploads that this deed refers to
	 * @param {mw.Api} api API object - useful for doing previews
	 */
	uw.deed.OwnWork = function UWDeedOwnWork( config, uploads, api ) {
		var deed = this,
			prefAuthName = mw.user.options.get( 'upwiz_licensename' );

		uw.deed.Abstract.call( this, 'ownwork', config );

		this.uploadCount = uploads.length;

		if ( !prefAuthName ) {
			prefAuthName = mw.config.get( 'wgUserName' );
		}

		// copyright holder
		this.authorInput = new OO.ui.TextInputWidget( {
			name: 'author',
			title: mw.message( 'mediauploader-tooltip-sign' ).text(),
			value: prefAuthName,
			classes: [ 'mediauploader-sign' ]
		} );
		this.fakeAuthorInput = new OO.ui.TextInputWidget( {
			readOnly: true,
			value: prefAuthName,
			classes: [ 'mediauploader-sign' ]
		} );
		this.authorInput.on( 'change', function () {
			deed.fakeAuthorInput.setValue( deed.authorInput.getValue() );
		} );

		// "use a different license"
		this.showCustomDiv = this.config.licensing.ownWork.licenses.length > 1;
		if ( this.showCustomDiv ) {
			this.licenseInput = new mw.UploadWizardLicenseInput(
				this.config.licensing.ownWork,
				this.uploadCount,
				api
			);
			this.licenseInput.$element.addClass( 'mediauploader-deed-license' );
			this.licenseInputField = new uw.FieldLayout( this.licenseInput );
		}
	};

	OO.inheritClass( uw.deed.OwnWork, uw.deed.Abstract );

	uw.deed.OwnWork.prototype.unload = function () {
		// No licenseInput is present if there's no custom licenses allowed (e.g. campaigns)
		if ( this.licenseInput !== undefined ) {
			this.licenseInput.unload();
		}
	};

	/**
	 * @return {uw.FieldLayout[]} Fields that need validation
	 */
	uw.deed.OwnWork.prototype.getFields = function () {
		var fields = [ this.authorInputField ];
		if ( this.showCustomDiv ) {
			fields.push( this.licenseInputField );
		}
		return fields;
	};

	uw.deed.OwnWork.prototype.setFormFields = function ( $selector ) {
		var $customDiv, $formFields, $toggler, crossfaderWidget, defaultLicense,
			defaultLicenseURL, defaultLicenseMsg, defaultLicenseExplainMsg,
			$defaultLicenseLink, $standardDiv, $crossfader, deed, languageCode;

		this.$selector = $selector;
		deed = this;
		languageCode = mw.config.get( 'wgUserLanguage' );

		defaultLicense = this.getDefaultLicense();

		defaultLicenseURL = this.config.licenses[ defaultLicense ].url === undefined ?
			'#missing license URL' :
			this.config.licenses[ defaultLicense ].url + 'deed.' + languageCode;
		defaultLicenseMsg = 'mediauploader-source-ownwork-assert-' + defaultLicense;
		defaultLicenseExplainMsg = 'mediauploader-source-ownwork-' + defaultLicense + '-explain';
		$defaultLicenseLink = $( '<a>' ).attr( { target: '_blank', href: defaultLicenseURL } );

		this.$form = $( '<form>' );

		/* eslint-disable mediawiki/msg-doc */
		$standardDiv = $( '<div>' ).addClass( 'mediauploader-standard' ).append(
			$( '<p>' ).msg(
				defaultLicenseMsg,
				this.uploadCount,
				this.authorInput.$element,
				$defaultLicenseLink,
				mw.user
			),
			$( '<p>' ).addClass( 'mwe-small-print' ).msg(
				defaultLicenseExplainMsg,
				this.uploadCount
			)
		);
		$crossfader = $( '<div>' ).addClass( 'mediauploader-crossfader' ).append( $standardDiv );
		/* eslint-enable mediawiki/msg-doc */

		if ( this.showCustomDiv ) {
			$customDiv = $( '<div>' ).addClass( 'mediauploader-custom' ).append(
				$( '<p>' ).msg( 'mediauploader-source-ownwork-assert-custom',
					this.uploadCount,
					this.fakeAuthorInput.$element )
			);

			$crossfader.append( $customDiv );
		}

		crossfaderWidget = new OO.ui.Widget();
		crossfaderWidget.$element.append( $crossfader );
		// See uw.DetailsWidget
		crossfaderWidget.getErrors = this.getAuthorErrors.bind( this, this.authorInput );
		crossfaderWidget.getWarnings = this.getAuthorWarnings.bind( this, this.authorInput );

		this.authorInputField = new uw.FieldLayout( crossfaderWidget );
		// Aggregate 'change' event
		this.authorInput.on( 'change', OO.ui.debounce( function () {
			crossfaderWidget.emit( 'change' );
		}, 500 ) );

		$formFields = $( '<div>' ).addClass( 'mediauploader-deed-form-internal' )
			.append( this.authorInputField.$element );

		if ( this.showCustomDiv ) {
			// FIXME: Move CSS rule to CSS file
			$toggler = $( '<p>' ).addClass( 'mwe-more-options' ).css( 'text-align', 'right' )
				.append( $( '<a>' )
					.msg( 'mediauploader-license-show-all' )
					.on( 'click', function () {
						if ( $crossfader.data( 'crossfadeDisplay' ).get( 0 ) === $customDiv.get( 0 ) ) {
							deed.standardLicense();
						} else {
							deed.customLicense();
						}
					} ) );

			$formFields.append( this.licenseInputField.$element.hide(), $toggler );
		}

		this.$form.append( $formFields ).appendTo( $selector );

		// done after added to the DOM, so there are true heights
		$crossfader.morphCrossfader();

		this.setDefaultLicense();
	};

	/**
	 * OwnWork's default value is different than the default LicenseInput defaults...
	 * LicenseInput supports multiple default values, but this one does not, because
	 * it may not even display a selection at first, just the 1 default value.
	 */
	uw.deed.OwnWork.prototype.setDefaultLicense = function () {
		var defaultLicense = {};
		if ( this.showCustomDiv ) {
			defaultLicense[ this.getDefaultLicense() ] = true;
			this.licenseInput.setValues( defaultLicense );
		}
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.OwnWork.prototype.getSourceWikiText = function () {
		return mw.message( 'mediauploader-content-source-ownwork' ).plain();
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.OwnWork.prototype.getAuthorWikiText = function () {
		var author = this.authorInput.getValue(),
			userPageTitle;

		if ( author.indexOf( '[' ) >= 0 || author.indexOf( '{' ) >= 0 ) {
			return author;
		}

		// Construct a Title for the user page to get the localized NS prefix
		userPageTitle = new mw.Title( 'User:' + mw.config.get( 'wgUserName' ) );
		return '[[' + userPageTitle.getPrefixedText() + '|' + author + ']]';
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.OwnWork.prototype.getLicenseWikiText = function () {
		var wikitext = '';

		if ( this.showCustomDiv && this.licenseInput.getWikiText() !== '' ) {
			wikitext += this.licenseInput.getWikiText();
		} else {
			wikitext += '{{' +
				this.config.licensing.ownWork.template +
				'|' +
				this.getDefaultLicense() +
				'}}';
		}

		return wikitext;
	};

	/**
	 * @return {Object}
	 */
	uw.deed.OwnWork.prototype.getSerialized = function () {
		var serialized = $.extend( uw.deed.Abstract.prototype.getSerialized.call( this ), {
			author: this.authorInput.getValue()
		} );

		if ( this.showCustomDiv ) {
			serialized.license = this.licenseInput.getSerialized();
		}

		return serialized;
	};

	/**
	 * @param {Object} serialized
	 */
	uw.deed.OwnWork.prototype.setSerialized = function ( serialized ) {
		uw.deed.Abstract.prototype.setSerialized.call( this, serialized );

		if ( serialized.author ) {
			this.authorInput.setValue( serialized.author );
		}

		if ( this.showCustomDiv && serialized.license ) {
			// only need to set license if it's not the default license
			if ( !( this.getDefaultLicense() in serialized.license ) ) {
				// expand licenses container
				this.customLicense();
				this.licenseInput.setSerialized( serialized.license );
			}
		}
	};

	uw.deed.OwnWork.prototype.swapNodes = function ( a, b ) {
		var
			parentA = a.parentNode,
			parentB = b.parentNode,
			nextA = a.nextSibling,
			nextB = b.nextSibling;

		// This is not correct if a and b are siblings, or if one is a child of the
		// other, or if they're detached, or maybe in other cases, but we don't care
		parentA[ nextA ? 'insertBefore' : 'appendChild' ]( b, nextA );
		parentB[ nextB ? 'insertBefore' : 'appendChild' ]( a, nextB );
	};

	uw.deed.OwnWork.prototype.getDefaultLicense = function () {
		var license;
		if ( this.config.licensing.defaultType === 'ownwork' ) {
			license = this.config.licensing.ownWork.defaults;
			return license instanceof Array ? license[ 0 ] : license;
		} else {
			return this.config.licensing.ownWork.licenses[ 0 ];
		}
	};

	uw.deed.OwnWork.prototype.standardLicense = function () {
		var deed = this,
			$crossfader = this.$selector.find( '.mediauploader-crossfader' ),
			$standardDiv = this.$selector.find( '.mediauploader-standard' ),
			$toggler = this.$selector.find( '.mwe-more-options a' );

		this.setDefaultLicense();

		$crossfader.morphCrossfade( $standardDiv )
			.promise().done( function () {
				deed.swapNodes( deed.authorInput.$element[ 0 ], deed.fakeAuthorInput.$element[ 0 ] );
			} );

		// FIXME: Use CSS transition
		// eslint-disable-next-line no-jquery/no-slide, no-jquery/no-animate
		this.licenseInputField.$element
			.slideUp()
			.animate( { opacity: 0 }, { queue: false, easing: 'linear' } );

		$toggler.msg( 'mediauploader-license-show-all' );
	};

	uw.deed.OwnWork.prototype.customLicense = function () {
		var deed = this,
			$crossfader = this.$selector.find( '.mediauploader-crossfader' ),
			$customDiv = this.$selector.find( '.mediauploader-custom' ),
			$toggler = this.$selector.find( '.mwe-more-options a' );

		$crossfader.morphCrossfade( $customDiv )
			.promise().done( function () {
				deed.swapNodes( deed.authorInput.$element[ 0 ], deed.fakeAuthorInput.$element[ 0 ] );
			} );

		// FIXME: Use CSS transition
		// eslint-disable-next-line no-jquery/no-slide, no-jquery/no-animate
		this.licenseInputField.$element
			.slideDown()
			.css( { opacity: 0 } ).animate( { opacity: 1 }, { queue: false, easing: 'linear' } );

		$toggler.msg( 'mediauploader-license-show-recommended' );
	};

	/**
	 * @param {OO.ui.InputWidget} input
	 * @return {jQuery.Promise}
	 */
	uw.deed.OwnWork.prototype.getAuthorErrors = function ( input ) {
		var
			errors = [],
			minLength = this.config.minAuthorLength,
			maxLength = this.config.maxAuthorLength,
			text = input.getValue().trim();

		if ( text === '' ) {
			errors.push( mw.message( 'mediauploader-error-signature-blank' ) );
		} else if ( text.length < minLength ) {
			errors.push( mw.message( 'mediauploader-error-signature-too-short', minLength ) );
		} else if ( text.length > maxLength ) {
			errors.push( mw.message( 'mediauploader-error-signature-too-long', maxLength ) );
		}

		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @return {jQuery.Promise}
	 */
	uw.deed.OwnWork.prototype.getAuthorWarnings = function () {
		return $.Deferred().resolve( [] ).promise();
	};

}( mw.uploadWizard ) );
