<?php

    /**
     * Class WordCounterUtils
     * 
     * Utility class for WordCounter extension.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\MediaWikiServices;
    use MediaWiki\Parser\ParserOptions;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Revision\SlotRecord;
    use MediaWiki\Title\Title;
    use MediaWiki\User\User;

    /**
     * Class WordCounterUtils
     * 
     * Utility class for WordCounter extension.
     */
    class WordCounterUtils {

        private const CACHE_KEY = [ 'wordcounter', 'total-words' ];
        private const CACHE_TTL = 3600;

        /**
         * Count words from a revision record.
         *
         * @param RevisionRecord $revisionRecord - The revision record to count words from
         * @return int|null - The word count or null if not applicable
         */
        public static function countWordsFromRevision (
            RevisionRecord $revisionRecord
        ) : ?int {

            $content = $revisionRecord->getContent( SlotRecord::MAIN );

            if ( $content && $content->getModel() === CONTENT_MODEL_WIKITEXT ) {

                $parser = MediaWikiServices::getInstance()->getParser();

                $parserOutput = $parser->parse(
                    $content->getText(),
                    $revisionRecord->getPageAsLinkTarget(),
                    ParserOptions::newFromUser(
                        User::newSystemUser( 'System' )
                    )
                );

                $plainText = trim( strip_tags(
                    $parserOutput->getText( [ 'unwrap' => true ] )
                ) );

                return $plainText ? preg_match_all( '/\p{L}+/u', $plainText ) : 0;

            }

            return null;

        }

        /**
         * Get the total word count from the cache or database.
         *
         * @return int - The total word count
         */
        public static function getTotalWordCount () : int {

            $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

            return $cache->getWithSetCallback(
                $cache->makeKey(
                    self::CACHE_KEY[ 0 ],
                    self::CACHE_KEY[ 1 ]
                ),
                self::CACHE_TTL,
                function () {
                    return WordCounterDatabase::getTotalWordCount();
                }
            );

        }

        /**
         * Clear the total word count cache.
         */
        public static function clearTotalWordCountCache () : void {

            $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

            $cache->delete(
                $cache->makeKey(
                    self::CACHE_KEY[ 0 ],
                    self::CACHE_KEY[ 1 ]
                )
            );

        }

        /**
         * Get the word count for a specific page by title.
         *
         * @param string $titleText - The title of the page
         * @return int - The word count for the page
         */
        public static function getWordCountByTitle (
            Title $title
        ) : int {

            if (
                ! $title || ! $title->exists() || $title->getNamespace() !== NS_MAIN ||
                ! ( $pageId = $title->getArticleID() )
            ) return 0;

            return WordCounterDatabase::getWordCount( $pageId ) ?? 0;

        }

    }

?>