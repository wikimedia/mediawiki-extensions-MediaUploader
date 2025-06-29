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
	QUnit.module( 'uw.controller.Step', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', ( assert ) => {
		const step = new uw.controller.Step( { on: function () {} }, new mw.Api(), {} );
		assert.true( !!step );
		assert.true( !!step.ui );
	} );

}( mw.uploadWizard ) );
