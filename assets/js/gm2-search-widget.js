( function( $ ) {
    'use strict';

    const namespace = '.gm2SearchTrigger';

    const applyTriggerBehaviour = function( $forms ) {
        if ( ! $forms || ! $forms.length ) {
            return;
        }

        $forms.each( function() {
            const $form   = $( this );
            const trigger = $form.data( 'submit-trigger' );
            const $input  = $form.find( '.elementor-search-form__input' );

            if ( ! $input.length ) {
                return;
            }

            $input.off( 'keydown' + namespace );

            if ( 'click_submit' === trigger ) {
                $input.on( 'keydown' + namespace, function( event ) {
                    if ( 'Enter' === event.key || 13 === event.which ) {
                        event.preventDefault();
                    }
                } );
            }
        } );
    };

    const initSearchWidget = function( $scope ) {
        applyTriggerBehaviour( $scope.find( 'form.elementor-search-form[data-submit-trigger]' ) );
    };

    $( function() {
        applyTriggerBehaviour( $( 'form.elementor-search-form[data-submit-trigger]' ) );
    } );

    $( window ).on( 'elementor/frontend/init', function() {
        if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
            elementorFrontend.hooks.addAction( 'frontend/element_ready/gm2-search-bar.default', initSearchWidget );
            elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function( $scope ) {
                if ( $scope.hasClass( 'elementor-widget-gm2-search-bar' ) ) {
                    initSearchWidget( $scope );
                }
            } );
        }
    } );
}( jQuery ) );
