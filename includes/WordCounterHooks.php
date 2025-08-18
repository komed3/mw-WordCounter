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
    use MediaWiki\Page\WikiPage;
    use MediaWiki\Parser\Parser;
    use MediaWiki\Parser\PPFrame;
    use MediaWiki\Permissions\Authority;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Storage\EditResult;
    use MediaWiki\Title\Title;
    use MediaWiki\User\UserIdentity;
    use DatabaseUpdater;
    use StatusValue;

    /**
     * Class WordCounterHooks
     * 
     * This class implements hooks for the WordCounter extension.
     */
    class WordCounterHooks implements
        \MediaWiki\Storage\Hook\PageSaveCompleteHook,
        \MediaWiki\Page\Hook\PageDeleteHook,
        \MediaWiki\Hook\InfoActionHook,
        \MediaWiki\Hook\SpecialStatsAddExtraHook,
        \MediaWiki\Hook\GetMagicVariableIDsHook,
        \MediaWiki\Hook\ParserGetVariableValueSwitchHook,
        \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
    {

        /**
         * Update word count on page save.
         * 
         * @param WikiPage $wikiPage
         * @param UserIdentity $user
         * @param string $summary
         * @param int $flags
         * @param RevisionRecord $revisionRecord
         * @param EditResult $editResult
         */
        public function onPageSaveComplete (
            $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
        ) {

            $pageId = WordCounterUtils::getPageIDFromTitle( $wikiPage->getTitle() );
            $wordCount = WordCounterUtils::countWordsFromRevision( $revisionRecord );

            if ( $pageId && $wordCount ) {

                // Store the word count in the database
                WordCounterDatabase::updateWordCount( $pageId, $wordCount );

                // Clear cache for total word/page count
                WordCounterUtils::clearCache();

            } else {

                wfDebugLog( 'WordCounter', 'Could not count words for page ' .
                    $wikiPage->getTitle()->getPrefixedText()
                );

            }

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
                WordCounterUtils::clearCache();

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

            if ( ( $wordCount = WordCounterUtils::getWordCountByTitle( $context->getTitle() ) ) !== null ) {

                $pageInfo[ 'header-basic' ][] = [
                    $context->msg( 'wordcounter-info-label' ),
                    $context->getLanguage()->formatNum( $wordCount )
                ];

            }

        }

        /**
         * Add extra statistics to the Special:Stats page.
         * 
         * @param array &$extraStats - The array to add extra stats to
         * @param IContextSource $context - The context of the request
         */
        public function onSpecialStatsAddExtra (
            &$extraStats, $context
        ) {

            $totalWords = WordCounterUtils::getTotalWordCount() ?? 0;
            $totalPages = WordCounterUtils::getTotalPageCount() ?? 0;

            $extraStats[ 'wordcounter-stats' ] = [
                'wordcounter-stats-total' => $totalWords,
                'wordcounter-stats-average' => (
                    $totalPages ? round( $totalWords / $totalPages ) : 0
                )
            ];

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
            $variableIDs[] = 'WC_TOTALWORDS';
            $variableIDs[] = 'WC_TOTALPAGES';

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
        ) {

            switch ( $magicWordId ) {

                case 'WC_PAGEWORDS':

                    $wordCount = WordCounterUtils::getWordCountByTitle( $parser->getTitle() );
                    $ret = $wordCount !== null ? (string) $wordCount : '0';

                    return true;

                case 'WC_TOTALWORDS':

                    $totalWords = WordCounterUtils::getTotalWordCount();
                    $ret = $totalWords !== null ? (string) $totalWords : '0';

                    return true;

                case 'WC_TOTALPAGES':

                    $totalPages = WordCounterUtils::getTotalPageCount();
                    $ret = $totalPages !== null ? (string) $totalPages : '0';

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
