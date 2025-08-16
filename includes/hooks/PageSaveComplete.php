<?php

    namespace MediaWiki\Extension\WordCounter\Hooks;

    use MediaWiki\Storage\Hook\PageSaveCompleteHook;
    use MediaWiki\Title\Title;
    use MediaWiki\User\User;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Storage\EditResult;
    use MediaWiki\Page\WikiPageIdentity;

    /**
     * Hook to handle actions after a page is saved
     */
    class ExtensionSchemaUpdates implements PageSaveCompleteHook {

        /**
         * Hook called when a page is saved
         *
         * @param WikiPageIdentity $wikiPage
         * @param User $user
         * @param string $summary
         * @param int $flags
         * @param RevisionRecord $revisionRecord
         * @param EditResult $editResult
         */
        public function onPageSaveComplete (
            WikiPageIdentity $wikiPage,
            User $user,
            string $summary,
            int $flags,
            RevisionRecord $revisionRecord,
            EditResult $editResult
        ) : void {

            // Only process pages in the main namespace
            if ( $wikiPage->getNamespace() !== NS_MAIN ) return;

            $title = Title::newFromPageIdentity( $wikiPage );

            // Check if the title / page exists
            if ( ! $title ) return;

            // Get the content and count words
            $content = $revisionRecord->getContent( 'main' );

            if ( $content && $content->getModel() === CONTENT_MODEL_WIKITEXT ) {

                $text = $content->getText();
                //$wordCount = WordCounterUtils::countWords( $text );

                // Store the word count in the database
                //WordCounterDatabase::updateWordCount( $wikiPage->getId(), $wordCount );

                // Clear cache for total word count
                //WordCounterUtils::clearTotalWordCountCache();

            }

        }

    }

?>
