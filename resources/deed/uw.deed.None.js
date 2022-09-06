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
	 * Pseudo-deed used when the licensing step is disabled entirely.
	 *
	 * @param {Object} config The UW config
	 * @class uw.deed.None
	 * @constructor
	 */
	uw.deed.None = function UWDeedNone( config ) {
		uw.deed.Abstract.call( this, 'none', config );
	};

	OO.inheritClass( uw.deed.None, uw.deed.Abstract );

	/**
	 * @inheritdoc
	 */
	uw.deed.None.prototype.getSourceWikiText = function () {
		return null;
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.None.prototype.getAuthorWikiText = function () {
		return null;
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.None.prototype.getLicenseWikiText = function () {
		return null;
	};
}( mw.uploadWizard ) );
