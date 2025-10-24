<?php
/**
 * Custom product card for Gm2 search results.
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
    return;
}

if ( ! $product->is_visible() ) {
    return;
}
?>
<li <?php wc_product_class( 'gm2-search-loop__product-card', $product ); ?>>
    <a class="woocommerce-LoopProduct-link woocommerce-loop-product__link" href="<?php the_permalink(); ?>">
        <?php woocommerce_show_product_loop_sale_flash(); ?>
        <?php woocommerce_template_loop_product_thumbnail(); ?>
    </a>

    <div class="gm2-search-loop__summary">
        <h2 class="woocommerce-loop-product__title">
            <a href="<?php the_permalink(); ?>">
                <?php echo esc_html( get_the_title() ); ?>
            </a>
        </h2>
        <?php woocommerce_template_loop_rating(); ?>
        <?php woocommerce_template_loop_price(); ?>
    </div>

    <div class="gm2-search-loop__actions">
        <?php gm2_search_render_product_action_controls( $product ); ?>
    </div>
</li>
