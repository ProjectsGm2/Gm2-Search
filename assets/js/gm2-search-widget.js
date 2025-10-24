( function( $ ) {
    'use strict';

    const triggerNamespace = '.gm2SearchTrigger';
    const filterNamespace = '.gm2CategoryFilter';
    let filterDocumentEventsBound = false;
    let ajaxAugmentationBound = false;

    const collectSearchState = function( $context ) {
        const $forms = $context && $context.length ? $context.find( 'form.elementor-search-form' ) : $( 'form.elementor-search-form' );

        const state = {
            searchTerm: '',
            categoryFilter: '',
            categoryTaxonomy: '',
        };

        if ( ! $forms.length ) {
            return state;
        }

        $forms.each( function() {
            const $form = $( this );

            if ( ! state.searchTerm ) {
                const searchValue = $form.find( '.elementor-search-form__input' ).val();

                if ( searchValue ) {
                    state.searchTerm = searchValue;
                }
            }

            if ( ! state.categoryFilter ) {
                const categoryValue = $form.find( 'input[name="gm2_category_filter"]' ).val();

                if ( categoryValue ) {
                    state.categoryFilter = categoryValue;
                }
            }

            if ( ! state.categoryTaxonomy ) {
                const taxonomyValue = $form.find( 'input[name="gm2_category_taxonomy"]' ).val();

                if ( taxonomyValue ) {
                    state.categoryTaxonomy = taxonomyValue;
                }
            }
        } );

        return state;
    };

    const applyStateToSearchParams = function( params, state ) {
        if ( ! params || 'function' !== typeof params.set ) {
            return;
        }

        if ( state.searchTerm ) {
            params.set( 's', state.searchTerm );
        }

        if ( state.categoryFilter ) {
            params.set( 'gm2_category_filter', state.categoryFilter );
        }

        if ( state.categoryTaxonomy ) {
            params.set( 'gm2_category_taxonomy', state.categoryTaxonomy );
        }
    };

    const normaliseAjaxDataToParams = function( ajaxOptions ) {
        if ( ! ajaxOptions ) {
            return null;
        }

        if ( ajaxOptions.data instanceof URLSearchParams ) {
            return ajaxOptions.data;
        }

        if ( 'string' === typeof ajaxOptions.data ) {
            return new URLSearchParams( ajaxOptions.data );
        }

        if ( window.URLSearchParams && ajaxOptions.data && 'object' === typeof ajaxOptions.data && ! ( ajaxOptions.data instanceof FormData ) ) {
            const params = new URLSearchParams();

            Object.keys( ajaxOptions.data ).forEach( function( key ) {
                const value = ajaxOptions.data[ key ];

                if ( Array.isArray( value ) ) {
                    value.forEach( function( item ) {
                        params.append( key, item );
                    } );
                    return;
                }

                if ( null !== value && undefined !== value ) {
                    params.append( key, value );
                }
            } );

            return params;
        }

        return null;
    };

    const updateAjaxOptionsWithState = function( ajaxOptions, state ) {
        if ( ! ajaxOptions ) {
            return;
        }

        const params = normaliseAjaxDataToParams( ajaxOptions );

        if ( ! params || 'gm2_get_filter_products' !== params.get( 'action' ) ) {
            return;
        }

        applyStateToSearchParams( params, state );

        ajaxOptions.data = params.toString();

        if ( ! ajaxOptions.url ) {
            return;
        }

        try {
            const requestUrl = new URL( ajaxOptions.url, window.location.href );
            applyStateToSearchParams( requestUrl.searchParams, state );
            ajaxOptions.url = requestUrl.toString();
            return;
        } catch ( error ) {
            // Fallback for relative URLs that cannot be parsed via the URL constructor.
        }

        if ( ajaxOptions.url.indexOf( '?' ) === -1 ) {
            return;
        }

        const parts = ajaxOptions.url.split( '?' );
        const urlParams = new URLSearchParams( parts[1] );
        applyStateToSearchParams( urlParams, state );
        ajaxOptions.url = parts[0] + '?' + urlParams.toString();
    };

    const ensureAjaxAugmentation = function() {
        if ( ajaxAugmentationBound ) {
            return;
        }

        ajaxAugmentationBound = true;

        $( document ).ajaxSend( function( event, jqXHR, ajaxOptions ) {
            const state = collectSearchState();
            updateAjaxOptionsWithState( ajaxOptions, state );
        } );
    };

    const closeCategoryDropdown = function( $filter, focusToggle ) {
        if ( ! $filter || ! $filter.length ) {
            return;
        }

        const $dropdown = $filter.find( '.gm2-category-filter__dropdown' );
        const $toggle   = $filter.find( '.gm2-category-filter__toggle' );

        $filter.removeClass( 'gm2-category-filter--open' );

        if ( $dropdown.length ) {
            $dropdown.attr( 'hidden', 'hidden' );
        }

        if ( $toggle.length ) {
            $toggle.attr( 'aria-expanded', 'false' );

            if ( focusToggle ) {
                $toggle.trigger( 'focus' );
            }
        }
    };

    const closeAllCategoryDropdowns = function( excludeFilter ) {
        $( '.gm2-category-filter--multi.gm2-category-filter--open' ).each( function() {
            const $filter = $( this );

            if ( excludeFilter && $filter.is( excludeFilter ) ) {
                return;
            }

            closeCategoryDropdown( $filter );
        } );
    };

    const bindCategoryDocumentEvents = function() {
        if ( filterDocumentEventsBound ) {
            return;
        }

        filterDocumentEventsBound = true;

        $( document ).on( 'click' + filterNamespace, function( event ) {
            const $target = $( event.target );

            if ( $target.closest( '.gm2-category-filter--multi' ).length ) {
                return;
            }

            closeAllCategoryDropdowns();
        } );

        $( document ).on( 'keydown' + filterNamespace, function( event ) {
            if ( 'Escape' === event.key || 27 === event.which ) {
                closeAllCategoryDropdowns();
            }
        } );
    };

    const updateCategoryFilterValue = function( $filter, placeholder ) {
        const $checkboxes = $filter.find( '.gm2-category-filter__checkbox' );
        const $hidden     = $filter.find( '.gm2-category-filter__value-input' );
        const $valueText  = $filter.find( '.gm2-category-filter__value-text' );

        if ( ! $hidden.length || ! $valueText.length ) {
            return;
        }

        const values = [];
        const labels = [];

        $checkboxes.each( function() {
            const $checkbox = $( this );

            if ( $checkbox.prop( 'checked' ) ) {
                values.push( $checkbox.val() );
                const label = $checkbox.data( 'label' );
                labels.push( label ? label : $checkbox.closest( '.gm2-category-filter__option-label' ).text().trim() );
            }
        } );

        $hidden.val( values.join( ',' ) );

        if ( labels.length ) {
            $valueText.text( labels.join( ', ' ) );
            $filter.addClass( 'gm2-category-filter--has-value' );
        } else {
            $valueText.text( placeholder );
            $filter.removeClass( 'gm2-category-filter--has-value' );
        }

        $filter.find( '.gm2-category-filter__option' ).each( function() {
            const $option   = $( this );
            const $checkbox = $option.find( '.gm2-category-filter__checkbox' );
            const selected  = $checkbox.length && $checkbox.prop( 'checked' );

            $option.attr( 'aria-selected', selected ? 'true' : 'false' );
        } );
    };

    const initCategoryMultiSelect = function( $scope ) {
        const $filters = $scope.find( '.gm2-category-filter--multi' );

        if ( ! $filters.length ) {
            return;
        }

        bindCategoryDocumentEvents();

        $filters.each( function() {
            const $filter = $( this );

            if ( $filter.data( 'gm2CategoryInit' ) ) {
                return;
            }

            const $toggle     = $filter.find( '.gm2-category-filter__toggle' );
            const $dropdown   = $filter.find( '.gm2-category-filter__dropdown' );
            const $checkboxes = $filter.find( '.gm2-category-filter__checkbox' );
            const placeholder = $filter.data( 'placeholder' ) || '';

            if ( ! $toggle.length || ! $dropdown.length ) {
                return;
            }

            $filter.data( 'gm2CategoryInit', true );

            const updateValue = function() {
                updateCategoryFilterValue( $filter, placeholder );
            };

            updateValue();

            $toggle.on( 'click' + filterNamespace, function( event ) {
                event.preventDefault();
                event.stopPropagation();

                if ( $filter.hasClass( 'gm2-category-filter--open' ) ) {
                    closeCategoryDropdown( $filter );
                    return;
                }

                closeAllCategoryDropdowns( $filter );
                $filter.addClass( 'gm2-category-filter--open' );
                $dropdown.removeAttr( 'hidden' );
                $toggle.attr( 'aria-expanded', 'true' );
            } );

            $checkboxes.on( 'change' + filterNamespace, function() {
                updateValue();
            } );

            $dropdown.on( 'click' + filterNamespace, function( event ) {
                event.stopPropagation();
            } );

            $filter.on( 'keydown' + filterNamespace, function( event ) {
                if ( 'Escape' === event.key || 27 === event.which ) {
                    closeCategoryDropdown( $filter, true );
                }
            } );

            $filter.closest( 'form' ).on( 'submit' + filterNamespace, function() {
                closeCategoryDropdown( $filter );
            } );
        } );
    };

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

            $input.off( 'keydown' + triggerNamespace );

            if ( 'click_submit' === trigger ) {
                $input.on( 'keydown' + triggerNamespace, function( event ) {
                    if ( 'Enter' === event.key || 13 === event.which ) {
                        event.preventDefault();
                    }
                } );
            }
        } );
    };

    const initSearchWidget = function( $scope ) {
        applyTriggerBehaviour( $scope.find( 'form.elementor-search-form[data-submit-trigger]' ) );
        initCategoryMultiSelect( $scope );
        ensureAjaxAugmentation();
    };

    $( function() {
        applyTriggerBehaviour( $( 'form.elementor-search-form[data-submit-trigger]' ) );
        initCategoryMultiSelect( $( document ) );
        ensureAjaxAugmentation();
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
