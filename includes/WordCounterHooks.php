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
    use MediaWiki\Page\ProperPageIdentity;
    use MediaWiki\Page\WikiPage;
    use MediaWiki\Parser\Parser;
    use MediaWiki\Parser\PPFrame;
    use MediaWiki\Permissions\Authority;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Storage\EditResult;
    use MediaWiki\User\UserIdentity;
    use DatabaseUpdater;
    use StatusValue;

    /**
     * Class WordCounterHooks
     * 
     * This class implements hooks for the WordCounter extension.
     */
    class WordCounterHooks implements
        \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook,
        \MediaWiki\Storage\Hook\PageSaveCompleteHook,
        \MediaWiki\Page\Hook\PageDeleteHook,
        \MediaWiki\Hook\ParserFirstCallInitHook,
        \MediaWiki\Hook\InfoActionHook,
        \MediaWiki\Hook\SpecialStatsAddExtraHook
    {

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

                // Clear the total word/page count cache
                WordCounterUtils::clearCache();

                // Invalidate parser cache for this page and any pages that might reference it
                WordCounterUtils::invalidateParserCache( $wikiPage );

            } else {

                wfDebugLog( 'WordCounter', 'Could not count words for page <' .
                    $wikiPage->getTitle()->getPrefixedText() . '>'
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
            $page, $deleter, $reason, $status, $suppress
        ) {

            if ( $pageId = $page->getId() ) {

                WordCounterDatabase::deleteWordCount( $pageId );
                WordCounterUtils::clearCache();

            }

        }

        /**
         * Register parser functions for word counting.
         * 
         * @param Parser $parser - The parser instance
         */
        public function onParserFirstCallInit (
            $parser
        ) {

            $parser->setFunctionHook( 'pagewords', [ __CLASS__, 'renderPageWords' ] );
            $parser->setFunctionHook( 'totalwords', [ __CLASS__, 'renderTotalWords' ] );
            $parser->setFunctionHook( 'totalpages', [ __CLASS__, 'renderTotalPages' ] );

        }

        public static function renderPageWords (
            $parser, $format = '', $pageName = ''
        ) {

            return WordCounterParserFunctions::renderPageWords(
                $parser, $format, $pageName
            );

        }

        public static function renderTotalWords (
            $parser, $format = ''
        ) {

            return WordCounterParserFunctions::renderTotalWords(
                $parser, $format
            );

        }

        public static function renderTotalPages (
            $parser, $format = ''
        ) {

            return WordCounterParserFunctions::renderTotalPages(
                $parser, $format
            );

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

    }

?>
