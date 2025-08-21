<?php

    /**
     * Class WordCounter/Tasks/PurgeOrphanedTask
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
     * Class WordCounter/Tasks/PurgeOrphanedTask
     * 
     * Implements the task to purge orphaned or invalid word counter entries.
     */
    class PurgeOrphanedTask extends TaskBase {

        /**
         * Runs the task to purge orphaned or invalid word counter entries.
         * 
         * @param array $options - Options for the task
         * @return array - Result of the task execution
         */
        public function runTask (
            array $options
        ) : array {

            // Set up task
            $this->output( 'Starting orphaned wordcounter entry cleanup.' );
            $this->setDryRun( (bool) $options[ 'dry-run' ] );

            // Prepare options
            $limit = (int) $options[ 'limit' ] ?: 1000;

            // Delete orphaned or invalid entries
            $deleted = Database::deleteOrphanedEntries( $limit, $this->isDryRun() );

            $this->output( $deleted === 0
                ? 'No entries to delete.'
                : ( $this->isDryRun() ? 'Would delete ' : 'Deleted ' ) .
                  $deleted . ' orphaned entries.'
            );

            // Clear cache if entries were deleted and not in dry-run mode
            if ( $deleted && ! $this->isDryRun() ) {

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
