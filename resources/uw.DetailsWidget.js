( function ( uw ) {

	/**
	 * A single logical field in UploadWizard's "Details" step form.
	 *
	 * This can be composed of multiple smaller widgets, but represents a single unit (e.g. a
	 * "location" field could be composed of "latitude" and "longitude" inputs).
	 *
	 * @extends OO.ui.Widget
	 * @abstract
	 * @param {Object} config
	 */
	uw.DetailsWidget = function UWDetailsWidget( config ) {
		this.config = config;
		uw.DetailsWidget.parent.call( this, config || {} );
	};
	OO.inheritClass( uw.DetailsWidget, OO.ui.Widget );

	/**
	 * A 'change' event is emitted when the state of this widget (and the serialized value) changes.
	 *
	 * @event change
	 */

	/**
	 * @inheritdoc OO.ui.mixin.PendingElement#pushPending
	 */
	uw.DetailsWidget.prototype.pushPending = function () {
		// Do nothing by default
	};

	/**
	 * @inheritdoc OO.ui.mixin.PendingElement#popPending
	 */
	uw.DetailsWidget.prototype.popPending = function () {
		// Do nothing by default
	};

	/**
	 * Get the list of errors about the current state of the widget.
	 *
	 * @return {jQuery.Promise} Promise resolved with an array of mw.Message objects
	 *   representing errors. (Checking for errors might require API queries, etc.)
	 */
	uw.DetailsWidget.prototype.getErrors = function () {
		return $.Deferred().resolve( [] ).promise();
	};

	/**
	 * Get the list of warnings about the current state of the widget.
	 *
	 * @return {jQuery.Promise} Promise resolved with an array of mw.Message objects
	 *   representing warnings. (Checking for warnings might require API queries, etc.)
	 */
	uw.DetailsWidget.prototype.getWarnings = function () {
		return $.Deferred().resolve( [] ).promise();
	};

	/**
	 * If `isEmpty` and the field is recommended, adds an appropriate warning to `warnings` and
	 * return true. Returns false otherwise.
	 *
	 * @method
	 * @param {boolean} isEmpty
	 * @param {mw.Message[]} warnings
	 * @return {boolean}
	 */
	uw.DetailsWidget.prototype.getEmptyWarning = function ( isEmpty, warnings ) {
		if ( this.config.recommended && isEmpty ) {
			warnings.push( mw.message( 'mediauploader-warning-value-missing', this.config.fieldName ) );
			return true;
		}
		return false;
	};

	/**
	 * Get a wikitext snippet generated from current state of the widget.
	 * Alternatively can return a map of string -> string, representing subfields of the field.
	 *
	 * @method
	 * @return {string|Object} Wikitext or map of subfield -> wikitext
	 */
	uw.DetailsWidget.prototype.getWikiText = null;

	/**
	 * Get a machine-readable representation of the current state of the widget. It can be passed to
	 * #setSerialized to restore this state (or to set it for another instance of the same class).
	 *
	 * @method
	 * @return {Object}
	 */
	uw.DetailsWidget.prototype.getSerialized = null;

	/**
	 * Same as getSerialized, but returns a version of the serialized fields that are suitable for
	 * user display.
	 *
	 * @method
	 * @return {Object}
	 */
	uw.DetailsWidget.prototype.getSerializedParsed = function () {
		return this.getSerialized();
	};

	/**
	 * Set the state of this widget from machine-readable representation, as returned by
	 * #getSerialized.
	 *
	 * @method
	 * @param {Object} serialized
	 */
	uw.DetailsWidget.prototype.setSerialized = null;

}( mw.uploadWizard ) );
