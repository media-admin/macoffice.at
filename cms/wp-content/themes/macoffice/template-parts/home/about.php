<?php
/**
 * Über uns: Überschrift und Text (inkl. Imagevideo als Video-Block im Text).
 *
 * @var array $args { title, text }
 */

if ( empty( $args['text'] ) ) {
    return;
}
?>
<section id="about-us" class="home-section home-section--about" aria-labelledby="about-us-title">
    <div class="container">
        <?php get_template_part( 'template-parts/home/section-heading', null, array(
            'id'    => 'about-us',
            'title' => $args['title'] ?? '',
        ) ); ?>
        <div class="home-about entry-content"><?php echo do_blocks( $args['text'] ); // Text enthält Block-Markup (Überschrift, Video); ACF liefert ihn bereits gefiltert ?></div>
    </div>
</section>
