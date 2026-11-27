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

/** Eine Karte ausgeben (läuft innerhalb der Loop). */
$render_card = static function () {
    $post_id   = get_the_ID();
    $pretitle  = function_exists( 'get_field' ) ? get_field( 'post-pretitle', $post_id ) : get_post_meta( $post_id, 'post-pretitle', true );
    $subtitle  = function_exists( 'get_field' ) ? get_field( 'post-subtitle', $post_id ) : get_post_meta( $post_id, 'post-subtitle', true );
    ?>
    <article class="home-card">
        <a class="home-card__link" href="<?php the_permalink(); ?>">
            <?php if ( $pretitle ) : ?>
                <p class="home-card__pretitle"><?php echo esc_html( $pretitle ); ?></p>
            <?php endif; ?>
            <h3 class="home-card__title"><?php the_title(); ?></h3>
            <?php if ( $subtitle ) : ?>
                <p class="home-card__subtitle"><?php echo esc_html( $subtitle ); ?></p>
            <?php endif; ?>
            <?php if ( has_post_thumbnail() ) : ?>
                <span class="home-card__media">
                    <?php the_post_thumbnail( 'large', array( 'class' => 'home-card__image', 'loading' => 'lazy' ) ); ?>
                </span>
            <?php endif; ?>
            <?php if ( has_excerpt() ) : ?>
                <span class="home-card__text"><?php echo esc_html( get_the_excerpt() ); ?></span>
            <?php endif; ?>
        </a>
    </article>
    <?php
};
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
                            <div class="swiper-slide"><?php $render_card(); ?></div>
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
                    <?php $render_card(); ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
