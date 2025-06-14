( function ( uw ) {

	/**
	 * A multi-language input field in UploadWizard's "Details" step form.
	 *
	 * @class uw.MultipleLanguageInputWidget
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} [config]
	 * @cfg {boolean} [required=false]
	 * @cfg {number} [minLength=0] Minimum input length
	 * @cfg {number} [maxLength=99999] Maximum input length
	 * @cfg {Object} [languages] { langcode: text } map of languages
	 */
	uw.MultipleLanguageInputWidget = function UWMultipleLanguageInputWidget( config ) {
		this.config = Object.assign( {}, config );
		uw.MultipleLanguageInputWidget.parent.call( this, this.config );
		OO.ui.mixin.GroupElement.call( this );

		this.required = !!this.config.required;
		this.addButton = new OO.ui.ButtonWidget( {
			classes: [ 'mediauploader-multipleLanguageInputWidget-addItem' ],
			framed: true,
			icon: 'add',
			flags: [ 'progressive' ],
			label: this.getLabelText()
		} );
		this.addButton.connect( this, { click: [ 'addLanguageInput', this.config ] } );

		// if a language becomes available because the input gets removed,
		// or unavailable because it gets added, we'll need to update other
		// language dropdowns to reflect the change
		this.connect( this, { add: 'onChangeLanguages' } );
		this.connect( this, { remove: 'onChangeLanguages' } );

		// update the 'add language' button accordingly
		this.connect( this, { add: 'recount' } );
		this.connect( this, { remove: 'recount' } );

		// Aggregate 'change' event
		this.aggregate( { change: 'change' } );

		this.$element.addClass( 'mediauploader-multipleLanguageInputsWidget' );
		this.$element.append(
			this.$group,
			this.addButton.$element
		);

		// Add empty input (non-removable if this field is required)
		this.addLanguageInput( Object.assign( {}, this.config, { canBeRemoved: !this.required } ) );
	};
	OO.inheritClass( uw.MultipleLanguageInputWidget, uw.DetailsWidget );
	OO.mixinClass( uw.MultipleLanguageInputWidget, OO.ui.mixin.GroupElement );

	/**
	 * @param {Object} config
	 * @param {string} [text]
	 */
	uw.MultipleLanguageInputWidget.prototype.addLanguageInput = function ( config, text ) {
		let allLanguages = this.config.languages,
			unusedLanguages = this.getUnusedLanguages(),
			languages = {},
			item;

		if ( unusedLanguages.length === 0 ) {
			return;
		}

		// only add given language + unused/remaining languages - we don't want
		// languages that have already been selected to show up in the next dropdown...
		if ( config.defaultLanguage ) {
			languages[ config.defaultLanguage ] = allLanguages[ config.defaultLanguage ];
			languages = Object.assign( {}, languages, unusedLanguages );
		} else {
			languages = unusedLanguages;
		}

		config = Object.assign( {}, config, {
			languages: languages,
			required: false
		} );
		item = new uw.SingleLanguageInputWidget( config );
		item.setText( text || '' );

		// if a language is changed, we'll need to update other language dropdowns
		// to reflect the change
		item.connect( this, { select: 'onChangeLanguages' } );

		this.addItems( [ item ] );
	};

	/**
	 * When a language changes (or an input is removed), the old language
	 * becomes available again in other language dropdowns, and the new
	 * language should no longer be selected.
	 * This will iterate all inputs, destroy then, and construct new ones
	 * with the updated language selections.
	 */
	uw.MultipleLanguageInputWidget.prototype.onChangeLanguages = function () {
		let allLanguages = this.config.languages,
			unusedLanguages = this.getUnusedLanguages(),
			items = this.getItems(),
			languages,
			item,
			i;

		for ( i = 0; i < items.length; i++ ) {
			item = items[ i ];

			// only add existing language + unused/remaining languages - we don't want
			// languages that have already been selected to show up in the next dropdown...
			languages = {};
			languages[ item.getLanguage() ] = allLanguages[ item.getLanguage() ];
			languages = Object.assign( {}, languages, unusedLanguages );
			item.updateLanguages( languages );
		}
	};

	/**
	 * Returns an object of `langcode: text` pairs with the languages
	 * already used in dropdowns.
	 *
	 * @return {Object}
	 */
	uw.MultipleLanguageInputWidget.prototype.getUsedLanguages = function () {
		const allLanguages = this.config.languages,
			items = this.getItems();

		return items.reduce( ( obj, item ) => {
			const languageCode = item.getLanguage();
			obj[ languageCode ] = allLanguages[ languageCode ];
			return obj;
		}, {} );
	};

	/**
	 * Returns an object of `langcode: text` pairs with remaining languages
	 * not yet used in dropdowns.
	 *
	 * @return {Object}
	 */
	uw.MultipleLanguageInputWidget.prototype.getUnusedLanguages = function () {
		const allLanguages = this.config.languages,
			usedLanguageCodes = Object.keys( this.getUsedLanguages() );

		return Object.keys( allLanguages ).reduce( ( remaining, language ) => {
			if ( !usedLanguageCodes.includes( language ) ) {
				remaining[ language ] = allLanguages[ language ];
			}
			return remaining;
		}, {} );
	};

	/**
	 * Update the button label after adding or removing inputs.
	 */
	uw.MultipleLanguageInputWidget.prototype.recount = function () {
		const text = this.getLabelText(),
			unusedLanguages = this.getUnusedLanguages();

		this.addButton.setLabel( text );
		// hide the button if there are no remaining languages...
		this.addButton.toggle( Object.keys( unusedLanguages ).length > 0 );
	};

	/**
	 * @return {string}
	 */
	uw.MultipleLanguageInputWidget.prototype.getLabelText = function () {
		return mw.message( 'mediauploader-multilang-add' ).params( [ this.items.length ] ).text();
	};

	/**
	 * @inheritdoc
	 */
	uw.MultipleLanguageInputWidget.prototype.getWarnings = function () {
		const warnings = [];
		this.getEmptyWarning( this.getWikiText() === '', warnings );

		return $.Deferred().resolve( warnings ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.MultipleLanguageInputWidget.prototype.getErrors = function () {
		// Gather errors from each item
		const errorPromises = this.getItems().map( ( item ) => item.getErrors() );

		return $.when.apply( $, errorPromises ).then( function () {
			let i, errors;
			errors = [];
			// Fold all errors into a single one (they are displayed in the UI for each item, but we still
			// need to return an error here to prevent form submission).
			for ( i = 0; i < arguments.length; i++ ) {
				if ( arguments[ i ].length ) {
					// One of the items has errors
					errors.push( mw.message( 'mediauploader-error-bad-multilang' ) );
					break;
				}
			}
			// And add some more:
			if ( this.required && this.getWikiText() === '' ) {
				errors.push( mw.message( 'mediauploader-error-blank' ) );
			}
			// TODO Check for duplicate languages
			return errors;
		}.bind( this ) );
	};

	/**
	 * @return {Object} Object where the properties are language codes & values are input
	 */
	uw.MultipleLanguageInputWidget.prototype.getValues = function () {
		let values = {},
			widgets = this.getItems(),
			language,
			text,
			i;

		for ( i = 0; i < widgets.length; i++ ) {
			language = widgets[ i ].getLanguage();
			text = widgets[ i ].getText();

			if ( text !== '' ) {
				values[ language ] = text;
			}
		}

		return values;
	};

	/**
	 * @inheritdoc
	 */
	uw.MultipleLanguageInputWidget.prototype.getWikiText = function () {
		// Some code here and in mw.UploadWizardDetails relies on this function returning an empty
		// string when there are some inputs, but all are empty.
		return this.getItems().map( ( widget ) => widget.getWikiText() ).filter( ( wikiText ) => !!wikiText ).join( '\n' );
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.MultipleLanguageInputWidget.prototype.getSerialized = function () {
		const inputs = this.getItems().map( ( widget ) => widget.getSerialized() );
		return {
			inputs: inputs
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object|string} serialized
	 * @param {Object[]} serialized.inputs Array of serialized inputs,
	 *   see uw.SingleLanguageInputWidget#setSerialized
	 */
	uw.MultipleLanguageInputWidget.prototype.setSerialized = function ( serialized ) {
		let config = this.config,
			i;

		if ( typeof serialized === 'string' ) {
			this.setSerialized( { inputs: [ { text: serialized } ] } );
			return;
		}

		// remove all existing
		this.removeItems( this.getItems() );

		for ( i = 0; i < serialized.inputs.length; i++ ) {
			config = Object.assign( {}, config, { defaultLanguage: serialized.inputs[ i ].language } );
			this.addLanguageInput( config, serialized.inputs[ i ].text );
		}
	};

	/**
	 * Returns the value of the field which can be used as a caption.
	 *
	 * @return {string}
	 */
	uw.MultipleLanguageInputWidget.prototype.getCaption = function () {
		const items = this.getItems();

		if ( items.length > 0 ) {
			return items[ 0 ].getCaption();
		}

		return '';
	};

}( mw.uploadWizard ) );
