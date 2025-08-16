<?php

    namespace MediaWiki\Extension\WordCounter;

    class WordCounterHooks implements
        \MediaWiki\Storage\Hook\PageSaveCompleteHook
    {

        public function onPageSaveComplete (
            $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
        ) {

            //

        }

    }

?>
