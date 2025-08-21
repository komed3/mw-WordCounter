<?php

    /**
     * Class WordCounter/JobScheduler
     * 
     * Will manage the scheduling of background jobs for the WordCounter
     * extension. Handles direct and lazy scheduling of jobs. If needed,
     * the job status can be retrieved.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\Extension\WordCounter\Jobs;
    use MediaWiki\JobQueue\Job;
    use MediaWiki\MediaWikiServices;

    /**
     * Class JobScheduler
     * 
     * Manages the scheduling of background jobs for WordCounter.
     */
    class JobScheduler {

        /**
         * Registry of job names and their corresponding class names.
         * 
         * @var array<string, string>
         */
        private const JOB_REGISTRY = [
            'CountWordsJob' => 'WordCounterCountWords',
            'PurgeOrphanedJob' => 'WordCounterPurgeOrphaned'
        ];

        /**
         * Maybe schedule jobs based on configuration.
         * 
         * This method checks the configuration for each job and schedules
         * it if necessary. It uses a cache to avoid scheduling the same
         * job multiple times within the configured interval.
         */
        public static function maybeSchedule () : void {

            $cache = Utils::getCacheService();

            foreach ( array_keys( self::JOB_REGISTRY ) as $job ) {

                $key = $cache->makeKey( 'wordcounter', $job );
                $cfg = 'WordCounter' . $job;

                if (
                    Utils::getConfig( $cfg . 'Limit', 0 ) > 0 &&
                    $cache->get( $key ) === false
                ) {

                    // Set cache with TTL to avoid immediate rescheduling
                    $cache->set( $key, 1, (int) Utils::getConfig( $cfg . 'Interval', 3600 ) );

                    // Schedule the job
                    self::scheduleNext( $job );

                }

            }

        }

        /**
         * Schedule a job to run in the background.
         * 
         * @param string $jobName - The name of the job to schedule
         */
        public static function scheduleNext (
            string $jobName
        ) : void {

            // Check if the job class exists and is a valid Job instance
            if (
                array_key_exists( $jobName, self::JOB_REGISTRY ) &&
                class_exists( $className = 'MediaWiki\\Extension\\WordCounter\\Jobs\\' . $jobName ) &&
                ( $job = new $className() ) && is_subclass_of( $job, 'Job' )
            ) {

                // Lazy push the job to the job queue group
                MediaWikiServices::getInstance()
                    ->getJobQueueGroupFactory()
                    ->makeJobQueueGroup()
                    ->lazyPush( $job );

            } else {

                // Log an error if the job class does not exist or is not a valid Job
                wfDebugLog( 'WordCounter', 'Could not schedule job <' . $jobName . '>' );

            }

        }

        /**
         * Get job queue status information.
         * 
         * @return array - Status information about job queues
         */
        public static function getJobStatus () : array {

            $jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
            $status = [];

            foreach ( self::JOB_REGISTRY as $job => $type ) {

                if ( $queue = $jobQueueGroup->get( $type ) ) {

                    $status[ $job ] = [
                        'type' => $type,
                        'size' => $queue->getSize(),
                        'delayed' => $queue->getDelayedCount()
                    ];

                }

            }
            
            return $status;

        }

    }

?>