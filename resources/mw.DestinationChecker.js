( function () {

	mw.DestinationChecker = {

		api: new mw.Api(),

		// cached results from uniqueness api calls
		cachedResult: {},
		cachedBlacklist: {},

		/**
		 * Check title for validity.
		 *
		 * @param {string} title Title to check
		 * @return {jQuery.Promise}
		 *  {Function} return.done
		 *  {string} return.done.title The title that was passed in
		 *  {Object|boolean} return.done.blacklist See #checkBlacklist
		 *  {Object|boolean} return.done.unique See #checkUnique
		 */
		checkTitle: function ( title ) {
			return $.when(
				this.checkUnique( title ),
				this.checkBlacklist( title )
			).then( function ( unique, blacklist ) {
				return {
					unique: unique,
					blacklist: blacklist,
					title: title
				};
			} );
		},

		/**
		 * Async check if a title is in the titleblacklist.
		 *
		 * @param {string} title Title to check against the blacklist
		 * @return {jQuery.Promise}
		 *  {Function} return.done
		 *  {boolean} return.done.notBlacklisted
		 *  {string} [return.done.blacklistReason] See mw.Api#isBlacklisted
		 *  {string} [return.done.blacklistMessage] See mw.Api#isBlacklisted
		 *  {string} [return.done.blacklistLine] See mw.Api#isBlacklisted
		 */
		checkBlacklist: function ( title ) {
			var checker = this;

			/**
			 * Process result of a TitleBlacklist API call.
			 *
			 * @private
			 * @param {Object|boolean} blacklistResult `false` if not blacklisted, object if blacklisted
			 * @return {Object}
			 */
			function blacklistResultProcessor( blacklistResult ) {
				var result;

				if ( blacklistResult === false ) {
					result = { notBlacklisted: true };
				} else {
					result = {
						notBlacklisted: false,
						blacklistReason: blacklistResult.reason,
						blacklistMessage: blacklistResult.message,
						blacklistLine: blacklistResult.line
					};
				}

				checker.cachedBlacklist[ title ] = result;
				return result;
			}

			if ( this.cachedBlacklist[ title ] !== undefined ) {
				return $.Deferred().resolve( this.cachedBlacklist[ title ] );
			}

			return mw.loader.using( 'mediawiki.api.titleblacklist' ).then( function () {
				return checker.api.isBlacklisted( title ).then( blacklistResultProcessor );
			}, function () {
				// it's not blacklisted, because the API isn't even available
				return $.Deferred().resolve( { notBlacklisted: true, unavailable: true } );
			} );
		},

		/**
		 * Async check if a filename is unique. Can be attached to a field's change() event
		 * This is a more abstract version of AddMedia/UploadHandler.js::doDestCheck
		 *
		 * @param {string} title Title to check for uniqueness
		 * @return {jQuery.Promise}
		 *  {Function} return.done
		 *  {boolean} return.done.isUnique
		 *  {boolean} [return.done.isProtected]
		 *  {Object} [return.done.img] Image info
		 *  {string} [return.done.href] URL to file description page
		 */
		checkUnique: function ( title ) {
			var checker = this,
				NS_FILE = mw.config.get( 'wgNamespaceIds' ).file,
				titleObj, prefix, ext;

			titleObj = mw.Title.newFromText( title );
			ext = mw.Title.normalizeExtension( titleObj.getExtension() || '' );
			// Strip namespace and file extension
			prefix = titleObj.getNameText();

			/**
			 * Process result of a an imageinfo API call.
			 *
			 * @private
			 * @param {Object} data API result
			 * @return {Object}
			 */
			function checkUniqueProcessor( data ) {
				var result, protection, pageId, ntitle, ntitleObj, img;

				result = { isUnique: true };

				if ( data.query && data.query.pages ) {
					// The API will check for files with that filename.
					// If no file found: a page with a key of -1 and no imageinfo
					// If file found on another repository, such as when the wiki is using InstantCommons: page with a key of -1, plus imageinfo
					// If file found on this repository: page with some positive numeric key
					if ( data.query.pages[ -1 ] && !data.query.pages[ -1 ].imageinfo ) {
						protection = data.query.pages[ -1 ].protection;
						if ( protection && protection.length > 0 ) {
							protection.forEach( function ( val ) {
								if ( mw.config.get( 'wgUserGroups' ).indexOf( val.level ) === -1 ) {
									result = {
										isUnique: true,
										isProtected: true
									};
								}
							} );
						} else {
							// No conflict found on any repository this wiki uses
							result = { isUnique: true };
						}
					} else {
						for ( pageId in data.query.pages ) {
							if ( !Object.prototype.hasOwnProperty.call( data.query.pages, pageId ) ) {
								continue;
							}
							ntitle = data.query.pages[ pageId ].title;
							ntitleObj = mw.Title.newFromText( ntitle );
							if ( ntitleObj.getNameText() !== prefix ) {
								// It's a different file name entirely
								continue;
							}
							if ( ext !== mw.Title.normalizeExtension( ntitleObj.getExtension() || '' ) ) {
								// It's a different extension, that's fine (e.g. to upload a SVG version of a PNG file)
								continue;
							}

							// Conflict found, this filename is NOT unique

							if ( !data.query.pages[ pageId ].imageinfo ) {
								// This means that there's a page, but it's not a file. Well,
								// we should really report that anyway, but we shouldn't process
								// it like a file, and we should defer to other entries that may be files.
								result = {
									isUnique: false,
									title: ntitle,
									img: null,
									href: null
								};
								continue;
							}

							img = data.query.pages[ pageId ].imageinfo[ 0 ];

							result = {
								isUnique: false,
								img: img,
								title: ntitle,
								href: img.descriptionurl
							};

							break;
						}
					}
				}

				return result;
			}

			if ( this.cachedResult[ title ] !== undefined ) {
				return $.Deferred().resolve( this.cachedResult[ title ] );
			}

			// Setup the request -- will return thumbnail data if it finds one
			// XXX do not use iiurlwidth as it will create a thumbnail
			return $.when(
				// Checks for exact matches on this wiki and foreign file repos
				this.api.get( {
					action: 'query',
					titles: title,
					prop: 'info|imageinfo',
					inprop: 'protection',
					iiprop: 'url|mime|size',
					iiurlwidth: 150
				} ).then( checkUniqueProcessor ),
				// Checks for matches with different versions of the file extension on this wiki only
				this.api.get( {
					action: 'query',
					generator: 'allpages',
					gapnamespace: NS_FILE,
					gapprefix: prefix,
					prop: 'info|imageinfo',
					inprop: 'protection',
					iiprop: 'url|mime|size',
					iiurlwidth: 150
				} ).then( checkUniqueProcessor )
			).then( function ( exact, fuzzy ) {
				var result;
				if ( !exact.isUnique || exact.isProtected ) {
					result = exact;
				} else if ( !fuzzy.isUnique || fuzzy.isProtected ) {
					result = fuzzy;
				} else {
					result = { isUnique: true };
				}

				checker.cachedResult[ title ] = result;
				return result;
			} );
		},

		/**
		 * Clears the result cache
		 */
		clearCache: function () {
			this.cachedResult = {};
		}

	};

}() );
