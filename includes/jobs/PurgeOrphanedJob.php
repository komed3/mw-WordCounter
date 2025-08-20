<?php

    /**
     * Job: PurgeOrphaned
     * 
     * Background job that executes the PurgeOrphaned task.
     * Cleans up orphaned entries in configurable batches without output.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Jobs;

    use MediaWiki\Extension\WordCounter\Tasks\PurgeOrphaned;
    use MediaWiki\Extension\WordCounter\Utils;
    use MediaWiki\JobQueue\Job;

    /**
     * Class PurgeOrphaned
     * 
     * Executes orphaned entry cleanup in the background job queue.
     */
    class PurgeOrphanedJob extends Job {

        /**
         * PurgeOrphanedJob constructor.
         * 
         * Initializes the job with a unique name and sets it to remove duplicates.
         */
        public function __construct () {

            parent::__construct( 'WordCounterPurgeOrphaned', null );
            $this->removeDuplicates = true;

        }

        /**
         * Run the job.
         * 
         * Checks if jobs are enabled, sets up the PurgeOrphaned task,
         * and runs it with a limit. If entries are deleted, it schedules
         * the next job immediately.
         * 
         * @return bool - Always return true for cleanup jobs
         */
        public function run () {

            // Check if jobs are enabled (limit > 0)
            $limit = (int) Utils::getConfig( 'WordCounterJobPurgeOrphanedLimit', 1000 );
            if ( $limit <= 0 ) return true;

            // Set up the task
            $task = new PurgeOrphaned ();

            // Run the task
            $result = $task->runTask( [ 'limit' => $limit ] ) !== null;

            // Schedule next job if we processed the full limit
            if ( $result[ 'result' ][ 'deleted' ] >= $limit ) {

                //

            }

            return true;

        }

    }

?>
