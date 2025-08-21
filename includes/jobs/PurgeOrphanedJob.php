<?php

    /**
     * Class WordCounter/Jobs/PurgeOrphanedJob
     * 
     * Background job that executes the PurgeOrphaned task.
     * Cleans up orphaned entries in configurable batches without output.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Jobs;

    use Job;
    use MediaWiki\Extension\WordCounter\JobScheduler;
    use MediaWiki\Extension\WordCounter\Tasks\PurgeOrphanedTask;
    use MediaWiki\Extension\WordCounter\Utils;

    /**
     * Class WordCounter/Jobs/PurgeOrphanedJob
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

            parent::__construct( 'WordCounterPurgeOrphaned', [] );
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
        public function run () : bool {

            // Check if jobs are enabled (limit > 0)
            $limit = (int) Utils::getConfig( 'WordCounterPurgeOrphanedJobLimit', 1000 );
            if ( $limit <= 0 ) return true;

            // Set up the task
            $task = new PurgeOrphanedTask ();

            // Run the task
            $result = $task->runTask( [ 'limit' => $limit ] ) !== null;

            // Schedule next job if we processed the full limit
            if ( $result[ 'result' ][ 'deleted' ] >= $limit ) {

                JobScheduler::scheduleNext( __CLASS__ );

            }

            return true;

        }

    }

?>
