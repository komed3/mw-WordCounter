<?php

    /**
     * Job: CountWords
     * 
     * Background job that executes the CountWords task.
     * Processes pages in configurable batches without output.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Jobs;

    use MediaWiki\Extension\WordCounter\Tasks\CountWords;
    use MediaWiki\Extension\WordCounter\Utils;
    use MediaWiki\JobQueue\Job;

    /**
     * Class CountWordsJob
     * 
     * Executes word counting in the background job queue.
     */
    class CountWordsJob extends Job {

        /**
         * CountWordsJob constructor.
         * 
         * Initializes the job with a unique name and sets it to remove duplicates.
         */
        public function __construct () {

            parent::__construct( 'WordCounterCountWords', null );
            $this->removeDuplicates = true;

        }

        /**
         * Run the job.
         * 
         * Checks if jobs are enabled, sets up the CountWords task,
         * and runs it with a limit. If the full limit is processed,
         * it schedules the next job immediately.
         * 
         * @return bool True if successful, false otherwise.
         */
        public function run () {

            // Check if jobs are enabled (limit > 0)
            $limit = (int) Utils::getConfig( 'WordCounterJobCountWordsLimit', 50 );
            if ( $limit <= 0 ) return true;

            // Set up the task
            $task = new CountWords ();

            // Run the task
            $result = $task->runTask( [ 'limit' => $limit ] ) !== null;

            // Schedule next job if we processed the full limit
            if ( $result[ 'result' ][ 'processed' ] >= $limit ) {

                //

            }

            return $result[ 'result' ][ 'errors' ] === 0;

        }

    }

?>
