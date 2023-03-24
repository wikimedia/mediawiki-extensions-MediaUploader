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
	QUnit.module( 'uw.controller.Tutorial', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', function ( assert ) {
		var step = new uw.controller.Tutorial( new mw.Api() );
		assert.true( !!step );
		assert.true( step instanceof uw.controller.Step );
		assert.true( !!step.ui );
		assert.true( !!step.api );
	} );

	QUnit.test( 'setSkipPreference', function ( assert ) {
		var mnStub,
			api = new mw.Api(),
			step = new uw.controller.Tutorial( api ),
			acwStub = { release: this.sandbox.stub() },
			pwtd = $.Deferred();

		this.sandbox.stub( mw, 'confirmCloseWindow' ).returns( acwStub );
		this.sandbox.stub( api, 'postWithToken' ).returns( pwtd.promise() );

		step.setSkipPreference( true );

		assert.true( mw.confirmCloseWindow.called );
		assert.true( api.postWithToken.calledWithExactly( 'options', {
			action: 'options',
			change: 'upwiz_skiptutorial=1'
		} ) );

		pwtd.resolve();
		assert.true( acwStub.release.called );

		api = new mw.Api();
		step = new uw.controller.Tutorial( api );
		acwStub.release.reset();
		pwtd = $.Deferred();
		mnStub = this.sandbox.stub( mw, 'notify' );

		this.sandbox.stub( api, 'postWithToken' ).returns( pwtd.promise() );

		step.setSkipPreference( true );
		assert.false( acwStub.release.called );

		pwtd.reject( 'http', { textStatus: 'Foo bar' } );
		assert.true( mnStub.calledWith( 'Foo bar' ) );
	} );
}( mw.uploadWizard ) );
