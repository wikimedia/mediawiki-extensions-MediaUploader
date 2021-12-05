( function ( uw ) {

	/**
	 * A date field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} config Configuration options
	 * @cfg {mw.UploadWizardUpload} upload
	 */
	uw.DateDetailsWidget = function UWDateDetailsWidget( config ) {
		uw.DateDetailsWidget.parent.call( this, config );

		this.config = config;
		this.upload = this.config.upload;
		this.dateInputWidgetMode = null; // or: 'calendar', 'arbitrary'
		this.dateInputWidgetToggler = new OO.ui.ButtonSelectWidget( {
			classes: [ 'mediauploader-dateDetailsWidget-toggler' ],
			items: [
				new OO.ui.ButtonOptionWidget( {
					data: 'calendar',
					icon: 'calendar',
					title: mw.msg( 'mediauploader-calendar-date' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'arbitrary',
					icon: 'edit',
					title: mw.msg( 'mediauploader-custom-date' )
				} )
			],
			disabled: this.config.disabled
		} )
			.selectItemByData( 'calendar' )
			.on( 'choose', function ( selectedItem ) {
				this.setupDateInput( selectedItem.getData() );
				this.dateInputWidget.focus();
			}.bind( this ) );

		this.$element.addClass( 'mediauploader-dateDetailsWidget' );
		this.$element.append(
			this.dateInputWidgetToggler.$element
			// this.dateInputWidget.$element goes here after setupDateInput() runs
		);
		this.setupDateInput();
	};
	OO.inheritClass( uw.DateDetailsWidget, uw.DetailsWidget );

	/**
	 * Set up the date input field, or switch between 'calendar' and 'arbitrary' mode.
	 *
	 * @param {string} [mode] Mode to switch to, 'calendar' or 'arbitrary'
	 * @private
	 */
	uw.DateDetailsWidget.prototype.setupDateInput = function ( mode ) {
		var
			oldDateInputWidget = this.dateInputWidget;

		if ( mode === undefined ) {
			mode = this.dateInputWidgetMode === 'calendar' ? 'arbitrary' : 'calendar';
		}
		this.dateInputWidgetMode = mode;
		this.dateInputWidgetToggler.selectItemByData( mode );

		if ( mode === 'arbitrary' ) {
			this.dateInputWidget = new OO.ui.TextInputWidget( {
				classes: [ 'mwe-date', 'mediauploader-dateDetailsWidget-date' ],
				placeholder: mw.msg( 'mediauploader-select-date' ),
				disabled: this.config.disabled
			} );
		} else {
			this.dateInputWidget = new mw.widgets.DateInputWidget( {
				classes: [ 'mwe-date', 'mediauploader-dateDetailsWidget-date' ],
				placeholderLabel: mw.msg( 'mediauploader-select-date' ),
				disabled: this.config.disabled
			} );
			// If the user types '{{', assume that they are trying to input template wikitext and switch
			// to 'arbitrary' mode. This might help confused power-users (T110026#1567714).
			this.dateInputWidget.textInput.on( 'change', function ( value ) {
				if ( value === '{{' ) {
					this.setupDateInput( 'arbitrary' );
					this.dateInputWidget.setValue( '{{' );
					this.dateInputWidget.moveCursorToEnd();
				}
			}.bind( this ) );
		}

		if ( oldDateInputWidget ) {
			this.dateInputWidget.setValue( oldDateInputWidget.getValue() );
			oldDateInputWidget.$element.replaceWith( this.dateInputWidget.$element );
		} else {
			this.dateInputWidgetToggler.$element.after( this.dateInputWidget.$element );
		}

		// Aggregate 'change' event
		this.dateInputWidget.connect( this, { change: [ 'emit', 'change' ] } );

		// Also emit if the value was changed to fit the new widget
		if ( oldDateInputWidget && oldDateInputWidget.getValue() !== this.dateInputWidget.getValue() ) {
			this.emit( 'change' );
		}
	};

	/**
	 * Gets the selected license(s). The returned value will be a license
	 * key => license props map, as defined in MediaUploader.config.php.
	 *
	 * @return {Object}
	 */
	uw.DateDetailsWidget.prototype.getLicenses = function () {
		if ( this.upload.deedChooser && this.upload.deedChooser.deed && this.upload.deedChooser.deed.licenseInput ) {
			return this.upload.deedChooser.deed.licenseInput.getLicenses();
		}

		// no license has been selected yet
		// this could happen when uploading multiple files and selecting to
		// provide copyright information for each file individually
		return {};
	};

	/**
	 * @inheritdoc
	 */
	uw.DateDetailsWidget.prototype.getWarnings = function () {
		var warnings = [],
			dateVal = Date.parse( this.dateInputWidget.getValue().trim() ),
			now = new Date();

		this.getEmptyWarning( this.dateInputWidget.getValue().trim() === '', warnings );

		// We don't really know what timezone this datetime is in. It could be the user's timezone, or
		// it could be the camera's timezone for data imported from EXIF, and we don't know what
		// timezone that is. UTC+14 is the highest timezone that currently exists, so assume that to
		// avoid giving false warnings.
		if ( this.dateInputWidgetMode === 'calendar' &&
			dateVal > now.getTime() + 14 * 60 * 60 ) {
			warnings.push( mw.message( 'mediauploader-warning-postdate' ) );
		}

		return $.Deferred().resolve( warnings ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.DateDetailsWidget.prototype.getErrors = function () {
		var errors = [];

		if ( this.config.required && this.dateInputWidget.getValue().trim() === '' ) {
			errors.push( mw.message( 'mediauploader-error-blank' ) );
		}

		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.DateDetailsWidget.prototype.getWikiText = function () {
		return this.dateInputWidget.getValue().trim();
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.DateDetailsWidget.prototype.getSerialized = function () {
		return {
			mode: this.dateInputWidgetMode,
			value: this.dateInputWidget.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object|string} serialized
	 * @param {string} serialized.mode Date input mode ('calendar' or 'arbitrary')
	 * @param {string} serialized.value Date value for given mode
	 */
	uw.DateDetailsWidget.prototype.setSerialized = function ( serialized ) {
		if ( typeof serialized === 'string' ) {
			this.setSerialized( {
				mode: 'arbitrary',
				value: serialized
			} );
			return;
		}

		this.setupDateInput( serialized.mode );
		this.dateInputWidget.setValue( serialized.value );
	};

}( mw.uploadWizard ) );
