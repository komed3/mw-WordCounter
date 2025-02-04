<?php

    namespace MediaWiki\Extension\WordCounter\Hooks;

    class InfoAction {

        public static function onInfoAction(
            $context,
            array &$pageInfo
        ) {

            $title = $context->getTitle();

            if ( HookUtils::hasWordCount( $title ) ) {

                $pageInfo[ 'header-basic' ][] = [
                    $context->msg( 'wordcounter-info-label' ),
                    HookUtils::getWordCountFormatted( $title )
                ];

            }

        }

    }

?>