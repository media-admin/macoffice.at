<?php
/**
 * Karten aus Beiträgen einer Kategorie, als Slider oder Raster.
 *
 * @var array $args { id, category, variant (slider|grid), title, intro }
 */

$id       = $args['id'] ?? 'cards';
$category = $args['category'] ?? '';
$variant  = ( $args['variant'] ?? 'grid' ) === 'slider' ? 'slider' : 'grid';

if ( '' === $category ) {
    return;
}

$query = new WP_Query( array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'category_name'  => $category,
    'posts_per_page' => -1,
    'no_found_rows'  => true,
) );

if ( ! $query->have_posts() ) {
    wp_reset_postdata();
    return;
}

?>
<section id="<?php echo esc_attr( $id ); ?>" class="home-section home-section--<?php echo esc_attr( $id ); ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>-title">
    <div class="container">
        <?php get_template_part( 'template-parts/home/section-heading', null, array(
            'id'    => $id,
            'title' => $args['title'] ?? '',
            'intro' => $args['intro'] ?? '',
        ) ); ?>

        <?php if ( 'slider' === $variant ) : ?>
            <div class="home-slider">
                <div class="carousel swiper" data-slides="4" data-tablet="2" data-mobile="1" data-space="24" data-loop="false">
                    <div class="swiper-wrapper">
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                            <div class="swiper-slide"><?php get_template_part( 'template-parts/home/card' ); ?></div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <button type="button" class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Zurück', 'macoffice' ); ?>"></button>
                <button type="button" class="swiper-button-next" aria-label="<?php esc_attr_e( 'Weiter', 'macoffice' ); ?>"></button>
                <div class="swiper-pagination"></div>
            </div>
        <?php else : ?>
            <div class="home-grid home-grid--cards">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php get_template_part( 'template-parts/home/card' ); ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
