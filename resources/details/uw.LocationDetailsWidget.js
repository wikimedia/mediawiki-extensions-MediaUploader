( function ( uw ) {

	/**
	 * A set of location fields in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {string[]} [fields] List of fields to show in the widget
	 */
	uw.LocationDetailsWidget = function UWLocationDetailsWidget( config ) {
		this.config = $.extend( {
			fields: [ 'latitude', 'longitude' ]
		}, config );

		uw.LocationDetailsWidget.parent.call( this, this.config );

		this.$element.addClass( 'mediauploader-locationDetailsWidget' );

		this.config.showField = {};
		this.inputs = {};
		this.allFields = [ 'latitude', 'longitude', 'altitude', 'heading' ];

		this.config.fields.forEach( function ( field ) {
			this.config.showField[ field ] = true;
		}, this );

		// Go over all available fields in order
		this.allFields.forEach( function ( field ) {
			if ( !this.config.showField[ field ] ) {
				return;
			}
			this.inputs[ field ] = new OO.ui.TextInputWidget( { disabled: this.config.disabled } );

			// Messages that can be used here:
			// * mediauploader-location-latitude
			// * mediauploader-location-longitude
			// * mediauploader-location-altitude
			// * mediauploader-location-heading
			this.$element.append(
				new OO.ui.FieldLayout( this.inputs[ field ], {
					align: 'top',
					label: mw.message( 'mediauploader-location-' + field ).text(),
					disabled: this.config.disabled
				} ).$element
			);

			// Aggregate 'change' events
			this.inputs[ field ].connect( this, { change: [ 'emit', 'change' ] } );
		}, this );

		this.$map = $( '<div>' ).css( { width: 500, height: 300 } );
		this.mapButton = new OO.ui.PopupButtonWidget( {
			icon: 'mapPin',
			title: mw.message( 'mediauploader-location-button' ).text(),
			popup: {
				$content: this.$map,
				width: 500,
				height: 300
			},
			disabled: this.config.disabled
		} );

		this.mapButton.setDisabled( true );
		this.$element.append( this.mapButton.$element );

		this.mapButton.connect( this, { click: 'onMapButtonClick' } );
		this.connect( this, { change: 'onChange' } );

		this.mapButton.toggle( false );
		mw.loader.using( [ 'ext.kartographer.box', 'ext.kartographer.editing' ] ).done( function () {
			// Kartographer is installed and we'll be able to show the map. Display the button.
			this.mapButton.toggle( true );
		}.bind( this ) );
	};

	OO.inheritClass( uw.LocationDetailsWidget, uw.DetailsWidget );

	/**
	 * @private
	 */
	uw.LocationDetailsWidget.prototype.onChange = function () {
		var widget = this;
		this.getErrors().done( function ( errors ) {
			widget.mapButton.setDisabled( !( errors.length === 0 && widget.getWikiText() !== '' ) );
		} );
	};

	/**
	 * @private
	 */
	uw.LocationDetailsWidget.prototype.onMapButtonClick = function () {
		var coords = this.getSerializedParsed();

		// Disable clipping because it doesn't play nicely with the map
		this.mapButton.getPopup().toggleClipping( false );

		if ( !this.map ) {
			this.map = require( 'ext.kartographer.box' ).map( {
				container: this.$map[ 0 ]
			} );
		}
		require( 'ext.kartographer.editing' ).getKartographerLayer( this.map ).setGeoJSON( {
			type: 'Feature',
			properties: {},
			geometry: { type: 'Point', coordinates: [ coords.longitude, coords.latitude ] }
		} );
		this.map.setView( [ coords.latitude, coords.longitude ], 9 );
	};

	/**
	 * @inheritdoc
	 */
	uw.LocationDetailsWidget.prototype.getErrors = function () {
		var errors = [],
			serialized = this.getSerialized(),
			parsed = this.getSerializedParsed(),
			field;

		// If the field is required and any of the subfields is empty
		// -> throw an error
		if ( this.config.required ) {
			for ( field in this.config.showField ) {
				if ( !serialized[ field ] ) {
					errors.push( mw.message( 'mediauploader-error-blank' ) );
					break;
				}
			}
		}

		// input is invalid if the coordinates are out of bounds, or if the
		// coordinates that were derived from the input are 0, without a 0 even
		// being present in the input
		if ( this.config.showField.latitude && serialized.latitude ) {
			if ( isNaN( parsed.latitude ) || parsed.latitude > 90 || parsed.latitude < -90 || ( parsed.latitude === 0 && serialized.latitude.indexOf( '0' ) < 0 ) ) {
				errors.push( mw.message( 'mediauploader-error-latitude' ) );
			}
		}

		if ( this.config.showField.longitude && serialized.longitude ) {
			if ( isNaN( parsed.longitude ) || parsed.longitude > 180 || parsed.longitude < -180 || ( parsed.longitude === 0 && serialized.longitude.indexOf( '0' ) < 0 ) ) {
				errors.push( mw.message( 'mediauploader-error-longitude' ) );
			}
		}

		if ( this.config.showField.heading && serialized.heading !== '' ) {
			if ( isNaN( parsed.heading ) || parsed.heading > 360 || parsed.heading < 0 ) {
				errors.push( mw.message( 'mediauploader-error-heading' ) );
			}
		}

		if ( this.config.showField.altitude && serialized.altitude !== '' && isNaN( parsed.altitude ) ) {
			errors.push( mw.message( 'mediauploader-error-altitude' ) );
		}

		return $.Deferred().resolve( errors );
	};

	/**
	 * @inheritdoc
	 */
	uw.LocationDetailsWidget.prototype.getWikiText = function () {
		var field,
			result = '',
			serialized = this.getSerializedParsed();

		if ( 'latitude' in this.config.showField &&
			( !isNaN( serialized.latitude ) || !isNaN( serialized.longitude ) )
		) {
			result = ( isNaN( serialized.latitude ) ? '?' : serialized.latitude ).toString() +
				'; ' + ( isNaN( serialized.longitude ) ? '?' : serialized.longitude ).toString();
		}

		for ( field in [ 'heading', 'altitude' ] ) {
			if ( field in this.config.showField && serialized[ field ] && !isNaN( serialized[ field ] )
			) {
				// Messages that can be used here:
				// * mediauploader-location-heading
				// * mediauploader-location-altitude
				result += ' ' + mw.msg( 'mediauploader-location-' + field ) + ': ' +
					serialized[ field ].toString();
			}
		}

		return result.trim();
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.LocationDetailsWidget.prototype.getSerialized = function () {
		var field,
			result = {};

		for ( field in this.config.showField ) {
			result[ field ] = this.inputs[ field ].getValue();
		}

		return result;
	};

	/**
	 * Returns a serialized representation of the subfields' values that were parsed to a number.
	 *
	 * @return {Object} Serialized, parsed values of the subfields (numbers)
	 */
	uw.LocationDetailsWidget.prototype.getSerializedParsed = function () {
		var field,
			result = {},
			serialized = this.getSerialized();

		for ( field in this.config.showField ) {
			if ( serialized[ field ] === '' || serialized[ field ] === undefined ) {
				result[ field ] = NaN;
			} else if ( field === 'latitude' || field === 'longitude' ) {
				result[ field ] = this.normalizeCoordinate( serialized[ field ] );
			} else {
				result[ field ] = parseFloat( serialized[ field ] );
			}
		}

		return result;
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {number} serialized.latitude Latitude value
	 * @param {number} serialized.longitude Longitude value
	 * @param {string} serialized.altitude Altitude value
	 * @param {string} serialized.heading Heading value
	 */
	uw.LocationDetailsWidget.prototype.setSerialized = function ( serialized ) {
		var field;

		for ( field in this.config.showField ) {
			if ( serialized[ field ] !== undefined ) {
				this.inputs[ field ].setValue( serialized[ field ] );
			}
		}
	};

	/**
	 * Interprets a wide variety of coordinate input formats, it'll return the
	 * coordinate in decimal degrees.
	 *
	 * Formats understood include:
	 * * degrees minutes seconds: 40° 26' 46" S
	 * * degrees decimal minutes: 40° 26.767' S
	 * * decimal degrees: 40.446° S
	 * * decimal degrees exact value: -40.446
	 *
	 * @param {string} coordinate
	 * @return {number}
	 */
	uw.LocationDetailsWidget.prototype.normalizeCoordinate = function ( coordinate ) {
		var sign = coordinate.match( /[sw]/i ) ? -1 : 1,
			parts, value;

		// fix commonly used character alternatives
		coordinate = coordinate.replace( /\s*[,.]\s*/, '.' );

		// convert degrees, minutes, seconds (or degrees & decimal minutes) to
		// decimal degrees
		// there can be a lot of variation in the notation, so let's only
		// focus on "groups of digits" (and not whether e.g. ″ or " is used)
		parts = coordinate.match( /(-?[0-9.]+)[^0-9.]+([0-9.]+)(?:[^0-9.]+([0-9.]+))?/ );
		if ( parts ) {
			value = this.dmsToDecimal( parts[ 1 ], parts[ 2 ], parts[ 3 ] || 0 );
		} else {
			value = coordinate.replace( /[^\-0-9.]/g, '' ) * 1;
		}

		// round to 6 decimal places
		return Math.round( sign * value * 1000000 ) / 1000000;
	};

	/**
	 * Convert degrees, minutes & seconds to decimal.
	 *
	 * @param {number} degrees
	 * @param {number} minutes
	 * @param {number} seconds
	 * @return {number}
	 */
	uw.LocationDetailsWidget.prototype.dmsToDecimal = function ( degrees, minutes, seconds ) {
		return ( degrees * 1 ) + ( minutes / 60.0 ) + ( seconds / 3600.0 );
	};

}( mw.uploadWizard ) );
