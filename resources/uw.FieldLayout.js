( function ( uw ) {

	/**
	 * FieldLayout with some UploadWizard-specific bonuses.
	 *
	 * @extends OO.ui.FieldLayout
	 *
	 * @constructor
	 * @inheritdoc
	 * @param {OO.ui.Widget} fieldWidget
	 * @param {Object} [config]
	 * @param {boolean} [config.required=false] Whether to mark this field as required
	 * @param {boolean} [config.align='top']
	 */
	uw.FieldLayout = function UWFieldLayout( fieldWidget, config ) {
		// FieldLayout will add an icon which, when clicked, reveals more information
		// about the input. We'll want to display that by default, so we're getting
		// rid of the "help" property here & will later append that after the header
		var help = config && config.help ? config.help : '';
		config = Object.assign( { align: 'top', required: false }, config, { help: '' } );

		uw.FieldLayout.parent.call( this, fieldWidget, config );
		uw.ValidationMessageElement.call( this, { validatedWidget: fieldWidget } );

		this.required = null;
		this.optionalMarker = new OO.ui.LabelWidget( {
			classes: [ 'mediauploader-fieldLayout-indicator' ],
			label: mw.msg( 'mediauploader-label-optional' )
		} );

		this.$element.addClass( 'mediauploader-fieldLayout' );

		this.$element.addClass( 'mediauploader-details-fieldname-input' );
		this.$label.addClass( 'mediauploader-details-fieldname' );
		this.$field.addClass( 'mediauploader-details-input' );

		if ( help ) {
			this.help = new OO.ui.LabelWidget( { label: help } );
			this.$header.after( this.help.$element.addClass( 'mediauploader-details-help' ) );
		}

		this.setRequired( config.required );
	};
	OO.inheritClass( uw.FieldLayout, OO.ui.FieldLayout );
	OO.mixinClass( uw.FieldLayout, uw.ValidationMessageElement );

	/**
	 * @param {boolean} required Whether to mark this field as required
	 */
	uw.FieldLayout.prototype.setRequired = function ( required ) {
		this.required = !!required;
		// only add 'optional' marker after the label if that label
		// has content...
		if ( !this.required && this.$label.text() !== '' ) {
			this.$header.after( this.optionalMarker.$element );
		} else {
			this.optionalMarker.$element.remove();
		}
	};

}( mw.uploadWizard ) );
