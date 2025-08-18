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

        private const NS_FALLBACK = [ NS_MAIN ];
        private const CACHE_KEY = [ 'wordcounter', 'total-words' ];
        private const CACHE_TTL = 3600;

        /**
         * Get the supported namespaces for word counting.
         * Use $wgWordCounterNamespaces to define valid namespaces.
         * 
         * @return array - Array of supported namespace IDs
         */
        public static function supportedNamespaces () : array {

            $config = MediaWikiServices::getInstance()->getMainConfig();

            return (array) $config->get( 'WordCounterNamespaces' ) ?: self::NS_FALLBACK;

        }

        /**
         * Check if the namespace is valid for word counting.
         * 
         * @param int $namespace - The namespace ID
         * @return bool - True if valid, false otherwise
         */
        public static function supportsNamespace (
            int $namespace
        ) : bool {

            return in_array( $namespace, self::supportedNamespaces() );

        }

        /**
         * Get the page ID from the title.
         * 
         * @param Title $title - The title of the page
         * @return int|null - The page ID if valid, null otherwise
         */
        public static function getPageIDFromTitle (
            Title $title
        ) : ?int {

            return (
                $title instanceof Title &&
                $title->exists() && ! $title->isRedirect() &&
                self::supportsNamespace( $title->getNamespace() ) &&
                ( $pageId = $title->getArticleID() ) && $pageId
            ) ? $pageId : null;

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

            return ( $pageId = self::getPageIDFromTitle( $title ) )
                ? WordCounterDatabase::getWordCount( $pageId ) ?? 0
                : 0;

        }

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
                        User::newSystemUser( 'WordCounter', [ 'steal' => true ] )
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
                $cache->makeKey( self::CACHE_KEY[ 0 ], self::CACHE_KEY[ 1 ] ),
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

            $cache->delete( $cache->makeKey(
                self::CACHE_KEY[ 0 ], self::CACHE_KEY[ 1 ]
            ) );

        }

    }

?>
