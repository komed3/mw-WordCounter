<?php

    namespace MediaWiki\Extension\WordCounter;

    use MediaWiki\Extension\WordCounter\Jobs;
    use MediaWiki\JobQueue\Job;
    use MediaWiki\MediaWikiServices;

    class JobScheduler {

        public static function maybeSchedule () {

            

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
                class_exists( $className = 'MediaWiki\\Extension\\WordCounter\\Jobs\\' . $jobName ) &&
                ( $job = new $className() ) && $job instanceof Job
            ) {

                // Lazy push the job to the job queue group
                MediaWikiServices::getInstance()
                    ->getJobQueueGroupFactory()
                    ->makeJobQueueGroup()
                    ->lazyPush( $job );

            }

        }

    }

?>