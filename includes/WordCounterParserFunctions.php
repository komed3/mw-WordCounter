<?php

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\Parser\Parser;
    use MediaWiki\Title\Title;

    class WordCounterParserFunctions {

        private static function formatNum (
            $parser, $result, $format = ''
        ) : string {

            if ( strtoupper( trim( $format ) ) === 'R' )
                return (string) $result;

            return $parser->getTargetLanguage()->formatNum( $result );

        }

        public static function renderPageWords (
            $parser, $format = '', $pageName = ''
        ) : array {

            $title = $parser->getTitle();

            if ( $pageName && ! ( $title = Title::newFromText( $pageName ) ) ) {

                return [ '0', 'noparse' => false ];

            }

            $wordCount = WordCounterUtils::getWordCountByTitle( $title );

            return [
                self::formatNum( $parser, $wordCount, $format ),
                'noparse' => false
            ];

        }

        public static function renderTotalWords (
            $parser, $format = ''
        ) : array {

            $totalWords = WordCounterUtils::getTotalWordCount();

            return [
                self::formatNum( $parser, $totalWords, $format ),
                'noparse' => false
            ];

        }

        public static function renderTotalPages (
            $parser, $format = ''
        ) : array {

            $totalPages = WordCounterUtils::getTotalPageCount();

            return [
                self::formatNum( $parser, $totalPages, $format ),
                'noparse' => false
            ];

        }

    }

?>