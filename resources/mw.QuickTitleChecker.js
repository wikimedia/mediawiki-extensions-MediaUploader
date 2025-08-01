/**
 * Does some local checking of a filename.
 * If you want more specific filename checks (such as bad words, other blacklisted stuff) use the TitleBlacklist API.
 *
 * These checks are ignored when the TitleBlacklist API is available, since presumably they should be configured there instead, where it's
 * possible to update them easily and higher-quality error messages are available.
 *
 * This is an incomplete rendering of some of the meta.wikimedia.org and commons.wikimedia.org blacklist as they existed on 2011-05-05, and
 * ignores cases that are irrelevant to uploading new media images.
 *   - all regexes are case INsensitive by default
 *   - namespaces and File: prefix are removed since everything we upload is under File: anyway
 *   - noedit, moveonly, repuload is irrelevant
 *   - we can't check autoconfirmed-ness of users here, so we ignore it
 *   - Javascript doesn't have a standard way to access unicode character properties in regexes, so \p{PROPERTY}, \P{PROPERTY}, and [[:PROPERTY:]] have been changed when possible
 *     or the associated regex removed
 */
( function () {

	mw.QuickTitleChecker = {};

	mw.QuickTitleChecker.regexSets = {

		invalid: [
			/[\u00A0\u1680\u180E\u2000-\u200B\u2028\u2029\u202F\u205F\u3000]/, // NBSP and other unusual spaces
			/[\u202A-\u202E]/, // BiDi overrides
			// eslint-disable-next-line no-control-regex
			/[\x00-\x1f]/, // Control characters
			/\uFEFF/, // Byte order mark
			/\u00AD/, // Soft-hyphen
			/[\uD800-\uDFFF\uE000-\uF8FF\uFFF0-\uFFFF]/, // Surrogates, Private Use Area and Specials, including the Replacement Character U+FFFD
			/[^\0-\uFFFF]/, //  Very few characters outside the Basic Multilingual Plane are useful in titles
			/''/
		],

		// note lack of extension, since we test title without extension.
		senselessimagename: [
			/^DCP[\d\s]+$/i, //  Kodak
			/^DSC.[\d\s]+$/i, //  [[w:Design rule for Camera File system]] (Nikon, Fuji, Polaroid)
			/^MVC-?[\d\s]+$/i, //  Sony Mavica
			/^P[\dA-F][\d\s]+$/, //  Olympus, Kodak
			/^I?MG[P_]?[\d\s]+$/, //  Canon, Pentax
			/^1\d+-\d+(_IMG)?$/, //  Canon
			/^(IM|EX)[\d\s]+$/, //  HP Photosmart
			/^DC[\d\s]+[SML]$/, //  Kodak
			/^PIC[T_]?[\d\s]+$/, //  Minolta
			/^PANA[\d\s]+$/, //  Panasonic
			/^DUW[\d\s]+$/, //  some mobile phones
			/^CIMG[\d\s]+$/, //  Casio
			/^JD[\d\s]+$/, //  Jenoptik
			/^SDC[\d\s]+$/, //  Samsung
			/^DVC[\d\s]+$/, //  DoCoMo
			/^SANY[\d\s]+$/ //  Sanyo
		],

		thumbnail: [
			/^\d+px-.*/
		],

		extension: [
			/\.(jpe?g|png|gif|svg|ogg|ogv|oga)$/
		]

	};

	/**
	 * Check title for validity, without relying on TitleBlacklist extension or API requests.
	 *
	 * @param {string} title
	 * @return {string[]} Array of error codes; if it's empty, the title is acceptable.
	 *   Possible error codes are 'invalid', 'senselessimagename', 'thumbnail', 'extension'.
	 */
	mw.QuickTitleChecker.checkTitle = function ( title ) {
		const errors = [];
		Object.keys( mw.QuickTitleChecker.regexSets ).forEach( ( setName ) => {
			const regexes = mw.QuickTitleChecker.regexSets[ setName ];
			regexes.forEach( ( regex ) => {
				if ( title.match( regex ) ) {
					errors.push( setName );
				}
			} );
		} );
		return errors;
	};

}() );
