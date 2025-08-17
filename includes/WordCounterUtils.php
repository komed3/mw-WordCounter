<?php

    namespace MediaWiki\Extension\WordCounter;

    class WordCounterUtils {

        public static function countWords (
            string $text
        ) : int {

            $text = self::stripWikitext( $text );

            $text = preg_replace( '/\s+/', ' ', trim( $text ) );

            if ( $text === '' ) return 0;

            return count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );

        }

        private static function stripWikitext (
            string $text
        ) : string {

            // Remove comments
            $text = preg_replace( '/<!--.*?-->/s', '', $text );
            // Remove templates (basic removal)
            $text = preg_replace( '/\{\{[^}]*\}\}/', '', $text );
            // Remove file/image links
            $text = preg_replace( '/\[\[(?:File|Image):[^\]]*\]\]/', '', $text );
            // Remove category links
            $text = preg_replace( '/\[\[Category:[^\]]*\]\]/', '', $text );
            // Convert internal links to just the display text
            $text = preg_replace( '/\[\[(?:[^|\]]*\|)?([^\]]*)\]\]/', '$1', $text );
            // Remove external links, keeping just the display text
            $text = preg_replace( '/\[https?:\/\/[^\s\]]+ ([^\]]*)\]/', '$1', $text );
            $text = preg_replace( '/https?:\/\/[^\s]+/', '', $text );
            // Remove wiki markup
            $text = preg_replace( "/'''?([^']*?)'''?/", '$1', $text ); // Bold/italic
            $text = preg_replace( '/^[#*:;]+\s*/m', '', $text ); // Lists
            $text = preg_replace( '/^=+\s*(.*?)\s*=+$/m', '$1', $text ); // Headers
            $text = preg_replace( '/\{\|.*?\|\}/s', '', $text ); // Tables
            // Remove HTML tags
            $text = strip_tags( $text );
            // Decode HTML entities
            $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

            return $text;

        }

    }

?>