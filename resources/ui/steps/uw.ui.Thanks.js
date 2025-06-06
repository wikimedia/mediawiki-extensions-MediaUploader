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
	 * Represents the UI for the wizard's Thanks step.
	 *
	 * @class uw.ui.Thanks
	 * @extends uw.ui.Step
	 * @constructor
	 * @param {Object} config
	 */
	uw.ui.Thanks = function UWUIThanks( config ) {
		let $header,
			beginButtonTarget,
			thanks = this;

		this.config = config;

		uw.ui.Step.call(
			this,
			'thanks'
		);

		this.$div.prepend(
			$( '<div>' ).attr( 'id', 'mediauploader-thanks' )
		);

		$( '<p>' )
			.addClass( 'mediauploader-thanks-explain' )
			.msg( 'mediauploader-thanks-explain' )
			.prependTo( this.$div );

		$header = $( '<h3>' )
			.addClass( 'mediauploader-thanks-header' )
			.prependTo( this.$div );

		if ( !this.config.display || !this.config.display.thanksLabel ) {
			$header.text( mw.message( 'mediauploader-thanks-intro' ).text() );
		} else {
			$header.html( this.config.display.thanksLabel );
		}

		this.homeButton = new OO.ui.ButtonWidget( {
			label: this.getButtonConfig( 'homeButton', 'label' ) || mw.message( 'mediauploader-home' ).text(),
			href: this.getButtonConfig( 'homeButton', 'target' ) || mw.config.get( 'wgArticlePath' ).replace( '$1', '' )
		} );

		this.beginButton = new OO.ui.ButtonWidget( {
			label: this.getButtonConfig( 'beginButton', 'label' ) || mw.message( 'mediauploader-upload-another' ).text(),
			flags: [ 'progressive', 'primary' ]
		} );

		// TODO: make the step order configurable by campaign definitions instead of using these hacks
		beginButtonTarget = this.getButtonConfig( 'beginButton', 'target' );
		if ( !beginButtonTarget ) {
			this.beginButton.on( 'click', () => {
				thanks.emit( 'next-step' );
			} );
		} else {
			this.beginButton.setHref( beginButtonTarget );
		}
		this.beginButton.on( 'click', () => {
			mw.DestinationChecker.clearCache();
		} );

		this.buttonGroup = new OO.ui.HorizontalLayout( {
			items: [ this.homeButton, this.beginButton ]
		} );

		this.$buttons.append( this.buttonGroup.$element );
	};

	OO.inheritClass( uw.ui.Thanks, uw.ui.Step );

	/**
	 * Adds an upload to the Thanks interface.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.ui.Thanks.prototype.addUpload = function ( upload ) {
		let thumbWikiText, $thanksDiv, $thumbnailWrapDiv, $thumbnailDiv, $thumbnailCaption, $thumbnailLink;

		thumbWikiText = '[[' + [
			upload.details.getTitle().getPrefixedText(),
			'thumb',
			upload.details.getThumbnailCaption()
		].join( '|' ) + ']]';

		$thanksDiv = $( '<div>' )
			.addClass( 'mediauploader-thanks ui-helper-clearfix' );
		$thumbnailWrapDiv = $( '<div>' )
			.addClass( 'mediauploader-thumbnail-side' )
			.appendTo( $thanksDiv );
		$thumbnailDiv = $( '<div>' )
			.addClass( 'mediauploader-thumbnail' )
			.appendTo( $thumbnailWrapDiv );
		$thumbnailCaption = $( '<div>' )
			.css( { 'text-align': 'center', 'font-size': 'small' } )
			.appendTo( $thumbnailWrapDiv );
		$thumbnailLink = $( '<a>' )
			.text( upload.details.getTitle().getMainText() )
			.appendTo( $thumbnailCaption );

		$( '<div>' )
			.addClass( 'mediauploader-data' )
			.appendTo( $thanksDiv )
			.append(
				this.makeReadOnlyInput( thumbWikiText, mw.message( 'mediauploader-thanks-wikitext' ).text(), true ),
				this.makeReadOnlyInput( upload.imageinfo.descriptionurl, mw.message( 'mediauploader-thanks-url' ).text() )
			);

		// This must match the CSS dimensions of .mediauploader-thumbnail
		upload.getThumbnail( 120, 120 ).done( ( thumb ) => {
			mw.UploadWizard.placeThumbnail( $thumbnailDiv, thumb );
		} );

		// Set the thumbnail links so that they point to the image description page
		$thumbnailLink.add( $thumbnailDiv.find( '.mediauploader-thumbnail-link' ) ).attr( {
			href: upload.imageinfo.descriptionurl,
			target: '_blank'
		} );

		this.$div.find( '.mediauploader-buttons' ).before( $thanksDiv );
	};

	/**
	 * Make an mw.widgets.CopyTextLayout, which features a button
	 * to copy the text provided.
	 *
	 * @param {string} value Text it will contain
	 * @param {string} label Label
	 * @param {string} [useEditFont] Use edit font (for wikitext values)
	 * @return {jQuery}
	 */
	uw.ui.Thanks.prototype.makeReadOnlyInput = function ( value, label, useEditFont ) {
		const copyText = new mw.widgets.CopyTextLayout( {
			align: 'top',
			label: label,
			copyText: value
		} );

		if ( useEditFont ) {
			// The following classes are used here:
			// * mw-editfont-monospace
			// * mw-editfont-sans-serif
			// * mw-editfont-serif
			copyText.textInput.$element.addClass( 'mw-editfont-' + mw.user.options.get( 'editfont' ) );
		}

		return copyText.$element;
	};

	/**
	 * Get button configuration options from a campaign definition
	 *
	 * @param {string} buttonName name of the button as defined in campaign configuration
	 * @param {string} configField name of the button's attributes
	 * @return {Object|undefined}
	 */
	uw.ui.Thanks.prototype.getButtonConfig = function ( buttonName, configField ) {
		if ( !this.config.display || !this.config.display[ buttonName ] ) {
			return;
		}

		return this.config.display[ buttonName ][ configField ];
	};

}( mw.uploadWizard ) );
