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
 * Recursively unslash string values without altering non-string types.
 *
 * @param mixed $value Raw value.
 * @return mixed
 */
function gm2_search_maybe_unslash_value( $value ) {
    if ( is_array( $value ) ) {
        foreach ( $value as $key => $item ) {
            $value[ $key ] = gm2_search_maybe_unslash_value( $item );
        }

        return $value;
    }

    if ( is_string( $value ) ) {
        return wp_unslash( $value );
    }

    return $value;
}

/**
 * Attempt to decode a JSON or query-string payload fragment.
 *
 * @param string $fragment Raw fragment string.
 * @return array<string, mixed>|null
 */
function gm2_search_maybe_decode_payload_fragment( $fragment ) {
    $fragment = trim( $fragment );

    if ( '' === $fragment ) {
        return null;
    }

    if ( strlen( $fragment ) > 20000 ) {
        return null;
    }

    $first = substr( $fragment, 0, 1 );
    $last  = substr( $fragment, -1 );

    if ( ( '{' === $first && '}' === $last ) || ( '[' === $first && ']' === $last ) ) {
        $decoded = json_decode( $fragment, true );

        if ( is_array( $decoded ) ) {
            return $decoded;
        }
    }

    if ( false !== strpos( $fragment, '=' ) ) {
        parse_str( $fragment, $parsed );

        if ( is_array( $parsed ) && ! empty( $parsed ) ) {
            return $parsed;
        }
    }

    return null;
}

/**
 * Search an Elementor payload fragment for a specific key.
 *
 * @param mixed  $payload Payload fragment.
 * @param string $key     Requested key.
 * @param int    $depth   Recursion depth.
 * @return mixed|null
 */
function gm2_search_search_payload_for_key( $payload, $key, $depth = 0 ) {
    if ( $depth > 6 ) {
        return null;
    }

    if ( is_array( $payload ) ) {
        if ( array_key_exists( $key, $payload ) ) {
            return $payload[ $key ];
        }

        foreach ( $payload as $value ) {
            $found = gm2_search_search_payload_for_key( $value, $key, $depth + 1 );

            if ( null !== $found ) {
                return $found;
            }
        }

        return null;
    }

    if ( is_string( $payload ) ) {
        $decoded = gm2_search_maybe_decode_payload_fragment( $payload );

        if ( is_array( $decoded ) ) {
            return gm2_search_search_payload_for_key( $decoded, $key, $depth + 1 );
        }
    }

    return null;
}

/**
 * Collect decoded Elementor AJAX payload fragments.
 *
 * @return array<int, mixed>
 */
function gm2_search_get_elementor_payloads() {
    static $payloads = null;

    if ( null !== $payloads ) {
        return $payloads;
    }

    $payloads = [];
    $candidate_keys = [ 'actions', 'data', 'settings', 'args' ];

    foreach ( $candidate_keys as $candidate_key ) {
        if ( ! isset( $_POST[ $candidate_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            continue;
        }

        $value = gm2_search_maybe_unslash_value( $_POST[ $candidate_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( is_array( $value ) ) {
            $payloads[] = $value;
            continue;
        }

        if ( is_string( $value ) ) {
            $decoded = gm2_search_maybe_decode_payload_fragment( $value );

            if ( is_array( $decoded ) ) {
                $payloads[] = $decoded;
            }
        }
    }

    return $payloads;
}

/**
 * Look for a GM2 request value inside Elementor payloads.
 *
 * @param string $key Requested parameter name.
 * @return mixed|null
 */
function gm2_search_find_in_elementor_payloads( $key ) {
    $payloads = gm2_search_get_elementor_payloads();

    foreach ( $payloads as $payload ) {
        $found = gm2_search_search_payload_for_key( $payload, $key );

        if ( null !== $found ) {
            return $found;
        }
    }

    return null;
}

/**
 * Retrieve a request value from $_GET, $_POST, or Elementor AJAX payloads.
 *
 * @param string $key Parameter key.
 * @return mixed|null
 */
function gm2_search_get_request_var( $key ) {
    if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return gm2_search_maybe_unslash_value( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return gm2_search_maybe_unslash_value( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }

    $elementor_value = gm2_search_find_in_elementor_payloads( $key );

    if ( null !== $elementor_value ) {
        return gm2_search_maybe_unslash_value( $elementor_value );
    }

    return null;
}

function gm2_search_get_request_ids( $key ) {
    $raw = gm2_search_get_request_var( $key );

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
 * Retrieve post types provided in the current request or query vars.
 *
 * @return array<int, string>
 */
function gm2_search_get_request_post_types() {
    $post_types = gm2_search_get_request_var( 'post_type' );

    if ( null === $post_types && get_query_var( 'post_type' ) ) {
        $post_types = get_query_var( 'post_type' );
    }

    $post_types = array_map( 'sanitize_key', (array) $post_types );
    $post_types = array_filter(
        $post_types,
        static function ( $post_type ) {
            return ! empty( $post_type ) && post_type_exists( $post_type );
        }
    );

    return array_values( array_unique( $post_types ) );
}

/**
 * Parse a comma or space separated list of slugs from a query variable.
 *
 * @param string $key Query parameter key.
 * @return array<string>
 */
function gm2_search_get_request_slugs( $key ) {
    $raw = gm2_search_get_request_var( $key );

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
    $raw = gm2_search_get_request_var( $key );
    $raw_taxonomy = is_string( $raw ) ? sanitize_key( $raw ) : '';

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

function gm2_search_populate_query_from_request( $query ) {
    $is_main_query = method_exists( $query, 'is_main_query' ) ? $query->is_main_query() : false;

    $post_types = gm2_search_get_request_post_types();

    if ( empty( $post_types ) && post_type_exists( 'product' ) ) {
        $post_types = [ 'product' ];
    }

    if ( 1 === count( $post_types ) ) {
        $single_post_type = reset( $post_types );
        $query->set( 'post_type', $single_post_type );
        if ( $is_main_query ) {
            set_query_var( 'post_type', $single_post_type );
        }
    } elseif ( ! empty( $post_types ) ) {
        $query->set( 'post_type', $post_types );
        if ( $is_main_query ) {
            set_query_var( 'post_type', $post_types );
        }
    } elseif ( ! empty( $post_types ) ) {
        $query->set( 'post_type', $post_types );
        if ( $is_main_query ) {
            set_query_var( 'post_type', $post_types );
        }
    }

    $post_types = gm2_search_get_request_post_types();

    if ( empty( $post_types ) && post_type_exists( 'product' ) ) {
        $post_types = [ 'product' ];
    }

    if ( 1 === count( $post_types ) ) {
        $single_post_type = reset( $post_types );
        $query->set( 'post_type', $single_post_type );
        set_query_var( 'post_type', $single_post_type );
    } elseif ( ! empty( $post_types ) ) {
        $query->set( 'post_type', $post_types );
        set_query_var( 'post_type', $post_types );
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
                'field'    => 'term_id',
                'terms'    => $include_categories,
                'operator' => 'IN',
            ];
        }

        if ( ! empty( $exclude_categories ) ) {
            $tax_query[] = [
                'taxonomy' => $category_taxonomy,
                'field'    => 'term_id',
                'terms'    => $exclude_categories,
                'operator' => 'NOT IN',
            ];
        }

        $query->set( 'tax_query', $tax_query );
    }

    $category_filter_taxonomy = gm2_search_get_request_taxonomy( 'gm2_category_taxonomy', 'category' );
    $filter_category_slugs    = gm2_search_get_request_slugs( 'gm2_category_filter' );

    if ( ! empty( $filter_category_slugs ) && taxonomy_exists( $category_filter_taxonomy ) ) {
        $filter_category_ids = gm2_search_get_term_ids_from_slugs( $filter_category_slugs, $category_filter_taxonomy );

        if ( ! empty( $filter_category_ids ) ) {
            $tax_query   = (array) $query->get( 'tax_query' );
            $tax_query[] = [
                'taxonomy' => $category_filter_taxonomy,
                'field'    => 'term_id',
                'terms'    => $filter_category_ids,
                'operator' => 'IN',
            ];

            $query->set( 'tax_query', $tax_query );
        }
    }

    $date_range_raw = gm2_search_get_request_var( 'gm2_date_range' );
    $date_range      = is_string( $date_range_raw ) ? sanitize_key( $date_range_raw ) : '';

    if ( $date_range ) {
        $date_query = gm2_search_build_date_query( $date_range );

        if ( $date_query ) {
            $query->set( 'date_query', [ $date_query ] );
        }
    }

    $order_by_raw = gm2_search_get_request_var( 'gm2_orderby' );
    $order_raw    = gm2_search_get_request_var( 'gm2_order' );

    $order_by = is_string( $order_by_raw ) ? sanitize_key( $order_by_raw ) : '';
    $order    = is_string( $order_raw ) ? strtoupper( sanitize_text_field( $order_raw ) ) : '';

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

    $query_id_raw = gm2_search_get_request_var( 'gm2_query_id' );
    $query_id     = is_string( $query_id_raw ) ? sanitize_key( $query_id_raw ) : '';

    if ( ! empty( $query_id ) ) {
        $query->set( 'gm2_query_id', $query_id );
        /**
         * Allow developers to hook into the customised search query.
         */
        do_action( 'gm2_search/query/' . $query_id, $query );
    }
}

/**
 * Retrieve the active search term from the current request or query.
 *
 * @return string
 */
function gm2_search_get_request_search_term() {
    $search_query = get_query_var( 's' );

    if ( ! is_string( $search_query ) || '' === $search_query ) {
        $search_query = get_search_query( false );
    }

    if ( ! is_string( $search_query ) || '' === $search_query ) {
        $request_search = gm2_search_get_request_var( 's' );

        if ( is_string( $request_search ) && '' !== $request_search ) {
            $search_query = sanitize_text_field( $request_search );
        }
    }

    return is_string( $search_query ) ? $search_query : '';
}

/**
 * Apply query configuration provided by the Elementor widget.
 */
function gm2_search_apply_query_parameters( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( ! $query->is_search() ) {
        $search_term = gm2_search_get_request_search_term();

        if ( '' === $search_term ) {
            return;
        }

        $query->set( 's', $search_term );
    }

    gm2_search_populate_query_from_request( $query );
}
add_action( 'pre_get_posts', 'gm2_search_apply_query_parameters' );

function gm2_search_request_has_filters() {
    if ( '' !== gm2_search_get_request_search_term() ) {
        return true;
    }

    $filter_keys = [
        'gm2_include_posts',
        'gm2_exclude_posts',
        'gm2_include_categories',
        'gm2_exclude_categories',
        'gm2_category_filter',
        'gm2_date_range',
        'gm2_orderby',
        'gm2_order',
        'gm2_query_id',
    ];

    foreach ( $filter_keys as $key ) {
        $value = gm2_search_get_request_var( $key );

        if ( is_array( $value ) ) {
            if ( ! empty( $value ) ) {
                return true;
            }
            continue;
        }

        if ( is_string( $value ) && '' !== trim( $value ) ) {
            return true;
        }
    }

    $raw_post_type = gm2_search_get_request_var( 'post_type' );

    if ( null === $raw_post_type ) {
        return false;
    }

    $post_types = array_map( 'sanitize_key', (array) $raw_post_type );

    return ! empty( array_filter( $post_types ) );
}

function gm2_search_apply_secondary_product_queries( $query ) {
    if ( is_admin() || $query->is_main_query() ) {
        return;
    }

    if ( ! gm2_search_request_has_filters() ) {
        return;
    }

    $post_type = $query->get( 'post_type' );

    if ( empty( $post_type ) ) {
        $post_type = gm2_search_get_request_post_types();
    }

    $post_types = (array) $post_type;

    if ( empty( $post_types ) ) {
        return;
    }

    $post_types = array_map( 'sanitize_key', $post_types );

    if ( ! in_array( 'product', $post_types, true ) ) {
        return;
    }

    gm2_search_populate_query_from_request( $query );
}
add_action( 'pre_get_posts', 'gm2_search_apply_secondary_product_queries', 11 );

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

    $search_query = gm2_search_get_request_search_term();

    if ( '' !== $search_query ) {
        $args['s'] = $search_query;
    }

    $category_slugs = gm2_search_get_request_slugs( 'gm2_category_filter' );
    if ( ! empty( $category_slugs ) ) {
        $args['gm2_category_filter'] = implode( ',', $category_slugs );
    }

    $category_taxonomy_raw = gm2_search_get_request_var( 'gm2_category_taxonomy' );

    if ( is_string( $category_taxonomy_raw ) ) {
        $taxonomy = sanitize_key( $category_taxonomy_raw );

        if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
            $args['gm2_category_taxonomy'] = $taxonomy;
        }
    }

    $date_range_raw = gm2_search_get_request_var( 'gm2_date_range' );

    if ( is_string( $date_range_raw ) ) {
        $allowed_ranges = [ 'past_day', 'past_week', 'past_month', 'past_year' ];
        $date_range     = sanitize_key( $date_range_raw );

        if ( in_array( $date_range, $allowed_ranges, true ) ) {
            $args['gm2_date_range'] = $date_range;
        }
    }

    $order_by_raw = gm2_search_get_request_var( 'gm2_orderby' );

    if ( is_string( $order_by_raw ) ) {
        $allowed_orderby = [ 'relevance', 'date', 'title', 'price', 'rand' ];
        $order_by        = sanitize_key( $order_by_raw );

        if ( in_array( $order_by, $allowed_orderby, true ) ) {
            $args['gm2_orderby'] = $order_by;
        }
    }

    $order_raw = gm2_search_get_request_var( 'gm2_order' );

    if ( is_string( $order_raw ) ) {
        $order = strtoupper( sanitize_text_field( $order_raw ) );

        if ( in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $args['gm2_order'] = $order;
        }
    }

    $query_id_raw = gm2_search_get_request_var( 'gm2_query_id' );

    if ( is_string( $query_id_raw ) ) {
        $query_id = sanitize_key( $query_id_raw );

        if ( ! empty( $query_id ) ) {
            $args['gm2_query_id'] = $query_id;
        }
    }

    $post_types = gm2_search_get_request_post_types();

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

    $charset      = get_bloginfo( 'charset' );
    $query_keys   = array_keys( $query_args );
    $looks_like_url = static function( $value ) {
        if ( ! is_string( $value ) || '' === $value ) {
            return false;
        }

        return false !== strpos( $value, ':' )
            || false !== strpos( $value, '/' )
            || false !== strpos( $value, '?' );
    };

    $rewrite_url = static function( $url ) use ( $query_args, $query_keys, $charset ) {
        if ( ! is_string( $url ) || '' === $url ) {
            return $url;
        }

        $decoded = html_entity_decode( $url, ENT_QUOTES, $charset );
        $stripped = remove_query_arg( $query_keys, $decoded );
        $updated  = add_query_arg( $query_args, $stripped );

        return esc_url( $updated );
    };

    $rewrite_url_raw = static function( $url ) use ( $query_args, $query_keys, $charset ) {
        if ( ! is_string( $url ) || '' === $url ) {
            return $url;
        }

        $decoded = html_entity_decode( $url, ENT_QUOTES, $charset );
        $stripped = remove_query_arg( $query_keys, $decoded );
        $updated  = add_query_arg( $query_args, $stripped );

        return esc_url_raw( $updated );
    };

    $rewrite_html = static function( $markup ) use ( $rewrite_url, $rewrite_url_raw, $looks_like_url, $charset ) {
        if ( ! is_string( $markup ) || '' === $markup ) {
            return $markup;
        }

        if ( false === strpos( $markup, 'href' ) && false === strpos( $markup, 'data-' ) ) {
            return $markup;
        }

        $attribute_pattern = '/\b(href|data-[a-z0-9_-]*(?:href|url|link))=([\'\"])([^\'\"]*)\2/i';

        $markup = preg_replace_callback(
            $attribute_pattern,
            static function( $matches ) use ( $rewrite_url, $charset ) {
                $attribute = $matches[1];
                $quote     = $matches[2];
                $value     = $matches[3];

                if ( '' === $value ) {
                    return $matches[0];
                }

                $decoded_value = html_entity_decode( $value, ENT_QUOTES, $charset );

                if (
                    false === strpos( $decoded_value, ':' )
                    && false === strpos( $decoded_value, '/' )
                    && false === strpos( $decoded_value, '?' )
                ) {
                    return $matches[0];
                }

                $updated = $rewrite_url( $value );

                if ( ! is_string( $updated ) || '' === $updated ) {
                    return $matches[0];
                }

                return $attribute . '=' . $quote . $updated . $quote;
            },
            $markup
        );

        if ( false === strpos( $markup, 'data-settings' ) ) {
            return $markup;
        }

        $settings_pattern = '/\bdata-settings=([\'\"])(.*?)\1/i';

        return preg_replace_callback(
            $settings_pattern,
            static function( $matches ) use ( $rewrite_url_raw, $looks_like_url, $charset ) {
                $quote        = $matches[1];
                $encoded_json = $matches[2];
                $decoded_json = html_entity_decode( $encoded_json, ENT_QUOTES, $charset );
                $data         = json_decode( $decoded_json, true );

                if ( ! is_array( $data ) ) {
                    return $matches[0];
                }

                $updated = false;

                array_walk_recursive(
                    $data,
                    static function( &$value ) use ( $rewrite_url_raw, $looks_like_url, &$updated ) {
                        if ( ! is_string( $value ) || ! $looks_like_url( $value ) ) {
                            return;
                        }

                        $rewritten = $rewrite_url_raw( $value );

                        if ( is_string( $rewritten ) && '' !== $rewritten && $rewritten !== $value ) {
                            $value   = $rewritten;
                            $updated = true;
                        }
                    }
                );

                if ( ! $updated ) {
                    return $matches[0];
                }

                $encoded = wp_json_encode( $data );

                if ( false === $encoded ) {
                    return $matches[0];
                }

                return 'data-settings=' . $quote . esc_attr( $encoded ) . $quote;
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
        $charset    = get_bloginfo( 'charset' );
        $query_keys = array_keys( $query_args );
        $decoded    = html_entity_decode( $args['base'], ENT_QUOTES, $charset );

        // Preserve the pagination placeholders so add_query_arg() doesn't encode them.
        $page_placeholder = 'gm2_page_placeholder_' . wp_rand();
        $base_placeholder = 'gm2_base_placeholder_' . wp_rand();

        $decoded = str_replace( '%#%', $page_placeholder, $decoded );
        $decoded = str_replace( '%_%', $base_placeholder, $decoded );

        $stripped = remove_query_arg( $query_keys, $decoded );
        $updated  = add_query_arg( $query_args, $stripped );

        $updated = str_replace( $page_placeholder, '%#%', $updated );
        $updated = str_replace( $base_placeholder, '%_%', $updated );

        $args['base'] = esc_url_raw( $updated );
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
