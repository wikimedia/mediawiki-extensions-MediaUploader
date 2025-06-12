( function () {

	/**
	 * Sets up indentation settings appropriate for YAML if CodeEditor is loaded.
	 */
	mw.hook( 'codeEditor.configure' ).add( ( session ) => {
		session.setOptions( {
			useSoftTabs: true,
			tabSize: 2
		} );
	} );
}() );
