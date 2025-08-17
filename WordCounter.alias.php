<?php

    /**
     * WordCounter Aliases
     * 
     * This file defines aliases for the WordCounter extension.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    // Define magic words {{PAGEWORDS}} and {{TOTALWORDS}}

    $magicWords = [];

    $magicWords[ 'en' ] = [
        'WC_PAGEWORDS' => [ 0, 'pagewords' ],
        'WC_TOTALWORDS' => [ 0, 'totalwords' ]
    ];

    $magicWords[ 'de' ] = [
        'WC_PAGEWORDS' => [ 0, 'artikelworte' ],
        'WC_TOTALWORDS' => [ 0, 'alleworte' ]
    ];

    // Define special pages

    $specialPageAliases = [];

    $specialPageAliases[ 'en' ] = [
        'WordCounterPages' => [
            'MostWords',
            'Pages with the most words'
        ]
    ];

    $specialPageAliases[ 'de' ] = [
        'WordCounterPages' => [
            'Meiste_Wörter',
            'Artikel mit den meisten Wörtern'
        ]
    ];

?>