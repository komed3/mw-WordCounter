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
    use MediaWiki\Page\WikiPage;
    use MediaWiki\Parser\ParserOptions;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Revision\SlotRecord;
    use MediaWiki\Title\Title;

    /**
     * Class WordCounterUtils
     * 
     * Utility class for WordCounter extension.
     */
    class WordCounterUtils {

        /**
         * Supported namespaces for word counting.
         * 
         * @var array
         */
        private const NS_FALLBACK = [
            NS_MAIN
        ];

        /**
         * Cache keys for total word and page counts.
         * 
         * @var array
         */
        private const CACHE_KEY = [
            'words' => 'total-words',
            'pages' => 'total-pages'
        ];

        /**
         * Cache TTL in seconds.
         * 
         * @var int
         */
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

            // Get the content of the revision
            $content = $revisionRecord->getContent( SlotRecord::MAIN );

            // If the content is not available or not in wikitext format, return null
            if ( ! $content || $content->getModel() !== CONTENT_MODEL_WIKITEXT ) return null;

            // Get services and create a parser options object from anon (no DB user)
            $services = MediaWikiServices::getInstance();
            $lang = $services->getContentLanguage();
            $parser = $services->getParser();
            $parserOptions = ParserOptions::newFromAnon();

            // Set the target language for the parser options
            if ( method_exists( $parserOptions, 'setTargetLanguage' ) )
                $parserOptions->setTargetLanguage( $lang );

            else if ( method_exists( $parserOptions, 'setUserLang' ) )
                $parserOptions->setUserLang( $lang );

            // Disable preview mode if applicable
            if ( method_exists( $parserOptions, 'setIsPreview' ) )
                $parserOptions->setIsPreview( false );

            // Parse the content to get Html output
            $parserOutput = $parser->parse(
                $content->getText(),
                $revisionRecord->getPageAsLinkTarget(),
                $parserOptions
            );

            // Strip Html tags and trim the text
            // If the text is empty after stripping tags, return 0
            if ( ( $plainText = trim( strip_tags(
                $parserOutput->getText( [ 'unwrap' => true ] )
            ) ) ) === '' ) return 0;

            // Determine if numbers should be counted as words
            $countNumbers = $services->getMainConfig()->get( 'WordCounterCountNumbers' );
            $pattern = $countNumbers ? '/[\p{L}\p{N}]+/u' : '/\p{L}+/u';

            // Count words
            return preg_match_all( $pattern, $plainText );

        }

        /**
         * Invalidate the parser cache for a specific page.
         * 
         * @param WikiPage $wikiPage - The wiki page to invalidate the cache for
         */
        public static function invalidateParserCache (
            $wikiPage
        ) : void {

            // Clear parser cache for the given page
            $parserCache = MediaWikiServices::getInstance()->getParserCache();
            $parserCache->deleteOptionsKey( $wikiPage );

            // Also clear the HTML cache
            $wikiPage->doPurge();

        }

        /**
         * Get the total word count from the cache or database.
         * 
         * @return int - The total word count
         */
        public static function getTotalWordCount () : int {

            $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

            return $cache->getWithSetCallback(
                $cache->makeKey( 'wordcounter', self::CACHE_KEY[ 'words' ] ),
                self::CACHE_TTL,
                function () {
                    return WordCounterDatabase::getTotalWordCount();
                }
            );

        }

        /**
         * Get the total page count from the cache or database.
         * 
         * @return int - The total page count
         */
        public static function getTotalPageCount () : int {

            $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

            return $cache->getWithSetCallback(
                $cache->makeKey( 'wordcounter', self::CACHE_KEY[ 'pages' ] ),
                self::CACHE_TTL,
                function () {
                    return WordCounterDatabase::getTotalPageCount();
                }
            );

        }

        /**
         * Clear the total word count cache.
         */
        public static function clearTotalWordCountCache () : void {

            $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

            $cache->delete( $cache->makeKey(
                'wordcounter', self::CACHE_KEY[ 'words' ]
            ) );

        }

        /**
         * Clear the total page count cache.
         */
        public static function clearTotalPageCountCache () : void {

            $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

            $cache->delete( $cache->makeKey(
                'wordcounter', self::CACHE_KEY[ 'pages' ]
            ) );

        }

        /**
         * Clear all caches related to word counting.
         */
        public static function clearCache () : void {

            self::clearTotalWordCountCache();
            self::clearTotalPageCountCache();

        }

    }

?>
