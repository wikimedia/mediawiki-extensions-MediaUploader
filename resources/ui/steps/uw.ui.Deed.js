/*
 * This file is part of the MediaWiki extension MediaUploader.
 *
 * MediaUploader is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaUploader is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaUploader.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw ) {
	/**
	 * Represents the UI for the wizard's Deed step.
	 *
	 * @class uw.ui.Deed
	 * @extends uw.ui.Step
	 * @constructor
	 */
	uw.ui.Deed = function UWUIDeed() {
		uw.ui.Step.call(
			this,
			'deeds'
		);

		this.addPreviousButton();
		this.addNextButton();
	};

	OO.inheritClass( uw.ui.Deed, uw.ui.Step );

	uw.ui.Deed.prototype.load = function ( uploads ) {
		var ui = this;

		uw.ui.Step.prototype.load.call( this, uploads );

		this.$div.prepend(
			$( '<div>' )
				.attr( 'id', 'mediauploader-deeds-thumbnails' )
				.addClass( 'ui-helper-clearfix' ),
			$( '<div>' )
				.attr( 'id', 'mediauploader-deeds' )
				.addClass( 'ui-helper-clearfix' ),
			$( '<div>' )
				.attr( 'id', 'mediauploader-deeds-custom' )
				.addClass( 'ui-helper-clearfix' )
		);

		this.nextButtonPromise.done( function () {
			// hide "next" button, controller will only show it once license has
			// been selected
			ui.nextButton.$element.hide();
		} );
	};
}( mw.uploadWizard ) );
