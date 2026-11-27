<?php
/**
 * Leistungen: Einleitung und Karten (ACF-Repeater der Startseite).
 *
 * @var array $args { title, intro, items[ pretitle, title, url, image ] }
 */

$items = is_array( $args['items'] ?? null ) ? $args['items'] : array();

if ( empty( $items ) && empty( $args['intro'] ) ) {
    return;
}
?>
<section id="services" class="home-section home-section--services" aria-labelledby="services-title">
    <div class="container">
        <?php get_template_part( 'template-parts/home/section-heading', null, array(
            'id'    => 'services',
            'title' => $args['title'] ?? '',
            'intro' => $args['intro'] ?? '',
        ) ); ?>

        <?php if ( $items ) : ?>
            <div class="home-grid home-grid--services">
                <?php foreach ( $items as $item ) :
                    $url = ! empty( $item['url'] ) ? ( 0 === strpos( $item['url'], 'http' ) ? $item['url'] : home_url( $item['url'] ) ) : '';
                    ?>
                    <article class="home-service">
                        <?php if ( $url ) : ?><a class="home-service__link" href="<?php echo esc_url( $url ); ?>"><?php else : ?><div class="home-service__link"><?php endif; ?>
                            <?php if ( ! empty( $item['pretitle'] ) ) : ?>
                                <p class="home-service__pretitle"><?php echo esc_html( $item['pretitle'] ); ?></p>
                            <?php endif; ?>
                            <h3 class="home-service__title"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
                            <?php if ( ! empty( $item['image'] ) ) : ?>
                                <span class="home-service__media">
                                    <?php echo wp_get_attachment_image( (int) $item['image'], 'large', false, array( 'class' => 'home-service__image', 'alt' => '', 'loading' => 'lazy' ) ); ?>
                                </span>
                            <?php endif; ?>
                        <?php echo $url ? '</a>' : '</div>'; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
