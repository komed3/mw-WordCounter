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
    use MediaWiki\User\User;

    /**
     * Class WordCounterUtils
     * 
     * Utility class for WordCounter extension.
     */
    class WordCounterUtils {

        /**
         * Count words from a revision record.
         *
         * @param RevisionRecord $revisionRecord - The revision record to count words from
         * @return int|null - The word count or null if not applicable
         */
        public static function countWordsFromRevision (
            RevisionRecord $revisionRecord
        ) : ?int {

            if (
                ( $content = $revisionRecord->getContent( SlotRecord::MAIN ) ) &&
                $content->getModel() === CONTENT_MODEL_WIKITEXT
            ) {

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

    }

?>