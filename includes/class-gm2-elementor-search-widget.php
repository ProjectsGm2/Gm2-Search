<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Search;

class Gm2_Search_Elementor_Widget extends Widget_Search {
    public function get_name() {
        return 'gm2-search-bar';
    }

    public function get_title() {
        return __( 'Gm2 Search Bar', 'woo-search-optimized' );
    }
}
