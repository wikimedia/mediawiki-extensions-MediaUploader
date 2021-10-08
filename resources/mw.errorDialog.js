( function () {

	OO.ui.getWindowManager().addWindows( {
		upwizErrorDialog: new OO.ui.MessageDialog( { size: 'medium' } )
	} );

	/**
	 * Displays an error message.
	 *
	 * @param {string} errorMessage
	 * @param {string} [title]
	 */
	mw.errorDialog = function ( errorMessage, title ) {
		OO.ui.getWindowManager().openWindow( 'upwizErrorDialog', {
			message: errorMessage,
			title: title || mw.message( 'mediauploader-errordialog-title' ).text(),
			verbose: true,
			actions: [
				{
					label: mw.message( 'mediauploader-errordialog-ok' ).text(),
					action: 'accept'
				}
			]
		} );
	};

}() );
