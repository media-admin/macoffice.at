/* Leasing-Rechner: sendet den Betrag an die REST-Route und zeigt die Raten an. Ohne JavaScript funktioniert das Formular per POST. */
( function () {
    'use strict';

    var cfg = window.macofficeLeasing;
    if ( ! cfg ) {
        return;
    }

    document.querySelectorAll( '[data-leasing-form]' ).forEach( function ( form ) {
        var target = form.parentNode.querySelector( '[data-leasing-result]' );
        if ( ! target ) {
            return;
        }

        form.addEventListener( 'submit', function ( event ) {
            event.preventDefault();

            var body = new FormData( form );
            target.setAttribute( 'aria-busy', 'true' );

            fetch( cfg.endpoint, { method: 'POST', body: body, credentials: 'same-origin' } )
                .then( function ( response ) { return response.json(); } )
                .then( function ( data ) {
                    target.innerHTML = data && data.html ? data.html : '';
                } )
                .catch( function () {
                    target.textContent = cfg.error;
                } )
                .then( function () {
                    target.removeAttribute( 'aria-busy' );
                } );
        } );
    } );
}() );
