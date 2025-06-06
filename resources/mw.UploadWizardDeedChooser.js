( function () {

	/**
	 * Interface widget to choose among various deeds -- for instance, if own work, or not own work, or other such cases.
	 *
	 * @param {Object} config The UW config
	 * @param {string|jQuery} selector Where to put this deed chooser
	 * @param {Object} deeds Keyed object of UploadWizardDeed items
	 * @param {mw.UploadWizardUpload[]} uploads Uploads that this applies to (this is just to make deleting and plurals work)
	 */
	mw.UploadWizardDeedChooser = function ( config, selector, deeds, uploads ) {
		const chooser = this;
		this.$selector = $( selector );
		this.uploads = uploads;
		this.deeds = deeds;

		// name for radio button set
		mw.UploadWizardDeedChooser.prototype.widgetCount++;
		this.name = 'deedChooser' + mw.UploadWizardDeedChooser.prototype.widgetCount.toString();

		this.onLayoutReady = function () {};

		Object.keys( this.deeds ).forEach( ( name ) => {
			const deed = chooser.deeds[ name ],
				radio = new OO.ui.RadioSelectWidget( {
					items: [ new OO.ui.RadioOptionWidget( {
						// eslint-disable-next-line mediawiki/msg-doc
						label: mw.message( 'mediauploader-source-' + deed.name, chooser.uploads.length ).text()
					} ) ]
				} ),
				// eslint-disable-next-line mediawiki/class-doc
				$deedInterface = $( '<div>' ).addClass( 'mediauploader-deed mediauploader-deed-' + deed.name ).append(
					$( '<div>' ).addClass( 'mediauploader-deed-option-title' ).append(
						$( '<span>' ).addClass( 'mediauploader-deed-header' ).append(
							radio.$element
						)
					),
					$( '<div>' ).addClass( 'mediauploader-deed-form' ).hide()
				);

			// Set the name attribute manually. We can't use RadioInputWidget which has
			// a name config because they don't emit change events. Ideally we would use
			// one RadioSelectWidget and not have to set this property.
			radio.items[ 0 ].radio.$input.attr( 'name', chooser.name );

			chooser.$selector.append( $deedInterface );

			deed.setFormFields( $deedInterface.find( '.mediauploader-deed-form' ) );

			if ( Object.keys( chooser.deeds ).length === 1 ) {
				chooser.onLayoutReady = chooser.selectDeed.bind( chooser, deed );
			} else {
				if ( config.licensing.defaultType.toLowerCase() === deed.name ) {
					chooser.onLayoutReady = chooser.selectDeed.bind( chooser, deed );
				}
				radio.on( 'choose', () => {
					chooser.selectDeed( deed );
				} );
			}
		} );

		// deselect all deeds
		this.deselectDeedInterface( this.$selector.find( '.mediauploader-deed' ) );
	};

	mw.UploadWizardDeedChooser.prototype = {
		/**
		 * How many deed choosers there are (important for creating unique ids, element names)
		 */
		widgetCount: 0,

		/**
		 * Check if this form is filled out correctly.
		 *
		 * @return {boolean} true if valid, false if not
		 */
		valid: function () {
			return !!this.deed;
		},

		/**
		 * Uploads this deed controls
		 */
		uploads: [],

		selectDeed: function ( deed ) {
			const $deedInterface = this.$selector.find( '.mediauploader-deed.mediauploader-deed-' + deed.name );

			this.choose( deed );
			this.selectDeedInterface( $deedInterface );
			$deedInterface.find( 'span.mediauploader-deed-header input' ).prop( 'checked', true );
		},

		choose: function ( deed ) {
			const chooser = this;

			this.deed = deed;

			this.uploads.forEach( ( upload ) => {
				upload.deedChooser = chooser;
			} );

			// eslint-disable-next-line no-jquery/no-global-selector
			$( '#mediauploader-stepdiv-deeds .mediauploader-button-next' ).show();
		},

		/**
		 * From the deed choices, make a choice fade to the background a bit, hide the extended form
		 *
		 * @param {jQuery} $deedSelector
		 */
		deselectDeedInterface: function ( $deedSelector ) {
			$deedSelector.removeClass( 'selected' );
			$deedSelector.find( '.mediauploader-deed-form' ).each( function () {
				const $form = $( this );
				// Prevent validation of deselected deeds by disabling all form inputs
				// TODO: Use a tag selector
				// eslint-disable-next-line no-jquery/no-sizzle
				$form.find( ':input' ).prop( 'disabled', true );
				// eslint-disable-next-line no-jquery/no-sizzle
				if ( $form.parents().is( ':hidden' ) ) {
					$form.hide();
				} else {
					// FIXME: Use CSS transition
					// eslint-disable-next-line no-jquery/no-slide
					$form.slideUp( 500 );
				}
			} );
		},

		/**
		 * From the deed choice page, show a particular deed
		 *
		 * @param {jQuery} $deedSelector
		 */
		selectDeedInterface: function ( $deedSelector ) {
			const $otherDeeds = $deedSelector.siblings().filter( '.mediauploader-deed' );
			this.deselectDeedInterface( $otherDeeds );
			// FIXME: Use CSS transition
			// eslint-disable-next-line no-jquery/no-fade
			$deedSelector.addClass( 'selected' ).fadeTo( 'fast', 1.0 );
			$deedSelector.find( '.mediauploader-deed-form' ).each( function () {
				const $form = $( this );
				// (Re-)enable all form inputs
				// TODO: Use a tag selector
				// eslint-disable-next-line no-jquery/no-sizzle
				$form.find( ':input' ).prop( 'disabled', false );
				// eslint-disable-next-line no-jquery/no-sizzle
				if ( $form.is( ':hidden' ) ) {
					// if the form was hidden, set things up so a slide-down works
					// FIXME: Use CSS transition
					// eslint-disable-next-line no-jquery/no-slide
					$form.show().slideUp( 0 );
				}
				// FIXME: Use CSS transition
				// eslint-disable-next-line no-jquery/no-slide
				$form.slideDown( 500 );
			} );
		},

		remove: function () {
			this.$selector.html( '' );
		},

		/**
		 * @return {Object}
		 */
		getSerialized: function () {
			return this.valid() ? this.deed.getSerialized() : {};
		},

		/**
		 * @param {Object} serialized
		 */
		setSerialized: function ( serialized ) {
			let deed;

			if ( serialized.name && serialized.name in this.deeds ) {
				deed = this.deeds[ serialized.name ];
				deed.setSerialized( serialized );
				this.selectDeed( deed );
			}
		}

	};

}() );
