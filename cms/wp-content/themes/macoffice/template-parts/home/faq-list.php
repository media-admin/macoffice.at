<?php
/**
 * FAQ-Akkordeon: alle veröffentlichten Einträge des Beitragstyps FAQ.
 * Genutzt auf der Startseite und vom Shortcode [shortcode_faqs].
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
        <div class="faq-accordion">
            <?php while ( $query->have_posts() ) : $query->the_post();
                $answer = function_exists( 'get_field' ) ? get_field( 'answer' ) : '';
                if ( ! $answer ) {
                    $answer = apply_filters( 'the_content', get_the_content() );
                }
                ?>
                <div class="faq-item">
                    <button type="button" class="faq-question" aria-expanded="false">
                        <span class="faq-question__text"><?php the_title(); ?></span>
                        <span class="faq-question__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer__content"><?php echo wp_kses_post( $answer ); ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
<?php wp_reset_postdata(); ?>
