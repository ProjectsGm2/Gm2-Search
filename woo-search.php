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
 * Determine whether diagnostic logging is enabled for the plugin.
 *
 * Logging can be activated by defining the GM2_SEARCH_DEBUG constant or by
 * hooking into the `gm2_search_logging_enabled` filter. When inactive the
 * helper functions become no-ops so production environments are not affected.
 *
 * @return bool
 */
function gm2_search_is_logging_enabled() {
    static $enabled = null;

    if ( null !== $enabled ) {
        return $enabled;
    }

    $default_flag = defined( 'GM2_SEARCH_DEBUG' ) ? GM2_SEARCH_DEBUG : ( defined( 'WP_DEBUG' ) ? WP_DEBUG : false );

    /**
     * Filter whether Gm2 search debugging is active.
     *
     * @param bool $is_enabled Whether logging should be activated.
     */
    $enabled = (bool) apply_filters( 'gm2_search_logging_enabled', $default_flag );

    return $enabled;
}

/**
 * Normalise a value for safe inclusion within a log entry.
 *
 * @param mixed $value Value to normalise.
 * @return mixed
 */
function gm2_search_normalise_for_log( $value ) {
    if ( is_null( $value ) || is_scalar( $value ) ) {
        return $value;
    }

    if ( is_array( $value ) ) {
        foreach ( $value as $key => $item ) {
            $value[ $key ] = gm2_search_normalise_for_log( $item );
        }

        return $value;
    }

    if ( is_object( $value ) ) {
        if ( class_exists( 'WP_Query' ) && $value instanceof WP_Query ) {
            return 'WP_Query:' . spl_object_id( $value );
        }

        if ( method_exists( $value, '__toString' ) ) {
            return (string) $value;
        }

        return 'object:' . get_class( $value );
    }

    return (string) $value;
}

/**
 * Write a structured entry to the WooCommerce logger (if available) or the PHP
 * error log. The context is JSON encoded to keep the output compact.
 *
 * @param string               $level   Log level.
 * @param string               $message Message to record.
 * @param array<string, mixed> $context Additional context.
 * @return void
 */
function gm2_search_log_event( $level, $message, array $context = [] ) {
    if ( ! gm2_search_is_logging_enabled() ) {
        return;
    }

    $normalised_context = [];

    foreach ( $context as $key => $value ) {
        $normalised_context[ $key ] = gm2_search_normalise_for_log( $value );
    }

    $payload = $normalised_context ? wp_json_encode( $normalised_context ) : '';

    if ( function_exists( 'wc_get_logger' ) ) {
        $logger = wc_get_logger();
        $logger->log(
            $level,
            $message . ( $payload ? ' ' . $payload : '' ),
            [ 'source' => 'gm2-search' ]
        );
        return;
    }

    $line = '[GM2 Search] ' . strtoupper( $level ) . ': ' . $message;

    if ( $payload ) {
        $line .= ' ' . $payload;
    }

    error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * Determine whether the current request should be treated as an admin-only context.
 *
 * WordPress marks Ajax and REST requests as "admin" which prevented the search filters from
 * running when Elementor paginated via admin-ajax.php. We treat those interactive requests as
 * frontend contexts so pagination retains the active filters.
 *
 * @return bool
 */
function gm2_search_is_backend_context() {
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
        return false;
    }

    if ( function_exists( 'wp_doing_rest' ) && wp_doing_rest() ) {
        return false;
    }

    return is_admin();
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
    $candidate_keys = [ 'actions', 'data', 'settings', 'args', 'query', 'query_vars', 'queryArgs', 'query_args' ];

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
 * Retrieve the requested Elementor results template ID.
 *
 * @return int
 */
function gm2_search_get_request_results_template_id() {
    $raw = gm2_search_get_request_var( 'gm2_results_template_id' );

    if ( is_array( $raw ) ) {
        $raw = reset( $raw );
    }

    if ( is_scalar( $raw ) ) {
        $template_id = absint( $raw );
    } else {
        $template_id = 0;
    }

    return (int) apply_filters( 'gm2_search_request_results_template_id', $template_id, $raw );
}

/**
 * Determine the active Elementor results template ID for the current request.
 *
 * @return int
 */
function gm2_search_get_active_results_template_id() {
    static $cached = null;

    if ( null === $cached ) {
        $cached = gm2_search_get_request_results_template_id();
    }

    return $cached;
}

/**
 * Determine whether the active Elementor template should render the full results layout or individual items.
 *
 * @return array{
 *     mode: 'none'|'item'|'layout',
 *     template_id: int,
 * }
 */
function gm2_search_get_results_template_render_state() {
    static $state = null;

    if ( null !== $state ) {
        return $state;
    }

    $state = [
        'mode'        => 'none',
        'template_id' => 0,
    ];

    $template_id = gm2_search_get_active_results_template_id();
    $state['template_id'] = $template_id;

    if ( ! $template_id || ! class_exists( '\\Elementor\\Plugin' ) ) {
        return $state;
    }

    $contains_loop = gm2_search_elementor_template_contains_results_loop( $template_id );

    if ( $contains_loop ) {
        $state['mode'] = 'layout';
    } else {
        $state['mode'] = 'item';
    }

    /**
     * Filter the resolved render state for the active results template.
     *
     * @param array $state {
     *     mode: 'none'|'item'|'layout',
     *     template_id: int,
     * }
     */
    $state = apply_filters( 'gm2_search_results_template_render_state', $state );

    return $state;
}

/**
 * Inspect an Elementor template to determine if it contains a products loop widget.
 *
 * @param int $template_id Template post ID.
 * @return bool
 */
function gm2_search_elementor_template_contains_results_loop( $template_id ) {
    $has_loop = false;

    if ( ! $template_id || ! class_exists( '\\Elementor\\Plugin' ) ) {
        return false;
    }

    $plugin = \Elementor\Plugin::instance();

    if ( isset( $plugin->documents ) && method_exists( $plugin->documents, 'get' ) ) {
        $document = $plugin->documents->get( $template_id );

        if ( $document && method_exists( $document, 'get_elements_data' ) ) {
            $elements = $document->get_elements_data();

            if ( gm2_search_elementor_elements_contain_widget(
                $elements,
                [
                    'woocommerce-products',
                    'archive-products',
                    'woocommerce-products-archive',
                    'woocommerce-archive-products',
                    'woocommerce-product-archive',
                ]
            ) ) {
                $has_loop = true;
            }
        }
    }

    if ( ! $has_loop && isset( $plugin->frontend ) && method_exists( $plugin->frontend, 'get_builder_content_for_display' ) ) {
        $content = $plugin->frontend->get_builder_content_for_display( $template_id, true );
        if ( gm2_search_elementor_template_markup_has_results_loop( $content ) ) {
            $has_loop = true;
        }
    }

    /**
     * Allow developers to override loop detection for Elementor templates.
     *
     * @param bool $has_loop Whether the template contains a loop widget.
     * @param int  $template_id Template post ID.
     */
    return (bool) apply_filters( 'gm2_search_elementor_template_contains_results_loop', $has_loop, $template_id );
}

/**
 * Recursively inspect Elementor element data for specific widget types.
 *
 * @param array<int, array<string, mixed>> $elements Elementor element data.
 * @param array<int, string>               $widget_types Widget slugs to detect.
 * @return bool
 */
function gm2_search_normalize_elementor_widget_type( $widget_type ) {
    if ( ! is_string( $widget_type ) || '' === $widget_type ) {
        return '';
    }

    $normalized = strtolower( $widget_type );

    // Elementor appends skin identifiers after a dot, e.g. `woocommerce-products.default`.
    $parts = preg_split( '/[.:]/', $normalized );
    $normalized = ( $parts && isset( $parts[0] ) ) ? $parts[0] : $normalized;

    $normalized = str_replace( '\\', '/', $normalized );
    $normalized = trim( $normalized );

    return sanitize_key( $normalized );
}

/**
 * Determine whether the provided Elementor loop grid widget is configured to query products.
 *
 * @param array<string, mixed> $element Elementor element definition.
 * @return bool
 */
function gm2_search_elementor_loop_grid_targets_products( $element ) {
    if ( empty( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
        return false;
    }

    $settings = $element['settings'];
    $candidates = [];

    foreach ( [ 'query_post_type', 'posts_post_type' ] as $key ) {
        if ( isset( $settings[ $key ] ) ) {
            $candidates[] = $settings[ $key ];
        }
    }

    if ( isset( $settings['query'] ) && is_array( $settings['query'] ) ) {
        $query_settings = $settings['query'];

        foreach ( [ 'post_type', 'source', 'query_type' ] as $key ) {
            if ( isset( $query_settings[ $key ] ) ) {
                $candidates[] = $query_settings[ $key ];
            }
        }
    }

    foreach ( $candidates as $value ) {
        if ( gm2_search_value_includes_product_post_type( $value ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Check if a value or list of values references WooCommerce products.
 *
 * @param mixed $value Value to inspect.
 * @return bool
 */
function gm2_search_value_includes_product_post_type( $value ) {
    if ( is_string( $value ) ) {
        $normalized = sanitize_key( $value );

        return in_array( $normalized, [ 'product', 'products', 'product_variation', 'woocommerce' ], true );
    }

    if ( is_array( $value ) ) {
        foreach ( $value as $item ) {
            if ( gm2_search_value_includes_product_post_type( $item ) ) {
                return true;
            }
        }
    }

    return false;
}

function gm2_search_elementor_elements_contain_widget( $elements, $widget_types ) {
    if ( empty( $elements ) || ! is_array( $elements ) ) {
        return false;
    }

    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $el_type = isset( $element['elType'] ) ? $element['elType'] : '';

        if ( 'widget' === $el_type ) {
            $widget_type = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
            $normalized  = gm2_search_normalize_elementor_widget_type( $widget_type );

            if ( $normalized && in_array( $normalized, $widget_types, true ) ) {
                return true;
            }

            if ( 'loop-grid' === $normalized && gm2_search_elementor_loop_grid_targets_products( $element ) ) {
                return true;
            }
        }

        if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
            if ( gm2_search_elementor_elements_contain_widget( $element['elements'], $widget_types ) ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Detect loop widgets by scanning rendered Elementor markup.
 *
 * @param string $content Rendered Elementor content.
 * @return bool
 */
function gm2_search_elementor_template_markup_has_results_loop( $content ) {
    if ( ! is_string( $content ) || '' === trim( $content ) ) {
        return false;
    }

    $markers = [
        'elementor-widget-woocommerce-products',
        'elementor-widget-archive-products',
        'elementor-widget-woocommerce-archive-products',
        'elementor-widget-woocommerce-products-archive',
        'data-widget_type="woocommerce-products',
        'data-widget_type="archive-products',
        'data-widget_type="woocommerce-archive-products',
        'data-widget_type="woocommerce-products-archive',
    ];

    foreach ( $markers as $marker ) {
        if ( false !== stripos( $content, $marker ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Render the active Elementor results template when it replaces the entire results layout.
 *
 * @return string
 */
function gm2_search_render_elementor_results_layout() {
    $state = gm2_search_get_results_template_render_state();

    if ( 'layout' !== $state['mode'] || empty( $state['template_id'] ) || ! class_exists( '\\Elementor\\Plugin' ) ) {
        return '';
    }

    $plugin = \Elementor\Plugin::instance();

    if ( ! isset( $plugin->frontend ) || ! method_exists( $plugin->frontend, 'get_builder_content_for_display' ) ) {
        return '';
    }

    $content = $plugin->frontend->get_builder_content_for_display( $state['template_id'], true );

    /**
     * Filter the rendered Elementor layout content before it is output.
     *
     * @param string $content Rendered layout HTML.
     * @param int    $template_id Template post ID.
     */
    return apply_filters( 'gm2_search_rendered_results_layout', $content, $state['template_id'] );
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

function gm2_search_build_query_args_from_request( array $overrides = [] ) {
    $args = [
        'post_status' => 'publish',
    ];

    $post_types = gm2_search_get_request_post_types();

    if ( empty( $post_types ) && post_type_exists( 'product' ) ) {
        $post_types = [ 'product' ];
    }

    if ( 1 === count( $post_types ) ) {
        $args['post_type'] = reset( $post_types );
    } elseif ( ! empty( $post_types ) ) {
        $args['post_type'] = array_values( $post_types );
    }

    $search_term = gm2_search_get_request_search_term();
    if ( '' !== $search_term ) {
        $args['s'] = $search_term;
    }

    $include_posts = gm2_search_get_request_ids( 'gm2_include_posts' );
    if ( ! empty( $include_posts ) ) {
        $args['post__in'] = $include_posts;
    }

    $exclude_posts = gm2_search_get_request_ids( 'gm2_exclude_posts' );
    if ( ! empty( $exclude_posts ) ) {
        $args['post__not_in'] = $exclude_posts;
    }

    $category_taxonomy = gm2_search_get_request_taxonomy( 'gm2_category_taxonomy', 'category' );

    $include_categories = gm2_search_get_request_ids( 'gm2_include_categories' );
    $exclude_categories = gm2_search_get_request_ids( 'gm2_exclude_categories' );
    $tax_query          = [];

    if ( ( ! empty( $include_categories ) || ! empty( $exclude_categories ) ) && taxonomy_exists( $category_taxonomy ) ) {
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
    }

    $filter_category_slugs = gm2_search_get_request_slugs( 'gm2_category_filter' );

    if ( ! empty( $filter_category_slugs ) && taxonomy_exists( $category_taxonomy ) ) {
        $filter_category_ids = gm2_search_get_term_ids_from_slugs( $filter_category_slugs, $category_taxonomy );

        if ( ! empty( $filter_category_ids ) ) {
            $tax_query[] = [
                'taxonomy' => $category_taxonomy,
                'field'    => 'term_id',
                'terms'    => $filter_category_ids,
                'operator' => 'IN',
            ];
        }
    }

    if ( ! empty( $tax_query ) ) {
        $args['tax_query'] = $tax_query;
    }

    $date_range_raw = gm2_search_get_request_var( 'gm2_date_range' );
    $date_range      = is_string( $date_range_raw ) ? sanitize_key( $date_range_raw ) : '';

    if ( $date_range ) {
        $date_query = gm2_search_build_date_query( $date_range );

        if ( $date_query ) {
            $args['date_query'] = [ $date_query ];
        }
    }

    $order_by_raw = gm2_search_get_request_var( 'gm2_orderby' );
    $order_raw    = gm2_search_get_request_var( 'gm2_order' );

    $order_by = is_string( $order_by_raw ) ? sanitize_key( $order_by_raw ) : '';
    $order    = is_string( $order_raw ) ? strtoupper( sanitize_text_field( $order_raw ) ) : '';

    if ( $order_by ) {
        $args['gm2_orderby'] = $order_by;

        switch ( $order_by ) {
            case 'date':
                $args['orderby'] = 'date';
                break;
            case 'title':
                $args['orderby'] = 'title';
                break;
            case 'price':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                break;
            case 'rand':
                $args['orderby'] = 'rand';
                break;
            case 'relevance':
            default:
                $args['orderby'] = 'relevance';
                break;
        }
    }

    if ( in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
        $args['gm2_order'] = $order;
        $args['order']     = $order;
    }

    $query_id_raw = gm2_search_get_request_var( 'gm2_query_id' );
    $query_id     = is_string( $query_id_raw ) ? sanitize_key( $query_id_raw ) : '';

    if ( ! empty( $query_id ) ) {
        $args['gm2_query_id'] = $query_id;
    }

    $category_taxonomy_raw = gm2_search_get_request_var( 'gm2_category_taxonomy' );
    if ( is_string( $category_taxonomy_raw ) ) {
        $taxonomy = sanitize_key( $category_taxonomy_raw );
        if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
            $args['gm2_category_taxonomy'] = $taxonomy;
        }
    }

    $posts_per_page_raw = gm2_search_get_request_var( 'posts_per_page' );
    if ( '' !== $posts_per_page_raw && null !== $posts_per_page_raw ) {
        $args['posts_per_page'] = max( 1, absint( $posts_per_page_raw ) );
    }

    $paged_raw = gm2_search_get_request_var( 'paged' );
    if ( null === $paged_raw ) {
        $paged_raw = gm2_search_get_request_var( 'page' );
    }

    if ( null !== $paged_raw && '' !== $paged_raw ) {
        $args['paged'] = max( 1, absint( $paged_raw ) );
    }

    $args = array_merge( $args, $overrides );

    return apply_filters( 'gm2_search_query_args', $args, $overrides );
}

function gm2_search_populate_query_from_request( $query ) {
    $is_main_query = method_exists( $query, 'is_main_query' ) ? $query->is_main_query() : false;

    $args = gm2_search_build_query_args_from_request();

    foreach ( $args as $key => $value ) {
        $query->set( $key, $value );

        if ( $is_main_query ) {
            set_query_var( $key, $value );
        }
    }

    if ( isset( $args['gm2_query_id'] ) && ! empty( $args['gm2_query_id'] ) ) {
        /**
         * Allow developers to hook into the customised search query.
         */
        do_action( 'gm2_search/query/' . $args['gm2_query_id'], $query );
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
    if ( gm2_search_is_backend_context() || ! $query->is_main_query() ) {
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
        'gm2_results_template_id',
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
    if ( gm2_search_is_backend_context() || $query->is_main_query() ) {
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
        'gm2_results_template_id',
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

    $results_template_id = gm2_search_get_request_results_template_id();

    if ( $results_template_id ) {
        $args['gm2_results_template_id'] = $results_template_id;
    }

    $post_types = gm2_search_get_request_post_types();

    if ( ! empty( $post_types ) ) {
        if ( 1 === count( $post_types ) ) {
            $args['post_type'] = reset( $post_types );
        } else {
            $args['post_type'] = array_values( $post_types );
        }
    }

    $snapshot_keys = array_merge(
        $id_keys,
        [
            's',
            'gm2_category_filter',
            'gm2_category_taxonomy',
            'gm2_date_range',
            'gm2_orderby',
            'gm2_order',
            'gm2_query_id',
            'gm2_results_template_id',
            'post_type',
        ]
    );

    $request_snapshot = [];

    foreach ( $snapshot_keys as $key ) {
        if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $value                    = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $request_snapshot[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
        }
    }

    gm2_search_log_event(
        'debug',
        'Computed active Gm2 search query arguments.',
        [
            'query_args'       => $args,
            'request_snapshot' => $request_snapshot,
        ]
    );

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
    if ( gm2_search_is_backend_context() ) {
        gm2_search_log_event(
            'debug',
            'Pagination link preservation skipped for backend request.',
            [
                'result' => $result,
                'ajax'   => function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : null,
            ]
        );
        return $result;
    }

    $args = gm2_search_get_active_query_args();

    if ( empty( $args ) ) {
        gm2_search_log_event(
            'debug',
            'Pagination link preservation skipped because no active filters were detected.',
            [
                'result'  => $result,
                'pagenum' => $pagenum,
            ]
        );
        return $result;
    }

    $updated = add_query_arg( $args, $result );

    gm2_search_log_event(
        'debug',
        'Pagination link updated with active Gm2 filters.',
        [
            'original' => $result,
            'updated'  => $updated,
            'pagenum'  => $pagenum,
            'escape'   => $escape,
        ]
    );

    return $updated;
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
    if ( gm2_search_is_backend_context() ) {
        gm2_search_log_event(
            'debug',
            'paginate_links_args filter skipped for backend request.',
            [
                'ajax' => function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : null,
            ]
        );
        return $args;
    }

    $query_args = gm2_search_get_active_query_args();

    if ( empty( $query_args ) ) {
        gm2_search_log_event(
            'debug',
            'paginate_links_args filter detected no active filters to merge.',
            [
                'args' => $args,
            ]
        );
        return $args;
    }

    gm2_search_log_event(
        'debug',
        'Merging active filters into paginate_links() arguments.',
        [
            'incoming_args' => $args,
            'active_args'   => $query_args,
        ]
    );

    if ( empty( $args['add_args'] ) ) {
        $args['add_args'] = $query_args;
        gm2_search_log_event(
            'debug',
            'paginate_links() arguments initialised with active filters.',
            [
                'result_args' => $args,
            ]
        );
        return $args;
    }

    if ( is_array( $args['add_args'] ) ) {
        $args['add_args'] = $query_args + $args['add_args'];
        gm2_search_log_event(
            'debug',
            'paginate_links() arguments merged with array add_args.',
            [
                'result_args' => $args,
            ]
        );
        return $args;
    }

    if ( is_string( $args['add_args'] ) ) {
        parse_str( $args['add_args'], $existing_args );
        if ( ! is_array( $existing_args ) ) {
            $existing_args = [];
        }

        $args['add_args'] = $query_args + $existing_args;
        gm2_search_log_event(
            'debug',
            'paginate_links() arguments merged with string add_args.',
            [
                'result_args' => $args,
            ]
        );
        return $args;
    }

    gm2_search_log_event(
        'debug',
        'paginate_links() arguments left unchanged because add_args type is unsupported.',
        [
            'result_args' => $args,
        ]
    );

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
    if ( gm2_search_is_backend_context() ) {
        gm2_search_log_event(
            'debug',
            'paginate_links output rewrite skipped for backend request.',
            [
                'ajax' => function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : null,
            ]
        );
        return $links;
    }

    $query_args = gm2_search_get_active_query_args();

    if ( empty( $query_args ) ) {
        gm2_search_log_event(
            'debug',
            'paginate_links output rewrite skipped because no active filters were detected.'
        );
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
        gm2_search_log_event(
            'debug',
            'Rewriting paginate_links() array output.',
            [
                'count'       => count( $links ),
                'active_args' => $query_args,
            ]
        );
        foreach ( $links as $index => $markup ) {
            $links[ $index ] = $rewrite_html( $markup );
        }

        return $links;
    }

    gm2_search_log_event(
        'debug',
        'Rewriting paginate_links() string output.',
        [
            'active_args' => $query_args,
        ]
    );

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
    if ( gm2_search_is_backend_context() ) {
        gm2_search_log_event(
            'debug',
            'WooCommerce pagination args filter skipped for backend request.',
            [
                'ajax' => function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : null,
            ]
        );
        return $args;
    }

    $query_args = gm2_search_get_active_query_args();

    if ( empty( $query_args ) ) {
        gm2_search_log_event(
            'debug',
            'WooCommerce pagination args filter detected no active filters to merge.',
            [
                'args' => $args,
            ]
        );
        return $args;
    }

    gm2_search_log_event(
        'debug',
        'Merging active filters into WooCommerce pagination arguments.',
        [
            'incoming_args' => $args,
            'active_args'   => $query_args,
        ]
    );

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

    gm2_search_log_event(
        'debug',
        'WooCommerce pagination arguments after merge.',
        [
            'result_args' => $args,
        ]
    );

    return $args;
}
add_filter( 'woocommerce_pagination_args', 'gm2_search_merge_woocommerce_pagination_args', 15 );

/**
 * Render the action controls for a product card, including the quantity selector when supported.
 *
 * @param WC_Product|int|null $product Product instance or product ID.
 * @return void
 */
function gm2_search_render_product_action_controls( $product = null ) {
    if ( ! function_exists( 'woocommerce_template_loop_add_to_cart' ) || ! class_exists( 'WC_Product' ) ) {
        return;
    }

    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( $product ? $product : get_the_ID() );
    }

    if ( ! $product ) {
        woocommerce_template_loop_add_to_cart();
        return;
    }

    if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && ! $product->is_sold_individually() ) {
        $min_value = $product->get_min_purchase_quantity();
        $max_value = $product->get_max_purchase_quantity();
        $quantity  = $min_value ? $min_value : 1;

        $button_classes = array_filter(
            [
                'button',
                'add_to_cart_button',
                $product->supports( 'ajax_add_to_cart' ) ? 'ajax_add_to_cart' : '',
                'product_type_' . $product->get_type(),
            ]
        );
        ?>
        <form class="cart gm2-search-loop__cart-form" action="<?php echo esc_url( $product->add_to_cart_url() ); ?>" method="post" enctype="multipart/form-data">
            <?php
            woocommerce_quantity_input(
                [
                    'input_name'  => 'quantity',
                    'input_value' => $quantity,
                    'min_value'   => $min_value,
                    'max_value'   => $max_value,
                ],
                $product
            );
            ?>
            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />
            <button type="submit" class="<?php echo esc_attr( implode( ' ', $button_classes ) ); ?>" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>" aria-label="<?php echo esc_attr( $product->add_to_cart_description() ); ?>">
                <?php echo esc_html( $product->add_to_cart_text() ); ?>
            </button>
        </form>
        <?php
        return;
    }

    woocommerce_template_loop_add_to_cart();
}

/**
 * Render a single product card using the custom gm2 template.
 *
 * @param WC_Product|int|null $product Product instance or product ID.
 * @return void
 */
function gm2_search_render_product_card( $product = null ) {
    if ( ! function_exists( 'wc_get_template' ) || ! class_exists( 'WC_Product' ) ) {
        return;
    }

    $render_state = gm2_search_get_results_template_render_state();

    if ( isset( $render_state['mode'] ) && 'layout' === $render_state['mode'] ) {
        return;
    }

    $had_global_product = array_key_exists( 'product', $GLOBALS );
    $previous_product   = $had_global_product ? $GLOBALS['product'] : null;

    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( $product ? $product : get_the_ID() );
    }

    if ( ! $product || ! $product->is_visible() ) {
        return;
    }

    $GLOBALS['product'] = $product;

    $rendered = false;

    $results_template_id = isset( $render_state['template_id'] ) ? (int) $render_state['template_id'] : gm2_search_get_active_results_template_id();
    $results_template_id = (int) apply_filters( 'gm2_search_results_template_id', $results_template_id, $product );

    if ( $results_template_id && class_exists( '\\Elementor\\Plugin' ) ) {
        $frontend = \Elementor\Plugin::instance()->frontend;

        if ( $frontend && method_exists( $frontend, 'get_builder_content_for_display' ) ) {
            $content = $frontend->get_builder_content_for_display( $results_template_id, true );

            if ( ! empty( $content ) ) {
                ?>
                <li <?php wc_product_class( 'gm2-search-loop__product-card gm2-search-loop__product-card--elementor', $product ); ?>>
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </li>
                <?php
                $rendered = true;
            }
        }
    }

    if ( ! $rendered ) {
        wc_get_template(
            'parts/gm2-search-product-card.php',
            [],
            '',
            plugin_dir_path( __FILE__ ) . 'templates/'
        );
    }

    if ( $had_global_product ) {
        $GLOBALS['product'] = $previous_product;
    } else {
        unset( $GLOBALS['product'] );
    }
}

/**
 * Ajax handler for loading filtered products while preserving the search term and filters.
 *
 * @return void
 */
function gm2_get_filter_products() {
    if ( ! function_exists( 'wc_get_template' ) ) {
        wp_send_json_error( [ 'message' => __( 'WooCommerce is required to use this search.', 'woo-search-optimized' ) ] );
    }

    $query_args = gm2_search_build_query_args_from_request();

    if ( empty( $query_args['post_type'] ) && post_type_exists( 'product' ) ) {
        $query_args['post_type'] = 'product';
    }

    $paged = isset( $query_args['paged'] ) ? max( 1, absint( $query_args['paged'] ) ) : 1;
    $query_args['paged'] = $paged;

    if ( empty( $query_args['posts_per_page'] ) ) {
        $query_args['posts_per_page'] = absint( get_option( 'posts_per_page', 12 ) );
    }

    $query = new WP_Query( $query_args );

    $previous_wp_query = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
    $GLOBALS['wp_query'] = $query;

    $render_state        = gm2_search_get_results_template_render_state();
    $is_layout_template  = ( isset( $render_state['mode'], $render_state['template_id'] ) && 'layout' === $render_state['mode'] && $render_state['template_id'] );
    $layout_content      = '';

    if ( $is_layout_template ) {
        $layout_content = gm2_search_render_elementor_results_layout();

        if ( '' === trim( (string) $layout_content ) ) {
            $is_layout_template = false;
        }
    }

    ob_start();

    if ( $is_layout_template ) {
        if ( function_exists( 'wc_setup_loop' ) ) {
            wc_setup_loop(
                [
                    'total'        => $query->found_posts,
                    'total_pages'  => $query->max_num_pages,
                    'per_page'     => (int) $query->get( 'posts_per_page' ),
                    'current'      => $paged,
                    'is_search'    => true,
                    'is_paginated' => $query->max_num_pages > 1,
                ]
            );
        }

        do_action( 'woocommerce_before_shop_loop' );

        echo $layout_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        do_action( 'woocommerce_after_shop_loop' );

        if ( function_exists( 'wc_reset_loop' ) ) {
            wc_reset_loop();
        }
    } elseif ( $query->have_posts() ) {
        if ( function_exists( 'wc_setup_loop' ) ) {
            wc_setup_loop(
                [
                    'total'        => $query->found_posts,
                    'total_pages'  => $query->max_num_pages,
                    'per_page'     => (int) $query->get( 'posts_per_page' ),
                    'current'      => $paged,
                    'is_search'    => true,
                    'is_paginated' => $query->max_num_pages > 1,
                ]
            );
        }

        if ( function_exists( 'woocommerce_product_loop_start' ) ) {
            woocommerce_product_loop_start();
        }

        while ( $query->have_posts() ) {
            $query->the_post();
            gm2_search_render_product_card();
        }

        if ( function_exists( 'woocommerce_product_loop_end' ) ) {
            woocommerce_product_loop_end();
        }

        if ( function_exists( 'wc_reset_loop' ) ) {
            wc_reset_loop();
        }
    } else {
        wc_get_template( 'loop/no-products-found.php' );
    }

    $content = ob_get_clean();

    if ( $previous_wp_query instanceof WP_Query ) {
        $GLOBALS['wp_query'] = $previous_wp_query;
    } else {
        unset( $GLOBALS['wp_query'] );
    }

    wp_reset_postdata();

    $search_term = gm2_search_get_request_search_term();
    $category_slugs = gm2_search_get_request_slugs( 'gm2_category_filter' );
    $category_value = ! empty( $category_slugs ) ? implode( ',', $category_slugs ) : '';
    $taxonomy_value = '';
    $taxonomy_raw   = gm2_search_get_request_var( 'gm2_category_taxonomy' );

    if ( is_string( $taxonomy_raw ) ) {
        $taxonomy = sanitize_key( $taxonomy_raw );
        if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
            $taxonomy_value = $taxonomy;
        }
    }

    $add_args = [];
    if ( '' !== $search_term ) {
        $add_args['s'] = $search_term;
    }
    if ( '' !== $category_value ) {
        $add_args['gm2_category_filter'] = $category_value;
    }
    if ( '' !== $taxonomy_value ) {
        $add_args['gm2_category_taxonomy'] = $taxonomy_value;
    }
    $results_template_id = gm2_search_get_request_results_template_id();
    if ( $results_template_id ) {
        $add_args['gm2_results_template_id'] = $results_template_id;
    }

    $pagination = paginate_links(
        [
            'total'    => max( 1, (int) $query->max_num_pages ),
            'current'  => $paged,
            'type'     => 'plain',
            'add_args' => $add_args,
        ]
    );

    wp_send_json_success(
        [
            'content'    => $content,
            'pagination' => $pagination,
            'max_pages'  => (int) $query->max_num_pages,
        ]
    );
}
add_action( 'wp_ajax_gm2_get_filter_products', 'gm2_get_filter_products' );
add_action( 'wp_ajax_nopriv_gm2_get_filter_products', 'gm2_get_filter_products' );

/**
 * Force the custom search loop template to load for product searches so the custom card is consistent.
 *
 * @param string $template Template path resolved by WordPress.
 * @return string
 */
function gm2_search_use_custom_search_template( $template ) {
    if ( gm2_search_is_backend_context() || is_admin() || ! function_exists( 'is_search' ) || ! is_search() ) {
        return $template;
    }

    $post_types = gm2_search_get_request_post_types();

    if ( empty( $post_types ) ) {
        $queried = get_query_var( 'post_type' );
        if ( $queried ) {
            $post_types = (array) $queried;
        }
    }

    if ( empty( $post_types ) ) {
        $post_types = [ 'product' ];
    }

    $post_types = array_unique( array_map( 'sanitize_key', (array) $post_types ) );

    if ( 1 !== count( $post_types ) || 'product' !== reset( $post_types ) ) {
        return $template;
    }

    $custom_template = plugin_dir_path( __FILE__ ) . 'templates/gm2-search-loop.php';

    if ( file_exists( $custom_template ) ) {
        return $custom_template;
    }

    return $template;
}
add_filter( 'template_include', 'gm2_search_use_custom_search_template', 40 );

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
