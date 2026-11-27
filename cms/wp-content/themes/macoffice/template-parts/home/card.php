<?php
/**
 * Beitragskarte (innerhalb der Loop): Vortitel, Titel, Untertitel, Bild, Auszug.
 * Genutzt auf der Startseite und von den Inhalts-Shortcodes (Beitragslisten).
 */

$post_id  = get_the_ID();
$pretitle = function_exists( 'get_field' ) ? get_field( 'post-pretitle', $post_id ) : get_post_meta( $post_id, 'post-pretitle', true );
$subtitle = function_exists( 'get_field' ) ? get_field( 'post-subtitle', $post_id ) : get_post_meta( $post_id, 'post-subtitle', true );
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
