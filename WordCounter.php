<?php

    /**
     * WordCounter Extension
     * 
     * WordCounter is a comprehensive MediaWiki extension that counts and tracks words in articles.
     * It provides features such as automatic word counting, magic words for word count display,
     * and a special page for listing articles by word count.
     * 
     * Features:
     *  - Automatic word counting when pages are saved
     *  - Word count display on page info
     *  - Magic words: {{PAGEWORDS}} and {{TOTALWORDS}}
     *  - Special page listing articles by word count
     *  - API integration for word count data
     *  - Total word count on Special:Statistics
     *  - Maintenance script for batch processing
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
