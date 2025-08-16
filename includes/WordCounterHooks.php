<?php

    namespace MediaWiki\Extension\WordCounter;

    use DatabaseUpdater;

    /**
     * Hooks for WordCounter extension
     */
    class WordCounterHooks {

        /**
         * Add database schema updates
         *
         * @param DatabaseUpdater $updater
         */
        public static function onLoadExtensionSchemaUpdates (
            DatabaseUpdater $updater
        ) : void {

            $updater->addExtensionTable(
                'wordcounter',
                __DIR__ . '/../sql/wordcounter.sql'
            );

        }

    }

?>
