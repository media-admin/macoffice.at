<?php
/**
 * [shortcode_services_overview] – Leistungs-Übersicht mit Apple-Rollen-Logos.
 *
 * Karten wie auf der Startseite (.home-service), dazu das Logo der jeweiligen Apple-Rolle.
 * Bilder und Logos liegen im Theme (assets/images/services, assets/images/branding).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$macoffice_services = array(
    array(
        'anchor'   => 'verkauf',
        'pretitle' => 'Kompetenz trifft Liebe zum Produkt',
        'title'    => 'Verkauf',
        'image'    => 'verkauf.webp',
        'logo'     => 'apple-haendler-white.svg',
        'logo_alt' => 'Logo Apple Autorisierter Händler',
        'logo_url' => 'https://locate.apple.com/at/de/sales?pt=4&lat=47.8162109&lon=16.2386804&address=2700+Wiener+Neustadt%2C+Österreich',
    ),
    array(
        'anchor'   => 'service-hardware',
        'pretitle' => 'Reparaturen vom Profi',
        'title'    => 'Service Hardware',
        'image'    => 'service-hardware.webp',
        'logo'     => 'apple-service-provider-white.svg',
        'logo_alt' => 'Logo Apple Autorisierter Service Provider',
        'logo_url' => 'https://getsupport.apple.com/repair-locations/details',
    ),
    array(
        'anchor'   => 'service-software',
        'pretitle' => 'Erfahrung nutzbar gemacht',
        'title'    => 'Software Service',
        'image'    => 'service-software.webp',
        'logo'     => 'apple-service-provider-white.svg',
        'logo_alt' => 'Logo Apple Autorisierter Service Provider',
        'logo_url' => 'https://getsupport.apple.com/repair-locations/details',
    ),
    array(
        'anchor'   => 'consulting',
        'pretitle' => 'Fachwissen auf Abruf',
        'title'    => 'Consulting',
        'image'    => 'consulting.webp',
        'logo'     => 'apple-consultants-network-white.svg',
        'logo_alt' => 'Logo Apple Consultants Network',
        'logo_url' => 'https://consultants.apple.com/at/profile/2007879',
    ),
);
?>
<p class="services-overview__intro">Durch unser langjähriges und umfassendes Know-How rund um Apple-Produkte und deren Anwendungen helfen wir Ihnen, jede Art von Hürde (sowohl was Hardware, als auch Software betrifft) hinter sich zu lassen und Ihre fantastischen Produkte, für die Sie sich entschieden haben, voll auszuschöpfen und zu genießen.</p>
<div class="services-overview home-grid home-grid--services">
    <?php foreach ( $macoffice_services as $service ) : ?>
        <article class="home-service services-overview__card">
            <a class="home-service__link" href="<?php echo esc_url( '#' . $service['anchor'] ); ?>">
                <p class="home-service__pretitle"><?php echo esc_html( $service['pretitle'] ); ?></p>
                <h3 class="home-service__title"><?php echo esc_html( $service['title'] ); ?></h3>
                <span class="home-service__media">
                    <img class="home-service__image" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/services/' . $service['image'] ) ); ?>" alt="<?php echo esc_attr( 'Bereich ' . $service['title'] ); ?>" loading="lazy">
                </span>
            </a>
            <a class="services-overview__logo" href="<?php echo esc_url( $service['logo_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/branding/' . $service['logo'] ) ); ?>" alt="<?php echo esc_attr( $service['logo_alt'] ); ?>" loading="lazy">
            </a>
        </article>
    <?php endforeach; ?>
</div>
