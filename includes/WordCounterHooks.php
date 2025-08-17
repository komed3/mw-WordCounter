<?php

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\Context\IContextSource;
    use MediaWiki\Message\Message;
    use DatebaseUpdater;

    /**
     * Class WordCounterHooks
     * 
     * This class implements hooks for the WordCounter extension.
     */
    class WordCounterHooks implements
        \MediaWiki\Hook\InfoActionHook,
        \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
    {

        /**
         * Add word count information to the page info
         * 
         * @param IContextSource $context - The context of the request
         * @param array &$pageInfo - The page information array to modify
         */
        public function onInfoAction (
            $context, &$pageInfo
        ) {

            $title = $context->getTitle();

            if ( $title->getNamespace() == NS_MAIN && $pageId = $title->getArticleID() ) {

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
         * Load the extension schema updates
         * 
         * @param DatebaseUpdater $updater - The updater instance
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
