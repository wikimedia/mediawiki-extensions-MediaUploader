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
	QUnit.module( 'uw.controller.Details', QUnit.newMwEnvironment() );

	function createTestUpload( sandbox, customDeedChooser, aborted ) {
		const stubs = {
			ucdc: sandbox.stub(),
			getSerialized: sandbox.stub(),
			setSerialized: sandbox.stub(),
			attach: sandbox.stub()
		};

		return {
			deedChooser: { deed: { name: customDeedChooser ? 'custom' : 'cc-by-sa-4.0' } },

			on: function () {},

			details: {
				useCustomDeedChooser: stubs.ucdc,
				getSerialized: stubs.getSerialized,
				setSerialized: stubs.setSerialized,
				attach: stubs.attach
			},

			state: aborted ? 'aborted' : 'stashed',

			stubs: stubs
		};
	}

	QUnit.test( 'Constructor sanity test', ( assert ) => {
		const step = new uw.controller.Details( new mw.Api(), {
			maxSimultaneousConnections: 1
		} );
		assert.true( !!step );
		assert.true( step instanceof uw.controller.Step );
		assert.true( !!step.ui );
	} );

	QUnit.test( 'load', function ( assert ) {
		let step = new uw.controller.Details( new mw.Api(), {
				maxSimultaneousConnections: 1
			} ),
			testUpload = createTestUpload( this.sandbox ),
			stepUiStub = this.sandbox.stub( step.ui, 'load' );

		// replace createDetails with a stub; UploadWizardDetails needs way too
		// much setup to actually be able to create it
		step.createDetails = this.sandbox.stub();

		step.load( [ testUpload ] );

		assert.strictEqual( testUpload.stubs.ucdc.called, false );
		assert.strictEqual( step.createDetails.callCount, 1 );
		assert.true( stepUiStub.called );

		testUpload = createTestUpload( this.sandbox, true );
		step.load( [ testUpload ] );

		assert.true( testUpload.stubs.ucdc.called );
		assert.strictEqual( step.createDetails.callCount, 2 );
		assert.true( stepUiStub.called );

		testUpload = createTestUpload( this.sandbox );
		step.load( [ testUpload, createTestUpload( this.sandbox ) ] );

		assert.strictEqual( testUpload.stubs.ucdc.called, false );
		assert.strictEqual( step.createDetails.callCount, 4 );
		assert.true( stepUiStub.called );

		testUpload = createTestUpload( this.sandbox );
		step.load( [ testUpload, createTestUpload( this.sandbox, false, true ) ] );

		assert.strictEqual( testUpload.stubs.ucdc.called, false );
		assert.strictEqual( step.createDetails.callCount, 6 );
		assert.true( stepUiStub.called );
	} );

	QUnit.test( 'canTransition', ( assert ) => {
		const upload = {},
			step = new uw.controller.Details( new mw.Api(), {
				maxSimultaneousConnections: 1
			} );

		assert.strictEqual( step.canTransition( upload ), false );
		upload.state = 'details';
		assert.strictEqual( step.canTransition( upload ), true );
		upload.state = 'complete';
		assert.strictEqual( step.canTransition( upload ), false );
	} );

	QUnit.test( 'transitionAll', function ( assert ) {
		let tostub,
			done = assert.async(),
			donestub = this.sandbox.stub(),
			ds = [ $.Deferred(), $.Deferred(), $.Deferred() ],
			ps = [ ds[ 0 ].promise(), ds[ 1 ].promise(), ds[ 2 ].promise() ],
			calls = [],
			step;

		tostub = this.sandbox.stub( uw.controller.Details.prototype, 'transitionOne' );
		tostub.onFirstCall().returns( ps[ 0 ] );
		tostub.onSecondCall().returns( ps[ 1 ] );
		tostub.onThirdCall().returns( ps[ 2 ] );

		this.sandbox.stub( uw.controller.Details.prototype, 'canTransition' ).returns( true );

		step = new uw.controller.Details( new mw.Api(), {
			maxSimultaneousConnections: 3
		} );

		step.uploads = [
			{ id: 15 },
			undefined,
			{ id: 21 },
			{ id: 'aoeu' }
		];

		step.transitionAll().done( donestub );
		setTimeout( () => {
			calls = [ tostub.getCall( 0 ), tostub.getCall( 1 ), tostub.getCall( 2 ) ];

			assert.strictEqual( calls[ 0 ].args[ 0 ].id, 15 );
			assert.strictEqual( calls[ 1 ].args[ 0 ].id, 21 );

			ds[ 0 ].resolve();
			ds[ 1 ].resolve();
			setTimeout( () => {
				assert.strictEqual( donestub.called, false );

				ds[ 2 ].resolve();
				setTimeout( () => {
					assert.true( donestub.called );

					done();
				} );
			} );
		} );
	} );

}( mw.uploadWizard ) );
