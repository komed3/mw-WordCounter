<?php

    namespace MediaWiki\Extension\WordCounter\Hooks;

    class PageSaveComplete {

        public static function onPageSaveComplete(
            WikiPage $wikiPage,
            MediaWiki\User\UserIdentity $user,
            string $summary,
            int $flags,
            MediaWiki\Revision\RevisionRecord $revisionRecord,
            MediaWiki\Storage\EditResult $editResult
        ) {

            //

        }

    }

?>