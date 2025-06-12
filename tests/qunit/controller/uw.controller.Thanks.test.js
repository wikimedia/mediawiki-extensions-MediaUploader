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
	QUnit.module( 'uw.controller.Thanks', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', ( assert ) => {
		const step = new uw.controller.Thanks( new mw.Api(), { display: { thanksLabel: 'Thanks!' } } );
		assert.true( !!step );
		assert.true( step instanceof uw.controller.Step );
		assert.true( !!step.ui );
	} );

	QUnit.test( 'load', function ( assert ) {
		const step = new uw.controller.Thanks( new mw.Api(), {} ),
			auStub = this.sandbox.stub( step.ui, 'addUpload' );

		this.sandbox.stub( step.ui, 'load' );
		step.load( [
			{ on: function () {} },
			{ on: function () {} },
			{ on: function () {} }
		] );

		assert.strictEqual( auStub.callCount, 3 );
	} );

	QUnit.test( 'Custom button configuration', ( assert ) => {
		const config = {
				display: {
					homeButton: {
						label: 'This is just a test',
						target: 'https://wiki.example.com/wiki/Main_Page'
					},
					beginButton: {
						label: 'Let me start again',
						target: 'https://commons.wikimedia.org/wiki/Special:UploadWizard'
					}
				}
			},
			uiThanks = new uw.ui.Thanks( config );

		assert.strictEqual(
			uiThanks.homeButton.getLabel(),
			'This is just a test',
			'The label of the home button matches the configured text.'
		);

		assert.strictEqual(
			uiThanks.homeButton.getHref(),
			'https://wiki.example.com/wiki/Main_Page',
			'The target of the home button matches the configured URL.'
		);

		assert.strictEqual(
			uiThanks.beginButton.getLabel(),
			'Let me start again',
			'The label of the begin button matches the configured text.'
		);

		assert.strictEqual(
			uiThanks.beginButton.getHref(),
			'https://commons.wikimedia.org/wiki/Special:UploadWizard',
			'The target of the begin button matches the configured URL.'
		);

	} );

}( mw.uploadWizard ) );
