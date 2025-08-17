<?php

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\MediaWikiServices;
    use Wikimedia\Rdbms\IDatabase;

    /**
     * Database operations for WordCounter extension
     */
    class Database {

        /**
         * Update word count for a page
         * 
         * @param int $pageId - The ID of the page to update
         * @param int $wordCount - The new word count for the page
         */
        public static function updateWordCount (
            int $pageId,
            int $wordCount
        ) : void {

            $dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
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

    }

?>
