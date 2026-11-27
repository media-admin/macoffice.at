<?php
/**
 * Produktfamilien-Kacheln: Shop-Kategorien der obersten Ebene.
 * Genutzt auf der Startseite und vom Shortcode [shortcode_product_categories].
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
<ul class="home-grid home-grid--categories">
            <?php foreach ( $terms as $term ) :
                $link = get_term_link( $term );
                if ( is_wp_error( $link ) ) {
                    continue;
                }
                $thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
                ?>
                <li class="home-category">
                    <a class="home-category__link" href="<?php echo esc_url( $link ); ?>">
                        <span class="home-category__media">
                            <?php if ( $thumb_id ) {
                                echo wp_get_attachment_image( $thumb_id, 'medium', false, array( 'class' => 'home-category__image', 'alt' => '', 'loading' => 'lazy' ) );
                            } ?>
                        </span>
                        <span class="home-category__title"><?php echo esc_html( $term->name ); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
