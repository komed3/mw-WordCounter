<?php

    /**
     * Class PurgeOrphaned
     * 
     * This task purges orphaned or invalid word counter entries
     * from the database. It extends the Task class and implements
     * the runTask method to perform the cleanup operation.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Tasks;

    use MediaWiki\Extension\WordCounter\Database;
    use MediaWiki\Extension\WordCounter\Utils;

    /**
     * Class PurgeOrphaned
     * 
     * Implements the task to purge orphaned or invalid word counter entries.
     */
    class PurgeOrphaned extends Task {

        /**
         * Runs the task to purge orphaned or invalid word counter entries.
         * 
         * @param array $options - Options for the task, including limit and dry-run mode.
         * @return array - Result of the task execution.
         */
        public function runTask (
            array $options
        ) : array {

            $this->output( 'Starting orphaned wordcounter entry cleanup.' );

            $limit = (int) $options[ 'limit' ] ?: 1000;
            $dryRun = (bool) $options[ 'dry-run' ];

            // Delete orphaned or invalid entries
            $deleted = Database::deleteOrphanedEntries( $limit, $dryRun );

            $this->output(
                ( $dryRun ? 'Would delete ' : 'Deleted ' ) .
                $deleted . ' orphaned entries.'
            );

            // Clear cache if entries were deleted and not in dry-run mode
            if ( $deleted && ! $dryRun ) {

                Utils::clearCache();
                $this->output( 'Cache cleared.' );

            }

            $this->output( 'Cleanup orphaned wordcounter entry finished.' );

            return [
                'result' => [
                    'deleted' => $deleted
                ]
            ];

        }

    }

?>
