( function ( uw ) {

	var NS_FILE = mw.config.get( 'wgNamespaceIds' ).file;

	/**
	 * Object that represents the Details (step 2) portion of the UploadWizard
	 * n.b. each upload gets its own details.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 * @param {jQuery} $containerDiv The `div` to put the interface into
	 */
	mw.UploadWizardDetails = function ( upload, $containerDiv ) {
		this.upload = upload;
		this.$containerDiv = $containerDiv;
		this.api = upload.api;

		this.fieldList = [];
		this.fieldMap = {};
		this.fieldWrapperList = [];
		this.fieldWrapperMap = {};

		// This widget has to be initialized early for
		// useCustomDeedChooser() to work.
		this.deedChooserDetails = new uw.DeedChooserDetailsWidget();
		this.customDeedChooser = false;

		this.$div = $( '<div>' ).addClass( 'mediauploader-info-file ui-helper-clearfix filled' );
	};

	mw.UploadWizardDetails.prototype = {
		// Has this details object been attached to the DOM already?
		isAttached: false,

		/**
		 * Build the interface and attach all elements - do this on demand.
		 */
		buildInterface: function () {
			var $moreDetailsWrapperDiv, $moreDetailsDiv,
				fKey, fSpec, fieldWidget, fieldWrapper, fConfigBase,
				details = this,
				config = mw.UploadWizard.config;

			this.$thumbnailDiv = $( '<div>' ).addClass( 'mediauploader-thumbnail mediauploader-thumbnail-side' );

			this.$dataDiv = $( '<div>' ).addClass( 'mediauploader-data' );

			for ( fKey in config.fields ) {
				// Make a deep copy
				fSpec = $.extend( {}, config.fields[ fKey ] );
				fSpec.key = fKey;
				fSpec.enabled = fSpec.enabled === undefined ? true : fSpec.enabled;
				// Override the label in case it wasn't set
				fSpec.label = fSpec.label ? $( $.parseHTML( fSpec.label ) ) : fSpec.key;

				// Common settings for all fields
				fConfigBase = {
					required: fSpec.required === 'required',
					recommended: fSpec.required === 'recommended',
					fieldName: fSpec.label,
					disabled: !fSpec.enabled
				};

				fieldWidget = null;
				switch ( fSpec.type ) {
					case 'title':
						fieldWidget = new uw.TitleDetailsWidget( $.extend( {}, fConfigBase, {
							// Normalize file extension, e.g. 'JPEG' to 'jpg'
							extension: mw.UploadWizard.config.content.titleField === fKey ?
								mw.Title.normalizeExtension( this.upload.title.getExtension() ) : '',
							minLength: fSpec.minLength || 5,
							maxLength: fSpec.maxLength || 240
						} ) );
						break;
					case 'text':
					case 'textarea':
						fieldWidget = new uw.TextWidget( $.extend( {}, fConfigBase, {
							disabled: !fSpec.enabled,
							minLength: fSpec.minLength,
							maxLength: fSpec.maxLength
						} ) );
						break;
					case 'singlelang':
						fieldWidget = new uw.SingleLanguageInputWidget( $.extend( {}, fConfigBase, {
							canBeRemoved: false,
							languages: this.getLanguageOptions(),
							minLength: fSpec.minLength,
							maxLength: fSpec.maxLength
						} ) );
						break;
					case 'multilang':
						fieldWidget = new uw.MultipleLanguageInputWidget( $.extend( {}, fConfigBase, {
							languages: this.getLanguageOptions(),
							minLength: fSpec.minLength,
							maxLength: fSpec.maxLength
						} ) );
						break;
					case 'select':
						fieldWidget = new uw.DropdownWidget( $.extend( {}, fConfigBase, {
							options: fSpec.options
						} ) );
						break;
					case 'license':
						fieldWidget = this.deedChooserDetails;
						break;
					case 'date':
						fieldWidget = new uw.DateDetailsWidget( $.extend( {}, fConfigBase, {
							upload: this.upload
						} ) );
						break;
					case 'location':
						fieldWidget = new uw.LocationDetailsWidget( $.extend( {}, fConfigBase, {
							fields: fSpec.fields
						} ) );
						break;
					case 'categories':
						fieldWidget = new uw.CategoriesDetailsWidget(
							$.extend( {}, fConfigBase, {} )
						);
						break;
					default:
						// Can't build the widget, ignore it
						mw.error( "Can't build details widget", fSpec );
						continue;
				}

				this.fieldList.push( fSpec );
				this.fieldMap[ fKey ] = fieldWidget;
			}

			this.fieldList.sort( function ( a, b ) {
				if ( a.order < b.order ) {
					return -1;
				}
				if ( a.order > b.order ) {
					return 1;
				}
				return 0;
			} );

			// Build the form for the file upload
			this.$form = $( '<form id="mediauploader-detailsform' + this.upload.index + '"></form>' )
				.addClass( 'detailsForm' );
			$moreDetailsDiv = $( '<div>' );

			this.fieldList.forEach( function ( spec ) {
				fieldWidget = this.fieldMap[ spec.key ];
				fieldWrapper = new uw.FieldLayout( fieldWidget, {
					required: spec.type === 'license' || spec.required === 'required',
					label: spec.label,
					help: spec.help ? $( $.parseHTML( spec.help ) ) : null
				} );
				if ( spec.type === 'license' ) {
					fieldWrapper.toggle( this.customDeedChooser ); // See useCustomDeedChooser()
				} else if ( spec.hidden ) {
					fieldWrapper.toggle( false );
				}

				// Apply field defaults
				this.prefillField( spec, fieldWidget );

				// List of fields for validation etc.
				this.fieldWrapperList.push( fieldWrapper );
				this.fieldWrapperMap[ spec.key ] = fieldWrapper;

				// Add the field wrapper to HTML of the form
				if ( spec.auxiliary ) {
					$moreDetailsDiv.append( fieldWrapper.$element );
					// If something changes the input "hidden" in the collapsed section,
					// expand it.
					fieldWidget.on( 'change', function () {
						$moreDetailsWrapperDiv.data( 'mw-collapsible' ).expand();
					} );
				} else {
					this.$form.append( fieldWrapper.$element );
				}
			}, this );

			// Wrap the auxiliary fields in a dropdown
			$moreDetailsWrapperDiv = $( '<div>' ).addClass( 'mwe-more-details' );
			$moreDetailsWrapperDiv
				.append(
					$( '<a>' ).text( mw.msg( 'mediauploader-more-options' ) )
						.addClass( 'mediauploader-details-more-options mw-collapsible-toggle mw-collapsible-arrow' ),
					$moreDetailsDiv.addClass( 'mw-collapsible-content' )
				)
				.makeCollapsible( { collapsed: true } );

			this.$form.on( 'submit', function ( e ) {
				// Prevent actual form submission
				e.preventDefault();
			} );

			this.$form.append(
				$moreDetailsWrapperDiv
			);

			// Add in remove control to form
			this.removeCtrl = new OO.ui.ButtonWidget( {
				label: mw.message( 'mediauploader-remove' ).text(),
				title: mw.message( 'mediauploader-remove-upload' ).text(),
				classes: [ 'mediauploader-remove-upload' ],
				flags: 'destructive',
				icon: 'trash',
				framed: false
			} ).on( 'click', function () {
				OO.ui.confirm( mw.message( 'mediauploader-license-confirm-remove' ).text(), {
					title: mw.message( 'mediauploader-license-confirm-remove-title' ).text()
				} ).done( function ( confirmed ) {
					if ( confirmed ) {
						details.upload.emit( 'remove-upload' );
					}
				} );
			} );

			this.$thumbnailDiv.append( this.removeCtrl.$element );

			this.statusMessage = new OO.ui.MessageWidget( { inline: true } );
			this.statusMessage.toggle( false );
			this.$spinner = $.createSpinner( { size: 'small', type: 'inline' } );
			this.$spinner.hide();
			this.$indicator = $( '<div>' ).addClass( 'mediauploader-file-indicator' ).append(
				this.$spinner,
				this.statusMessage.$element
			);

			this.$submittingDiv = $( '<div>' ).addClass( 'mediauploader-submitting' )
				.append(
					this.$indicator,
					$( '<div>' ).addClass( 'mediauploader-details-texts' ).append(
						$( '<div>' ).addClass( 'mediauploader-visible-file-filename-text' ),
						$( '<div>' ).addClass( 'mediauploader-file-status-line' )
					)
				);

			this.$dataDiv.append(
				this.$form,
				this.$submittingDiv
			).morphCrossfader();

			this.$div.append(
				this.$thumbnailDiv,
				this.$dataDiv
			);

			// This must match the CSS dimensions of .mediauploader-thumbnail
			this.upload.getThumbnail( 230 ).done( function ( thumb ) {
				mw.UploadWizard.placeThumbnail( this.$thumbnailDiv, thumb );
			}, this );

			this.interfaceBuilt = true;

			if ( this.savedSerialData ) {
				this.setSerialized( this.savedSerialData );
				this.savedSerialData = undefined;
			}
		},

		/*
		 * Append the div for this details object to the DOM.
		 * We need to ensure that we add divs in the right order
		 * (the order in which the user selected files).
		 *
		 * Will only append once.
		 */
		attach: function () {
			var $window = $( window ),
				details = this;

			function maybeBuild() {
				if ( !this.interfaceBuilt && $window.scrollTop() + $window.height() + 1000 >= details.$div.offset().top ) {
					details.buildInterface();
					$window.off( 'scroll', maybeBuild );
				}
			}

			if ( !this.isAttached ) {
				this.$containerDiv.append( this.$div );

				if ( $window.scrollTop() + $window.height() + 1000 >= this.$div.offset().top ) {
					this.buildInterface();
				} else {
					$window.on( 'scroll', maybeBuild );
				}

				this.isAttached = true;
			}
		},

		/**
		 * Get file page title for this upload.
		 *
		 * @return {mw.Title|null}
		 */
		getTitle: function () {
			var titleField = mw.UploadWizard.config.content.titleField;

			// title will not be set until we've actually submitted the file
			if ( this.title === undefined ) {
				return this.fieldMap[ titleField ].getTitle();
			}

			// once the file has been submitted, we'll have confirmation on
			// the filename and trust the authoritative source over own input
			return this.title;
		},

		/**
		 * Display error message about multiple uploaded files with the same title specified
		 *
		 * @return {mw.UploadWizardDetails}
		 * @chainable
		 */
		setDuplicateTitleError: function () {
			var titleField = mw.UploadWizard.config.content.titleField;
			// TODO This should give immediate response, not only when submitting the form
			this.fieldWrapperMap[ titleField ].setErrors(
				[ mw.message( 'mediauploader-error-title-duplicate' ) ]
			);
			return this;
		},

		/**
		 * Toggles whether we use the 'macro' deed or our own.
		 */
		useCustomDeedChooser: function () {
			this.customDeedChooser = true;
			this.deedChooserDetails.useCustomDeedChooser( this.upload );
		},

		/**
		 * @private
		 *
		 * @return {uw.FieldLayout[]}
		 */
		getAllFields: function () {
			return [].concat(
				this.fieldWrapperList,
				this.upload.deedChooser.deed ? this.upload.deedChooser.deed.getFields() : []
			);
		},

		/**
		 * Check all the fields for validity.
		 *
		 * @return {jQuery.Promise} Promise resolved with multiple array arguments, each containing a
		 *   list of error messages for a single field. If API requests necessary to check validity
		 *   fail, the promise may be rejected. The form is valid if the promise is resolved with all
		 *   empty arrays.
		 */
		getErrors: function () {
			return $.when.apply( $, this.getAllFields().map( function ( fieldLayout ) {
				return fieldLayout.fieldWidget.getErrors();
			} ) );
		},

		/**
		 * Check all the fields for warnings.
		 *
		 * @return {jQuery.Promise} Same as #getErrors
		 */
		getWarnings: function () {
			return $.when.apply( $, this.getAllFields().map( function ( fieldLayout ) {
				return fieldLayout.fieldWidget.getWarnings();
			} ) );
		},

		/**
		 * Check all the fields for errors and warnings and display them in the UI.
		 *
		 * @param {boolean} thorough True to perform a thorough validity check. Defaults to false for a fast on-change check.
		 * @return {jQuery.Promise} Combined promise of all fields' validation results.
		 */
		checkValidity: function ( thorough ) {
			var fields = this.getAllFields();

			return $.when.apply( $, fields.map( function ( fieldLayout ) {
				// Update any error/warning messages
				return fieldLayout.checkValidity( thorough );
			} ) );
		},

		/**
		 * Get a thumbnail caption for this upload (basically, the first caption).
		 *
		 * @return {string}
		 */
		getThumbnailCaption: function () {
			var captionField = mw.UploadWizard.config.content.captionField;

			// The caption field should be one of:
			// TextWidget, SingleLanguageInputWidget, MultipleLanguageInputWidget
			return this.fieldMap[ captionField ].getCaption();
		},

		/**
		 * Prefills the statically (defaults) and dynamically available info
		 * for the file (from EXIF etc.), for the given field.
		 *
		 * @param {Object} fSpec
		 * @param {uw.DetailsWidget} widget
		 */
		prefillField: function ( fSpec, widget ) {
			var dynPrefilled = false;

			// Try dynamic prefilling, if requested and available for this type
			if ( fSpec.autoFill ) {
				switch ( fSpec.type ) {
					case 'title':
						dynPrefilled = this.prefillTitle( widget );
						break;
					case 'text':
					case 'textarea':
					case 'singlelang':
					case 'multilang':
						dynPrefilled = this.prefillDescription( fSpec.type, widget );
						break;
					case 'date':
						dynPrefilled = this.prefillDate( widget );
						break;
					case 'location':
						dynPrefilled = this.prefillLocation( widget );
						break;
				}
			}

			if ( !dynPrefilled && fSpec.default !== undefined ) {
				widget.setSerialized( fSpec.default );
			}
		},

		/**
		 * Check if we got an EXIF date back and enter it into the details
		 * XXX We ought to be using date + time here...
		 * EXIF examples tend to be in ISO 8601, but the separators are sometimes things like colons, and they have lots of trailing info
		 * (which we should actually be using, such as time and timezone)
		 *
		 * @param {uw.DateDetailsWidget} widget
		 * @return {boolean}
		 */
		prefillDate: function ( widget ) {
			var dateObj, metadata, dateStr, saneTime,
				dateMode = 'calendar',
				yyyyMmDdRegex = /^(\d\d\d\d)[:/-](\d\d)[:/-](\d\d)\D.*/,
				timeRegex = /\D(\d\d):(\d\d):(\d\d)/;

			// XXX surely we have this function somewhere already
			function pad( n ) {
				return ( n < 10 ? '0' : '' ) + String( n );
			}

			function getSaneTime( date ) {
				var str = '';

				str += pad( date.getHours() ) + ':';
				str += pad( date.getMinutes() ) + ':';
				str += pad( date.getSeconds() );

				return str;
			}

			if ( this.upload.imageinfo.metadata ) {
				metadata = this.upload.imageinfo.metadata;
				[ 'datetimeoriginal', 'datetimedigitized', 'datetime', 'date' ].some( function ( propName ) {
					var matches, timeMatches,
						dateInfo = metadata[ propName ];
					if ( dateInfo ) {
						matches = dateInfo.trim().match( yyyyMmDdRegex );
						if ( matches ) {
							timeMatches = dateInfo.trim().match( timeRegex );
							if ( timeMatches ) {
								dateObj = new Date( parseInt( matches[ 1 ], 10 ),
									parseInt( matches[ 2 ], 10 ) - 1,
									parseInt( matches[ 3 ], 10 ),
									parseInt( timeMatches[ 1 ], 10 ),
									parseInt( timeMatches[ 2 ], 10 ),
									parseInt( timeMatches[ 3 ], 10 ) );
							} else {
								dateObj = new Date( parseInt( matches[ 1 ], 10 ),
									parseInt( matches[ 2 ], 10 ) - 1,
									parseInt( matches[ 3 ], 10 ) );
							}
							return true; // break from Array.some
						}
					}
					return false;
				} );
			}

			// if we don't have EXIF or other metadata, just don't put a date in.
			// XXX if we have FileAPI, it might be clever to look at file attrs, saved
			// in the upload object for use here later, perhaps
			if ( dateObj === undefined ) {
				return false;
			}

			dateStr = dateObj.getFullYear() + '-' + pad( dateObj.getMonth() + 1 ) + '-' + pad( dateObj.getDate() );

			// Add the time
			// If the date but not the time is set in EXIF data, we'll get a bogus
			// time value of '00:00:00'.
			// FIXME: Check for missing time value earlier rather than blacklisting
			// a potentially legitimate time value.
			saneTime = getSaneTime( dateObj );
			if ( saneTime !== '00:00:00' ) {
				dateStr += ' ' + saneTime;

				// Switch to freeform date field. DateInputWidget (with calendar) handles dates only, not times.
				dateMode = 'arbitrary';
			}

			// ok by now we should definitely have a dateObj and a date string
			widget.setSerialized( {
				mode: dateMode,
				value: dateStr
			} );

			return true;
		},

		/**
		 * Set the title of the thing we just uploaded, visibly
		 *
		 * @param {uw.TitleDetailsWidget} widget
		 * @return {boolean}
		 */
		prefillTitle: function ( widget ) {
			widget.setSerialized( {
				title: this.upload.title.getNameText()
			} );
			return true;
		},

		/**
		 * Prefill the image description if we have a description
		 *
		 * Note that this is not related to specifying the description from the query
		 * string (that happens earlier). This is for when we have retrieved a
		 * description from an upload_by_url upload or from the metadata.
		 *
		 * @param {string} type
		 * @param {uw.TextWidget} widget
		 * @return {boolean}
		 */
		prefillDescription: function ( type, widget ) {
			var m, descText;

			if (
				widget.getWikiText() === '' &&
				this.upload.file !== undefined
			) {
				m = this.upload.imageinfo.metadata;
				descText = this.upload.file.description ||
					( m && m.imagedescription && m.imagedescription[ 0 ] && m.imagedescription[ 0 ].value );

				if ( !descText ) {
					return false;
				}

				// strip out any HTML tags
				descText = descText.replace( /<[^>]+>/g, '' );

				// Set the text – both singlelang and multilang can fall back to
				// a simple string serialization.
				widget.setSerialized( descText.trim() );

				// Set the language – probably wrong in many cases...
				if ( type === 'singlelang' ) {
					widget.setLanguage(
						widget.getClosestAllowedLanguage( mw.config.get( 'wgContentLanguage' ) )
					);
				} else if ( type === 'multilang' ) {
					widget.getItems()[ 0 ].setLanguage(
						widget.getItems()[ 0 ].getClosestAllowedLanguage(
							mw.config.get( 'wgContentLanguage' )
						)
					);
				}

				return true;
			}

			return false;
		},

		/**
		 * Prefill location input from image info and metadata
		 *
		 * As of MediaWiki 1.18, the exif parser translates the rational GPS data tagged by the camera
		 * to decimal format. Let's just use that.
		 *
		 * @param {uw.LocationDetailsWidget} widget
		 * @return {boolean}
		 */
		prefillLocation: function ( widget ) {
			var dir,
				m = this.upload.imageinfo.metadata,
				modified = false,
				values = {};

			if ( m ) {
				dir = m.gpsimgdirection || m.gpsdestbearing;

				if ( dir ) {
					if ( dir.match( /^\d+\/\d+$/ ) !== null ) {
						// Apparently it can take the form "x/y" instead of
						// a decimal value. Mighty silly, but let's save it.
						dir = dir.split( '/' );
						dir = parseInt( dir[ 0 ], 10 ) / parseInt( dir[ 1 ], 10 );
					}

					values.heading = dir;

					modified = true;
				}

				// Prefill useful stuff only
				if ( Number( m.gpslatitude ) && Number( m.gpslongitude ) ) {
					values.latitude = m.gpslatitude;
					values.longitude = m.gpslongitude;
					modified = true;
				} else if (
					this.upload.file &&
					this.upload.file.location &&
					this.upload.file.location.latitude &&
					this.upload.file.location.longitude
				) {
					values.latitude = this.upload.file.location.latitude;
					values.longitude = this.upload.file.location.longitude;
					modified = true;
				}
			}

			if ( modified ) {
				widget.setSerialized( values );
				return true;
			}
			return false;
		},

		/**
		 * Returns the language list to use in (Single|Multiple)LanguageInputWidget
		 *
		 * @return {Object}
		 */
		getLanguageOptions: function () {
			var languages, code;

			languages = {};
			for ( code in mw.UploadWizard.config.languages ) {
				if ( Object.prototype.hasOwnProperty.call( mw.UploadWizard.config.languages, code ) ) {
					languages[ code ] = mw.UploadWizard.config.languages[ code ];
				}
			}
			return languages;
		},

		/**
		 * Get a machine-readable representation of the current state of the upload details. It can be
		 * passed to #setSerialized to restore this state (or to set it for another instance of the same
		 * class).
		 *
		 * Note that this doesn't include custom deed's state.
		 *
		 * @return {Object.<string,Object>}
		 */
		getSerialized: function () {
			var fieldWidget, serialized = {};

			if ( !this.interfaceBuilt ) {
				// We don't have the interface yet, but it'll get filled out as
				// needed.
				return;
			}

			this.fieldList.forEach( function ( fSpec ) {
				fieldWidget = this.fieldMap[ fSpec.key ];
				serialized[ fSpec.key ] = fieldWidget.getSerialized();
			}, this );

			return serialized;
		},

		/**
		 * Set the state of this widget from machine-readable representation, as returned by
		 * #getSerialized.
		 *
		 * Fields from the representation can be omitted to keep the current value.
		 *
		 * @param {Object.<string,Object>} [serialized]
		 */
		setSerialized: function ( serialized ) {
			if ( !this.interfaceBuilt ) {
				// There's no interface yet! Don't load the data, just keep it
				// around.
				if ( serialized === undefined ) {
					// Note: This will happen if we "undo" a copy operation while
					// some of the details interfaces aren't loaded.
					this.savedSerialData = undefined;
				} else {
					this.savedSerialData = $.extend( true,
						this.savedSerialData || {},
						serialized
					);
				}
				return;
			}

			if ( serialized === undefined ) {
				// This is meaningless if the interface is already built.
				return;
			}

			this.fieldList.forEach( function ( fSpec ) {
				if ( serialized[ fSpec.key ] ) {
					this.fieldMap[ fSpec.key ].setSerialized( serialized[ fSpec.key ] );
				}
			}, this );
		},

		/**
		 * Convert entire details for this file into wikiText, which will then be posted to the file
		 *
		 * This function assumes that all input is valid.
		 *
		 * @return {string} wikitext representing all details
		 */
		getWikiText: function () {
			var wikiText = mw.UploadWizard.config.content.wikitext,
				substitutions = {}, substList = [],
				deed = this.upload.deedChooser.deed,
				fieldWidget, serialized, valueType, re, escapedKey, replaceValue;

			if ( !wikiText ) {
				wikiText = mw.message( 'mediauploader-default-content-wikitext' ).plain();
			}
			if ( mw.UploadWizard.config.content.prepend ) {
				wikiText = mw.UploadWizard.config.content.prepend + '\n' + wikiText;
			}
			if ( mw.UploadWizard.config.content.append ) {
				wikiText += '\n' + mw.UploadWizard.config.content.append;
			}

			function addSubstitution( key, value ) {
				var v = value;
				if ( key in substitutions ) {
					return;
				}
				// Discard funny values that toString poorly
				if ( v === undefined || v === null || ( typeof v === 'number' && isNaN( v ) ) ) {
					v = '';
				}
				substList.push( key );
				substitutions[ key ] = v.toString();
			}

			// Add hardcoded substitutions
			addSubstitution( 'source', deed.getSourceWikiText( this.upload ) );
			addSubstitution( 'author', deed.getAuthorWikiText( this.upload ) );
			addSubstitution( 'license', deed.getLicenseWikiText() );

			// Add substitutions for all the defined details fields
			this.fieldList.forEach( function ( spec ) {
				if ( spec.type === 'license' ) {
					// Skip the license input... it is handled separately above.
					return;
				}

				fieldWidget = this.fieldMap[ spec.key ];
				addSubstitution( spec.key, fieldWidget.getWikiText() );
				serialized = fieldWidget.getSerializedParsed();
				if ( typeof serialized !== 'object' ) {
					return;
				}
				// Also add "subfields" based on the serialized values. Just in case.
				Object.keys( serialized ).forEach( function ( key ) {
					replaceValue = serialized[ key ];
					valueType = typeof replaceValue;
					if ( valueType === 'string' || valueType === 'number' || valueType === 'boolean' ) {
						addSubstitution( spec.key + '.' + key, replaceValue );
					}
				}, this );
			}, this );

			// Do the substitutions
			substList.forEach( function ( substKey ) {
				replaceValue = substitutions[ substKey ].trim();
				escapedKey = substKey.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
				re = new RegExp( '\\{\\{\\{ *' + escapedKey + ' *(\\|(.*?))?\\}\\}\\}', 'giu' );
				wikiText = wikiText.replace( re, function ( match, _, defaultValue ) {
					if ( !replaceValue ) {
						return defaultValue || '';
					} else {
						return replaceValue;
					}
				} );
			}, this );

			// remove too many newlines in a row
			wikiText = wikiText.replace( /\n{3,}/g, '\n\n' );

			return wikiText;
		},

		/**
		 * @return {jQuery.Promise}
		 */
		submit: function () {
			var details = this,
				wikitext, promise;

			this.$containerDiv.find( 'form' ).trigger( 'submit' );

			this.upload.title = this.getTitle();
			this.upload.state = 'submitting-details';
			this.setStatus( mw.message( 'mediauploader-submitting-details' ).text() );
			this.showIndicator( 'progress' );

			wikitext = this.getWikiText();
			promise = this.submitWikiText( wikitext );

			return promise.then( function () {
				details.showIndicator( 'success' );
				details.setStatus( mw.message( 'mediauploader-published' ).text() );
			} );
		},

		/**
		 * Post wikitext as edited here, to the file
		 *
		 * This function is only called if all input seems valid (which doesn't mean that we can't get
		 * an error, see #processError).
		 *
		 * @param {string} wikiText
		 * @return {jQuery.Promise}
		 */
		submitWikiText: function ( wikiText ) {
			var params,
				tags = [ 'uploadwizard' ],
				deed = this.upload.deedChooser.deed,
				comment = '',
				config = mw.UploadWizard.config;

			this.firstPoll = ( new Date() ).getTime();

			if ( this.upload.file.source ) {
				tags.push( 'uploadwizard-' + this.upload.file.source );
			}

			if ( deed.name === 'ownwork' ) {
				// This message does not have any parameters, so there's nothing to substitute
				comment = config.uploadComment.ownWork;
			} else {
				mw.messages.set(
					'mediauploader-upload-comment-third-party',
					config.uploadComment.thirdParty
				);
				comment = mw.message(
					'mediauploader-upload-comment-third-party',
					deed.getAuthorWikiText(),
					deed.getSourceWikiText()
				).plain();
			}

			params = {
				action: 'upload',
				filekey: this.upload.fileKey,
				filename: this.getTitle().getMain(),
				comment: comment,
				tags: config.CanAddTags ? tags : [],
				// we can ignore upload warnings here, we've already checked
				// when stashing the file
				// not ignoring warnings would prevent us from uploading a file
				// that is a duplicate of something in a foreign repo
				ignorewarnings: true,
				text: wikiText
			};

			// Only enable async publishing if file is larger than 10MiB
			if ( this.upload.transportWeight > 10 * 1024 * 1024 ) {
				params.async = true;
			}

			return this.submitWikiTextInternal( params );
		},

		/**
		 * Perform the API call with given parameters (which is expected to publish this file) and
		 * handle the result.
		 *
		 * @param {Object} params API call parameters
		 * @return {jQuery.Promise}
		 */
		submitWikiTextInternal: function ( params ) {
			var details = this,
				apiPromise = this.upload.api.postWithEditToken( params );

			return apiPromise
				// process the successful (in terms of HTTP status...) API call first:
				// there may be warnings or other issues with the upload that need
				// to be dealt with
				.then( this.validateWikiTextSubmitResult.bind( this, params ) )
				// making it here means the upload is a success, or it would've been
				// rejected by now (either by HTTP status code, or in validateWikiTextSubmitResult)
				.then( function ( result ) {
					details.title = mw.Title.makeTitle( 6, result.upload.filename );
					details.upload.extractImageInfo( result.upload.imageinfo );
					details.upload.thisProgress = 1.0;
					details.upload.state = 'complete';
					return result;
				} )
				// uh-oh - something went wrong!
				.catch( function ( code, result ) {
					details.upload.state = 'error';
					details.processError( code, result );
					return $.Deferred().reject( code, result );
				} )
				.promise( { abort: apiPromise.abort } );
		},

		/**
		 * Validates the result of a submission & returns a resolved promise with
		 * the API response if all went well, or rejects with error code & error
		 * message as you would expect from failed mediawiki API calls.
		 *
		 * @param {Object} params What we passed to the API that caused this response.
		 * @param {Object} result API result of an upload or status check.
		 * @return {jQuery.Promise}
		 */
		validateWikiTextSubmitResult: function ( params, result ) {
			var wx, warningsKeys, existingFile, existingFileUrl, existingFileExt, ourFileExt, code, message,
				details = this,
				warnings = null,
				ignoreTheseWarnings = false,
				deferred = $.Deferred();

			if ( result && result.upload && result.upload.result === 'Poll' ) {
				// if async publishing takes longer than 10 minutes give up
				if ( ( ( new Date() ).getTime() - this.firstPoll ) > 10 * 60 * 1000 ) {
					return deferred.reject( 'server-error', { errors: [ {
						code: 'server-error',
						html: 'Unknown server error'
					} ] } );
				} else {
					if ( result.upload.stage === undefined ) {
						return deferred.reject( 'no-stage', { errors: [ {
							code: 'no-stage',
							html: 'Unable to check file\'s status'
						} ] } );
					} else {
						// Messages that can be returned:
						// * mediauploader-queued
						// * mediauploader-publish
						// * mediauploader-assembling
						this.setStatus( mw.message( 'mediauploader-' + result.upload.stage ).text() );
						setTimeout( function () {
							if ( details.upload.state !== 'aborted' ) {
								details.submitWikiTextInternal( {
									action: 'upload',
									checkstatus: true,
									filekey: details.upload.fileKey
								} ).then( deferred.resolve, deferred.reject );
							} else {
								deferred.resolve( 'aborted' );
							}
						}, 3000 );

						return deferred.promise();
					}
				}
			}
			if ( result && result.upload && result.upload.warnings ) {
				warnings = result.upload.warnings;
			}
			if ( warnings && warnings.exists ) {
				existingFile = warnings.exists;
			} else if ( warnings && warnings[ 'exists-normalized' ] ) {
				existingFile = warnings[ 'exists-normalized' ];
				existingFileExt = mw.Title.normalizeExtension( existingFile.split( '.' ).pop() );
				ourFileExt = mw.Title.normalizeExtension( this.getTitle().getExtension() );

				if ( existingFileExt !== ourFileExt ) {
					delete warnings[ 'exists-normalized' ];
					ignoreTheseWarnings = true;
				}
			}
			if ( warnings && warnings[ 'was-deleted' ] ) {
				delete warnings[ 'was-deleted' ];
				ignoreTheseWarnings = true;
			}
			for ( wx in warnings ) {
				if ( Object.prototype.hasOwnProperty.call( warnings, wx ) ) {
					// if there are other warnings, deal with those first
					ignoreTheseWarnings = false;
				}
			}
			if ( result && result.upload && result.upload.imageinfo ) {
				return $.Deferred().resolve( result );
			} else if ( ignoreTheseWarnings ) {
				params.ignorewarnings = 1;
				return this.submitWikiTextInternal( params );
			} else if ( result && result.upload && result.upload.warnings ) {
				if ( warnings.thumb || warnings[ 'thumb-name' ] ) {
					code = 'error-title-thumbnail';
					message = mw.message( 'mediauploader-error-title-thumbnail' ).parse();
				} else if ( warnings.badfilename ) {
					code = 'title-invalid';
					message = mw.message( 'mediauploader-error-title-invalid' ).parse();
				} else if ( warnings[ 'bad-prefix' ] ) {
					code = 'title-senselessimagename';
					message = mw.message( 'mediauploader-error-title-senselessimagename' ).parse();
				} else if ( existingFile ) {
					existingFileUrl = mw.config.get( 'wgServer' ) + mw.Title.makeTitle( NS_FILE, existingFile ).getUrl();
					code = 'api-warning-exists';
					message = mw.message( 'mediauploader-api-warning-exists', existingFileUrl ).parse();
				} else if ( warnings.duplicate ) {
					code = 'upload-error-duplicate';
					message = mw.message( 'mediauploader-upload-error-duplicate' ).parse();
				} else if ( warnings[ 'duplicate-archive' ] !== undefined ) {
					// warnings[ 'duplicate-archive' ] may be '' (empty string) for revdeleted files
					if ( this.upload.handler.isIgnoredWarning( 'duplicate-archive' ) ) {
						// We already told the interface to ignore this warning, so
						// let's steamroll over it and re-call this handler.
						params.ignorewarnings = true;
						return this.submitWikiTextInternal( params );
					} else {
						// This should _never_ happen, but just in case....
						code = 'upload-error-duplicate-archive';
						message = mw.message( 'mediauploader-upload-error-duplicate-archive' ).parse();
					}
				} else {
					warningsKeys = Object.keys( warnings );
					code = 'unknown-warning';
					message = mw.message( 'api-error-unknown-warning', warningsKeys.join( ', ' ) ).parse();
				}

				return $.Deferred().reject( code, { errors: [ { html: message } ] } );
			} else {
				return $.Deferred().reject( 'this-info-missing', result );
			}
		},

		/**
		 * Create a recoverable error -- show the form again, and highlight the problematic field.
		 *
		 * @param {string} code
		 * @param {string} html Error message to show.
		 */
		recoverFromError: function ( code, html ) {
			var titleField = mw.UploadWizard.config.content.titleField;

			this.upload.state = 'recoverable-error';
			this.$dataDiv.morphCrossfade( '.detailsForm' );
			this.fieldWrapperMap[ titleField ].setErrors( [ { code: code, html: html } ] );
		},

		/**
		 * Show error state, possibly using a recoverable error form
		 *
		 * @param {string} code Error code
		 * @param {string} html Error message
		 */
		showError: function ( code, html ) {
			this.showIndicator( 'error' );
			this.setStatus( html );
		},

		/**
		 * Decide how to treat various errors
		 *
		 * @param {string} code Error code
		 * @param {Object} result Result from ajax call
		 */
		processError: function ( code, result ) {
			var recoverable = [
				'abusefilter-disallowed',
				'abusefilter-warning',
				'spamblacklist',
				'fileexists-shared-forbidden',
				'protectedpage',
				'titleblacklist-forbidden',

				// below are not actual API errors, but recoverable warnings that have
				// been discovered in validateWikiTextSubmitResult and fabricated to resemble
				// API errors and end up here to be dealt with
				'error-title-thumbnail',
				'title-invalid',
				'title-senselessimagename',
				'api-warning-exists',
				'upload-error-duplicate',
				'upload-error-duplicate',
				'upload-error-duplicate-archive',
				'unknown-warning'
			];

			if ( code === 'badtoken' ) {
				this.api.badToken( 'csrf' );
				// TODO Automatically try again instead of requiring the user to bonk the button
			}

			if ( code === 'ratelimited' ) {
				// None of the remaining uploads is going to succeed, and every failed one is going to
				// ping the rate limiter again.
				this.upload.wizard.steps.details.queue.abortExecuting();
			} else if ( code === 'http' && result && result.exception === 'abort' ) {
				// This upload has just been aborted because an earlier one got the 'ratelimited' error.
				// This could potentially also come up when an upload is removed by the user, but in that
				// case the UI is invisible anyway, so whatever.
				code = 'ratelimited';
			}

			if ( recoverable.indexOf( code ) > -1 ) {
				this.recoverFromError( code, result.errors[ 0 ].html );
				return;
			}

			this.showError( code, result.errors[ 0 ].html );
		},

		setStatus: function ( s ) {
			this.$div.find( '.mediauploader-file-status-line' ).html( s ).show();
		},

		// TODO: De-duplicate with code form mw.UploadWizardUploadInterface.js
		showIndicator: function ( status ) {
			this.$spinner.hide();
			this.statusMessage.toggle( false );

			if ( status === 'progress' ) {
				this.$spinner.show();
			} else if ( status ) {
				this.statusMessage.toggle( true ).setType( status );
			}
			this.$indicator.toggleClass( 'mediauploader-file-indicator-visible', !!status );
		},

		setVisibleTitle: function ( s ) {
			$( this.$submittingDiv )
				.find( '.mediauploader-visible-file-filename-text' )
				.text( s );
		}
	};

}( mw.uploadWizard ) );
