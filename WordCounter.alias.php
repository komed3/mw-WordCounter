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

    $magicWords = [];

    $magicWords[ 'en' ] = [
        'WC_PAGEWORDS' => [ 0, 'pagewords' ]
    ];

    $magicWords[ 'de' ] = [
        'WC_PAGEWORDS' => [ 0, 'artikelworte' ]
    ];

    $specialPageAliases = [];

    $specialPageAliases[ 'en' ] = [
        'WC_MostWords' => [ 'MostWords', 'Pages with the most words' ]
    ];

    $specialPageAliases[ 'de' ] = [
        'WC_MostWords' => [ 'Meiste_Wörter', 'Artikel mit den meisten Wörtern' ]
    ];

?>