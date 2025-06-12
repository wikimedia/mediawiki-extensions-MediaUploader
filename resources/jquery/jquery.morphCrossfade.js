/**
 * jQuery Morphing Crossfade plugin
 * Copyright Neil Kandalgaonkar, 2010
 *
 * This work is licensed under the terms of the GNU General Public License,
 * version 2 or later.
 * (see http://www.fsf.org/licensing/licenses/gpl.html).
 * Derivative works and later versions of the code must be free software
 * licensed under the same or a compatible license.
 *
 * There are a lot of cross-fading plugins out there, but most assume that all
 * elements are the same, fixed width. This will also grow or shrink the container
 * vertically while crossfading. This can be useful when (for instance) you have a
 * control panel and you want to switch from a simpler interface to a more advanced
 * version. Or, perhaps you like the way the Mac OS X Preferences panel works, where
 * you click on an icon and get a crossfade effect to another dialog, even if it's one
 * with different dimensions.
 *
 * How to use it:
 * Create some DOM structure where all the panels you want to crossfade are contained in
 * one parent, e.g.
 *
 *  <div id="container">
 *    <div id="panel1"/>
 *    <div id="panel2"/>
 *    <div id="panel3"/>
 *  </div>
 *
 * Initialize the crossfader:
 *
 *   $( '#container' ).morphCrossfader();
 *
 * By default, this will hide all elements except the first child (in this case #panel1).
 *
 * Then, whenever you want to crossfade, do something like this. The currently selected panel
 * will fade away, and your selection will fade in.
 *
 *   $( '#container' ).morphCrossfade( '#panel2' );
 *
 */

( function () {
	/**
	 * Initialize crossfading of the children of an element
	 *
	 * @return {jQuery}
	 * @chainable
	 */
	$.fn.morphCrossfader = function () {
		const $this = $( this );
		// the elements that are immediate children are the crossfadables
		// they must all be "on top" of each other, so position them relative
		$this.css( {
			position: 'relative'
		} );
		$this.children().css( {
			position: 'absolute',
			top: 0,
			left: 0,
			opacity: 0,
			visibility: 'hidden'
		} );

		// should achieve the same result as crossfade( this.children().first() ) but without
		// animation etc.
		$this.each( function () {
			const $container = $( this );
			$container.morphCrossfade( $container.children().first(), 0 );
		} );

		return this;
	};

	/**
	 * Initialize crossfading of the children of an element
	 *
	 * @param {string} newPanelSelector Selector of new thing to show; should be an immediate child of the crossfader element
	 * @param {number} [speed] How fast to crossfade, in milliseconds
	 * @return {jQuery}
	 * @chainable
	 */
	$.fn.morphCrossfade = function ( newPanelSelector, speed ) {
		const $this = $( this );

		if ( typeof speed === 'undefined' ) {
			speed = 400;
		}

		$this.each( function () {
			const $container = $( this ),
				$oldPanel = $( $container.data( 'crossfadeDisplay' ) ),
				$newPanel = ( typeof newPanelSelector === 'string' ) ?
					$container.find( newPanelSelector ) : $( newPanelSelector );

			if ( $oldPanel.get( 0 ) !== $newPanel.get( 0 ) ) {
				if ( $oldPanel.length ) {
					// remove auto setting of height from container, and
					// make doubly sure that the container height is equal to oldPanel,
					// and prevent now-oversized panels from sticking out
					$container.css( { height: $oldPanel.outerHeight() } );
					// take it out of the flow
					$oldPanel.css( { position: 'absolute' } );
					// fade WITHOUT hiding when opacity = 0
					// eslint-disable-next-line no-jquery/no-animate
					$oldPanel.stop().animate( { opacity: 0 }, speed, 'linear', () => {
						$oldPanel.css( { visibility: 'hidden' } );
					} );
				}
				$container.data( 'crossfadeDisplay', $newPanel );

				$newPanel.css( { visibility: 'visible' } );
				// eslint-disable-next-line no-jquery/no-animate
				$container.stop().animate( { height: $newPanel.outerHeight() }, speed, 'linear', () => {
					// we place it back into the flow, in case its size changes.
					$newPanel.css( { position: 'relative' } );
					// and allow the container to grow with it.
					$container.css( { height: 'auto' } );
				} );
				// eslint-disable-next-line no-jquery/no-animate
				$newPanel.stop().animate( { opacity: 1 }, speed );
			}
		} );

		return this;
	};

}() );
