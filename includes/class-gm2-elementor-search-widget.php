<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Icons_Manager;
use Elementor\Group_Control_Background;

class Gm2_Search_Elementor_Widget extends Widget_Base {
    /**
     * Active taxonomy used for category-related controls.
     *
     * @var string
     */
    private $category_taxonomy = '';

    public function get_style_depends() {
        return [ 'elementor-frontend', 'elementor-search-form', 'gm2-search-widget' ];
    }

    public function get_script_depends() {
        return [ 'gm2-search-widget' ];
    }

    public function get_name() {
        return 'gm2-search-bar';
    }

    public function get_title() {
        return __( 'Gm2 Search Bar', 'woo-search-optimized' );
    }

    public function get_icon() {
        return 'eicon-search';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    public function get_keywords() {
        return [ 'search', 'form', 'find', 'query' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Search Form', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'skin',
            [
                'label' => __( 'Skin', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'classic',
                'options' => [
                    'classic'     => __( 'Classic', 'woo-search-optimized' ),
                    'minimal'     => __( 'Minimal', 'woo-search-optimized' ),
                    'full_screen' => __( 'Full Screen', 'woo-search-optimized' ),
                ],
            ]
        );

        $this->add_control(
            'placeholder',
            [
                'label' => __( 'Placeholder', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Search &hellip;', 'woo-search-optimized' ),
                'placeholder' => __( 'Type search term', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'show_submit_button',
            [
                'label' => __( 'Show Submit Button', 'woo-search-optimized' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Show', 'woo-search-optimized' ),
                'label_off' => __( 'Hide', 'woo-search-optimized' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'submit_trigger',
            [
                'label' => __( 'Submit Trigger', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'click_submit',
                'options' => [
                    'click_submit' => __( 'Submit button', 'woo-search-optimized' ),
                    'key_enter'    => __( 'Enter key', 'woo-search-optimized' ),
                    'both'         => __( 'Both', 'woo-search-optimized' ),
                ],
                'condition' => [
                    'show_submit_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'submit_type',
            [
                'label' => __( 'Submit Type', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'text',
                'options' => [
                    'text' => __( 'Text', 'woo-search-optimized' ),
                    'icon' => __( 'Icon', 'woo-search-optimized' ),
                    'text_icon' => __( 'Text & Icon', 'woo-search-optimized' ),
                ],
                'condition' => [
                    'show_submit_button' => 'yes',
                    'submit_trigger!' => 'key_enter',
                ],
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __( 'Button Text', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Search', 'woo-search-optimized' ),
                'placeholder' => __( 'Enter button text', 'woo-search-optimized' ),
                'condition' => [
                    'show_submit_button' => 'yes',
                    'submit_trigger!' => 'key_enter',
                    'submit_type!' => 'icon',
                ],
            ]
        );

        $this->add_control(
            'button_icon',
            [
                'label' => __( 'Button Icon', 'woo-search-optimized' ),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'eicon-search',
                    'library' => 'eicons',
                ],
                'condition' => [
                    'show_submit_button' => 'yes',
                    'submit_trigger!' => 'key_enter',
                    'submit_type!' => 'text',
                ],
            ]
        );

        $this->add_control(
            'icon_position',
            [
                'label' => __( 'Icon Position', 'woo-search-optimized' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'before' => [
                        'title' => __( 'Before', 'woo-search-optimized' ),
                        'icon' => 'eicon-h-align-left',
                    ],
                    'after' => [
                        'title' => __( 'After', 'woo-search-optimized' ),
                        'icon' => 'eicon-h-align-right',
                    ],
                ],
                'default' => 'before',
                'toggle' => false,
                'condition' => [
                    'show_submit_button' => 'yes',
                    'submit_trigger!' => 'key_enter',
                    'submit_type' => [ 'text_icon' ],
                ],
            ]
        );

        $this->add_responsive_control(
            'alignment',
            [
                'label' => __( 'Alignment', 'woo-search-optimized' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __( 'Left', 'woo-search-optimized' ),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __( 'Center', 'woo-search-optimized' ),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __( 'Right', 'woo-search-optimized' ),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => __( 'Justified', 'woo-search-optimized' ),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__container' => 'justify-content: {{VALUE}};',
                ],
                'selectors_dictionary' => [
                    'left' => 'flex-start',
                    'center' => 'center',
                    'right' => 'flex-end',
                    'justify' => 'stretch',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_width',
            [
                'label' => __( 'Input Width', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ '%', 'px', 'vw' ],
                'range' => [
                    '%' => [ 'min' => 10, 'max' => 100 ],
                    'px' => [ 'min' => 50, 'max' => 1000 ],
                    'vw' => [ 'min' => 10, 'max' => 100 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'search_post_type',
            [
                'label' => __( 'Post Type', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'Leave empty for all post types', 'woo-search-optimized' ),
                'description' => __( 'Deprecated. Use the Source control below for new configurations.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'heading_query_settings',
            [
                'type' => Controls_Manager::HEADING,
                'label' => __( 'Query Settings', 'woo-search-optimized' ),
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'query_source',
            [
                'label' => __( 'Source', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_post_type_options(),
                'multiple' => true,
                'label_block' => true,
                'description' => __( 'Select one or more post types to include in search results. Leave empty to search all public post types.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'include_posts',
            [
                'label' => __( 'Include Posts', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __( 'Comma-separated post IDs (e.g. 12,34,56)', 'woo-search-optimized' ),
                'description' => __( 'Limit the results to specific post IDs.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'exclude_posts',
            [
                'label' => __( 'Exclude Posts', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __( 'Comma-separated post IDs (e.g. 12,34,56)', 'woo-search-optimized' ),
                'description' => __( 'Exclude specific post IDs from the results.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'include_categories',
            [
                'label' => __( 'Include Categories', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_category_control_options(),
                'description' => __( 'Restrict results to the selected categories.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'exclude_categories',
            [
                'label' => __( 'Exclude Categories', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_category_control_options(),
                'description' => __( 'Exclude the selected categories from the results.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'date_range',
            [
                'label' => __( 'Date', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => __( 'All time', 'woo-search-optimized' ),
                    'past_day' => __( 'Past Day', 'woo-search-optimized' ),
                    'past_week' => __( 'Past Week', 'woo-search-optimized' ),
                    'past_month' => __( 'Past Month', 'woo-search-optimized' ),
                    'past_year' => __( 'Past Year', 'woo-search-optimized' ),
                ],
                'default' => '',
                'description' => __( 'Filter results by publish date.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'order_by',
            [
                'label' => __( 'Order By', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => __( 'Default (Relevance)', 'woo-search-optimized' ),
                    'date' => __( 'Date', 'woo-search-optimized' ),
                    'title' => __( 'Title', 'woo-search-optimized' ),
                    'price' => __( 'Price', 'woo-search-optimized' ),
                    'rand' => __( 'Random', 'woo-search-optimized' ),
                ],
                'default' => '',
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __( 'Order', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => __( 'Default', 'woo-search-optimized' ),
                    'ASC' => __( 'Ascending', 'woo-search-optimized' ),
                    'DESC' => __( 'Descending', 'woo-search-optimized' ),
                ],
                'default' => '',
            ]
        );

        $this->add_control(
            'query_id',
            [
                'label' => __( 'Query ID', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'Enter a query ID', 'woo-search-optimized' ),
                'description' => __( 'Use this ID to target the search query in custom code (e.g. hooks).', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'results_template_id',
            [
                'label' => __( 'Results Template', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => false,
                'options' => $this->get_results_template_options(),
                'description' => __( 'Select an Elementor template to render each result item. Leave empty to use the default GM2 layout.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'heading_additional_settings',
            [
                'type' => Controls_Manager::HEADING,
                'label' => __( 'Additional Settings', 'woo-search-optimized' ),
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'pagination_type',
            [
                'label' => __( 'Pagination Type', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'none' => __( 'None', 'woo-search-optimized' ),
                    'numbers' => __( 'Numbers', 'woo-search-optimized' ),
                    'previous_next' => __( 'Previous/Next', 'woo-search-optimized' ),
                    'numbers_previous_next' => __( 'Numbers + Previous/Next', 'woo-search-optimized' ),
                ],
                'default' => 'none',
            ]
        );

        $this->add_control(
            'pagination_prev_label',
            [
                'label' => __( 'Previous Label', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Previous', 'woo-search-optimized' ),
                'condition' => [
                    'pagination_type' => [ 'previous_next', 'numbers_previous_next' ],
                ],
            ]
        );

        $this->add_control(
            'pagination_next_label',
            [
                'label' => __( 'Next Label', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Next', 'woo-search-optimized' ),
                'condition' => [
                    'pagination_type' => [ 'previous_next', 'numbers_previous_next' ],
                ],
            ]
        );

        $this->add_control(
            'pagination_page_limit',
            [
                'label' => __( 'Page Limit', 'woo-search-optimized' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 1,
                'condition' => [
                    'pagination_type!' => 'none',
                ],
            ]
        );

        $this->add_control(
            'pagination_shorten',
            [
                'label' => __( 'Shorten', 'woo-search-optimized' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'woo-search-optimized' ),
                'label_off' => __( 'No', 'woo-search-optimized' ),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => [
                    'pagination_type' => [ 'numbers', 'numbers_previous_next' ],
                ],
            ]
        );

        $this->add_control(
            'show_category_filter',
            [
                'label' => __( 'Show Category Filter', 'woo-search-optimized' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Show', 'woo-search-optimized' ),
                'label_off' => __( 'Hide', 'woo-search-optimized' ),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'category_filter_placeholder',
            [
                'label' => __( 'Category Placeholder', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'All categories', 'woo-search-optimized' ),
                'placeholder' => __( 'Select category', 'woo-search-optimized' ),
                'condition' => [
                    'show_category_filter' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'category_filter_multi_select',
            [
                'label' => __( 'Enable Multi-Select', 'woo-search-optimized' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'woo-search-optimized' ),
                'label_off' => __( 'No', 'woo-search-optimized' ),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => [
                    'show_category_filter' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'category_filter_terms',
            [
                'label' => __( 'Limit Categories', 'woo-search-optimized' ),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'options' => $this->get_category_control_options(),
                'condition' => [
                    'show_category_filter' => 'yes',
                ],
                'description' => __( 'Leave empty to display all categories.', 'woo-search-optimized' ),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_category_filter',
            [
                'label' => __( 'Category Filter', 'woo-search-optimized' ),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_category_filter' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'category_filter_typography',
                'selector' => '{{WRAPPER}} .gm2-category-filter__value-text, {{WRAPPER}} .gm2-category-filter__option-text',
            ]
        );

        $this->add_control(
            'category_filter_placeholder_color',
            [
                'label' => __( 'Placeholder Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter:not(.gm2-category-filter--has-value) .gm2-category-filter__value-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_value_color',
            [
                'label' => __( 'Selected Text Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter--has-value .gm2-category-filter__value-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_toggle_color',
            [
                'label' => __( 'Toggle Text Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__toggle' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_toggle_background',
            [
                'label' => __( 'Toggle Background', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__toggle' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_toggle_background_hover',
            [
                'label' => __( 'Toggle Background (Hover)', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__toggle:hover, {{WRAPPER}} .gm2-category-filter--open .gm2-category-filter__toggle' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_toggle_icon_color',
            [
                'label' => __( 'Toggle Icon Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__caret' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'category_filter_toggle_padding',
            [
                'label' => __( 'Toggle Padding', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'category_filter_toggle_border',
                'selector' => '{{WRAPPER}} .gm2-category-filter__toggle',
            ]
        );

        $this->add_responsive_control(
            'category_filter_toggle_radius',
            [
                'label' => __( 'Toggle Border Radius', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'category_filter_toggle_shadow',
                'selector' => '{{WRAPPER}} .gm2-category-filter__toggle',
            ]
        );

        $this->add_control(
            'category_filter_dropdown_background',
            [
                'label' => __( 'Dropdown Background', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__dropdown' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_dropdown_text_color',
            [
                'label' => __( 'Dropdown Text Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__option-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'category_filter_dropdown_border',
                'selector' => '{{WRAPPER}} .gm2-category-filter__dropdown',
            ]
        );

        $this->add_responsive_control(
            'category_filter_dropdown_radius',
            [
                'label' => __( 'Dropdown Border Radius', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'category_filter_dropdown_padding',
            [
                'label' => __( 'Dropdown Padding', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__options' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'category_filter_dropdown_shadow',
                'selector' => '{{WRAPPER}} .gm2-category-filter__dropdown',
            ]
        );

        $this->add_control(
            'category_filter_option_gap',
            [
                'label' => __( 'Option Spacing', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 40,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__option-label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_checkbox_border_color',
            [
                'label' => __( 'Checkbox Border Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__checkbox' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_checkbox_background',
            [
                'label' => __( 'Checkbox Background', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__checkbox' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_checkbox_checked_background',
            [
                'label' => __( 'Checkbox Checked Background', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__checkbox:checked' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'category_filter_checkbox_checkmark_color',
            [
                'label' => __( 'Checkbox Checkmark Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__checkbox:checked::after' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'category_filter_checkbox_size',
            [
                'label' => __( 'Checkbox Size', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [
                        'min' => 8,
                        'max' => 32,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__checkbox' => '--gm2-category-checkbox-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'category_filter_checkbox_spacing',
            [
                'label' => __( 'Checkbox Spacing', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 24,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gm2-category-filter__checkbox' => 'margin-right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_input',
            [
                'label' => __( 'Input', 'woo-search-optimized' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select',
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label' => __( 'Text Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_placeholder_color',
            [
                'label' => __( 'Placeholder Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input::placeholder' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_background_color',
            [
                'label' => __( 'Background Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_padding',
            [
                'label' => __( 'Padding', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_border_radius',
            [
                'label' => __( 'Border Radius', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'input_box_shadow',
                'selector' => '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select',
            ]
        );

        $this->add_responsive_control(
            'input_height',
            [
                'label' => __( 'Height', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 20, 'max' => 150 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__category-select' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_button',
            [
                'label' => __( 'Button', 'woo-search-optimized' ),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_submit_button' => 'yes',
                    'submit_trigger!' => 'key_enter',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .elementor-search-form__submit',
                'condition' => [
                    'submit_type!' => 'icon',
                    'submit_trigger!' => 'key_enter',
                ],
            ]
        );

        $this->start_controls_tabs( 'tabs_button_style' );

        $this->start_controls_tab(
            'tab_button_normal',
            [
                'label' => __( 'Normal', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __( 'Text Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_icon_color',
            [
                'label' => __( 'Icon Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit .elementor-button-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .elementor-search-form__submit .elementor-button-icon svg' => 'fill: {{VALUE}};',
                ],
                'condition' => [
                    'submit_type!' => 'text',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => __( 'Background Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .elementor-search-form__submit',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_button_hover',
            [
                'label' => __( 'Hover', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'button_text_color_hover',
            [
                'label' => __( 'Text Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit:hover, {{WRAPPER}} .elementor-search-form__submit:focus' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_icon_color_hover',
            [
                'label' => __( 'Icon Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit:hover .elementor-button-icon, {{WRAPPER}} .elementor-search-form__submit:focus .elementor-button-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .elementor-search-form__submit:hover .elementor-button-icon svg, {{WRAPPER}} .elementor-search-form__submit:focus .elementor-button-icon svg' => 'fill: {{VALUE}};',
                ],
                'condition' => [
                    'submit_type!' => 'text',
                ],
            ]
        );

        $this->add_control(
            'button_background_color_hover',
            [
                'label' => __( 'Background Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit:hover, {{WRAPPER}} .elementor-search-form__submit:focus' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow_hover',
                'selector' => '{{WRAPPER}} .elementor-search-form__submit:hover, {{WRAPPER}} .elementor-search-form__submit:focus',
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .elementor-search-form__submit',
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __( 'Border Radius', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __( 'Padding', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_spacing',
            [
                'label' => __( 'Gap', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit' => 'margin-left: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .elementor-search-form--icon-after .elementor-search-form__submit' => 'margin-left: 0; margin-right: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'submit_trigger!' => 'key_enter',
                ],
            ]
        );

        $this->add_responsive_control(
            'icon_size',
            [
                'label' => __( 'Icon Size', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em' ],
                'range' => [
                    'px' => [ 'min' => 8, 'max' => 60 ],
                    'em' => [ 'min' => 0.5, 'max' => 5 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit .elementor-button-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .elementor-search-form__submit svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_submit_button' => 'yes',
                    'submit_trigger!' => 'key_enter',
                    'submit_type' => [ 'icon', 'text_icon' ],
                ],
            ]
        );

        $this->add_responsive_control(
            'button_icon_gap',
            [
                'label' => __( 'Icon Gap', 'woo-search-optimized' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 60 ],
                    'em' => [ 'min' => 0, 'max' => 5 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__submit' => '--gm2-search-button-icon-gap: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_submit_button' => 'yes',
                    'submit_trigger!' => 'key_enter',
                    'submit_type' => [ 'icon', 'text_icon' ],
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_container',
            [
                'label' => __( 'Container', 'woo-search-optimized' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'types' => [ 'classic', 'gradient' ],
                'selector' => '{{WRAPPER}} .elementor-search-form',
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __( 'Padding', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __( 'Border Radius', 'woo-search-optimized' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .elementor-search-form',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $query_var   = 's';
        $placeholder = isset( $settings['placeholder'] ) ? $settings['placeholder'] : '';
        if ( '' === trim( (string) $placeholder ) ) {
            $placeholder = __( 'Search products…', 'woo-search-optimized' );
        }
        $post_type   = 'product';
        $selected_post_types = [ 'product' ];
        $include_post_ids = $this->parse_ids_setting( isset( $settings['include_posts'] ) ? $settings['include_posts'] : '' );
        $exclude_post_ids = $this->parse_ids_setting( isset( $settings['exclude_posts'] ) ? $settings['exclude_posts'] : '' );
        $include_category_ids = $this->parse_select_setting( isset( $settings['include_categories'] ) ? $settings['include_categories'] : [] );
        $exclude_category_ids = $this->parse_select_setting( isset( $settings['exclude_categories'] ) ? $settings['exclude_categories'] : [] );
        $date_range = $this->sanitize_date_range_value( isset( $settings['date_range'] ) ? $settings['date_range'] : '' );
        $order_by = $this->sanitize_order_by_value( isset( $settings['order_by'] ) ? $settings['order_by'] : '' );
        $order_direction = $this->sanitize_order_value( isset( $settings['order'] ) ? $settings['order'] : '' );
        $query_id = $this->sanitize_query_id( isset( $settings['query_id'] ) ? $settings['query_id'] : '' );
        $results_template_id = $this->sanitize_results_template_id( isset( $settings['results_template_id'] ) ? $settings['results_template_id'] : '' );
        $submit_trigger = isset( $settings['submit_trigger'] ) ? $settings['submit_trigger'] : 'click_submit';
        $show_button_setting = ( isset( $settings['show_submit_button'] ) && 'yes' === $settings['show_submit_button'] );
        $show_button = $show_button_setting && 'key_enter' !== $submit_trigger;
        $form_submit_trigger = $show_button ? $submit_trigger : 'key_enter';
        $submit_type = $show_button ? ( isset( $settings['submit_type'] ) ? $settings['submit_type'] : 'text' ) : '';
        $icon_position = isset( $settings['icon_position'] ) ? $settings['icon_position'] : 'before';
        $button_icon = isset( $settings['button_icon'] ) ? $settings['button_icon'] : [];
        $has_icon    = ! empty( $button_icon['value'] );
        $show_category_filter = ( isset( $settings['show_category_filter'] ) && 'yes' === $settings['show_category_filter'] );
        $category_taxonomy = $this->resolve_category_taxonomy( $settings, $selected_post_types, $post_type );
        $this->set_category_taxonomy( $category_taxonomy );
        $category_placeholder = isset( $settings['category_filter_placeholder'] ) ? $settings['category_filter_placeholder'] : '';
        if ( '' === trim( (string) $category_placeholder ) ) {
            $category_placeholder = __( 'All categories', 'woo-search-optimized' );
        }
        $multi_select_enabled = ( isset( $settings['category_filter_multi_select'] ) && 'yes' === $settings['category_filter_multi_select'] );
        $category_terms = $show_category_filter ? $this->get_categories_for_render( $settings ) : [];
        $category_query_var = $this->get_category_query_var();
        $current_category = '';
        $current_category_slugs = [];

        if ( $multi_select_enabled ) {
            $current_category_slugs = $this->get_multi_category_request_slugs();
        } else {
            $current_category = get_query_var( $category_query_var );
            if ( empty( $current_category ) && isset( $_GET[ $category_query_var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $current_category = sanitize_text_field( wp_unslash( $_GET[ $category_query_var ] ) );
            }
        }

        $selected_category_names = [];
        if ( $multi_select_enabled && ! empty( $current_category_slugs ) && ! empty( $category_terms ) ) {
            foreach ( $category_terms as $category_term ) {
                if ( in_array( $category_term->slug, $current_category_slugs, true ) ) {
                    $selected_category_names[] = $category_term->name;
                }
            }
        }

        $this->add_render_attribute( 'form', 'class', [
            'elementor-search-form',
            'elementor-search-form--skin-' . $settings['skin'],
        ] );

        if ( $show_category_filter && ! empty( $category_terms ) ) {
            $this->add_render_attribute( 'form', 'class', 'elementor-search-form--has-category' );
            if ( $multi_select_enabled ) {
                $this->add_render_attribute( 'form', 'class', 'elementor-search-form--category-multi' );
            }
        }

        if ( $show_button && 'text_icon' === $submit_type ) {
            $this->add_render_attribute( 'form', 'class', 'elementor-search-form--with-button' );
            $this->add_render_attribute( 'form', 'class', 'elementor-search-form--icon-' . $icon_position );
        } elseif ( $show_button && 'icon' === $submit_type ) {
            $this->add_render_attribute( 'form', 'class', 'elementor-search-form--submit-icon' );
        } elseif ( $show_button && 'text' === $submit_type ) {
            $this->add_render_attribute( 'form', 'class', 'elementor-search-form--submit-text' );
        } else {
            $this->add_render_attribute( 'form', 'class', 'elementor-search-form--no-button' );
        }

        $this->add_render_attribute( 'form', 'role', 'search' );
        $this->add_render_attribute( 'form', 'method', 'get' );
        $this->add_render_attribute( 'form', 'action', esc_url( home_url( '/' ) ) );
        $this->add_render_attribute( 'form', 'data-submit-trigger', $form_submit_trigger );

        $pagination_type = isset( $settings['pagination_type'] ) ? $settings['pagination_type'] : 'none';
        $pagination_page_limit = isset( $settings['pagination_page_limit'] ) ? absint( $settings['pagination_page_limit'] ) : 0;
        $pagination_shorten = isset( $settings['pagination_shorten'] ) && 'yes' === $settings['pagination_shorten'];
        $pagination_prev_label = ! empty( $settings['pagination_prev_label'] ) ? $settings['pagination_prev_label'] : __( 'Previous', 'woo-search-optimized' );
        $pagination_next_label = ! empty( $settings['pagination_next_label'] ) ? $settings['pagination_next_label'] : __( 'Next', 'woo-search-optimized' );

        $this->add_render_attribute( 'form', 'data-pagination-type', $pagination_type );

        if ( $pagination_page_limit > 0 ) {
            $this->add_render_attribute( 'form', 'data-pagination-page-limit', $pagination_page_limit );
        }

        $this->add_render_attribute( 'form', 'data-pagination-shorten', $pagination_shorten ? 'true' : 'false' );

        if ( in_array( $pagination_type, [ 'previous_next', 'numbers_previous_next' ], true ) ) {
            $this->add_render_attribute( 'form', 'data-pagination-prev-label', wp_strip_all_tags( $pagination_prev_label ) );
            $this->add_render_attribute( 'form', 'data-pagination-next-label', wp_strip_all_tags( $pagination_next_label ) );
        }

        $this->add_render_attribute( 'input', 'type', 'search' );
        $this->add_render_attribute( 'input', 'name', $query_var );
        $this->add_render_attribute( 'input', 'class', 'elementor-search-form__input' );
        $this->add_render_attribute( 'input', 'placeholder', wp_strip_all_tags( $placeholder ) );
        $this->add_render_attribute( 'input', 'value', get_search_query() );

        $button_text = isset( $settings['button_text'] ) ? $settings['button_text'] : '';
        $button_class = 'elementor-search-form__submit';

        if ( $show_button && 'minimal' === $settings['skin'] ) {
            $button_class .= ' elementor-search-form__submit--minimal';
        }

        if ( $show_button && 'text_icon' === $submit_type ) {
            $button_class .= ' elementor-search-form__submit--text-icon';
            $button_class .= ( 'after' === $icon_position ) ? ' elementor-align-icon-right' : ' elementor-align-icon-left';
        }

        $this->add_render_attribute( 'button', 'class', $button_class );
        $this->add_render_attribute( 'button', 'type', 'submit' );
        $this->add_render_attribute( 'button', 'aria-label', wp_strip_all_tags( $button_text ? $button_text : $placeholder ) );

        ?>
        <form <?php echo $this->get_render_attribute_string( 'form' ); ?>>
            <div class="elementor-search-form__container">
                <?php if ( $show_category_filter && ! empty( $category_terms ) ) : ?>
                    <?php
                    $category_control_id = 'gm2-search-category-' . $this->get_id();
                    $category_toggle_id  = $category_control_id . '-toggle';
                    $category_dropdown_id = $category_control_id . '-dropdown';
                    $has_selected_categories = $multi_select_enabled && ! empty( $selected_category_names );
                    $display_category_value = $has_selected_categories ? implode( ', ', $selected_category_names ) : $category_placeholder;
                    ?>
                    <div class="elementor-search-form__category">
                        <label class="elementor-search-form__category-label elementor-screen-only" for="<?php echo esc_attr( $multi_select_enabled ? $category_toggle_id : $category_control_id ); ?>">
                            <?php esc_html_e( 'Search category', 'woo-search-optimized' ); ?>
                        </label>
                        <?php if ( $multi_select_enabled ) : ?>
                            <div class="gm2-category-filter gm2-category-filter--multi<?php echo $has_selected_categories ? ' gm2-category-filter--has-value' : ''; ?>" data-placeholder="<?php echo esc_attr( $category_placeholder ); ?>">
                                <button type="button" class="gm2-category-filter__toggle" id="<?php echo esc_attr( $category_toggle_id ); ?>" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?php echo esc_attr( $category_dropdown_id ); ?>">
                                    <span class="gm2-category-filter__value-text"><?php echo esc_html( $display_category_value ); ?></span>
                                    <span class="gm2-category-filter__caret" aria-hidden="true"></span>
                                </button>
                                <div class="gm2-category-filter__dropdown" id="<?php echo esc_attr( $category_dropdown_id ); ?>" hidden>
                                    <ul class="gm2-category-filter__options" role="listbox" aria-multiselectable="true">
                                        <?php foreach ( $category_terms as $category_term ) : ?>
                                            <li class="gm2-category-filter__option" role="option" aria-selected="<?php echo in_array( $category_term->slug, $current_category_slugs, true ) ? 'true' : 'false'; ?>">
                                                <label class="gm2-category-filter__option-label">
                                                    <input type="checkbox" class="gm2-category-filter__checkbox" value="<?php echo esc_attr( $category_term->slug ); ?>" data-label="<?php echo esc_attr( $category_term->name ); ?>" <?php checked( in_array( $category_term->slug, $current_category_slugs, true ) ); ?> />
                                                    <span class="gm2-category-filter__option-text"><?php echo esc_html( $category_term->name ); ?></span>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <input type="hidden" class="gm2-category-filter__value-input" name="gm2_category_filter" value="<?php echo esc_attr( implode( ',', $current_category_slugs ) ); ?>" />
                            </div>
                        <?php else : ?>
                            <select id="<?php echo esc_attr( $category_control_id ); ?>" class="elementor-search-form__category-select" name="<?php echo esc_attr( $category_query_var ); ?>">
                                <option value="" <?php selected( '', $current_category ); ?>><?php echo esc_html( $category_placeholder ); ?></option>
                                <?php foreach ( $category_terms as $category_term ) : ?>
                                    <option value="<?php echo esc_attr( $category_term->slug ); ?>" <?php selected( $category_term->slug, $current_category ); ?>><?php echo esc_html( $category_term->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <input <?php echo $this->get_render_attribute_string( 'input' ); ?> />
                <?php if ( $show_button ) : ?>
                    <button <?php echo $this->get_render_attribute_string( 'button' ); ?>>
                        <span class="elementor-search-form__button-content">
                            <?php if ( 'icon' === $submit_type && $has_icon ) : ?>
                                <span class="elementor-button-icon">
                                    <?php Icons_Manager::render_icon( $button_icon, [ 'aria-hidden' => 'true' ] ); ?>
                                </span>
                            <?php elseif ( 'text' === $submit_type ) : ?>
                                <span class="elementor-button-text"><?php echo esc_html( $button_text ); ?></span>
                            <?php elseif ( 'text_icon' === $submit_type ) : ?>
                                <?php if ( 'before' === $icon_position && $has_icon ) : ?>
                                    <span class="elementor-button-icon">
                                        <?php Icons_Manager::render_icon( $button_icon, [ 'aria-hidden' => 'true' ] ); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="elementor-button-text"><?php echo esc_html( $button_text ); ?></span>
                                <?php if ( 'after' === $icon_position && $has_icon ) : ?>
                                    <span class="elementor-button-icon">
                                        <?php Icons_Manager::render_icon( $button_icon, [ 'aria-hidden' => 'true' ] ); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </button>
                <?php endif; ?>
                <input type="hidden" name="post_type" value="product" />
                <?php if ( ! empty( $include_post_ids ) ) : ?>
                    <input type="hidden" name="gm2_include_posts" value="<?php echo esc_attr( implode( ',', $include_post_ids ) ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $exclude_post_ids ) ) : ?>
                    <input type="hidden" name="gm2_exclude_posts" value="<?php echo esc_attr( implode( ',', $exclude_post_ids ) ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $include_category_ids ) ) : ?>
                    <input type="hidden" name="gm2_include_categories" value="<?php echo esc_attr( implode( ',', $include_category_ids ) ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $exclude_category_ids ) ) : ?>
                    <input type="hidden" name="gm2_exclude_categories" value="<?php echo esc_attr( implode( ',', $exclude_category_ids ) ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $date_range ) ) : ?>
                    <input type="hidden" name="gm2_date_range" value="<?php echo esc_attr( $date_range ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $order_by ) ) : ?>
                    <input type="hidden" name="gm2_orderby" value="<?php echo esc_attr( $order_by ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $order_direction ) ) : ?>
                    <input type="hidden" name="gm2_order" value="<?php echo esc_attr( $order_direction ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $query_id ) ) : ?>
                    <input type="hidden" name="gm2_query_id" value="<?php echo esc_attr( $query_id ); ?>" />
                <?php endif; ?>
                <?php if ( ! empty( $results_template_id ) ) : ?>
                    <input type="hidden" name="gm2_results_template_id" value="<?php echo esc_attr( $results_template_id ); ?>" />
                <?php endif; ?>
                <?php $active_taxonomy = $this->get_active_category_taxonomy(); ?>
                <?php if ( $active_taxonomy ) : ?>
                    <input type="hidden" name="gm2_category_taxonomy" value="<?php echo esc_attr( $active_taxonomy ); ?>" />
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    protected function content_template() {
        ?>
        <#
        const queryVar = 's';
        const submitTrigger = settings.submit_trigger ? settings.submit_trigger : 'click_submit';
        const showButtonSetting = 'yes' === settings.show_submit_button;
        const showButton = showButtonSetting && 'key_enter' !== submitTrigger;
        const submitType = showButton ? ( settings.submit_type ? settings.submit_type : 'text' ) : '';
        const formSubmitTrigger = showButton ? submitTrigger : 'key_enter';
        const showCategoryFilter = 'yes' === settings.show_category_filter;
        const categoryMultiSelect = 'yes' === settings.category_filter_multi_select;
        const categoryPlaceholder = settings.category_filter_placeholder ? settings.category_filter_placeholder : '<?php echo esc_js( __( 'All categories', 'woo-search-optimized' ) ); ?>';
        const categoryData = <?php echo wp_json_encode( $this->get_category_data_map() ); ?>;
        const placeholderText = settings.placeholder && settings.placeholder.trim().length ? settings.placeholder : '<?php echo esc_js( __( 'Search products…', 'woo-search-optimized' ) ); ?>';
        const parseMultiValue = function( value ) {
            if ( Array.isArray( value ) ) {
                return value;
            }
            if ( value && 'object' === typeof value ) {
                return Object.keys( value );
            }
            if ( value ) {
                return [ value ];
            }

            return [];
        };
        let selectedCategoryIds = [];
        if ( settings.category_filter_terms ) {
            if ( Array.isArray( settings.category_filter_terms ) ) {
                selectedCategoryIds = settings.category_filter_terms;
            } else {
                selectedCategoryIds = Object.keys( settings.category_filter_terms );
            }
        }
        if ( ! selectedCategoryIds.length ) {
            selectedCategoryIds = Object.keys( categoryData );
        }
        const wrapperClasses = [
            'elementor-search-form',
            'elementor-search-form--skin-' + settings.skin,
        ];

        const includeCategoriesValue = parseMultiValue( settings.include_categories ).join( ',' );
        const excludeCategoriesValue = parseMultiValue( settings.exclude_categories ).join( ',' );
        const includePostsValue = settings.include_posts ? settings.include_posts : '';
        const excludePostsValue = settings.exclude_posts ? settings.exclude_posts : '';
        const dateRangeValue = settings.date_range ? settings.date_range : '';
        const orderByValue = settings.order_by ? settings.order_by : '';
        const orderValue = settings.order ? settings.order : '';
        const queryIdValue = settings.query_id ? settings.query_id : '';
        const resultsTemplateIdValue = settings.results_template_id ? settings.results_template_id : '';

        if ( showButton ) {
            if ( 'text_icon' === submitType ) {
                wrapperClasses.push( 'elementor-search-form--with-button' );
                wrapperClasses.push( 'elementor-search-form--icon-' + settings.icon_position );
            } else if ( 'icon' === submitType ) {
                wrapperClasses.push( 'elementor-search-form--submit-icon' );
            } else if ( 'text' === submitType ) {
                wrapperClasses.push( 'elementor-search-form--submit-text' );
            }
        } else {
            wrapperClasses.push( 'elementor-search-form--no-button' );
        }
        if ( showCategoryFilter && Object.keys( categoryData ).length ) {
            wrapperClasses.push( 'elementor-search-form--has-category' );
            if ( categoryMultiSelect ) {
                wrapperClasses.push( 'elementor-search-form--category-multi' );
            }
        }
        #>
        <?php
        $home_url = esc_url( home_url( '/' ) );
        $default_prev_label = esc_js( __( 'Previous', 'woo-search-optimized' ) );
        $default_next_label = esc_js( __( 'Next', 'woo-search-optimized' ) );
        ?>
        <#
        const buttonClasses = [ 'elementor-search-form__submit' ];
        if ( showButton && 'minimal' === settings.skin ) {
            buttonClasses.push( 'elementor-search-form__submit--minimal' );
        }
        if ( showButton && 'text_icon' === submitType ) {
            buttonClasses.push( 'elementor-search-form__submit--text-icon' );
            buttonClasses.push( 'after' === settings.icon_position ? 'elementor-align-icon-right' : 'elementor-align-icon-left' );
        }
        const ariaLabel = settings.button_text ? settings.button_text : placeholderText;
        const categorySelectId = 'gm2-search-category-' + view.getID();
        const categoryToggleId = categorySelectId + '-toggle';
        const categoryDropdownId = categorySelectId + '-dropdown';
        const categoryOptionIds = selectedCategoryIds;
        const paginationType = settings.pagination_type ? settings.pagination_type : 'none';
        const paginationPageLimit = settings.pagination_page_limit ? settings.pagination_page_limit : '';
        const paginationShorten = 'yes' === settings.pagination_shorten;
        const paginationHasPrevNext = [ 'previous_next', 'numbers_previous_next' ].includes( paginationType );
        const paginationPrevLabel = settings.pagination_prev_label ? settings.pagination_prev_label : '<?php echo $default_prev_label; ?>';
        const paginationNextLabel = settings.pagination_next_label ? settings.pagination_next_label : '<?php echo $default_next_label; ?>';
        #>
        <form class="{{ wrapperClasses.join( ' ' ) }}" role="search" method="get" action="<?php echo $home_url; ?>" data-submit-trigger="{{ formSubmitTrigger }}" data-pagination-type="{{ paginationType }}" data-pagination-shorten="{{ paginationShorten ? 'true' : 'false' }}"
            <# if ( paginationPageLimit ) { #> data-pagination-page-limit="{{ paginationPageLimit }}"<# } #>
            <# if ( paginationHasPrevNext ) { #> data-pagination-prev-label="{{ paginationPrevLabel }}" data-pagination-next-label="{{ paginationNextLabel }}"<# } #>>
            <div class="elementor-search-form__container">
                <# if ( showCategoryFilter && Object.keys( categoryData ).length ) { #>
                    <div class="elementor-search-form__category">
                        <label class="elementor-search-form__category-label elementor-screen-only" for="{{ categoryMultiSelect ? categoryToggleId : categorySelectId }}"><?php esc_html_e( 'Search category', 'woo-search-optimized' ); ?></label>
                        <# if ( categoryMultiSelect ) { #>
                            <div class="gm2-category-filter gm2-category-filter--multi" data-placeholder="{{ categoryPlaceholder }}">
                                <button type="button" class="gm2-category-filter__toggle" id="{{ categoryToggleId }}" aria-haspopup="listbox" aria-expanded="false" aria-controls="{{ categoryDropdownId }}">
                                    <span class="gm2-category-filter__value-text">{{ categoryPlaceholder }}</span>
                                    <span class="gm2-category-filter__caret" aria-hidden="true"></span>
                                </button>
                                <div class="gm2-category-filter__dropdown" id="{{ categoryDropdownId }}" hidden>
                                    <ul class="gm2-category-filter__options" role="listbox" aria-multiselectable="true">
                                        <# categoryOptionIds.forEach( function( termId ) {
                                            const termData = categoryData[ termId ];
                                            if ( ! termData ) {
                                                return;
                                            }
                                        #>
                                            <li class="gm2-category-filter__option" role="option" aria-selected="false">
                                                <label class="gm2-category-filter__option-label">
                                                    <input type="checkbox" class="gm2-category-filter__checkbox" value="{{ termData.slug }}" data-label="{{ termData.name }}" />
                                                    <span class="gm2-category-filter__option-text">{{ termData.name }}</span>
                                                </label>
                                            </li>
                                        <# } ); #>
                                    </ul>
                                </div>
                                <input type="hidden" class="gm2-category-filter__value-input" name="gm2_category_filter" value="" />
                            </div>
                        <# } else { #>
                            <select class="elementor-search-form__category-select" id="{{ categorySelectId }}" name="<?php echo esc_js( $this->get_category_query_var() ); ?>">
                                <option value="">{{ categoryPlaceholder }}</option>
                                <# categoryOptionIds.forEach( function( termId ) {
                                    const termData = categoryData[ termId ];
                                    if ( ! termData ) {
                                        return;
                                    }
                                #>
                                    <option value="{{ termData.slug }}">{{ termData.name }}</option>
                                <# } ); #>
                            </select>
                        <# } #>
                    </div>
                <# } #>
                <input class="elementor-search-form__input" type="search" name="{{ queryVar }}" placeholder="{{{ placeholderText }}}" value="" />
                <# if ( showButton ) { #>
                    <button class="{{ buttonClasses.join( ' ' ) }}" type="submit" aria-label="{{ ariaLabel }}">
                        <span class="elementor-search-form__button-content">
                            <# if ( 'icon' === submitType && settings.button_icon && settings.button_icon.value ) { #>
                                <span class="elementor-button-icon">
                                    <i class="{{ settings.button_icon.value }}" aria-hidden="true"></i>
                                </span>
                            <# } else if ( 'text' === submitType ) { #>
                                <span class="elementor-button-text">{{{ settings.button_text }}}</span>
                            <# } else if ( 'text_icon' === submitType ) { #>
                                <# if ( 'before' === settings.icon_position && settings.button_icon && settings.button_icon.value ) { #>
                                    <span class="elementor-button-icon">
                                        <i class="{{ settings.button_icon.value }}" aria-hidden="true"></i>
                                    </span>
                                <# } #>
                                <span class="elementor-button-text">{{{ settings.button_text }}}</span>
                                <# if ( 'after' === settings.icon_position && settings.button_icon && settings.button_icon.value ) { #>
                                    <span class="elementor-button-icon">
                                        <i class="{{ settings.button_icon.value }}" aria-hidden="true"></i>
                                    </span>
                                <# } #>
                            <# } #>
                        </span>
                    </button>
                <# } #>
                <input type="hidden" name="post_type" value="product" />
                <# if ( includePostsValue ) { #>
                    <input type="hidden" name="gm2_include_posts" value="{{ includePostsValue }}" />
                <# } #>
                <# if ( excludePostsValue ) { #>
                    <input type="hidden" name="gm2_exclude_posts" value="{{ excludePostsValue }}" />
                <# } #>
                <# if ( includeCategoriesValue ) { #>
                    <input type="hidden" name="gm2_include_categories" value="{{ includeCategoriesValue }}" />
                <# } #>
                <# if ( excludeCategoriesValue ) { #>
                    <input type="hidden" name="gm2_exclude_categories" value="{{ excludeCategoriesValue }}" />
                <# } #>
                <# if ( dateRangeValue ) { #>
                    <input type="hidden" name="gm2_date_range" value="{{ dateRangeValue }}" />
                <# } #>
                <# if ( orderByValue ) { #>
                    <input type="hidden" name="gm2_orderby" value="{{ orderByValue }}" />
                <# } #>
                <# if ( orderValue ) { #>
                    <input type="hidden" name="gm2_order" value="{{ orderValue }}" />
                <# } #>
                <# if ( queryIdValue ) { #>
                    <input type="hidden" name="gm2_query_id" value="{{ queryIdValue }}" />
                <# } #>
                <# if ( resultsTemplateIdValue ) { #>
                    <input type="hidden" name="gm2_results_template_id" value="{{ resultsTemplateIdValue }}" />
                <# } #>
                <input type="hidden" name="gm2_category_taxonomy" value="<?php echo esc_js( $this->get_active_category_taxonomy() ); ?>" />
            </div>
        </form>
        <?php
    }

    private function get_post_type_options() {
        $post_types = get_post_types(
            [
                'public' => true,
            ],
            'objects'
        );

        $options = [];

        foreach ( $post_types as $post_type => $object ) {
            $options[ $post_type ] = isset( $object->labels->singular_name ) ? $object->labels->singular_name : $post_type;
        }

        return $options;
    }

    /**
     * Fetch local Elementor templates for the results template control.
     *
     * @return array<int, string>
     */
    private function get_results_template_options() {
        if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
            return [];
        }

        $plugin = \Elementor\Plugin::instance();

        if ( ! $plugin || ! isset( $plugin->templates_manager ) ) {
            return [];
        }

        $source = $plugin->templates_manager->get_source( 'local' );

        if ( ! $source || ! method_exists( $source, 'get_items' ) ) {
            return [];
        }

        $items = $source->get_items();

        if ( empty( $items ) || ! is_array( $items ) ) {
            return [];
        }

        $options = [];

        foreach ( $items as $item ) {
            if ( empty( $item['template_id'] ) ) {
                continue;
            }

            $template_id = absint( $item['template_id'] );

            if ( ! $template_id ) {
                continue;
            }

            if ( isset( $item['title'] ) && '' !== $item['title'] ) {
                $title = $item['title'];
            } else {
                /* translators: %d: Elementor template ID. */
                $title = sprintf( __( 'Template #%d', 'woo-search-optimized' ), $template_id );
            }
            $type  = isset( $item['type'] ) ? $item['type'] : '';

            if ( $type ) {
                /* translators: 1: Template title, 2: Template type */
                $options[ $template_id ] = sprintf( __( '%1$s (%2$s)', 'woo-search-optimized' ), $title, $type );
            } else {
                $options[ $template_id ] = $title;
            }
        }

        return $options;
    }

    private function prepare_post_types_for_render( $value ) {
        if ( empty( $value ) ) {
            return [];
        }

        if ( is_array( $value ) ) {
            $post_types = $value;
        } else {
            $post_types = [ $value ];
        }

        $allowed_post_types = array_keys( $this->get_post_type_options() );

        $post_types = array_map( 'sanitize_key', (array) $post_types );
        $post_types = array_intersect( $post_types, $allowed_post_types );

        return array_values( $post_types );
    }

    private function parse_ids_setting( $value ) {
        if ( empty( $value ) ) {
            return [];
        }

        if ( is_array( $value ) ) {
            $ids = $value;
        } else {
            $ids = preg_split( '/[\s,]+/', (string) $value );
        }

        $ids = array_map( 'absint', (array) $ids );
        $ids = array_filter( $ids );

        return array_values( $ids );
    }

    private function parse_select_setting( $value ) {
        if ( empty( $value ) ) {
            return [];
        }

        if ( is_array( $value ) ) {
            $values = $value;
        } else {
            $values = [ $value ];
        }

        $values = array_map( 'intval', (array) $values );
        $values = array_filter( $values );

        return array_values( $values );
    }

    private function sanitize_date_range_value( $value ) {
        $allowed = [ '', 'past_day', 'past_week', 'past_month', 'past_year' ];
        $value = (string) $value;

        if ( ! in_array( $value, $allowed, true ) ) {
            return '';
        }

        return $value;
    }

    private function sanitize_order_by_value( $value ) {
        $allowed = [ '', 'date', 'title', 'price', 'rand' ];
        $value = (string) $value;

        if ( ! in_array( $value, $allowed, true ) ) {
            return '';
        }

        return $value;
    }

    private function sanitize_order_value( $value ) {
        $allowed = [ 'ASC', 'DESC' ];
        $value = strtoupper( (string) $value );

        if ( ! in_array( $value, $allowed, true ) ) {
            return '';
        }

        return $value;
    }

    private function sanitize_query_id( $value ) {
        $value = sanitize_key( (string) $value );

        return $value;
    }

    private function sanitize_results_template_id( $value ) {
        if ( is_array( $value ) ) {
            $value = reset( $value );
        }

        return absint( $value );
    }

    private function get_category_control_options() {
        $terms = $this->get_category_terms();

        $options = [];

        foreach ( $terms as $term ) {
            $options[ $term->term_id ] = $term->name;
        }

        return $options;
    }

    private function get_category_data_map() {
        $terms = $this->get_category_terms();

        $data = [];

        foreach ( $terms as $term ) {
            $data[ (string) $term->term_id ] = [
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }

        return $data;
    }

    private function get_categories_for_render( $settings ) {
        $args = [];

        if ( ! empty( $settings['category_filter_terms'] ) ) {
            $term_ids = array_map( 'intval', (array) $settings['category_filter_terms'] );
            $args['include'] = $term_ids;
            $args['orderby'] = 'include';
        }

        return $this->get_category_terms( $args );
    }

    private function get_category_terms( $args = [] ) {
        $defaults = [
            'taxonomy' => $this->get_active_category_taxonomy(),
            'hide_empty' => false,
        ];

        $terms = get_terms( wp_parse_args( $args, $defaults ) );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        return $terms;
    }

    private function get_multi_category_request_slugs() {
        if ( ! isset( $_GET['gm2_category_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return [];
        }

        $raw = wp_unslash( $_GET['gm2_category_filter'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( is_array( $raw ) ) {
            $parts = $raw;
        } else {
            $parts = preg_split( '/[\s,]+/', (string) $raw );
        }

        $parts = array_map( 'sanitize_title', (array) $parts );
        $parts = array_filter( $parts );

        return array_values( array_unique( $parts ) );
    }

    private function get_category_query_var() {
        $taxonomy = $this->get_active_category_taxonomy();
        $taxonomy_object = get_taxonomy( $taxonomy );

        if ( $taxonomy_object && ! empty( $taxonomy_object->query_var ) ) {
            return $taxonomy_object->query_var;
        }

        if ( 'category' === $taxonomy ) {
            return 'category_name';
        }

        return $taxonomy;
    }

    private function resolve_category_taxonomy( $settings, $selected_post_types, $post_type ) {
        $post_types = [];

        if ( ! empty( $selected_post_types ) ) {
            $post_types = $selected_post_types;
        } elseif ( ! empty( $post_type ) ) {
            $post_types = [ $post_type ];
        }

        $post_types = array_unique( array_filter( array_map( 'sanitize_key', (array) $post_types ) ) );

        $default_taxonomy = $this->get_default_category_taxonomy();
        $taxonomy = $default_taxonomy;

        if ( empty( $post_types ) ) {
            $taxonomy = $default_taxonomy;
        } elseif ( 1 === count( $post_types ) && in_array( 'post', $post_types, true ) ) {
            $taxonomy = 'category';
        } elseif ( in_array( 'post', $post_types, true ) ) {
            $taxonomy = 'category';
        } elseif ( taxonomy_exists( 'product_cat' ) && in_array( 'product', $post_types, true ) ) {
            $taxonomy = 'product_cat';
        }

        return apply_filters( 'gm2_search_widget_category_taxonomy', $taxonomy, $settings, $post_types );
    }

    private function set_category_taxonomy( $taxonomy ) {
        if ( taxonomy_exists( $taxonomy ) ) {
            $this->category_taxonomy = $taxonomy;
            return;
        }

        $this->category_taxonomy = '';
    }

    private function get_active_category_taxonomy() {
        $taxonomy = $this->category_taxonomy;

        if ( empty( $taxonomy ) ) {
            $taxonomy = $this->get_default_category_taxonomy();
        }

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return 'category';
        }

        return $taxonomy;
    }

    private function get_default_category_taxonomy() {
        $default = taxonomy_exists( 'product_cat' ) ? 'product_cat' : 'category';

        return apply_filters( 'gm2_search_widget_default_category_taxonomy', $default );
    }
}
