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

            return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection(
                $primary ? DB_PRIMARY : DB_REPLICA
            );

        }

        /**
         * Update the word count for a page.
         * 
         * @param int $pageId - The ID of the page to update
         * @param int $wordCount - The new word count
         * @return bool - True if the update was successful, false otherwise
         */
        public static function updateWordCount (
            int $pageId,
            int $wordCount
        ) : bool {

            $dbw = self::getDBConnection( true );
            $dts = $dbw->timestamp();

            return $dbw->upsert(
                'wordcounter',
                [
                    'wc_page_id' => $pageId,
                    'wc_word_count' => $wordCount,
                    'wc_updated' => $dts
                ],
                [ 'wc_page_id' ],
                [
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
         * @param int $limit - The maximum number of pages to return
         * @param int $offset - The offset for pagination
         * @param bool $desc - Whether to order by descending word count
         * @return IResultWrapper - The result set containing page data
         */
        public static function getPagesOrderedByWordCount (
            int $limit = 50, int $offset = 0, bool $desc = true
        ) : IResultWrapper {

            $dbr = self::getDBConnection();

            return $dbr->select(
                [ 'wordcounter', 'page' ],
                [ 'page_id', 'page_title', 'page_namespace', 'wc_word_count' ],
                [
                    'page_namespace' => Utils::supportedNamespaces(),
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
         * Get a batch of wordcounter entries with page join.
         * 
         * @param int $limit - Maximum number of entries to return
         * @param int $offset - Offset for pagination
         * @return IResultWrapper - The result set
         */
        public static function getWordCounterEntriesWithPageJoin (
            int $limit = 100, int $offset = 0
        ) : IResultWrapper {

            $dbr = self::getDBConnection();

            return $res = $dbr->select(
                [ 'wordcounter', 'page' ],
                [
                    'wc_page_id',
                    'page_id',
                    'page_namespace',
                    'page_is_redirect',
                    'page_content_model'
                ],
                [],
                __METHOD__,
                [
                    'ORDER BY' => 'wc_page_id',
                    'LIMIT' => $limit, 'OFFSET' => $offset
                ],
                [
                    'page' => [
                        'LEFT JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            );

        }

        /**
         * Get all pages in supported namespaces (for forced recounting).
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

    }

?>
