<?php
// ── Daten aus Agency Core → Top Header (gleiche Quelle wie der Header) ──────────
$ft_get      = function_exists( 'get_field' ) ? 'get_field' : null;
$ft_address  = $ft_get ? $ft_get( 'top_header_address', 'option' ) : null;
$ft_hours    = $ft_get ? $ft_get( 'top_header_hours',   'option' ) : null;
$ft_phone    = $ft_get ? $ft_get( 'top_header_phone',   'option' ) : null;
$ft_email    = $ft_get ? $ft_get( 'top_header_email',   'option' ) : null;
$ft_social   = $ft_get ? $ft_get( 'top_header_social',  'option' ) : null;
$ft_extras   = macoffice_contact_extras();
$ft_hours_lines = ( ! empty( $ft_hours['enable'] ) && ! empty( $ft_hours['text'] ) )
    ? array_filter( array_map( 'trim', preg_split( '/\s*[|\n]\s*/', $ft_hours['text'] ) ) )
    : array();
$ft_social_icons = array(
    'facebook'  => array( 'Facebook',  '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>', 'fill' ),
    'instagram' => array( 'Instagram', '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>', 'stroke' ),
    'linkedin'  => array( 'LinkedIn',  '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>', 'fill' ),
    'youtube'   => array( 'YouTube',   '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/>', 'stroke' ),
);
?>
<footer class="site-footer site-footer--macoffice">
    <div class="container">

        <div class="site-footer__main">

            <?php // ── Marke: Logo + Apple-Zertifikate ───────────────────────── ?>
            <div class="site-footer__brand">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__logo-link">
                    <img src="<?php echo esc_url( macoffice_branding_url( 'logo-footer.svg' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="300" height="123" class="site-footer__logo" loading="lazy">
                </a>
                <a href="<?php echo esc_url( home_url( '/produkte' ) ); ?>" class="site-footer__certificates">
                    <img src="<?php echo esc_url( macoffice_branding_url( 'apple-certificates-footer.svg' ) ); ?>" alt="<?php esc_attr_e( 'Apple Autorisierter Händler und Service Provider', 'macoffice' ); ?>" width="100" height="59" loading="lazy">
                </a>
            </div>

            <?php // ── Kontaktblöcke ────────────────────────────────────────── ?>
            <div class="site-footer__contact">

                <?php if ( ! empty( $ft_address['enable'] ) && ( ! empty( $ft_address['street'] ) || ! empty( $ft_address['city'] ) ) ) : ?>
                <div class="site-footer__block">
                    <h2 class="site-footer__block-title"><?php esc_html_e( 'Adresse', 'macoffice' ); ?></h2>
                    <address class="site-footer__address">
                        <?php
                        $ft_addr_html = esc_html( $ft_address['street'] ?? '' ) . '<br>' . esc_html( $ft_address['city'] ?? '' );
                        if ( ! empty( $ft_address['country'] ) ) { $ft_addr_html .= '<br>' . esc_html( $ft_address['country'] ); }
                        if ( ! empty( $ft_address['maps_link'] ) ) : ?>
                            <a href="<?php echo esc_url( $ft_address['maps_link'] ); ?>" target="_blank" rel="noopener"><?php echo $ft_addr_html; // phpcs:ignore WordPress.Security.EscapeOutput -- Teile oben escaped ?></a>
                        <?php else : echo $ft_addr_html; // phpcs:ignore WordPress.Security.EscapeOutput
                        endif; ?>
                    </address>
                </div>
                <?php endif; ?>

                <div class="site-footer__block">
                    <h2 class="site-footer__block-title"><?php esc_html_e( 'Kontakt', 'macoffice' ); ?></h2>
                    <ul class="site-footer__list">
                        <?php if ( ! empty( $ft_phone['enable'] ) && ! empty( $ft_phone['number'] ) ) : ?>
                        <li><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $ft_phone['number'] ) ); ?>"><?php echo esc_html( ! empty( $ft_phone['display'] ) ? $ft_phone['display'] : $ft_phone['number'] ); ?></a></li>
                        <?php endif; ?>
                        <?php if ( ! empty( $ft_email['enable'] ) && ! empty( $ft_email['address'] ) ) : ?>
                        <li><a href="mailto:<?php echo esc_attr( $ft_email['address'] ); ?>"><?php echo esc_html( $ft_email['address'] ); ?></a></li>
                        <?php endif; ?>
                        <?php if ( ! empty( $ft_extras['emergency_email'] ) ) : ?>
                        <li><a href="mailto:<?php echo esc_attr( $ft_extras['emergency_email'] ); ?>"><?php esc_html_e( 'Notfall', 'macoffice' ); ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if ( $ft_hours_lines ) : ?>
                <div class="site-footer__block">
                    <h2 class="site-footer__block-title"><?php esc_html_e( 'Öffnungszeiten', 'macoffice' ); ?></h2>
                    <p class="site-footer__hours"><?php echo implode( '<br>', array_map( 'esc_html', $ft_hours_lines ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
                </div>
                <?php endif; ?>

                <?php
                $ft_has_social = ! empty( $ft_social['enable'] ) || ! empty( $ft_extras['whatsapp_url'] );
                if ( $ft_has_social ) : ?>
                <div class="site-footer__block site-footer__block--social">
                    <h2 class="site-footer__block-title"><?php esc_html_e( 'Folgen Sie uns', 'macoffice' ); ?></h2>
                    <ul class="site-footer__social">
                        <?php foreach ( $ft_social_icons as $key => $icon ) :
                            if ( empty( $ft_social['enable'] ) || empty( $ft_social[ $key ] ) ) continue;
                            $attrs = $icon[2] === 'fill' ? 'fill="currentColor"' : 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'; ?>
                        <li><a href="<?php echo esc_url( $ft_social[ $key ] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $icon[0] ); ?>"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo $icon[1]; // phpcs:ignore WordPress.Security.EscapeOutput ?></svg></a></li>
                        <?php endforeach; ?>
                        <?php if ( ! empty( $ft_extras['whatsapp_url'] ) ) : ?>
                        <li><a href="<?php echo esc_url( $ft_extras['whatsapp_url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

            </div><!-- .site-footer__contact -->

            <?php if ( ! empty( $ft_extras['vcard_url'] ) ) : ?>
            <a class="site-footer__vcard" href="<?php echo esc_url( $ft_extras['vcard_url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Visitenkarte (vCard) herunterladen', 'macoffice' ); ?>">
                <img src="<?php echo esc_url( macoffice_branding_url( 'vcard-qr.svg' ) ); ?>" alt="<?php esc_attr_e( 'QR-Code zur Visitenkarte', 'macoffice' ); ?>" width="96" height="96" loading="lazy">
            </a>
            <?php endif; ?>

            <?php
            if ( has_nav_menu( 'footer' ) ) :
                wp_nav_menu( array(
                    'theme_location'       => 'footer',
                    'menu_class'           => 'footer-nav__list',
                    'container'            => 'nav',
                    'container_class'      => 'site-footer__nav footer-nav',
                    'container_aria_label' => 'Footer Navigation',
                    'depth'                => 2,
                    'fallback_cb'          => false,
                ) );
            endif;
            ?>

        </div><!-- .site-footer__main -->

        <div class="site-footer__bottom">

            <p class="site-footer__copyright">
                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>
                <?php if ( ! empty( $ft_extras['tagline'] ) ) : ?>
                    <span class="site-footer__tagline"><?php echo esc_html( $ft_extras['tagline'] ); ?></span>
                <?php endif; ?>
            </p>

            <?php
            if ( has_nav_menu( 'footer-legal' ) ) :
                wp_nav_menu( array(
                    'theme_location'       => 'footer-legal',
                    'menu_class'           => 'footer-legal__list',
                    'container'            => 'nav',
                    'container_class'      => 'footer-legal',
                    'container_aria_label' => __( 'Rechtliche Links', 'macoffice' ),
                    'depth'                => 1,
                    'fallback_cb'          => false,
                ) );
            endif;
            ?>

        </div><!-- .site-footer__bottom -->

        <?php
        // Agentur-Credit: Standard an, abschaltbar unter Agency Core → Logo / Globale Einstellungen
        $footer_credit_on = function_exists( 'get_field' ) ? get_field( 'footer_credit_enabled', 'option' ) : null;
        $footer_credit_on = ( $footer_credit_on === null || $footer_credit_on === '' ) ? true : (bool) $footer_credit_on;
        if ( $footer_credit_on ) : ?>
        <div class="site-footer__credit">
            <p>
                <?php esc_html_e( 'Konzept und Programmierung:', 'macoffice' ); ?>
                <a href="https://www.media-lab.at" target="_blank" rel="noopener noreferrer">Media Lab Tritremmel GmbH</a>
            </p>
        </div>
        <?php endif; ?>

    </div><!-- .container -->
</footer>

</div><!-- #page -->

<?php
// ── Back-to-Top Button ────────────────────────────────────────────────────
if ( function_exists('get_field') && get_field('btt_enabled', 'option') ) : ?>
<button
    class="back-to-top"
    aria-label="<?php esc_attr_e('Zurück nach oben', 'macoffice'); ?>"
    type="button"
>
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</button>
<?php endif; ?>

<?php 
// ── Media-Lab AI Agent Widget (falls aktiviert) ─────────────────────────────
if (function_exists('mlt_ai_render_widget')) { mlt_ai_render_widget(); } ?>

<?php wp_footer(); ?>
</body>
</html>
