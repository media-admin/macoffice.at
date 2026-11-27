<?php
/**
 * AppleCare+: Preislisten je Gerätegruppe (aus der bisherigen Seite übernommen).
 *
 * Pro Gerät: Spalten (Vertragsarten) und Zeilen (Modelle) mit Preis je Spalte.
 * N = Normal, F = Fix, D = Diebstahl + Verlust, A = Abo (Preisangaben wie auf der bisherigen Seite).
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'iphone' => array(
        'title' => 'iPhone',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (2 Jahre)', 'AppleCare+ mit Diebstahl und Verlust (2 Jahre)', 'AppleCare+ (monatlich)', 'AppleCare+ mit Diebstahl und Verlust (monatlich)'),
                'rows' => array(
                    array( 'model' => 'iPhone 16 Pro, 16 Pro Max', 'prices' => array('€ 229,-', '€ 299,-', '€ 11,99', '€ 14,99') ),
                    array( 'model' => 'iPhone 16 Plus, 15 Plus, 14 Plus', 'prices' => array('€ 199,-', '€ 269,-', '€ 9,99', '€ 13,49') ),
                    array( 'model' => 'iPhone 16, 15, 14, 13', 'prices' => array('€ 169,-', '€ 229,-', '€ 8,99', '€ 11,49') ),
                    array( 'model' => 'iPhone 16e', 'prices' => array('€ 139,-', '€ 199,-', '€ 6,99', '€ 9,99') ),
                ),
            ),
        ),
    ),
    'ipad' => array(
        'title' => 'iPad',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (2 Jahre)', 'AppleCare+ (monatlich)'),
                'rows' => array(
                    array( 'model' => '13-inch iPad Pro (M4)', 'prices' => array('€ 179,-', '€ 8,99') ),
                    array( 'model' => '11-inch iPad Pro (M4)', 'prices' => array('€ 159,-', '€ 7,99') ),
                    array( 'model' => '13-inch iPad Air (M3)', 'prices' => array('€ 109,-', '€ 5,49') ),
                    array( 'model' => '11-inch iPad Air (M3)', 'prices' => array('€ 89,-', '€ 4,49') ),
                    array( 'model' => 'iPad, iPad mini', 'prices' => array('€ 79,-', '€ 3,99') ),
                ),
            ),
        ),
    ),
    'mac' => array(
        'title' => 'Mac',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (3 Jahre)', 'AppleCare+ (jährlich)'),
                'rows' => array(
                    array( 'model' => '13-inch MacBook Air (M2)', 'prices' => array('€ 199,-', '€ 69,99') ),
                    array( 'model' => '13-inch MacBook Air (M3/M4)', 'prices' => array('€ 219,-', '€ 79,99') ),
                    array( 'model' => '15-inch MacBook Air (M3/M4)', 'prices' => array('€ 249,-', '€ 89,99') ),
                    array( 'model' => '14-inch MacBook Pro', 'prices' => array('€ 299,-', '€ 109,99') ),
                    array( 'model' => '16-inch MacBook Pro', 'prices' => array('€ 429,-', '€ 159,99') ),
                    array( 'model' => 'iMac', 'prices' => array('€ 179,-', '€ 64,99') ),
                    array( 'model' => 'Mac mini', 'prices' => array('€ 119,-', '€ 44,99') ),
                    array( 'model' => 'Mac Studio', 'prices' => array('€ 179,-', '€ 64,99') ),
                    array( 'model' => 'Mac Pro', 'prices' => array('€ 529,-', '€ 189,99') ),
                ),
            ),
        ),
    ),
    'watch' => array(
        'title' => 'Watch',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (2 Jahre)', 'AppleCare+ (monatlich)'),
                'rows' => array(
                    array( 'model' => 'Apple Watch Ultra 2', 'prices' => array('€ 109,-', '€ 5,49') ),
                    array( 'model' => 'Apple Watch Series 10', 'prices' => array('€ 89,-', '€ 4,49') ),
                    array( 'model' => 'Apple Watch SE', 'prices' => array('€ 65,-', '€ 2,99') ),
                ),
            ),
        ),
    ),
    'kopfhoerer' => array(
        'title' => 'Kopfhörer',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (2 Jahre)'),
                'rows' => array(
                    array( 'model' => 'AirPods, AirPods Pro, Beats', 'prices' => array('€ 35,-') ),
                    array( 'model' => 'AirPods Max', 'prices' => array('€ 59,-') ),
                ),
            ),
        ),
    ),
    'display' => array(
        'title' => 'Display',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (3 Jahre)'),
                'rows' => array(
                    array( 'model' => 'Apple Studio Display', 'prices' => array('€ 139,-') ),
                    array( 'model' => 'Pro Display XDR', 'prices' => array('€ 499,-') ),
                ),
            ),
        ),
    ),
    'tv' => array(
        'title' => 'TV',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (3 Jahre)'),
                'rows' => array(
                    array( 'model' => 'Apple TV', 'prices' => array('€ 29,-') ),
                ),
            ),
        ),
    ),
    'homepod' => array(
        'title' => 'HomePod',
        'tables' => array(
            array(
                'columns' => array('AppleCare+ (2 Jahre)'),
                'rows' => array(
                    array( 'model' => 'HomePod', 'prices' => array('€ 39,-') ),
                    array( 'model' => 'HomePod mini', 'prices' => array('€ 15,-') ),
                ),
            ),
        ),
    ),
);
