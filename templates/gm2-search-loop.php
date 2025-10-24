<?php
/**
 * Custom search results template for Gm2 product searches.
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

if ( function_exists( 'woocommerce_product_loop' ) && woocommerce_product_loop() ) :
    do_action( 'woocommerce_before_shop_loop' );

    woocommerce_product_loop_start();

    while ( have_posts() ) :
        the_post();
        gm2_search_render_product_card();
    endwhile;

    woocommerce_product_loop_end();

    do_action( 'woocommerce_after_shop_loop' );
else :
    do_action( 'woocommerce_no_products_found' );
endif;

get_footer( 'shop' );
