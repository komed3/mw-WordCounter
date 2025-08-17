<?php

    /**
     * WordCounterDatabase class
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

    /**
     * Class WordCounterDatabase
     * 
     * This class handles database interactions for the WordCounter extension.
     */
    class WordCounterDatabase {

        /**
         * Get a database connection
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
         * Update the word count for a page
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
         * Get the word count for a page
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

    }

?>
