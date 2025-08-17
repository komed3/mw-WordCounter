<?php

    /**
     * Class WordCounterHooks
     * 
     * This class implements hooks for the WordCounter extension.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\Context\IContextSource;
    use MediaWiki\Message\Message;
    use MediaWiki\Page\ProperPageIdentity;
    use MediaWiki\Parser\Parser;
    use MediaWiki\Parser\PPFrame;
    use MediaWiki\Permissions\Authority;
    use MediaWiki\Title\Title;
    use DatebaseUpdater;
    use StatusValue;

    /**
     * Class WordCounterHooks
     * 
     * This class implements hooks for the WordCounter extension.
     */
    class WordCounterHooks implements
        \MediaWiki\Page\Hook\PageDeleteHook,
        \MediaWiki\Hook\InfoActionHook,
        \MediaWiki\Hook\GetMagicVariableIDsHook,
        \MediaWiki\Hook\ParserGetVariableValueSwitchHook,
        \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
    {

        /**
         * Get the page ID from the title.
         * 
         * @param Title $title - The title of the page
         * @return int|null - The page ID if valid, null otherwise
         */
        private function _pageIDFromTitle (
            Title $title
        ) : ?int {

            return (
                $title instanceof Title &&
                $title->getNamespace() == NS_MAIN &&
                $pageId = $title->getArticleID()
            ) ? $pageId : null;

        }

        /**
         * Remove the word count for a page being deleted.
         * 
         * @param ProperPageIdentity $page - The page being deleted
         * @param Authority $deleter - The user performing the deletion
         * @param string $reason - The reason for deletion
         * @param StatusValue $status - The status of the deletion
         * @param bool $suppress - Whether the deletion is suppressed
         */
        public function onPageDelete (
            ProperPageIdentity $page,
            Authority $deleter,
            string $reason,
            StatusValue $status,
            bool $suppress
        ) {

            if ( $pageId = $page->getId() ) {

                WordCounterDatabase::deleteWordCount( $pageId );

            }

        }

        /**
         * Add word count information to the page info.
         * 
         * @param IContextSource $context - The context of the request
         * @param array &$pageInfo - The page information array to modify
         */
        public function onInfoAction (
            $context, &$pageInfo
        ) {

            if ( $pageId = $this->_pageIDFromTitle( $context->getTitle() ) ) {

                $wordCount = WordCounterDatabase::getWordCount( $pageId );

                if ( $wordCount !== null ) {

                    $pageInfo[ 'header-basic' ][] = [
                        $context->msg( 'wordcounter-info-label' ),
                        $context->getLanguage()->formatNum( $wordCount )
                    ];

                }

            }

        }

        /**
         * Register the magic variable IDs for the extension.
         * 
         * @param array &$variableIDs - The array to add magic variable IDs to
         */
        public function onGetMagicVariableIDs (
            &$variableIDs
        ) {

            $variableIDs[] = 'WC_PAGEWORDS';

        }

        /**
         * Handle the magic variable switches for WordCounter extension.
         * 
         * @param \Parser $parser - The parser instance
         * @param array &$variableCache - The variable cache
         * @param string $magicWordId - The magic word ID being processed
         * @param string &$ret - The return value to set
         * @param \ParserFrame $frame - The parser frame
         * @return bool - True if handled, false otherwise
         */
        public function onParserGetVariableValueSwitch (
            $parser, &$variableCache, $magicWordId, &$ret, $frame
        ) : bool {

            switch ( $magicWordId ) {

                case 'WC_PAGEWORDS':

                    if ( $pageId = $this->_pageIDFromTitle( $parser->getTitle() ) ) {

                        $wordCount = WordCounterDatabase::getWordCount( $pageId );
                        $ret = $wordCount !== null ? (string) $wordCount : '0';

                    } else $ret = '0';

                    return true;

            }

            return false;

        }

        /**
         * Load the extension schema updates.
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
