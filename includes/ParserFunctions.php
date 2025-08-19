<?php

    /**
     * Class WordCounter/ParserFunctions
     * 
     * This class provides parser functions for the WordCounter extension.
     * 
     * Usage examples:
     *  - {{#pagewords:}} - Current page word count (formatted)
     *  - {{#pagewords:R}} - Current page word count (raw number)
     *  - {{#pagewords:|<Title>}} - Word count for <Title> (formatted)
     *  - {{#pagewords:R|<Title>}} - Word count for <Title> (raw number)
     *  - {{#totalwords:}} - Total word count across all pages (formatted)
     *  - {{#totalwords:R}} - Total word count across all pages (raw number)
     *  - {{#totalpages:}} - Total number of pages (formatted)
     *  - {{#totalpages:R}} - Total number of pages (raw number)
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\Parser\Parser;
    use MediaWiki\Title\Title;

    /**
     * Class WordCounter/ParserFunctions
     * 
     * This class provides parser functions for the WordCounter extension.
     */
    class ParserFunctions {

        /**
         * Format a number based on the specified format.
         *
         * @param Parser $parser - The parser instance
         * @param int|string $result - The number to format
         * @param string $format - The format specifier
         * @return string - The formatted number
         */
        private static function formatNum (
            $parser, $result, $format = ''
        ) : string {

            if ( strtoupper( trim( $format ) ) === 'R' )
                return (string) $result;

            return $parser->getTargetLanguage()->formatNum( $result );

        }

        /**
         * Render the number of words on the current page.
         *
         * @param Parser $parser - The parser instance
         * @param string $format - The format specifier
         * @param string $pageName - Optional page name to count words for
         * @return string - The formatted word count
         */
        public static function renderPageWords (
            $parser, $format = '', $pageName = ''
        ) : string {

            $title = $parser->getTitle();

            // If a specific page name is provided, use it to get the title
            // Otherwise, use the current page title
            if ( $pageName && (
                ! ( $title = Title::newFromText( $pageName ) ) ||
                ! $title->exists() || $title->isRedirect() ||
                ! Utils::supportsNamespace( $title->getNamespace() )
            ) ) return '0';

            $wordCount = Utils::getWordCountByTitle( $title );

            return self::formatNum( $parser, $wordCount, $format );

        }

        /**
         * Render the total number of words across all pages.
         *
         * @param Parser $parser - The parser instance
         * @param string $format - The format specifier
         * @return string - The formatted total word count
         */
        public static function renderTotalWords (
            $parser, $format = ''
        ) : string {

            $totalWords = Utils::getTotalWordCount();

            return self::formatNum( $parser, $totalWords, $format );

        }

        /**
         * Render the total number of pages.
         *
         * @param Parser $parser - The parser instance
         * @param string $format - The format specifier
         * @return string - The formatted total page count
         */
        public static function renderTotalPages (
            $parser, $format = ''
        ) : string {

            $totalPages = Utils::getTotalPageCount();

            return self::formatNum( $parser, $totalPages, $format );

        }

    }

?>
