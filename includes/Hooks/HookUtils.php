<?php

    namespace MediaWiki\Extension\WordCounter\Hooks;

    use NumberFormatter;
    use MediaWiki\MediaWikiServices;
    use MediaWiki\Title\Title;

    class HookUtils {

        public static function getWordCount(
            Title $title
        ) : int|string|null {

            return MediaWikiServices::getInstance()->getPageProps()->getProperties(
                $title, 'wordcount'
            )[0] ?? null;

        }

        public static function getWordCountSave(
            Title $title
        ) : int {

            return ( int ) self::getWordCount( $title );

        }

        public static function getWordCountFormatted(
            Title $title
        ) : string {

            return ( new NumberFormatter(
                MediaWikiServices::getInstance()->getContentLanguage()->getCode(),
                NumberFormatter::DECIMAL
            ) )->format(
                self::getWordCountSave( $title )
            );

        }

        public static function hasWordCount(
            Title $title
        ) : bool {

            return self::getWordCount( $title ) !== null;

        }

    }

?>