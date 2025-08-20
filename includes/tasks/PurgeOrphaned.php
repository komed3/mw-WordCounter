<?php

    namespace MediaWiki\Extension\WordCounter\Tasks;

    use MediaWiki\Extension\WordCounter\Database;
    use MediaWiki\Extension\WordCounter\Utils;

    class PurgeOrphaned extends Task {

        public function runTask (
            array $options
        ) {

            $this->output( 'Starting orphaned wordcounter entry cleanup ...' );

            $limit = (int) $options[ 'limit' ] ?: 1000;
            $dryRun = (bool) $options[ 'dry-run' ];

            $deleted = Database::deleteOrphanedEntries( $limit, $dryRun );

            $this->output( 'Deleted entries: ' . $deleted );

            if ( $deleted && ! $dryRun ) {

                Utils::clearCache();
                $this->output( 'Cache cleared.' );

            }

            $this->output( 'Cleanup orphaned wordcounter entry finished.' );

        }

    }

?>
