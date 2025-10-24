<?php
/**
 * Custom search results template for Gm2 product searches.
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

do_action( 'woocommerce_before_main_content' );

global $wp_query;

$render_state       = function_exists( 'gm2_search_get_results_template_render_state' ) ? gm2_search_get_results_template_render_state() : [ 'mode' => 'none', 'template_id' => 0 ];
$is_layout_template = ( isset( $render_state['mode'], $render_state['template_id'] ) && 'layout' === $render_state['mode'] && $render_state['template_id'] );
$layout_content     = '';

if ( $is_layout_template && function_exists( 'gm2_search_render_elementor_results_layout' ) ) {
    $layout_content = gm2_search_render_elementor_results_layout();

    if ( '' === trim( (string) $layout_content ) ) {
        $is_layout_template = false;
    }
}

if ( ( function_exists( 'woocommerce_product_loop' ) && woocommerce_product_loop() ) || have_posts() ) {
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

    if ( ! $is_layout_template ) {
        woocommerce_product_loop_start();
    }

    if ( $is_layout_template ) {
        echo $layout_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } else {
        while ( have_posts() ) {
            the_post();
            wc_get_template_part( 'content', 'product' );
        }
    }

    if ( ! $is_layout_template ) {
        woocommerce_product_loop_end();
    }

    do_action( 'woocommerce_after_shop_loop' );

    if ( function_exists( 'wc_reset_loop' ) ) {
        wc_reset_loop();
    }
} else {
    wc_no_products_found();
}

do_action( 'woocommerce_after_main_content' );
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
