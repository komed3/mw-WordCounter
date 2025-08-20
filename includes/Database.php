<?php

    /**
     * Class WordCounter/Database
     * 
     * This class handles database interactions for the WordCounter extension.
     * It provides methods to update and retrieve word counts for pages.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\MediaWikiServices;
    use Wikimedia\Rdbms\IDatabase;
    use Wikimedia\Rdbms\IResultWrapper;

    /**
     * Class WordCounter/Database
     * 
     * This class handles database interactions for the WordCounter extension.
     */
    class Database {

        /**
         * Get a database connection.
         * 
         * @param bool $primary - Whether to get the primary connection
         * @return IDatabase - The database connection
         */
        private static function getDBConnection (
            bool $primary = false
        ) : IDatabase {

            return MediaWikiServices::getInstance()
                ->getDBLoadBalancer()
                ->getConnection(
                    $primary ? DB_PRIMARY : DB_REPLICA
                );

        }

        /**
         * Update the word count for a page.
         * 
         * @param int $pageId - The ID of the page to update
         * @param int $revId - The ID of the revision to update
         * @param int $wordCount - The new word count
         * @return bool|null - True if the update was successful, false otherwise
         */
        public static function updateWordCount (
            int $pageId, int $revId, int $wordCount
        ) : ?bool {

            $dbw = self::getDBConnection( true );
            $dts = $dbw->timestamp();

            return $dbw->upsert(
                'wordcounter',
                [
                    'wc_page_id' => $pageId,
                    'wc_rev_id' => $revId,
                    'wc_word_count' => $wordCount,
                    'wc_updated' => $dts
                ],
                [ 'wc_page_id' ],
                [
                    'wc_rev_id' => $revId,
                    'wc_word_count' => $wordCount,
                    'wc_updated' => $dts
                ],
                __METHOD__
            );

        }

        /**
         * Delete the word count for a page.
         * 
         * @param int $pageId - The ID of the page to delete the word count for
         * @return bool - True if the deletion was successful, false otherwise
         */
        public static function deleteWordCount (
            int $pageId
        ) : bool {

            $dbw = self::getDBConnection( true );

            return $dbw->delete(
                'wordcounter',
                [ 'wc_page_id' => $pageId ],
                __METHOD__
            );

        }

        /**
         * Delete multiple entries by page IDs.
         * 
         * @param array $pageIds - Array of page IDs to delete
         * @return bool - True if deletion was successful, false otherwise
         */
        public static function deleteWordCounts (
            array $pageIds
        ) : bool {

            if ( empty( $pageIds ) ) return true;

            $dbw = self::getDBConnection( true );

            return $dbw->delete(
                'wordcounter',
                [ 'wc_page_id' => $pageIds ],
                __METHOD__
            );

        }

        /**
         * Get the word count for a page.
         * 
         * @param int $pageId - The ID of the page to get the word count for
         * @return int|null - The word count, or null if not found
         */
        public static function getWordCount (
            int $pageId
        ) : ?int {

            $dbr = self::getDBConnection();
            
            $wordCount = $dbr->selectField(
                'wordcounter',
                'wc_word_count',
                [ 'wc_page_id' => $pageId ],
                __METHOD__
            );
            
            return $wordCount !== false ? (int) $wordCount : null;

        }

        /**
         * Get pages ordered by word count.
         * 
         * This method retrieves pages ordered by their word count, optionally
         * filtered by namespace and ordered in ascending or descending order.
         * 
         * @param int $limit - The maximum number of pages to return
         * @param int $offset - The offset for pagination
         * @param bool $desc - Whether to order by descending word count
         * @param array|null $ns - An array of namespace IDs to filter by, or null for all supported namespaces
         * @return IResultWrapper - The result set containing page data
         */
        public static function getPagesOrderedByWordCount (
            int $limit = 50, int $offset = 0, bool $desc = true, ?array $ns = null
        ) : ?IResultWrapper {

            $dbr = self::getDBConnection();

            $namespaces = Utils::supportedNamespaces();
            $namespaces = $ns ? array_intersect( $namespaces, $ns ) : $namespaces;

            return empty( $namespaces ) ? null : $dbr->select(
                [ 'wordcounter', 'page' ],
                [ 'page_id', 'page_title', 'page_namespace', 'wc_word_count' ],
                [
                    'page_namespace' => $namespaces,
                    'page_is_redirect' => 0
                ],
                __METHOD__,
                [
                    'ORDER BY' => 'wc_word_count ' . ( $desc ? 'DESC' : 'ASC' ),
                    'LIMIT' => $limit, 'OFFSET' => $offset
                ],
                [
                    'page' => [
                        'INNER JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

        }

        /**
         * Get the total word count across all pages.
         * 
         * @return int - The total word count
         */
        public static function getTotalWordCount () : int {

            $dbr = self::getDBConnection();

            $total = $dbr->selectField(
                [ 'wordcounter', 'page' ],
                'SUM( wc_word_count )',
                [
                    'page_namespace' => Utils::supportedNamespaces(),
                    'page_is_redirect' => 0
                ],
                __METHOD__,
                [],
                [
                    'page' => [
                        'INNER JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

            return $total ? (int) $total : 0;

        }

        /**
         * Get the total number of pages with word counts.
         * 
         * @return int - The total number of pages
         */
        public static function getTotalPageCount () : int {

            $dbr = self::getDBConnection();

            $count = $dbr->selectField(
                [ 'wordcounter', 'page' ],
                'COUNT( wc_page_id )',
                [
                    'page_namespace' => Utils::supportedNamespaces(),
                    'page_is_redirect' => 0
                ],
                __METHOD__,
                [],
                [
                    'page' => [
                        'INNER JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

            return $count ? (int) $count : 0;

        }

        /**
         * Get the number of pages that need word count updates.
         * 
         * This method counts pages that are in supported namespaces,
         * not redirects, and have a wikitext content model, but do not
         * have a corresponding entry in the wordcounter table.
         * 
         * @return int - The number of pages needing word count updates
         */
        public static function getPagesNeedingCount () : int {

            $dbr = self::getDBConnection();

            return $dbr->selectField(
                [ 'page', 'wordcounter' ],
                'COUNT(*)',
                [
                    'page_namespace' => Utils::supportedNamespaces(),
                    'page_is_redirect' => 0,
                    'page_content_model' => CONTENT_MODEL_WIKITEXT,
                    'wc_page_id IS NULL'
                ],
                __METHOD__,
                [],
                [
                    'wordcounter' => [
                        'LEFT JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            ) ?: 0;

        }

        /**
         * Get pages that need word counting with limit.
         * 
         * This method retrieves pages that have not been counted yet,
         * filtered by supported namespaces and content model.
         * 
         * @param int $limit - Maximum number of pages to return
         * @return IResultWrapper - The result set
         */
        public static function getUncountedPages (
            int $limit = 100
        ) : IResultWrapper {

            $dbr = self::getDBConnection();

            return $dbr->select(
                [ 'page', 'wordcounter' ],
                [ 'page_id', 'page_title', 'page_namespace' ],
                [
                    'page_namespace' => Utils::supportedNamespaces(),
                    'page_is_redirect' => 0,
                    'page_content_model' => CONTENT_MODEL_WIKITEXT,
                    'wc_page_id IS NULL'
                ],
                __METHOD__,
                [
                    'ORDER BY' => 'page_id',
                    'LIMIT' => $limit
                ],
                [
                    'wordcounter' => [
                        'LEFT JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

        }

        /**
         * Get pages with outdated word counts that need updating.
         * 
         * This method retrieves pages where the word count is outdated,
         * meaning the word count was last updated before the page was last touched.
         * 
         * @param int $limit - Maximum number of pages to return
         * @return IResultWrapper - The result set containing outdated word counts
         */
        public static function getPagesWithOutdatedWordCount (
            int $limit = 100
        ) : IResultWrapper {

            $dbr = self::getDBConnection();

            return $dbr->select(
                [ 'wordcounter', 'page' ],
                [ 'wc_page_id', 'wc_word_count', 'page_title', 'page_namespace' ],
                [
                    'page_namespace' => Utils::supportedNamespaces(),
                    'page_is_redirect' => 0,
                    'wc_updated < page_touched'
                ],
                __METHOD__,
                [
                    'ORDER BY' => 'wc_page_id',
                    'LIMIT' => $limit
                ],
                [
                    'page' => [
                        'INNER JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

        }

        /**
         * Get all pages in supported namespaces (for forced recounting).
         * 
         * This method retrieves all pages that are in supported namespaces,
         * not redirects, and have the wikitext content model.
         * 
         * @param int $limit - Maximum number of pages to return
         * @param int $offset - Offset for pagination
         * @return IResultWrapper - The result set
         */
        public static function getAllSupportedPages (
            int $limit = 100, int $offset = 0
        ) : IResultWrapper {

            $dbr = self::getDBConnection();

            return $dbr->select(
                'page',
                [ 'page_id', 'page_title', 'page_namespace' ],
                [
                    'page_namespace' => Utils::supportedNamespaces(),
                    'page_is_redirect' => 0,
                    'page_content_model' => CONTENT_MODEL_WIKITEXT
                ],
                __METHOD__,
                [
                    'ORDER BY' => 'page_id',
                    'LIMIT' => $limit, 'OFFSET' => $offset
                ]
            );

        }

        /**
         * Delete orphaned entries directly using efficient SQL.
         * 
         * This method finds and deletes entries in the wordcounter table
         * that do not have a corresponding page entry.
         * 
         * @param int $limit - Maximum number of entries to delete in this batch
         * @param bool $tryRun - If true, only simulate the deletion without executing it
         * @return int - Number of deleted entries
         */
        public static function deleteOrphanedEntries (
            int $limit = 1000, bool $tryRun = false
        ) : int {

            $dbr = self::getDBConnection();

            // Find orphaned entries (no matching page)
            $orphanedIds = $dbr->selectFieldValues(
                [ 'wordcounter', 'page' ],
                'wc_page_id',
                [ 'page_id IS NULL' ],
                __METHOD__,
                [ 'LIMIT' => $limit ],
                [
                    'page' => [
                        'LEFT JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

            // Delete orphaned entries
            if ( $orphanedIds && ! $tryRun ) self::deleteWordCounts( $orphanedIds );
            $totalDeleted = count( $orphanedIds );

            // If limit is reached, do not proceed further
            if ( $totalDeleted >= $limit ) return $totalDeleted;

            // Find entries for unsupported namespaces, redirects, or non-wikitext
            $invalidIds = $dbr->selectFieldValues(
                [ 'wordcounter', 'page' ],
                'wc_page_id',
                [
                    $dbr->makeList( [
                        'page_namespace NOT IN (' . $dbr->makeList( Utils::supportedNamespaces() ) . ')',
                        'page_is_redirect = 1',
                        'page_content_model != ' . $dbr->addQuotes( CONTENT_MODEL_WIKITEXT )
                    ], LIST_OR )
                ],
                __METHOD__,
                [ 'LIMIT' => $limit - $totalDeleted ],
                [
                    'page' => [
                        'INNER JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

            // Delete invalid entries
            if ( $invalidIds && ! $tryRun ) self::deleteWordCounts( $invalidIds );
            return $totalDeleted + count( $invalidIds );

        }

    }

?>
