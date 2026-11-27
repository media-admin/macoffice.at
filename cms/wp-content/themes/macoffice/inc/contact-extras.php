<?php
/**
 * macoffice: Kontaktangaben, die über die Agency-Core-Einstellungen hinausgehen
 *
 * Adresse, Öffnungszeiten, Telefon, E-Mail und Social-Links kommen aus Agency Core →
 * Logo / Globale Einstellungen → Top Header (ACF-Optionen top_header_*). Header und Footer lesen
 * dieselben Werte. Hier stehen nur die Angaben, für die es dort kein Feld gibt.
 *
 * Mit dem Filter 'macoffice_contact_extras' lassen sich die Werte pro Umgebung überschreiben.
 *
 * @package Macoffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return array{emergency_email:string,remote_support_url:string,whatsapp_url:string,vcard_url:string,tagline:string}
 */
function macoffice_contact_extras(): array {
    return apply_filters( 'macoffice_contact_extras', array(
        'emergency_email'    => 'notfall@macoffice.at',
        'remote_support_url' => home_url( '/fernwartung' ),
        'whatsapp_url'       => 'https://wa.me/436801115350',
        'vcard_url'          => home_url( '/macoffice.vcf' ),
        'tagline'            => 'Ihr autorisierter Apple Händler und Service Provider in Wiener Neustadt.',
    ) );
}

/**
 * Pfad zu einem Branding-Bild des Themes (assets/images/branding/…).
 */
function macoffice_branding_url( string $file ): string {
    return get_template_directory_uri() . '/assets/images/branding/' . ltrim( $file, '/' );
}
