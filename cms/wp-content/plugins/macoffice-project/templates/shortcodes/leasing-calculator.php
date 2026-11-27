<?php
/**
 * Leasing-Rechner (Formular + Ergebnisbereich).
 *
 * @var string $amount Eingegebener Betrag (Rohwert)
 * @var string $result Fertiges Ergebnis-HTML (ohne JavaScript)
 * @var string $action Ziel des Formulars
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="leasing-calculator">
    <form class="leasing-calculator__form" method="post" action="<?php echo esc_url( $action ); ?>" data-leasing-form>
        <p class="leasing-amount">
            <label class="leasing-amount__label" for="leasing-amount"><strong>Gewünschter Leasing-Betrag</strong> (netto)</label>
            <small class="leasing-amount__hint">Mindestbetrag für Finanzierung: EUR 500,00</small>
            <span class="leasing-amount__input">
                <span aria-hidden="true">EUR</span>
                <input class="leasing-amount__field" id="leasing-amount" name="amount" type="text" inputmode="decimal" autocomplete="off" required value="<?php echo esc_attr( $amount ); ?>">
            </span>
        </p>
        <p><button class="btn btn--primary leasing-amount__btn" type="submit">Rate berechnen</button></p>
    </form>
    <div class="leasing-calculator__result" aria-live="polite" data-leasing-result><?php echo $result; // phpcs:ignore WordPress.Security.EscapeOutput -- bereits maskiert ?></div>
</div>
