<?php
/**
 * Fragen & Antworten: Beitragstyp FAQ als Akkordeon.
 *
 * @var array $args { title, intro }
 */

if ( ! post_type_exists( 'faq' ) ) {
    return;
}

$query = new WP_Query( array(
    'post_type'      => 'faq',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
    'no_found_rows'  => true,
) );

if ( ! $query->have_posts() ) {
    wp_reset_postdata();
    return;
}
?>
<section id="faq" class="home-section home-section--faq" aria-labelledby="faq-title">
    <div class="container">
        <?php get_template_part( 'template-parts/home/section-heading', null, array(
            'id'    => 'faq',
            'title' => $args['title'] ?? '',
            'intro' => $args['intro'] ?? '',
        ) ); ?>

        <?php get_template_part( 'template-parts/home/faq-list' ); ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
