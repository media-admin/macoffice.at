<?php
/**
 * „Fehler melden“: Button und Modal mit dem Contact-Form-7-Formular „Fehler melden“.
 */

if ( ! shortcode_exists( 'contact-form-7' ) ) {
    return;
}
?>
<section class="home-section home-section--error-report">
    <div class="container">
        <button type="button" class="btn btn--outline" data-modal-trigger="error-report-modal"><?php esc_html_e( 'Fehler melden', 'macoffice' ); ?></button>
    </div>
</section>

<div class="modal" id="error-report-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Fehler melden', 'macoffice' ); ?>">
    <div class="modal__dialog">
        <button type="button" class="modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Schließen', 'macoffice' ); ?>">&times;</button>
        <div class="modal__body">
            <?php echo do_shortcode( '[contact-form-7 title="Fehler melden"]' ); ?>
        </div>
    </div>
</div>
