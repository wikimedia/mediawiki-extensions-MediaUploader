( function ( uw ) {

	/**
	 * A field with a dropdown.
	 *
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {boolean} [required=false] Whether to mark this field as required
	 * @cfg {Object} [options] Map of select dropdown options
	 */
	uw.DropdownWidget = function MUDropdownWidget( config ) {
		config = $.extend( { type: 'text' }, config );
		uw.DropdownWidget.parent.call( this, config );

		this.required = !!config.required;
		this.wikitext = config.wikitext;
		this.input = new OO.ui.DropdownInputWidget( {
			classes: [ 'mwe-idfield', 'mediauploader-dropdownWidget-input' ],
			options: Object.keys( config.options ).map( function ( key ) {
				return { data: key, label: config.options[ key ] };
			} )
		} );

		// Aggregate 'change' event
		// (but do not flash warnings in the user's face while they're typing)
		this.input.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

		this.$element.addClass( 'mwe-id-field mediauploader-dropdownWidget' );
		this.$element.append(
			this.input.$element
		);
	};
	OO.inheritClass( uw.DropdownWidget, uw.DetailsWidget );

	/**
	 * @inheritdoc
	 */
	uw.DropdownWidget.prototype.getErrors = function () {
		var errors = [];
		if ( this.required && this.input.getValue().trim() === '' ) {
			errors.push( mw.message( 'mediauploader-error-blank' ) );
		}
		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.DropdownWidget.prototype.getWarnings = function () {
		var warnings = [];
		this.getEmptyWarning( this.input.getValue().trim() === '', warnings );

		return $.Deferred().resolve( warnings ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.DropdownWidget.prototype.getWikiText = function () {
		return this.input.getValue().trim();
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.DropdownWidget.prototype.getSerialized = function () {
		return {
			value: this.input.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {string} serialized.value Campaign informations text
	 */
	uw.DropdownWidget.prototype.setSerialized = function ( serialized ) {
		this.input.setValue( serialized.value );
	};

}( mw.uploadWizard ) );
