<?php
/**
 * [shortcode_leasing_calculator] – Leasing-Rechner.
 *
 * Übernommen aus der bisherigen Seite (classes/getLeasingResult.php): je Laufzeit und Betragsstufe ein Prozentsatz;
 * Gesamtzins = Betrag × Satz/100 × Monate, Rate = (Betrag + Gesamtzins) / Monate.
 *
 * Abweichungen zur bisherigen Seite (bewusst):
 *  - Stufengrenzen sind inklusive (bisher fielen z. B. genau 2.500 € durch alle Stufen und ergaben 0).
 *  - Mindestbetrag 500 €, über 100.000 € "auf Anfrage" (für die 7. Stufe gab es nie Daten; bisher Fehler/0).
 *  - Berechnung läuft über die REST-Route macoffice/v1/leasing (bisher ein direkt aufrufbares PHP-Script im Theme).
 *
 * OFFEN: Die Formel (Satz × Monate) und ob das Ergebnis brutto oder netto ist, wurde fachlich nicht bestätigt.
 * Die bisherige Seite rechnet ohne Umsatzsteuer; falls die Rate brutto angezeigt werden soll, den Filter
 * 'macoffice_leasing_vat_rate' auf 0.2 setzen.
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const MACOFFICE_LEASING_MIN = 500.0;
const MACOFFICE_LEASING_MAX = 100000.0;

/**
 * Obere Grenzen der Betragsstufen (inklusive), in derselben Reihenfolge wie die Sätze unten.
 *
 * @return float[]
 */
function macoffice_leasing_bands(): array {
    return array( 2500.0, 5000.0, 10000.0, 25000.0, 50000.0, 100000.0 );
}

/**
 * Monatssatz-Faktoren (Prozent) je Laufzeit (Monate) und Betragsstufe.
 *
 * @return array<int,float[]>
 */
function macoffice_leasing_rates(): array {
    return (array) apply_filters( 'macoffice_leasing_rates', array(
        24 => array( 4.92, 4.90, 4.88, 4.86, 4.84, 4.82 ),
        36 => array( 3.42, 3.40, 3.38, 3.36, 3.34, 3.32 ),
        48 => array( 2.66, 2.60, 2.54, 2.52, 2.50, 2.48 ),
        54 => array( 2.45, 2.39, 2.33, 2.29, 2.27, 2.25 ),
        60 => array( 2.24, 2.18, 2.12, 2.10, 2.08, 2.06 ),
        72 => array( 1.96, 1.90, 1.86, 1.82, 1.80, 1.78 ),
    ) );
}

/**
 * Zahl aus Benutzereingabe lesen ("1.234,56", "1234.56", "1 234").
 */
function macoffice_leasing_parse_amount( $raw ): ?float {
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        return null;
    }
    $raw = str_replace( array( ' ', "\u{00a0}", '€' ), '', $raw );
    if ( preg_match( '/^\d{1,3}(\.\d{3})+(,\d+)?$/', $raw ) || false !== strpos( $raw, ',' ) ) {
        $raw = str_replace( '.', '', $raw );
        $raw = str_replace( ',', '.', $raw );
    }
    return is_numeric( $raw ) ? (float) $raw : null;
}

/**
 * Raten berechnen.
 *
 * @return array{amount:float,rates:array<int,float>}|WP_Error
 */
function macoffice_leasing_calculate( $amount ) {
    $amount = is_float( $amount ) ? $amount : macoffice_leasing_parse_amount( $amount );

    if ( null === $amount ) {
        return new WP_Error( 'invalid', __( 'Bitte geben Sie einen Betrag ein.', 'macoffice' ), array( 'status' => 400 ) );
    }
    if ( $amount < MACOFFICE_LEASING_MIN ) {
        return new WP_Error( 'too_low', __( 'Der Mindestbetrag für eine Finanzierung beträgt EUR 500,00.', 'macoffice' ), array( 'status' => 400 ) );
    }
    if ( $amount > MACOFFICE_LEASING_MAX ) {
        return new WP_Error( 'too_high', __( 'Für Beträge über EUR 100.000,00 erstellen wir Ihnen gerne ein individuelles Angebot.', 'macoffice' ), array( 'status' => 400 ) );
    }

    $band = 0;
    foreach ( macoffice_leasing_bands() as $i => $upper ) {
        if ( $amount <= $upper ) {
            $band = $i;
            break;
        }
    }

    $vat   = (float) apply_filters( 'macoffice_leasing_vat_rate', 0.0 );
    $rates = array();
    foreach ( macoffice_leasing_rates() as $months => $factors ) {
        $interest        = $amount * ( $factors[ $band ] / 100 ) * $months;
        $rates[ $months ] = round( ( ( $amount + $interest ) / $months ) * ( 1 + $vat ), 2 );
    }

    return array(
        'amount' => $amount,
        'rates'  => $rates,
    );
}

function macoffice_leasing_format( float $value ): string {
    return 'EUR ' . number_format( $value, 2, ',', '.' );
}

/**
 * Ergebnis als HTML (Tabelle) bzw. Fehlermeldung.
 *
 * @param array|WP_Error $result
 */
function macoffice_leasing_result_html( $result ): string {
    if ( is_wp_error( $result ) ) {
        return '<p class="leasing-result leasing-result--error" role="alert">' . esc_html( $result->get_error_message() ) . '</p>';
    }

    $html  = '<div class="leasing-result"><p class="leasing-result__title">Monatliche Rate bei …</p><table class="leasing-result__table"><tbody>';
    foreach ( $result['rates'] as $months => $rate ) {
        $html .= sprintf(
            '<tr><th scope="row">%d Monaten</th><td>%s</td></tr>',
            (int) $months,
            esc_html( macoffice_leasing_format( $rate ) )
        );
    }
    $html .= '</tbody></table></div>';

    return $html;
}

add_action( 'rest_api_init', static function (): void {
    register_rest_route( 'macoffice/v1', '/leasing', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'args'                => array(
            'amount' => array( 'required' => true ),
        ),
        'callback'            => static function ( WP_REST_Request $request ) {
            $result = macoffice_leasing_calculate( $request->get_param( 'amount' ) );
            return rest_ensure_response( array(
                'ok'   => ! is_wp_error( $result ),
                'html' => macoffice_leasing_result_html( $result ),
            ) );
        },
    ) );
} );

add_shortcode( 'shortcode_leasing_calculator', static function (): string {
    $raw    = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $result = '' !== $raw ? macoffice_leasing_result_html( macoffice_leasing_calculate( $raw ) ) : '';

    wp_enqueue_script(
        'macoffice-leasing',
        MACOFFICE_PROJECT_URL . 'assets/js/leasing.js',
        array(),
        MACOFFICE_PROJECT_VERSION,
        array( 'strategy' => 'defer', 'in_footer' => true )
    );
    wp_localize_script( 'macoffice-leasing', 'macofficeLeasing', array(
        'endpoint' => esc_url_raw( rest_url( 'macoffice/v1/leasing' ) ),
        'error'    => __( 'Die Berechnung ist derzeit nicht möglich. Bitte versuchen Sie es später erneut.', 'macoffice' ),
    ) );

    return macoffice_capture_plugin_template( 'leasing-calculator', array(
        'amount' => $raw,
        'result' => $result,
        'action' => get_permalink() ?: '',
    ) );
} );
