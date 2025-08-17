<?php

    /**
     * Class WordCounterDatabase
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
     * Class WordCounterDatabase
     * 
     * This class handles database interactions for the WordCounter extension.
     */
    class WordCounterDatabase {

        /**
         * Get a database connection.
         * 
         * @param bool $primary - Whether to get the primary connection
         * @return IDatabase - The database connection
         */
        private static function _dbConnection (
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
         */
        public static function updateWordCount (
            int $pageId,
            int $wordCount
        ) : void {

            $dbw = self::_dbConnection( true );
            $dts = $dbw->timestamp();

            $dbw->upsert(
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
         * Get the word count for a page.
         * 
         * @param int $pageId - The ID of the page to get the word count for
         * @return int|null - The word count, or null if not found
         */
        public static function getWordCount (
            int $pageId
        ) : ?int {

            $dbr = self::_dbConnection();
            
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

            $dbr = self::_dbConnection();

            return $dbr->select(
                [ 'wordcounter', 'page' ],
                [ 'page_id', 'page_title', 'page_namespace', 'wc_word_count' ],
                [
                    'page_namespace' => NS_MAIN,
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

    }

?>
