<?php

    /**
     * Class WordCounter/Hooks
     * 
     * This class implements hooks for the WordCounter extension,
     * handling schema updates, page saves, deletions, parser functions,
     * and special stats.
     * 
     * Methods will not have type hints for parameters or return types
     * because MediaWiki does not yet support them.
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
     * Class WordCounter/Hooks
     * 
     * This class implements hooks for the WordCounter extension.
     */
    class Hooks implements
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
         * On large wikis, this can be performance-sensitive,
         * so it is recommended to disable counting on page save
         * by setting $wgWordCounterCountOnPageSave to false.
         * 
         * @param WikiPage $wikiPage - The wiki page being saved
         * @param UserIdentity $user - The user performing the save
         * @param string $summary - The edit summary
         * @param int $flags - Flags for the edit operation
         * @param RevisionRecord $revisionRecord - The revision being saved
         * @param EditResult $editResult - The result of the edit operation
         */
        public function onPageSaveComplete (
            $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
        ) {

            // Maybe schedule background jobs
            JobScheduler::maybeSchedule();

            // If word counting on page save is disabled, return early
            if ( ! Utils::countOnPageSave() ) return true;

            // Get page ID from Title object and count words from last revision
            $pageId = Utils::getPageIdSave( $wikiPage->getTitle() );
            $wordCount = Utils::countWordsFromRevision( $revisionRecord );

            // If page exists and words have been successfully counted
            if ( $pageId && $wordCount !== null ) {

                // Store the word count in the database
                Database::updateWordCount( $pageId, $wordCount );

                // Clear cache
                Utils::clearCache();

                // Invalidate parser cache for this page (parser functions)
                Utils::invalidateParserCache( $wikiPage );

            } else {

                // If page not meet the requirements, delete the word count
                Database::deleteWordCount( $wikiPage->getId() );

                // Log a debug message if word count could not be counted
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

                // Delete orphaned word count entry from database
                Database::deleteWordCount( $pageId );

                // Clear cache
                Utils::clearCache();

                // Maybe schedule background jobs
                JobScheduler::maybeSchedule();

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

        /**
         * Wrapper to render the number of words on the current page.
         * 
         * @param Parser $parser - The parser instance
         * @param string $format - The format specifier
         * @param string $pageName - Optional page name to count words for
         * @return array - The rendered word count and options
         */
        public static function renderPageWords (
            Parser $parser, string $format = '', string $pageName = ''
        ) : array {

            return [
                ParserFunctions::renderPageWords(
                    $parser, $format, $pageName
                ),
                'noparse' => false
            ];

        }

        /**
         * Wrapper to render the total number of words across all pages.
         * 
         * @param Parser $parser - The parser instance
         * @param string $format - The format specifier
         * @return array - The rendered total word count and options
         */
        public static function renderTotalWords (
            Parser $parser, string $format = ''
        ) : array {

            return [
                ParserFunctions::renderTotalWords(
                    $parser, $format
                ),
                'noparse' => false
            ];

        }

        /**
         * Wrapper to render the total number of pages.
         * 
         * @param Parser $parser - The parser instance
         * @param string $format - The format specifier
         * @return array - The rendered total page count and options
         */
        public static function renderTotalPages (
            Parser $parser, string $format = ''
        ) : array {

            return [
                ParserFunctions::renderTotalPages(
                    $parser, $format
                ),
                'noparse' => false
            ];

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

            if ( ( $wordCount = Utils::getWordCountByTitle( $context->getTitle() ) ) !== null ) {

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

            $totalWords = Utils::getTotalWordCount() ?? 0;
            $totalPages = Utils::getTotalPageCount() ?? 0;

            $extraStats[ 'wordcounter-stats' ] = [
                'wordcounter-stats-total' => $totalWords,
                'wordcounter-stats-average' => (
                    $totalPages ? round( $totalWords / $totalPages ) : 0
                )
            ];

        }

    }

?>
