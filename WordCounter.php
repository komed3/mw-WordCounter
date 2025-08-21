<?php

    /**
     * WordCounter Extension
     * 
     * WordCounter is a comprehensive MediaWiki extension that counts and tracks the number
     * of words in any pages. It provides features such as automatic word counting, parser
     * functions for displaying the word count, a special page for listing articles by word
     * count, and an API module.
     * 
     * Features:
     *  - Automatic word counting for pages
     *  - Parser functions to display word counts
     *  - Special page to list pages with the most words
     *  - API module for retrieving word counts
     *  - Maintenance scripts to manage word counts
     *  - Job scheduler integration for periodic updates
     *  - Configurable options for caching and namespaces
     *  - Support for multiple languages
     * 
     * @author Paul Köhler (komed3)
     * @version 0.1.0
     * @license MIT
     */

    if ( function_exists( 'wfLoadExtension' ) ) {

        wfLoadExtension( 'WordCounter' );

        $wgMessagesDirs[ 'WordCounter' ] = __DIR__ . '/i18n';

        wfWarn(
            'Deprecated PHP entry point used for WordCounter extension. ' .
            'Please use wfLoadExtension() instead, ' .
            'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
        );

        return;

    } else {

        die ( 'This version of the WordCounter extension requires MediaWiki 1.43+' );

    }

?>
