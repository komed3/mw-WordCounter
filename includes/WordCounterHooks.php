<?php

    namespace MediaWiki\Extension\WordCounter;

    class WordCounterHooks implements
        \MediaWiki\Hook\InfoActionHook,
        \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
    {

        /**
         * Add word count information to the page info action
         * 
         * @param IContextSource $context
         * @param array &$pageInfo
         */
        public function onInfoAction (
            $context, &$pageInfo
        ) {

            $title = $context->getTitle();

            if ( $title->getNamespace() !== NS_MAIN ) return;

            if ( $pageId = $title->getArticleID() ) {

                $wordCount = WordCounterDatabase::getWordCount( $pageId );

                if ( $wordCount !== null ) {

                    $pageInfo['header-basic'][] = [
                        $context->msg( 'wordcounter-info-label' ),
                        $context->getLanguage()->formatNum( $wordCount )
                    ];

                }

            }

        }

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
