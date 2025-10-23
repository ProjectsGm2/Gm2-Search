<?php
/**
 * Plugin Name:       Woo Search Optimized
 * Plugin URI:        https://gm2web.com/
 * Description:       Optimized WooCommerce product search that includes product title, price, description, attributes, and SKU with weighted ranking.
 * Version:           1.8.1
 * Author:            GM2 Web
 * Author URI:        https://gm2web.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-search-optimized
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add JOINs for _price, _sku, and aggregated product attributes.
 * Unique alias names are used to avoid conflicts.
 */
function woo_search_opt_joins( $join, $wp_query ) {
    global $wpdb;
    
    $search_term = $wp_query->get('s');
    if ( empty( $search_term ) ) {
        return $join;
    }
    
    // Only modify queries for products.
    $post_types = $wp_query->get('post_type');
    if ( (is_array($post_types) && ! in_array('product', $post_types)) || 
         (!is_array($post_types) && 'product' !== $post_types) ) {
        return $join;
    }
    
    // Join postmeta for price and SKU.
    $join .= " LEFT JOIN {$wpdb->postmeta} AS woo_pm_price ON ({$wpdb->posts}.ID = woo_pm_price.post_id AND woo_pm_price.meta_key = '_price') ";
    $join .= " LEFT JOIN {$wpdb->postmeta} AS woo_pm_sku ON ({$wpdb->posts}.ID = woo_pm_sku.post_id AND woo_pm_sku.meta_key = '_sku') ";
    
    // Join a subquery to aggregate attribute names (from taxonomies starting with 'pa_').
    $join .= " LEFT JOIN (
                SELECT tr.object_id, GROUP_CONCAT(t.name SEPARATOR ' ') AS attributes
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                WHERE tt.taxonomy LIKE 'pa\\_%'
                GROUP BY tr.object_id
               ) AS woo_attr ON woo_attr.object_id = {$wpdb->posts}.ID ";
    
    return $join;
}
add_filter('posts_join', 'woo_search_opt_joins', 20, 2);

/**
 * Use the posts_search filter to add custom search conditions.
 * This will combine the default search conditions with our optimized conditions.
 */
function woo_search_opt_posts_search( $search, $wp_query ) {
    global $wpdb;
    
    $search_term = $wp_query->get('s');
    if ( empty( $search_term ) ) {
        return $search;
    }
    
    // Only affect product queries.
    $post_types = $wp_query->get('post_type');
    if ( (is_array($post_types) && ! in_array('product', $post_types)) ||
         (!is_array($post_types) && 'product' !== $post_types) ) {
        return $search;
    }
    
    // Prepare search patterns.
    $like = '%' . $wpdb->esc_like( $search_term ) . '%';
    $price_stripped = str_replace( '$', '', $search_term );
    $price_like_stripped = '%' . $wpdb->esc_like( $price_stripped ) . '%';
    
    // Build our custom search conditions.
    $custom_search = "(
         {$wpdb->posts}.post_title LIKE '{$like}'
         OR {$wpdb->posts}.post_content LIKE '{$like}'
         OR (woo_pm_price.meta_value LIKE '{$like}' OR woo_pm_price.meta_value LIKE '{$price_like_stripped}')
         OR (woo_attr.attributes LIKE '{$like}')
         OR (woo_pm_sku.meta_value LIKE '{$like}')
    )";
    
    // Combine with the default search conditions.
    if ( ! empty( $search ) ) {
        // Remove any leading "AND" from the default search clause.
        $search = preg_replace('/^\s*AND\s*/', '', $search);
        $search = " AND ( ($search) OR $custom_search ) ";
    } else {
        $search = " AND ( $custom_search ) ";
    }
    
    return $search;
}
add_filter('posts_search', 'woo_search_opt_posts_search', 20, 2);

/**
 * Add a computed "relevance" field to the SELECT clause.
 * The relevance is weighted as follows:
 *   - Title: 100
 *   - Price: 90
 *   - Description: 80
 *   - Attributes: 70
 *   - SKU: 60
 */
function woo_search_opt_relevance( $fields, $wp_query ) {
    global $wpdb;
    
    $search_term = $wp_query->get('s');
    if ( empty( $search_term ) ) {
        return $fields;
    }
    
    $like = '%' . $wpdb->esc_like( $search_term ) . '%';
    $price_stripped = str_replace( '$', '', $search_term );
    $price_like_stripped = '%' . $wpdb->esc_like( $price_stripped ) . '%';
    
    $relevance = "(";
    $relevance .= " (CASE WHEN {$wpdb->posts}.post_title LIKE '{$like}' THEN 100 ELSE 0 END) + ";
    $relevance .= " (CASE WHEN (woo_pm_price.meta_value LIKE '{$like}' OR woo_pm_price.meta_value LIKE '{$price_like_stripped}') THEN 90 ELSE 0 END) + ";
    $relevance .= " (CASE WHEN {$wpdb->posts}.post_content LIKE '{$like}' THEN 80 ELSE 0 END) + ";
    $relevance .= " (CASE WHEN woo_attr.attributes LIKE '{$like}' THEN 70 ELSE 0 END) + ";
    $relevance .= " (CASE WHEN woo_pm_sku.meta_value LIKE '{$like}' THEN 60 ELSE 0 END)";
    $relevance .= ") AS relevance";
    
    $fields .= ", " . $relevance;
    return $fields;
}
add_filter('posts_fields', 'woo_search_opt_relevance', 20, 2);

/**
 * Order results by computed relevance (highest first) and then by post title.
 */
function woo_search_opt_orderby( $orderby, $wp_query ) {
    global $wpdb;

    $search_term = $wp_query->get('s');
    if ( empty( $search_term ) ) {
        return $orderby;
    }

    $custom_orderby = $wp_query->get( 'gm2_orderby' );
    $custom_order = strtoupper( $wp_query->get( 'gm2_order' ) );

    if ( ! in_array( $custom_order, [ 'ASC', 'DESC' ], true ) ) {
        $custom_order = 'DESC';
    }

    if ( $custom_orderby ) {
        switch ( $custom_orderby ) {
            case 'date':
                return "{$wpdb->posts}.post_date {$custom_order}";
            case 'title':
                return "{$wpdb->posts}.post_title {$custom_order}";
            case 'price':
                return "CAST(woo_pm_price.meta_value AS DECIMAL(10,4)) {$custom_order}";
            case 'rand':
                return 'RAND()';
            case 'relevance':
            default:
                return "relevance {$custom_order}, {$wpdb->posts}.post_title ASC";
        }
    }

    if ( 'RAND' === strtoupper( $wp_query->get( 'orderby' ) ) ) {
        return 'RAND()';
    }

    return "relevance DESC, {$wpdb->posts}.post_title ASC";
}
add_filter('posts_orderby', 'woo_search_opt_orderby', 20, 2);

/**
 * Group by post ID to ensure each product appears only once.
 */
function woo_search_opt_groupby( $groupby, $wp_query ) {
    global $wpdb;
    $groupby = "{$wpdb->posts}.ID";
    return $groupby;
}
add_filter('posts_groupby', 'woo_search_opt_groupby', 20, 2);

/**
 * Parse a comma or space separated list of IDs from a query variable.
 *
 * @param string $key Query parameter key.
 * @return array<int>
 */
function gm2_search_get_request_ids( $key ) {
    if ( ! isset( $_GET[ $key ] ) ) {
        return [];
    }

    $raw = wp_unslash( $_GET[ $key ] );

    if ( is_array( $raw ) ) {
        $parts = $raw;
    } else {
        $parts = preg_split( '/[\s,]+/', (string) $raw );
    }

    $parts = array_map( 'absint', (array) $parts );
    $parts = array_filter( $parts );

    return array_values( $parts );
}

/**
 * Parse a comma or space separated list of slugs from a query variable.
 *
 * @param string $key Query parameter key.
 * @return array<string>
 */
function gm2_search_get_request_slugs( $key ) {
    if ( ! isset( $_GET[ $key ] ) ) {
        return [];
    }

    $raw = wp_unslash( $_GET[ $key ] );

    if ( is_array( $raw ) ) {
        $parts = $raw;
    } else {
        $parts = preg_split( '/[\s,]+/', (string) $raw );
    }

    $parts = array_map( 'sanitize_title', (array) $parts );
    $parts = array_filter( $parts );

    return array_values( array_unique( $parts ) );
}

/**
 * Get term IDs for a set of slugs within a taxonomy.
 *
 * @param array<string> $slugs Term slugs.
 * @param string        $taxonomy Taxonomy name.
 * @return array<int>
 */
function gm2_search_get_term_ids_from_slugs( $slugs, $taxonomy ) {
    if ( empty( $slugs ) ) {
        return [];
    }

    $terms = get_terms(
        [
            'taxonomy'   => $taxonomy,
            'slug'       => $slugs,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]
    );

    if ( is_wp_error( $terms ) ) {
        return [];
    }

    return array_map( 'intval', $terms );
}

/**
 * Resolve a taxonomy name from a request variable.
 *
 * @param string $key     Query parameter key.
 * @param string $default Default taxonomy.
 * @return string
 */
function gm2_search_get_request_taxonomy( $key, $default = 'category' ) {
    $raw_taxonomy = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $raw_taxonomy && taxonomy_exists( $raw_taxonomy ) ) {
        return $raw_taxonomy;
    }

    if ( $default && taxonomy_exists( $default ) ) {
        return $default;
    }

    return 'category';
}

/**
 * Convert a preset date range slug into a WP_Query-compatible date query.
 *
 * @param string $range Range slug.
 * @return array<string, mixed>|null
 */
function gm2_search_build_date_query( $range ) {
    $now = current_time( 'timestamp' );
    $after = null;

    switch ( $range ) {
        case 'past_day':
            $after = $now - DAY_IN_SECONDS;
            break;
        case 'past_week':
            $after = $now - WEEK_IN_SECONDS;
            break;
        case 'past_month':
            $after = strtotime( '-1 month', $now );
            break;
        case 'past_year':
            $after = strtotime( '-1 year', $now );
            break;
        default:
            $after = null;
            break;
    }

    if ( ! $after ) {
        return null;
    }

    return [
        'after' => gmdate( 'Y-m-d H:i:s', $after ),
        'inclusive' => true,
        'column' => 'post_date',
    ];
}

/**
 * Apply query configuration provided by the Elementor widget.
 */
function gm2_search_apply_query_parameters( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
        return;
    }

    $elementor_page = 0;

    if ( isset( $_GET['e-search-page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $elementor_page = absint( wp_unslash( $_GET['e-search-page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    if ( $elementor_page > 0 ) {
        $current_paged = absint( $query->get( 'paged' ) );

        if ( $elementor_page !== $current_paged ) {
            $query->set( 'paged', $elementor_page );
            $query->set( 'page', $elementor_page );
            set_query_var( 'paged', $elementor_page );
            set_query_var( 'page', $elementor_page );
        }
    }

    $include_posts = gm2_search_get_request_ids( 'gm2_include_posts' );
    if ( ! empty( $include_posts ) ) {
        $query->set( 'post__in', $include_posts );
    }

    $exclude_posts = gm2_search_get_request_ids( 'gm2_exclude_posts' );
    if ( ! empty( $exclude_posts ) ) {
        $query->set( 'post__not_in', $exclude_posts );
    }

    $category_taxonomy = gm2_search_get_request_taxonomy( 'gm2_category_taxonomy', 'category' );

    $include_categories = gm2_search_get_request_ids( 'gm2_include_categories' );
    $exclude_categories = gm2_search_get_request_ids( 'gm2_exclude_categories' );

    if ( ( ! empty( $include_categories ) || ! empty( $exclude_categories ) ) && taxonomy_exists( $category_taxonomy ) ) {
        $tax_query = (array) $query->get( 'tax_query' );

        if ( ! empty( $include_categories ) ) {
            $tax_query[] = [
                'taxonomy' => $category_taxonomy,
                'field' => 'term_id',
                'terms' => $include_categories,
                'operator' => 'IN',
            ];
        }

        if ( ! empty( $exclude_categories ) ) {
            $tax_query[] = [
                'taxonomy' => $category_taxonomy,
                'field' => 'term_id',
                'terms' => $exclude_categories,
                'operator' => 'NOT IN',
            ];
        }

        $query->set( 'tax_query', $tax_query );
    }

    $filter_category_slugs = gm2_search_get_request_slugs( 'gm2_category_filter' );
    if ( ! empty( $filter_category_slugs ) && taxonomy_exists( $category_taxonomy ) ) {
        $filter_category_ids = gm2_search_get_term_ids_from_slugs( $filter_category_slugs, $category_taxonomy );

        if ( ! empty( $filter_category_ids ) ) {
            $tax_query   = (array) $query->get( 'tax_query' );
            $tax_query[] = [
                'taxonomy' => $category_taxonomy,
                'field'    => 'term_id',
                'terms'    => $filter_category_ids,
                'operator' => 'IN',
            ];

            $query->set( 'tax_query', $tax_query );
        }
    }

    $date_range = isset( $_GET['gm2_date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['gm2_date_range'] ) ) : '';
    if ( ! empty( $date_range ) ) {
        $date_query = gm2_search_build_date_query( $date_range );
        if ( $date_query ) {
            $query->set( 'date_query', [ $date_query ] );
        }
    }

    $order_by = isset( $_GET['gm2_orderby'] ) ? sanitize_key( wp_unslash( $_GET['gm2_orderby'] ) ) : '';
    $order = isset( $_GET['gm2_order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['gm2_order'] ) ) ) : '';

    if ( $order_by ) {
        $query->set( 'gm2_orderby', $order_by );

        if ( 'rand' === $order_by ) {
            $query->set( 'orderby', 'rand' );
        } elseif ( in_array( $order_by, [ 'date', 'title' ], true ) ) {
            $query->set( 'orderby', $order_by );
        } elseif ( 'price' === $order_by ) {
            $query->set( 'orderby', 'meta_value_num' );
            $query->set( 'meta_key', '_price' );
        }
    }

    if ( in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
        $query->set( 'gm2_order', $order );
        $query->set( 'order', $order );
    }

    $query_id = isset( $_GET['gm2_query_id'] ) ? sanitize_key( wp_unslash( $_GET['gm2_query_id'] ) ) : '';
    if ( ! empty( $query_id ) ) {
        $query->set( 'gm2_query_id', $query_id );
        /**
         * Allow developers to hook into the customised search query.
         */
        do_action( 'gm2_search/query/' . $query_id, $query );
    }
}
add_action( 'pre_get_posts', 'gm2_search_apply_query_parameters' );

/**
 * Ensure custom query variables are recognised by WordPress so they persist
 * across pagination and other generated links.
 *
 * @param array<string> $public_query_vars Public query var names.
 * @return array<string>
 */
function gm2_search_register_query_vars( $public_query_vars ) {
    $gm2_vars = [
        'gm2_include_posts',
        'gm2_exclude_posts',
        'gm2_include_categories',
        'gm2_exclude_categories',
        'gm2_category_filter',
        'gm2_category_taxonomy',
        'gm2_date_range',
        'gm2_orderby',
        'gm2_order',
        'gm2_query_id',
    ];

    foreach ( $gm2_vars as $var ) {
        if ( ! in_array( $var, $public_query_vars, true ) ) {
            $public_query_vars[] = $var;
        }
    }

    return $public_query_vars;
}
add_filter( 'query_vars', 'gm2_search_register_query_vars' );

/**
 * Collect active Gm2 search query arguments for pagination links.
 *
 * @return array<string, mixed>
 */
function gm2_search_get_active_query_args() {
    $args = [];

    $id_keys = [
        'gm2_include_posts',
        'gm2_exclude_posts',
        'gm2_include_categories',
        'gm2_exclude_categories',
    ];

    foreach ( $id_keys as $key ) {
        $ids = gm2_search_get_request_ids( $key );

        if ( ! empty( $ids ) ) {
            $args[ $key ] = implode( ',', $ids );
        }
    }

    $search_query = get_query_var( 's' );

    if ( ! is_string( $search_query ) || '' === $search_query ) {
        $search_query = get_search_query( false );
    }

    if ( ! is_string( $search_query ) || '' === $search_query ) {
        if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $search_query = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
    }

    if ( is_string( $search_query ) && '' !== $search_query ) {
        $args['s'] = $search_query;
    }

    $category_slugs = gm2_search_get_request_slugs( 'gm2_category_filter' );
    if ( ! empty( $category_slugs ) ) {
        $args['gm2_category_filter'] = implode( ',', $category_slugs );
    }

    if ( isset( $_GET['gm2_category_taxonomy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $taxonomy = sanitize_key( wp_unslash( $_GET['gm2_category_taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
            $args['gm2_category_taxonomy'] = $taxonomy;
        }
    }

    if ( isset( $_GET['gm2_date_range'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $allowed_ranges = [ 'past_day', 'past_week', 'past_month', 'past_year' ];
        $date_range     = sanitize_text_field( wp_unslash( $_GET['gm2_date_range'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( in_array( $date_range, $allowed_ranges, true ) ) {
            $args['gm2_date_range'] = $date_range;
        }
    }

    if ( isset( $_GET['gm2_orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $allowed_orderby = [ 'relevance', 'date', 'title', 'price', 'rand' ];
        $order_by        = sanitize_key( wp_unslash( $_GET['gm2_orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( in_array( $order_by, $allowed_orderby, true ) ) {
            $args['gm2_orderby'] = $order_by;
        }
    }

    if ( isset( $_GET['gm2_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = strtoupper( sanitize_text_field( wp_unslash( $_GET['gm2_order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $args['gm2_order'] = $order;
        }
    }

    if ( isset( $_GET['gm2_query_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $query_id = sanitize_key( wp_unslash( $_GET['gm2_query_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( ! empty( $query_id ) ) {
            $args['gm2_query_id'] = $query_id;
        }
    }

    $post_types = [];

    if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post_types = wp_unslash( $_GET['post_type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    } elseif ( get_query_var( 'post_type' ) ) {
        $post_types = get_query_var( 'post_type' );
    }

    $post_types = array_map( 'sanitize_key', (array) $post_types );
    $post_types = array_filter( $post_types );

    if ( ! empty( $post_types ) ) {
        if ( 1 === count( $post_types ) ) {
            $args['post_type'] = reset( $post_types );
        } else {
            $args['post_type'] = array_values( $post_types );
        }
    }

    return $args;
}

/**
 * Append active Gm2 query arguments to pagination links so filters persist across pages.
 *
 * @param string $result Generated pagination URL.
 * @param int    $pagenum Page number for the link.
 * @param bool   $escape  Whether the result will be escaped.
 * @return string
 */
function gm2_search_preserve_query_args_in_pagination( $result, $pagenum, $escape = true ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
    if ( is_admin() ) {
        return $result;
    }

    $args = gm2_search_get_active_query_args();

    if ( empty( $args ) ) {
        return $result;
    }

    return add_query_arg( $args, $result );
}
add_filter( 'get_pagenum_link', 'gm2_search_preserve_query_args_in_pagination', 10, 3 );

/**
 * Ensure active Gm2 query arguments are passed into paginate_links() so themes that rely on the
 * helper inherit the widget filters via the standard add_args parameter instead of rewriting the
 * generated HTML.
 *
 * @param array<string, mixed> $args Arguments provided to paginate_links().
 * @return array<string, mixed>
 */
function gm2_search_merge_paginate_links_args( $args ) {
    if ( is_admin() ) {
        return $args;
    }

    $query_args = gm2_search_get_active_query_args();

    if ( empty( $query_args ) ) {
        return $args;
    }

    if ( empty( $args['add_args'] ) ) {
        $args['add_args'] = $query_args;
        return $args;
    }

    if ( is_array( $args['add_args'] ) ) {
        $args['add_args'] = $query_args + $args['add_args'];
        return $args;
    }

    if ( is_string( $args['add_args'] ) ) {
        parse_str( $args['add_args'], $existing_args );
        if ( ! is_array( $existing_args ) ) {
            $existing_args = [];
        }

        $args['add_args'] = $query_args + $existing_args;
        return $args;
    }

    return $args;
}
add_filter( 'paginate_links_args', 'gm2_search_merge_paginate_links_args' );

/**
 * Append the active search arguments to pagination markup emitted directly by paginate_links().
 *
 * Some themes filter the output HTML instead of relying on the add_args parameter, which can drop
 * the Gm2-specific query vars. We rewrite the href attributes so every generated link keeps the
 * current search context.
 *
 * @param string|array<int, string> $links Pagination output.
 * @return string|array<int, string>
 */
function gm2_search_preserve_query_args_in_paginate_links_output( $links ) {
    if ( is_admin() ) {
        return $links;
    }

    $query_args = gm2_search_get_active_query_args();

    if ( empty( $query_args ) ) {
        return $links;
    }

    $charset     = get_bloginfo( 'charset' );
    $query_keys  = array_keys( $query_args );
    $rewrite_url = static function( $url ) use ( $query_args, $query_keys, $charset ) {
        if ( ! is_string( $url ) || '' === $url ) {
            return $url;
        }

        $decoded = html_entity_decode( $url, ENT_QUOTES, $charset );
        $stripped = remove_query_arg( $query_keys, $decoded );
        $updated  = add_query_arg( $query_args, $stripped );

        return esc_url( $updated );
    };

    $rewrite_html = static function( $markup ) use ( $rewrite_url ) {
        if ( ! is_string( $markup ) || '' === $markup || false === strpos( $markup, 'href=' ) ) {
            return $markup;
        }

        return preg_replace_callback(
            "/href=(['\"])([^'\"]*)\\1/",
            static function( $matches ) use ( $rewrite_url ) {
                $quote   = $matches[1];
                $url     = $matches[2];
                $updated = $rewrite_url( $url );

                return 'href=' . $quote . $updated . $quote;
            },
            $markup
        );
    };

    if ( is_array( $links ) ) {
        foreach ( $links as $index => $markup ) {
            $links[ $index ] = $rewrite_html( $markup );
        }

        return $links;
    }

    return $rewrite_html( $links );
}
add_filter( 'paginate_links', 'gm2_search_preserve_query_args_in_paginate_links_output', 10 );

/**
 * Ensure WooCommerce pagination arguments keep the active search filters.
 *
 * WooCommerce builds its own paginate_links() argument array and can override the add_args/base
 * values we set elsewhere. We merge the current query vars into those values so product listings
 * retain the user's filters when navigating between result pages.
 *
 * @param array<string, mixed> $args WooCommerce pagination arguments.
 * @return array<string, mixed>
 */
function gm2_search_merge_woocommerce_pagination_args( $args ) {
    if ( is_admin() ) {
        return $args;
    }

    $query_args = gm2_search_get_active_query_args();

    if ( empty( $query_args ) ) {
        return $args;
    }

    if ( empty( $args['add_args'] ) ) {
        $args['add_args'] = $query_args;
    } elseif ( is_array( $args['add_args'] ) ) {
        $args['add_args'] = $query_args + $args['add_args'];
    } elseif ( is_string( $args['add_args'] ) ) {
        parse_str( $args['add_args'], $existing_args );
        if ( ! is_array( $existing_args ) ) {
            $existing_args = [];
        }

        $args['add_args'] = $query_args + $existing_args;
    }

    if ( ! empty( $args['base'] ) && is_string( $args['base'] ) ) {
        $charset   = get_bloginfo( 'charset' );
        $query_keys = array_keys( $query_args );
        $decoded  = html_entity_decode( $args['base'], ENT_QUOTES, $charset );
        $stripped = remove_query_arg( $query_keys, $decoded );
        $args['base'] = esc_url_raw( add_query_arg( $query_args, $stripped ) );
    }

    return $args;
}
add_filter( 'woocommerce_pagination_args', 'gm2_search_merge_woocommerce_pagination_args', 15 );

/**
 * Register the Gm2 Search Bar Elementor widget, cloning the default Elementor search widget.
 */
function gm2_search_register_elementor_widget( $widgets_manager ) {
    if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
        return;
    }

    require_once plugin_dir_path( __FILE__ ) . 'includes/class-gm2-elementor-search-widget.php';

    $widget = new \Gm2_Search_Elementor_Widget();

    if ( method_exists( $widgets_manager, 'register' ) ) {
        $widgets_manager->register( $widget );
        return;
    }

    if ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
        $widgets_manager->register_widget_type( $widget );
    }
}
add_action( 'elementor/widgets/register', 'gm2_search_register_elementor_widget' );

/**
 * Fallback registration for Elementor versions prior to 3.5 where the
 * `elementor/widgets/register` action and `register()` method are not
 * available on the widgets manager.
 */
function gm2_search_register_elementor_widget_legacy() {
    if ( did_action( 'elementor/widgets/register' ) ) {
        return;
    }

    if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
        return;
    }

    gm2_search_register_elementor_widget( \Elementor\Plugin::instance()->widgets_manager );
}
add_action( 'elementor/widgets/widgets_registered', 'gm2_search_register_elementor_widget_legacy' );

/**
 * Ensure the Elementor search form styles are available on the front end.
 *
 * Elementor Pro registers the `elementor-search-form` stylesheet, but the
 * handle is not available when only the free Elementor plugin is installed.
 * The widget clones Elementor's markup, so we register a lightweight fallback
 * stylesheet under the same handle when it is missing.
 */
function gm2_search_register_elementor_widget_styles() {
    $relative_path = 'assets/css/gm2-search-widget.css';
    $style_file    = plugin_dir_path( __FILE__ ) . $relative_path;
    $style_url     = plugins_url( $relative_path, __FILE__ );
    $version       = file_exists( $style_file ) ? filemtime( $style_file ) : false;

    if ( ! wp_style_is( 'gm2-search-widget', 'registered' ) ) {
        wp_register_style( 'gm2-search-widget', $style_url, [ 'elementor-frontend' ], $version );
    }

    if ( ! wp_style_is( 'elementor-search-form', 'registered' ) ) {
        wp_register_style( 'elementor-search-form', $style_url, [ 'gm2-search-widget' ], $version );
    }

    $script_relative_path = 'assets/js/gm2-search-widget.js';
    $script_file          = plugin_dir_path( __FILE__ ) . $script_relative_path;

    if ( file_exists( $script_file ) && ! wp_script_is( 'gm2-search-widget', 'registered' ) ) {
        $script_url     = plugins_url( $script_relative_path, __FILE__ );
        $script_version = filemtime( $script_file );

        wp_register_script( 'gm2-search-widget', $script_url, [ 'jquery', 'elementor-frontend' ], $script_version, true );
    }
}
add_action( 'init', 'gm2_search_register_elementor_widget_styles' );

/**
 * Ensure the widget stylesheet is loaded anywhere Elementor renders the widget,
 * including the editor preview iframe.
 */
function gm2_search_enqueue_elementor_widget_styles() {
    if ( wp_style_is( 'gm2-search-widget', 'registered' ) ) {
        wp_enqueue_style( 'gm2-search-widget' );
    }

    if ( wp_style_is( 'elementor-search-form', 'registered' ) ) {
        wp_enqueue_style( 'elementor-search-form' );
    }
}
add_action( 'elementor/frontend/after_enqueue_styles', 'gm2_search_enqueue_elementor_widget_styles' );
add_action( 'elementor/editor/after_enqueue_styles', 'gm2_search_enqueue_elementor_widget_styles' );
add_action( 'elementor/preview/enqueue_styles', 'gm2_search_enqueue_elementor_widget_styles' );

/**
 * Ensure the widget script is present inside the Elementor editor preview
 * so interactive controls (like the category multi-select) work as expected.
 */
function gm2_search_enqueue_elementor_widget_scripts() {
    if ( wp_script_is( 'gm2-search-widget', 'registered' ) ) {
        wp_enqueue_script( 'gm2-search-widget' );
    }
}
add_action( 'elementor/frontend/after_enqueue_scripts', 'gm2_search_enqueue_elementor_widget_scripts' );
add_action( 'elementor/editor/after_enqueue_scripts', 'gm2_search_enqueue_elementor_widget_scripts' );
add_action( 'elementor/preview/enqueue_scripts', 'gm2_search_enqueue_elementor_widget_scripts' );
