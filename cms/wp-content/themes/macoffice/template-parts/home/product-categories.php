<?php
/**
 * Produktfamilien: Shop-Kategorien der obersten Ebene als Kacheln.
 *
 * @var array $args { title }
 */

if ( ! taxonomy_exists( 'product_cat' ) ) {
    return;
}

$terms = get_terms( array(
    'taxonomy'   => 'product_cat',
    'parent'     => 0,
    'hide_empty' => true,
    'orderby'    => 'name',
    'order'      => 'ASC',
    'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
) );

if ( is_wp_error( $terms ) || empty( $terms ) ) {
    return;
}
?>
<section id="product-categories" class="home-section home-section--categories" aria-labelledby="product-categories-title">
    <div class="container">
        <?php get_template_part( 'template-parts/home/section-heading', null, array(
            'id'    => 'product-categories',
            'title' => $args['title'] ?? '',
        ) ); ?>

        <?php get_template_part( 'template-parts/home/category-tiles' ); ?>
    </div>
</section>
