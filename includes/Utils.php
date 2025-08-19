<?php

    /**
     * Class WordCounter/Utils
     * 
     * Utility class for WordCounter extension.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter;

    use InvalidArgumentException;
    use MediaWiki\MediaWikiServices;
    use MediaWiki\Page\WikiPage;
    use MediaWiki\Parser\ParserOptions;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Revision\SlotRecord;
    use MediaWiki\Title\Title;

    /**
     * Class WordCounter/Utils
     * 
     * Utility class for WordCounter extension.
     */
    class Utils {

        /**
         * Cache services for different environments.
         * 
         * @var array
         */
        private const CACHE_SERVICES = [
            'local' => 'getLocalServerObjectCache',
            'wan' => 'getMainWANObjectCache',
            'micro' => 'getMicroStash',
            'main' => 'getMainObjectStash'
        ];

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
         * Get a configuration value or return a default.
         * 
         * @param string $key - The configuration key
         * @param mixed $default - Default value if key does not exist
         * @return mixed - The configuration value or default
         */
        public static function getConfig (
            string $key,
            mixed $default = null
        ) : mixed {

            $config = MediaWikiServices::getInstance()->getMainConfig();

            return $config->has( $key ) ? $config->get( $key ) : $default;

        }

        /**
         * Get the cache service based on configuration.
         * 
         * @return - The cache service instance
         * @throws InvalidArgumentException - If the configured cache service is invalid
         */
        public static function getCacheService () {

            $service = self::getConfig( 'WordCounterCacheService', 'local' );

            if ( ! array_key_exists( $service, self::CACHE_SERVICES ) ) {

                throw new InvalidArgumentException (
                    'Invalid cache service <' . $service . '>. ' .
                    'Valid options are: <' . implode( ', ', array_keys( self::CACHE_SERVICES ) ) . '>'
                );

            }

            return MediaWikiServices::getInstance()->{
                self::CACHE_SERVICES[ $service ]
            }();

        }

        /**
         * Check if word counting should be performed on page save.
         * 
         * @return bool - True if word counting is enabled on page save
         */
        public static function countOnPageSave () : bool {

            return (bool) self::getConfig( 'WordCounterCountOnPageSave', true );

        }

        /**
         * Get the supported namespaces for word counting.
         * Use $wgWordCounterNamespaces to define valid namespaces.
         * 
         * @return array - Array of supported namespace IDs
         */
        public static function supportedNamespaces () : array {

            return (array) self::getConfig( 'WordCounterNamespaces', self::NS_FALLBACK );

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
         * Checks if the title exists, is not a redirect and is in a supported namespace.
         * 
         * @param Title $title - The title of the page
         * @return int|null - The page ID if valid, null otherwise
         */
        public static function getPageTitleSave (
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
        ) : ?int {

            return ( $pageId = self::getPageTitleSave( $title ) )
                ? Database::getWordCount( $pageId )
                : null;

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

            // Determine which pattern to use for word counting
            // Use the configured pattern or default to counting words
            $pattern = self::getConfig( 'WordCounterPattern', null ) ??
                ( self::getConfig( 'WordCounterCountNumbers', false )
                    ? '/(?:[\p{N}]+([.,][\p{N}]+)*)|[\p{L}]+/u'
                    : '/[\p{L}]+/u' );

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

            $cache = self::getCacheService();

            return $cache->getWithSetCallback(
                $cache->makeKey( 'wordcounter', self::CACHE_KEY[ 'words' ] ),
                self::CACHE_TTL,
                function () {
                    return Database::getTotalWordCount();
                }
            );

        }

        /**
         * Get the total page count from the cache or database.
         * 
         * @return int - The total page count
         */
        public static function getTotalPageCount () : int {

            $cache = self::getCacheService();

            return $cache->getWithSetCallback(
                $cache->makeKey( 'wordcounter', self::CACHE_KEY[ 'pages' ] ),
                self::CACHE_TTL,
                function () {
                    return Database::getTotalPageCount();
                }
            );

        }

        /**
         * Clear the total word count cache.
         */
        public static function clearTotalWordCountCache () : void {

            $cache = self::getCacheService();

            $cache->delete( $cache->makeKey(
                'wordcounter', self::CACHE_KEY[ 'words' ]
            ) );

        }

        /**
         * Clear the total page count cache.
         */
        public static function clearTotalPageCountCache () : void {

            $cache = self::getCacheService();

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
