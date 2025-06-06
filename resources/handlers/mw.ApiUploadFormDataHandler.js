( function () {
	/**
	 * Represents an object which configures an html5 FormData object to upload.
	 * Large files are uploaded in chunks.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 * @param {mw.Api} api
	 */
	mw.ApiUploadFormDataHandler = function ( upload, api ) {
		mw.ApiUploadHandler.call( this, upload, api );

		this.formData = {
			action: 'upload',
			stash: 1,
			format: 'json'
		};

		this.transport = new mw.FormDataTransport(
			this.api,
			this.formData
		).on( 'update-stage', ( stage ) => {
			upload.ui.setStatus( 'mediauploader-' + stage );
		} );
	};

	OO.inheritClass( mw.ApiUploadFormDataHandler, mw.ApiUploadHandler );

	mw.ApiUploadFormDataHandler.prototype.abort = function () {
		this.transport.abort();
	};

	/**
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadFormDataHandler.prototype.submit = function () {
		const handler = this;

		return this.configureEditToken().then( () => {
			handler.beginTime = Date.now();
			handler.upload.ui.setStatus( 'mediauploader-transport-started' );
			handler.upload.ui.showTransportProgress();

			return handler.transport.upload( handler.upload.file, handler.upload.title.getMainText() )
				.progress( ( fraction ) => {
					if ( handler.upload.state === 'aborted' ) {
						handler.abort();
						return;
					}

					if ( fraction !== null ) {
						handler.upload.setTransportProgress( fraction );
					}
				} );
		} );
	};

	/**
	 * Obtain a fresh edit token.
	 * If successful, store token and call a callback.
	 *
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadFormDataHandler.prototype.configureEditToken = function () {
		const handler = this;

		return this.api.getEditToken().then( ( token ) => {
			handler.formData.token = token;
		} );
	};
}() );
