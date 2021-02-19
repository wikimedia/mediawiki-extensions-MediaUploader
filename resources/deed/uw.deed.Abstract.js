/*
 * This file is part of the MediaWiki extension UploadWizard.
 *
 * UploadWizard is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * UploadWizard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UploadWizard.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw ) {
	/**
	 * Represents a generic deed.
	 *
	 * @class uw.deed.Abstract
	 * @constructor
	 * @param {string} name The name of this step
	 * @param {Object} config The UW config
	 */
	uw.deed.Abstract = function UWDeedInterface( name, config ) {
		this.name = name;
		this.config = config;
		uw.deed.Abstract.prototype.instanceCount++;
		this.instanceCount = uw.deed.Abstract.prototype.instanceCount;
	};

	/**
	 * @type {number}
	 */
	uw.deed.Abstract.prototype.instanceCount = 0;

	uw.deed.Abstract.prototype.unload = function () {};

	/**
	 * @return {number}
	 */
	uw.deed.Abstract.prototype.getInstanceCount = function () {
		return this.instanceCount;
	};

	/**
	 * @return {uw.FieldLayout[]} Fields that need validation
	 */
	uw.deed.Abstract.prototype.getFields = function () {
		return [];
	};

	/**
	 * @param {jQuery} $selector
	 */
	uw.deed.Abstract.prototype.setFormFields = function () {};

	/**
	 * @method
	 * @abstract
	 * @param {mw.UploadWizardUpload} upload
	 * @return {string}
	 */
	uw.deed.Abstract.prototype.getSourceWikiText = null;

	/**
	 * @method
	 * @abstract
	 * @param {mw.UploadWizardUpload} upload
	 * @return {string}
	 */
	uw.deed.Abstract.prototype.getAuthorWikiText = null;

	/**
	 * Get wikitext representing the licenses selected in the license object
	 *
	 * @method
	 * @abstract
	 * @return {string} wikitext of all applicable license templates.
	 */
	uw.deed.Abstract.prototype.getLicenseWikiText = null;

	/**
	 * @return {Object}
	 */
	uw.deed.Abstract.prototype.getSerialized = function () {
		return {
			name: this.name
		};
	};

	/**
	 * @param {Object} serialized
	 */
	uw.deed.Abstract.prototype.setSerialized = function ( serialized ) {
		if ( serialized.name ) {
			this.name = serialized.name;
		}
	};

}( mw.uploadWizard ) );
