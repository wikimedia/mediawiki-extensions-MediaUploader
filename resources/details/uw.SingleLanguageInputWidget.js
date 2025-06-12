( function ( uw ) {

	/**
	 * A single language input field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} config
	 * @param {Object} config.languages { langcode: text } map of languages
	 * @param {Object} [config.defaultLanguage]
	 * @param {Object} [config.required]
	 * @param {boolean} [config.canBeRemoved=true]
	 * @param {number} [config.minLength=0] Minimum input length
	 * @param {number} [config.maxLength=99999] Maximum input length
	 */
	uw.SingleLanguageInputWidget = function UWSingleLanguageInputWidget( config ) {
		this.config = Object.assign( {
			minLength: 0,
			maxLength: 99999
		}, config );

		uw.SingleLanguageInputWidget.parent.call( this, this.config );
		uw.ValidationMessageElement.call( this );

		if ( mw.loader.getState( 'ext.uls.mediawiki' ) === 'ready' ) {
			this.languageSelector = new uw.UlsWidget( {
				languages: config.languages,
				classes: [ 'mediauploader-singleLanguageInputWidget-language' ],
				disabled: this.config.disabled
			} );
		} else {
			this.languageSelector = new uw.LanguageDropdownWidget( {
				languages: config.languages,
				classes: [ 'mediauploader-singleLanguageInputWidget-language' ],
				disabled: this.config.disabled
			} );
		}
		this.languageSelector.setValue( config.defaultLanguage || this.getDefaultLanguage() );

		this.textInput = new OO.ui.MultilineTextInputWidget( {
			classes: [ 'mediauploader-singleLanguageInputWidget-text' ],
			autosize: true,
			rows: 2,
			disabled: this.config.disabled
		} );
		this.removeButton = new OO.ui.ButtonWidget( {
			classes: [ 'mediauploader-singleLanguageInputWidget-removeItem' ],
			icon: 'trash',
			framed: false,
			flags: [ 'destructive' ],
			title: mw.message( 'mediauploader-multilang-remove' ).text(),
			disabled: this.config.disabled
		} );

		this.removeButton.connect( this, {
			click: 'onRemoveClick'
		} );

		this.languageSelector.connect( this, { select: [ 'emit', 'select' ] } );
		// Aggregate 'change' event
		// (but do not flash warnings in the user's face while they're typing)
		this.textInput.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

		this.$element.addClass( 'mediauploader-singleLanguageInputWidget' );
		this.$element.append( this.languageSelector.getElement() );
		// HACK: ValidationMessageElement will append messages after this.$body
		this.$body = this.textInput.$element;
		if ( this.config.canBeRemoved !== false ) {
			this.$element.append( this.removeButton.$element );
			this.$body = this.removeButton.$element; // HACK
		}
		this.$element.append( this.textInput.$element );

	};
	OO.inheritClass( uw.SingleLanguageInputWidget, uw.DetailsWidget );
	OO.mixinClass( uw.SingleLanguageInputWidget, uw.ValidationMessageElement );

	/**
	 * Handle remove button click events.
	 *
	 * @private
	 */
	uw.SingleLanguageInputWidget.prototype.onRemoveClick = function () {
		const element = this.getElementGroup();

		if ( element && typeof element.removeItems === 'function' ) {
			element.removeItems( [ this ] );
		}
	};

	/**
	 * Check if the given language code can be used for inputs.
	 * If not, try finding a similar language code that can be.
	 *
	 * @public
	 * @param {string} code Language code
	 * @param {string} [fallback] Language code to use when there's nothing close,
	 *   defaults to result of #getDefaultLanguage
	 * @return {string|null}
	 */
	uw.SingleLanguageInputWidget.prototype.getClosestAllowedLanguage = function ( code, fallback ) {
		// Is this still needed?
		if ( code === 'nan' || code === 'minnan' ) {
			code = 'zh-min-nan';
		}
		if ( this.config.languages[ code ] ) {
			return code;
		}
		if ( code.lastIndexOf( '-' ) !== -1 ) {
			return this.getClosestAllowedLanguage( code.slice( 0, Math.max( 0, code.lastIndexOf( '-' ) ) ) );
		}
		return arguments.length > 1 ? fallback : this.getDefaultLanguage();
	};

	/**
	 * Get the default language to use for inputs.
	 * Choose a sane default based on user preferences and wiki config.
	 *
	 * @public
	 * @return {string}
	 */
	uw.SingleLanguageInputWidget.prototype.getDefaultLanguage = function () {
		let defaultLanguage;

		if ( this.defaultLanguage !== undefined ) {
			return this.defaultLanguage;
		}

		if ( this.getClosestAllowedLanguage( mw.config.get( 'wgUserLanguage' ), null ) ) {
			defaultLanguage = this.getClosestAllowedLanguage( mw.config.get( 'wgUserLanguage' ) );
		} else if ( this.getClosestAllowedLanguage( mw.config.get( 'wgContentLanguage' ), null ) ) {
			defaultLanguage = this.getClosestAllowedLanguage( mw.config.get( 'wgContentLanguage' ) );
		} else if ( this.getClosestAllowedLanguage( 'en', null ) ) {
			defaultLanguage = this.getClosestAllowedLanguage( 'en' );
		} else {
			defaultLanguage = Object.keys( this.config.languages )[ 0 ];
		}

		// Logic copied from MediaWiki:UploadForm.js
		// Per request from Portuguese and Brazilian users, treat Brazilian Portuguese as Portuguese.
		if ( defaultLanguage === 'pt-br' ) {
			defaultLanguage = 'pt';
		// this was also in UploadForm.js, but without the heartwarming justification
		} else if ( defaultLanguage === 'en-gb' ) {
			defaultLanguage = 'en';
		}

		this.defaultLanguage = defaultLanguage;
		return defaultLanguage;
	};

	/**
	 * @inheritdoc
	 */
	uw.SingleLanguageInputWidget.prototype.getWarnings = function () {
		const warnings = [];
		this.getEmptyWarning( this.textInput.getValue().trim() === '', warnings );

		return $.Deferred().resolve( warnings ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.SingleLanguageInputWidget.prototype.getErrors = function () {
		const
			errors = [],
			text = this.textInput.getValue().trim();

		if ( this.config.required && text.length === 0 ) {
			errors.push( mw.message( 'mediauploader-error-blank' ) );
		}

		if ( text.length !== 0 && text.length < this.config.minLength ) {
			// Empty input is allowed
			errors.push( mw.message( 'mediauploader-error-too-short', this.config.minLength ) );
		}
		if ( text.length > this.config.maxLength ) {
			errors.push( mw.message( 'mediauploader-error-too-long', this.config.maxLength ) );
		}

		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @param {Object} languages
	 */
	uw.SingleLanguageInputWidget.prototype.updateLanguages = function ( languages ) {
		this.languageSelector.updateLanguages( languages );
	};

	/**
	 * @return {string} language code
	 */
	uw.SingleLanguageInputWidget.prototype.getLanguage = function () {
		return this.languageSelector.getValue();
	};

	/**
	 * @param {string} value language code
	 */
	uw.SingleLanguageInputWidget.prototype.setLanguage = function ( value ) {
		this.languageSelector.setValue( value );
	};

	/**
	 * @return {string} text input
	 */
	uw.SingleLanguageInputWidget.prototype.getText = function () {
		return this.textInput.getValue().trim();
	};

	/**
	 * @param {string} value text input
	 */
	uw.SingleLanguageInputWidget.prototype.setText = function ( value ) {
		this.textInput.setValue( value );
	};

	/**
	 * @inheritdoc
	 */
	uw.SingleLanguageInputWidget.prototype.getWikiText = function () {
		let
			language = this.getLanguage(),
			text = this.getText();

		if ( !text ) {
			return '';
		}

		if ( mw.UploadWizard.config.languageTemplateFixups[ language ] ) {
			language = mw.UploadWizard.config.languageTemplateFixups[ language ];
		}

		return '{{' + language + '|1=' + mw.Escaper.escapeForTemplate( text ) + '}}';
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.SingleLanguageInputWidget.prototype.getSerialized = function () {
		return {
			language: this.languageSelector.getValue(),
			text: this.textInput.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object|string} serialized
	 * @param {string} [serialized.language] Language code
	 * @param {string} serialized.text Text
	 */
	uw.SingleLanguageInputWidget.prototype.setSerialized = function ( serialized ) {
		if ( typeof serialized === 'string' ) {
			this.setSerialized( { text: serialized } );
			return;
		}
		this.setLanguage( serialized.language );
		this.setText( serialized.text );
	};

	/**
	 * Returns the value of the field which can be used as a caption.
	 *
	 * @return {string}
	 */
	uw.SingleLanguageInputWidget.prototype.getCaption = function () {
		return this.textInput.getValue().trim();
	};

}( mw.uploadWizard ) );
