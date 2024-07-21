( function ( uw ) {

	/**
	 * A generic text input field.
	 *
	 * @extends uw.DetailsWidget
	 * @param {Object} config
	 * @param {string} [config.mode] Mode, either 'text' or 'textarea'
	 * @param {number} [config.minLength=0] Minimum input length
	 * @param {number} [config.maxLength=99999] Maximum input length
	 */
	uw.TextWidget = function UWTextWidget( config ) {
		this.config = Object.assign( {
			minLength: 0,
			maxLength: 99999,
			mode: 'text'
		}, config );

		uw.TextWidget.parent.call( this, this.config );

		if ( this.config.mode === 'text' ) {
			this.textInput = new OO.ui.TextInputWidget( {
				classes: [ 'mediauploader-other-text', 'mediauploader-textWidget-other' ],
				maxLength: this.config.maxLength,
				disabled: this.config.disabled
			} );
			this.$element.addClass( 'mediauploader-textWidget' );
		} else {
			this.textInput = new OO.ui.MultilineTextInputWidget( {
				classes: [ 'mediauploader-other-textarea', 'mediauploader-textAreaWidget-other' ],
				autosize: true,
				rows: 2,
				disabled: this.config.disabled
			} );
			this.$element.addClass( 'mediauploader-textAreaWidget' );
		}

		// Aggregate 'change' event
		// (but do not flash warnings in the user's face while they're typing)
		this.textInput.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

		this.$element.append(
			this.textInput.$element
		);
	};
	OO.inheritClass( uw.TextWidget, uw.DetailsWidget );

	/**
	 * @inheritdoc
	 */
	uw.TextWidget.prototype.getWarnings = function () {
		var warnings = [];
		this.getEmptyWarning( this.textInput.getValue().trim() === '', warnings );

		return $.Deferred().resolve( warnings ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.TextWidget.prototype.getErrors = function () {
		var
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
	 * @inheritdoc
	 */
	uw.TextWidget.prototype.getWikiText = function () {
		return this.textInput.getValue().trim();
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.TextWidget.prototype.getSerialized = function () {
		return this.textInput.getValue();
	};

	/**
	 * @inheritdoc
	 * @param {string} serialized
	 */
	uw.TextWidget.prototype.setSerialized = function ( serialized ) {
		this.textInput.setValue( serialized );
	};

	/**
	 * Returns the value of the field which can be used as a caption.
	 *
	 * @return {string}
	 */
	uw.TextWidget.prototype.getCaption = function () {
		return this.getWikiText();
	};

}( mw.uploadWizard ) );
