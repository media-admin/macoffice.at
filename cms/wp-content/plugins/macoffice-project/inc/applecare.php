<?php
/**
 * [shortcode_applecare] – AppleCare+-Preisübersicht.
 *
 * Schritt 1: Gerätegruppe wählen (?device=iphone), Schritt 2: Betrag anklicken. Der Klick setzt ?modell=…&vertrag=…#second;
 * diese Werte füllt das Formular auf der Seite (Contact Form 7, Feldvorbelegung per GET) vor.
 * Die Preise stehen in data/applecare.php.
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return array<string,array{title:string,tables:array}>
 */
function macoffice_applecare_data(): array {
    static $data = null;
    if ( null === $data ) {
        $data = (array) include MACOFFICE_PROJECT_PATH . 'data/applecare.php';
        $data = (array) apply_filters( 'macoffice_applecare_data', $data );
    }
    return $data;
}

add_shortcode( 'shortcode_applecare', static function (): string {
    $data   = macoffice_applecare_data();
    $device = isset( $_GET['device'] ) ? sanitize_key( wp_unslash( $_GET['device'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $base   = get_permalink() ?: home_url( '/' );

    ob_start();
    echo '<div class="applecare">';

    if ( '' !== $device && isset( $data[ $device ] ) ) {
        $entry = $data[ $device ];

        echo '<div id="first"></div>';
        printf(
            '<h3 class="applecare__title">AppleCare für <strong>%s</strong> abschließen (Betrag anklicken) <a class="applecare__back" href="%s">(zurück)</a></h3>',
            esc_html( $entry['title'] ),
            esc_url( $base )
        );

        foreach ( $entry['tables'] as $table ) {
            echo '<table class="applecare__table"><thead><tr><th scope="col">Modell</th>';
            foreach ( $table['columns'] as $column ) {
                echo '<th scope="col">' . esc_html( $column ) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ( $table['rows'] as $row ) {
                echo '<tr><th scope="row">' . esc_html( $row['model'] ) . '</th>';
                foreach ( $row['prices'] as $i => $price ) {
                    $contract = $table['columns'][ $i ] . ' um ' . $price;
                    $url      = add_query_arg(
                        array(
                            'device'  => $device,
                            'modell'  => $row['model'],
                            'vertrag' => $contract,
                        ),
                        $base
                    ) . '#second';
                    printf(
                        '<td data-label="%s"><a class="btn btn--outline btn--sm applecare__price" href="%s">%s</a></td>',
                        esc_attr( $table['columns'][ $i ] ),
                        esc_url( $url ),
                        esc_html( $price )
                    );
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    } else {
        echo '<h3 class="applecare__title">Für welches Gerät möchten Sie AppleCare+ abschließen?</h3>';
        echo '<p class="applecare__devices">';
        foreach ( $data as $key => $entry ) {
            printf(
                '<a class="btn btn--outline applecare__device" href="%s">%s</a> ',
                esc_url( add_query_arg( 'device', $key, $base ) . '#first' ),
                esc_html( $entry['title'] )
            );
        }
        echo '</p>';
    }

    echo '<div id="second"></div></div>';

    return (string) ob_get_clean();
} );
