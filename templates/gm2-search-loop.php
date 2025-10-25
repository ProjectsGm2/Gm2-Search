<?php
/**
 * Custom search results template for Gm2 product searches.
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

global $wp_query;

$current_page         = max( 1, absint( get_query_var( 'paged', 1 ) ) );
$total_pages          = ( $wp_query instanceof WP_Query ) ? (int) $wp_query->max_num_pages : 0;
$pagination_priority  = has_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination' );
$render_after_shop_loop = static function () use ( $pagination_priority ) {
    if ( false !== $pagination_priority ) {
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', (int) $pagination_priority );
    }

    do_action( 'woocommerce_after_shop_loop' );

    if ( false !== $pagination_priority ) {
        add_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', (int) $pagination_priority );
    }
};

$render_pagination = static function ( $current_page, $total_pages ) {
    if ( $total_pages <= 1 ) {
        return;
    }

    $add_args = function_exists( 'gm2_search_get_active_query_args' ) ? gm2_search_get_active_query_args() : [];

    $add_args = array_filter(
        $add_args,
        static function ( $value ) {
            if ( is_array( $value ) ) {
                $filtered = array_filter(
                    $value,
                    static function ( $inner ) {
                        return '' !== $inner && null !== $inner;
                    }
                );

                return ! empty( $filtered );
            }

            return '' !== $value && null !== $value;
        }
    );

    $pagination_args = [
        'total'     => $total_pages,
        'current'   => $current_page,
        'type'      => 'list',
        'prev_text' => __( '« Previous', 'woo-search-optimized' ),
        'next_text' => __( 'Next »', 'woo-search-optimized' ),
    ];

    if ( ! empty( $add_args ) ) {
        $pagination_args['add_args'] = $add_args;
    }

    $links = paginate_links( $pagination_args );

    if ( empty( $links ) ) {
        return;
    }
    ?>
    <nav class="gm2-search-pagination woocommerce-pagination" role="navigation" aria-label="<?php echo esc_attr__( 'Products navigation', 'woo-search-optimized' ); ?>">
        <?php echo wp_kses_post( $links ); ?>
    </nav>
    <?php
};

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
    $has_before_shop_loop = has_action( 'woocommerce_before_shop_loop' );

    if ( function_exists( 'wc_setup_loop' ) && isset( $wp_query ) && $wp_query instanceof WP_Query ) {
        wc_setup_loop(
            [
                'total'        => $wp_query->found_posts,
                'total_pages'  => $total_pages,
                'per_page'     => (int) $wp_query->get( 'posts_per_page' ),
                'current'      => $current_page,
                'is_search'    => true,
                'is_paginated' => $total_pages > 1,
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

    if ( ! $is_layout_template ) {
        if ( function_exists( 'woocommerce_product_loop_start' ) ) {
            woocommerce_product_loop_start();
        } else {
            echo '<ul class="products">';
            $gm2_loop_wrapper_started = true;
        }
    }

    if ( $is_layout_template ) {
        echo $layout_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } else {
        while ( have_posts() ) {
            the_post();
            gm2_search_render_product_card();
        }
    }

    if ( ! $is_layout_template ) {
        if ( function_exists( 'woocommerce_product_loop_end' ) ) {
            woocommerce_product_loop_end();
        } elseif ( $gm2_loop_wrapper_started ) {
            echo '</ul>';
        }
    }

    $render_after_shop_loop();

    $render_pagination( $current_page, $total_pages );

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
