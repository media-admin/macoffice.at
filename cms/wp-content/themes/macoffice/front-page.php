<?php
/**
 * Startseite
 *
 * Texte, Überschriften und Leistungs-Karten: ACF-Gruppe „Startseite“ (Plugin macoffice-project).
 * Karten aus Inhalten: Beiträge der Kategorien „neuheiten“ und „lieblingsprodukte“,
 * Shop-Kategorien (oberste Ebene) und der Beitragstyp FAQ.
 *
 * @package Macoffice
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$macoffice_fp_id = (int) get_option( 'page_on_front' ) ?: (int) get_queried_object_id();

/**
 * Liest ein ACF-Feld der Startseite, mit Standardwert wenn leer.
 */
$field = static function ( string $name, $default = '' ) use ( $macoffice_fp_id ) {
    $value = function_exists( 'get_field' ) ? get_field( $name, $macoffice_fp_id ) : null;
    return ( null === $value || '' === $value || false === $value ) ? $default : $value;
};
?>

<main id="main-content" class="site-main home">

    <h1 class="sr-only"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>

    <?php
    get_template_part( 'template-parts/home/post-cards', null, array(
        'id'       => 'news',
        'category' => 'neuheiten',
        'variant'  => 'slider',
        'title'    => $field( 'fp_news_title', 'Neuheiten, die faszinieren' ),
        'intro'    => $field( 'fp_news_intro' ),
    ) );

    get_template_part( 'template-parts/home/post-cards', null, array(
        'id'       => 'featured',
        'category' => 'lieblingsprodukte',
        'variant'  => 'grid',
        'title'    => $field( 'fp_featured_title', 'Lieblingsprodukte, die Freude bereiten' ),
        'intro'    => $field( 'fp_featured_intro' ),
    ) );

    get_template_part( 'template-parts/home/product-categories', null, array(
        'title' => $field( 'fp_categories_title', 'Produktfamilien' ),
    ) );

    get_template_part( 'template-parts/home/services', null, array(
        'title' => $field( 'fp_services_title', 'Leistungen' ),
        'intro' => $field( 'fp_services_intro' ),
        'items' => $field( 'fp_services', array() ),
    ) );

    get_template_part( 'template-parts/home/faq', null, array(
        'title' => $field( 'fp_faq_title', 'Fragen & Antworten' ),
        'intro' => $field( 'fp_faq_intro' ),
    ) );

    get_template_part( 'template-parts/home/about', null, array(
        'title' => $field( 'fp_about_title', 'Über uns' ),
        'text'  => $field( 'fp_about_text' ),
    ) );

    get_template_part( 'template-parts/home/error-report' );
    ?>

</main>

<?php get_footer(); ?>
