<?php

    namespace MediaWiki\Extension\WordCounter\Hooks;

    use DatabaseUpdater;

    /**
     * Hooks for updating the database schema
     */
    class ExtensionSchemaUpdates {

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
                __DIR__ . '/../../sql/wordcounter.sql'
            );

        }

    }

?>
