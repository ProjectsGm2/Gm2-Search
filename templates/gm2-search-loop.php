<?php
/**
 * Custom search results template for Gm2 product searches.
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

global $wp_query;

if ( ( function_exists( 'woocommerce_product_loop' ) && woocommerce_product_loop() ) || have_posts() ) {
    $has_before_shop_loop = has_action( 'woocommerce_before_shop_loop' );

    if ( function_exists( 'wc_setup_loop' ) && isset( $wp_query ) && $wp_query instanceof WP_Query ) {
        wc_setup_loop(
            [
                'total'        => $wp_query->found_posts,
                'total_pages'  => $wp_query->max_num_pages,
                'per_page'     => (int) $wp_query->get( 'posts_per_page' ),
                'current'      => max( 1, absint( get_query_var( 'paged', 1 ) ) ),
                'is_search'    => true,
                'is_paginated' => $wp_query->max_num_pages > 1,
            ]
        );
    }

    do_action( 'woocommerce_before_shop_loop' );

    if ( ! $has_before_shop_loop ) {
        if ( function_exists( 'woocommerce_result_count' ) ) {
            woocommerce_result_count();
        }

        if ( function_exists( 'woocommerce_catalog_ordering' ) ) {
            woocommerce_catalog_ordering();
        }
    }

    $gm2_loop_wrapper_started = false;

    if ( function_exists( 'woocommerce_product_loop_start' ) ) {
        woocommerce_product_loop_start();
    } else {
        echo '<ul class="products">';
        $gm2_loop_wrapper_started = true;
    }

    while ( have_posts() ) {
        the_post();
        gm2_search_render_product_card();
    }

    if ( function_exists( 'woocommerce_product_loop_end' ) ) {
        woocommerce_product_loop_end();
    } elseif ( $gm2_loop_wrapper_started ) {
        echo '</ul>';
    }

    do_action( 'woocommerce_after_shop_loop' );

    if ( function_exists( 'wc_reset_loop' ) ) {
        wc_reset_loop();
    }
} else {
    if ( has_action( 'woocommerce_no_products_found' ) ) {
        do_action( 'woocommerce_no_products_found' );
    } elseif ( function_exists( 'wc_no_products_found' ) ) {
        wc_no_products_found();
    }
}

get_footer( 'shop' );
