( function ( uw ) {

	const NS_CATEGORY = mw.config.get( 'wgNamespaceIds' ).category;

	/**
	 * A categories field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 * @param {Object} config
	 */
	uw.CategoriesDetailsWidget = function UWCategoriesDetailsWidget( config ) {
		const catDetails = this;
		this.config = config;

		uw.CategoriesDetailsWidget.parent.call( this, this.config );

		this.categoriesWidget = new mw.widgets.CategoryMultiselectWidget( {
			disabled: this.config.disabled
		} );

		this.categoriesWidget.createTagItemWidget = function ( data ) {
			const widget = this.constructor.prototype.createTagItemWidget.call( this, data );
			if ( !widget ) {
				return null;
			}
			widget.setMissing = function ( missing ) {
				this.constructor.prototype.setMissing.call( this, missing );
				// Aggregate 'change' event
				catDetails.emit( 'change' );
			};
			return widget;
		};

		this.$element.addClass( 'mediauploader-categoriesDetailsWidget' );
		this.$element.append( this.categoriesWidget.$element );

		// Aggregate 'change' event
		this.categoriesWidget.connect( this, { change: [ 'emit', 'change' ] } );
	};
	OO.inheritClass( uw.CategoriesDetailsWidget, uw.DetailsWidget );

	/**
	 * @inheritdoc
	 */
	uw.CategoriesDetailsWidget.prototype.getErrors = function () {
		const errors = [];

		if ( this.config.required && this.categoriesWidget.getItems().length === 0 ) {
			errors.push( mw.message( 'mediauploader-error-blank' ) );
		}

		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.CategoriesDetailsWidget.prototype.getWarnings = function () {
		const warnings = [];
		this.getEmptyWarning( this.categoriesWidget.getItems().length === 0, warnings );

		if ( this.categoriesWidget.getItems().some( ( item ) => item.missing ) ) {
			warnings.push( mw.message( 'mediauploader-categories-missing' ) );
		}
		return $.Deferred().resolve( warnings ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.CategoriesDetailsWidget.prototype.getWikiText = function () {
		let hiddenCats, missingCatsWikiText, categories, wikiText;

		hiddenCats = [];
		if ( this.config.hiddenDefault ) {
			hiddenCats = hiddenCats.concat( this.config.hiddenDefault );
		}
		if ( mw.UploadWizard.config.trackingCategory ) {
			if ( mw.UploadWizard.config.trackingCategory.campaign &&
				mw.UploadWizard.config.trackingCategory.autoAdd
			) {
				hiddenCats.push( mw.UploadWizard.config.trackingCategory.campaign );
			}
		}
		hiddenCats = hiddenCats.filter( ( cat ) =>
			// Keep only valid titles
			 !!mw.Title.makeTitle( NS_CATEGORY, cat )
		 );

		missingCatsWikiText = null;
		if (
			typeof this.config.missingWikitext === 'string' &&
			this.config.missingWikitext.length > 0
		) {
			missingCatsWikiText = this.config.missingWikitext;
		}

		categories = this.categoriesWidget.getItems().map( ( item ) => item.data );

		// add all categories
		wikiText = categories.concat( hiddenCats )
			.map( ( cat ) => '[[' + mw.Title.makeTitle( NS_CATEGORY, cat ).getPrefixedText() + ']]' )
			.join( '\n' );

		// if so configured, and there are no user-visible categories, add warning
		if ( missingCatsWikiText !== null && categories.length === 0 ) {
			wikiText += '\n\n' + missingCatsWikiText;
		}

		return wikiText;
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.CategoriesDetailsWidget.prototype.getSerialized = function () {
		return this.categoriesWidget.getItems().map( ( item ) => item.data );
	};

	/**
	 * @inheritdoc
	 * @param {string[]} serialized List of categories
	 */
	uw.CategoriesDetailsWidget.prototype.setSerialized = function ( serialized ) {
		const categories = ( serialized || [] ).filter( ( cat ) =>
			// Keep only valid titles
			 !!mw.Title.makeTitle( NS_CATEGORY, cat )
		 );
		this.categoriesWidget.setValue( categories );
	};

}( mw.uploadWizard ) );
