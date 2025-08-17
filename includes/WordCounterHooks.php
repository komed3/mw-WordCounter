<?php

    namespace MediaWiki\Extension\WordCounter;

    class WordCounterHooks implements
        \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
    {

        /**
         * Add database schema updates
         *
         * @param DatabaseUpdater $updater
         */
        public function onLoadExtensionSchemaUpdates (
            $updater
        ) {

            $updater->addExtensionTable(
                'wordcounter',
                __DIR__ . '/../sql/wordcounter.sql'
            );

        }

    }

?>
