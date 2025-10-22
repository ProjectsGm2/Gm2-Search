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
            'query_var',
            [
                'label' => __( 'Query Variable', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'default' => 's',
                'description' => __( 'The query string variable to use when submitting the search form.', 'woo-search-optimized' ),
            ]
        );

        $this->add_control(
            'search_post_type',
            [
                'label' => __( 'Post Type', 'woo-search-optimized' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'Leave empty for all post types', 'woo-search-optimized' ),
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
                'selector' => '{{WRAPPER}} .elementor-search-form__input',
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label' => __( 'Text Color', 'woo-search-optimized' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-search-form__input' => 'color: {{VALUE}};',
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
                    '{{WRAPPER}} .elementor-search-form__input' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .elementor-search-form__input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .elementor-search-form__input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .elementor-search-form__input',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'input_box_shadow',
                'selector' => '{{WRAPPER}} .elementor-search-form__input',
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
                    '{{WRAPPER}} .elementor-search-form__input' => 'height: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .elementor-search-form__submit svg' => 'fill: {{VALUE}};',
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
                    '{{WRAPPER}} .elementor-search-form__submit:hover svg, {{WRAPPER}} .elementor-search-form__submit:focus svg' => 'fill: {{VALUE}};',
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
                    'submit_type!' => 'text',
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

        $query_var   = ! empty( $settings['query_var'] ) ? sanitize_key( $settings['query_var'] ) : 's';
        $placeholder = isset( $settings['placeholder'] ) ? $settings['placeholder'] : '';
        $post_type   = ! empty( $settings['search_post_type'] ) ? $settings['search_post_type'] : '';
        $show_button = ( isset( $settings['show_submit_button'] ) && 'yes' === $settings['show_submit_button'] );
        $submit_type = $show_button ? $settings['submit_type'] : '';
        $icon_position = isset( $settings['icon_position'] ) ? $settings['icon_position'] : 'before';
        $button_icon = isset( $settings['button_icon'] ) ? $settings['button_icon'] : [];
        $has_icon    = ! empty( $button_icon['value'] );

        $this->add_render_attribute( 'form', 'class', [
            'elementor-search-form',
            'elementor-search-form--skin-' . $settings['skin'],
        ] );

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
                <?php if ( ! empty( $post_type ) ) : ?>
                    <input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>" />
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    protected function content_template() {
        ?>
        <#
        const queryVar = settings.query_var ? settings.query_var : 's';
        const showButton = 'yes' === settings.show_submit_button;
        const submitType = showButton ? settings.submit_type : '';
        const wrapperClasses = [
            'elementor-search-form',
            'elementor-search-form--skin-' + settings.skin,
        ];

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
        #>
        <?php $home_url = esc_url( home_url( '/' ) ); ?>
        <#
        const buttonClasses = [ 'elementor-search-form__submit' ];
        if ( 'minimal' === settings.skin ) {
            buttonClasses.push( 'elementor-search-form__submit--minimal' );
        }
        if ( showButton && 'text_icon' === submitType ) {
            buttonClasses.push( 'elementor-search-form__submit--text-icon' );
            buttonClasses.push( 'after' === settings.icon_position ? 'elementor-align-icon-right' : 'elementor-align-icon-left' );
        }
        const ariaLabel = settings.button_text ? settings.button_text : settings.placeholder;
        #>
        <form class="{{ wrapperClasses.join( ' ' ) }}" role="search" method="get" action="<?php echo $home_url; ?>">
            <div class="elementor-search-form__container">
                <input class="elementor-search-form__input" type="search" name="{{ queryVar }}" placeholder="{{{ settings.placeholder }}}" value="" />
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
                <# if ( settings.search_post_type ) { #>
                    <input type="hidden" name="post_type" value="{{ settings.search_post_type }}" />
                <# } #>
            </div>
        </form>
        <?php
    }
}
