<?php
/**
 * Abschnitts-Kopf der Startseite: Überschrift und optionale Einleitung.
 *
 * @var array $args { id, title, intro }
 */

$id    = $args['id'] ?? '';
$title = $args['title'] ?? '';
$intro = $args['intro'] ?? '';

if ( '' === $title && '' === $intro ) {
    return;
}
?>
<header class="home-section__header">
    <?php if ( $title ) : ?>
        <h2 class="home-section__title"<?php echo $id ? ' id="' . esc_attr( $id ) . '-title"' : ''; ?>><?php echo esc_html( $title ); ?></h2>
    <?php endif; ?>
    <?php if ( $intro ) : ?>
        <div class="home-section__intro"><?php echo wp_kses_post( $intro ); ?></div>
    <?php endif; ?>
</header>
