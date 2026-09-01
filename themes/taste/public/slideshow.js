/**
 * Taste Theme - Immediate, manual slideshow navigation without autoplay or animation
 * @license MIT, https://opensource.org/license/MIT
 */

window.addEventListener( 'load', () => {
    document.querySelectorAll( '.swiffy-slider' ).forEach( slider => {
        const container = slider.querySelector( '.slider-container' );

        if( !container ) {
            return;
        }

        slider.querySelectorAll( '.slider-nav' ).forEach( button => {
            button.addEventListener( 'click', () => {
                const width = container.firstElementChild?.getBoundingClientRect().width || container.clientWidth;
                const next = button.classList.contains( 'slider-nav-next' );
                const end = container.scrollWidth - container.clientWidth;
                let left = container.scrollLeft + ( next ? width : -width );

                if( next && container.scrollLeft >= end - 1 ) {
                    left = 0;
                } else if( !next && container.scrollLeft <= 1 ) {
                    left = end;
                }

                container.scrollTo( {left, behavior: 'auto'} );
            }, {passive: true} );
        } );
    } );
} );
