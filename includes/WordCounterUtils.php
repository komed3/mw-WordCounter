<?php

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\MediaWikiServices;
    use MediaWiki\Parser\ParserOptions;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Revision\SlotRecord;
    use MediaWiki\User\User;

    class WordCounterUtils {

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