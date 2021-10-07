( function ( uw ) {

	/**
	 * A deed chooser field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 */
	uw.DeedChooserDetailsWidget = function UWDeedChooserDetailsWidget() {
		uw.DeedChooserDetailsWidget.parent.call( this );

		this.deedChooser = false;
		this.$element.addClass( 'mediauploader-deedChooserDetailsWidget' );
	};
	OO.inheritClass( uw.DeedChooserDetailsWidget, uw.DetailsWidget );

	uw.DeedChooserDetailsWidget.prototype.unload = function () {
		if ( this.deedChooser.deed ) {
			this.deedChooser.deed.unload();
		}
	};

	/**
	 * Toggles whether we use the 'macro' deed or our own
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.DeedChooserDetailsWidget.prototype.useCustomDeedChooser = function ( upload ) {
		var $deedDiv = $( '<div>' ).addClass( 'mediauploader-custom-deed' );

		this.$element.append( $deedDiv );
		this.deedChooser = upload.deedChooser = new mw.UploadWizardDeedChooser(
			mw.UploadWizard.config,
			$deedDiv,
			mw.UploadWizard.getLicensingDeeds( [ upload ], mw.UploadWizard.config ),
			[ upload ] );
		this.deedChooser.onLayoutReady();
	};

	/**
	 * @inheritdoc
	 */
	uw.DeedChooserDetailsWidget.prototype.getErrors = function () {
		var errors = [];
		if ( this.deedChooser ) {
			if ( !this.deedChooser.deed ) {
				errors.push( mw.message( 'mediauploader-deeds-need-deed' ) );
			}
		}
		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @return {Object}
	 */
	uw.DeedChooserDetailsWidget.prototype.getSerialized = function () {
		if ( this.deedChooser ) {
			return this.deedChooser.getSerialized();
		}

		return {};
	};

	/**
	 * @param {Object} serialized
	 */
	uw.DeedChooserDetailsWidget.prototype.setSerialized = function ( serialized ) {
		if ( this.deedChooser ) {
			this.deedChooser.setSerialized( serialized );
		}
	};

}( mw.uploadWizard ) );
