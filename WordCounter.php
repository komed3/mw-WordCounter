<?php

    /**
     * WordCounter
     * count words of content pages (main namespace)
     * 
     * @author      komed3 (Paul Köhler)
     * @version     0.0.1 [BETA]
     */

    if ( function_exists( 'wfLoadExtension' ) ) {

        wfLoadExtension( 'WordCounter' );

        $wgMessagesDirs[ 'WordCounter' ] = __DIR__ . '/i18n';

        wfWarn(
            'Deprecated PHP entry point used for ShortDescription extension. ' .
            'Please use wfLoadExtension instead, ' .
            'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
        );

        return;

    } else {

        die( 'This version of the WordCounter extension requires MediaWiki 1.43+' );

    }

?>