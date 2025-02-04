<?php

    namespace MediaWiki\Extension\WordCounter\Hooks;

    use MediaWiki\User\UserIdentity;
    use MediaWiki\Revision\RevisionRecord;
    use MediaWiki\Storage\EditResult;
    use WikiPage;

    class PageSaveComplete {

        public static function onPageSaveComplete(
            WikiPage $wikiPage,
            UserIdentity $user,
            string $summary,
            int $flags,
            RevisionRecord $revisionRecord,
            EditResult $editResult
        ) {

            //

        }

    }

?>