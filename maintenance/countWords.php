<?php

    /**
     * Maintenance Script: Count Words
     * 
     * Will count words in articles and update the database. When in force
     * mode, it will recount words for all articles, even if they have already
     * been counted.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Maintenance;

    // @codeCoverageIgnoreStart
    if ( ! ( $IP = getenv( 'MW_INSTALL_PATH' ) ) ) $IP = __DIR__ . '/../../..';
    require_once $IP . '/maintenance/Maintenance.php';
    // @codeCoverageIgnoreEnd

    use MediaWiki\Extension\WordCounter\Tasks as Tasks;
    use Maintenance;

    /**
     * Class CountWords
     * 
     * Runs the word counting task for articles.
     */
    class CountWords extends Maintenance {

        /**
         * Constructor
         * Initializes the maintenance script with options and descriptions.
         */
        public function __construct () {

            parent::__construct();

            $this->requireExtension( 'WordCounter' );
            $this->setBatchSize( 100 );

            $this->addDescription( 'Count words in articles and update the database' );
            $this->addOption( 'force', 'Recount words for all articles, even if already counted' );
            $this->addOption( 'limit', 'Maximum number of pages to process', false, true );
            $this->addOption( 'pages', 'Process only those pages, separated by “|”', false, true );
            $this->addOption( 'dry-run', 'Show what would be done without making changes' );

        }

        /**
         * Executes the maintenance script.
         * 
         * Runs the CountWords task in batches until all pages are processed
         * or the specified limit is reached.
         */
        public function execute () {

            // Set up the task
            $task = new Tasks\CountWords ();

            $task->setOutputCallback( function( $msg ) {
                $this->output( $msg . PHP_EOL );
            } );

            // Prepare options
            $totalLimit = (int) $this->getOption( 'limit', 0 );
            $batchSize = $this->getBatchSize();
            $force = $this->hasOption( 'force' );
            $dryRun = $this->hasOption( 'dry-run' );

            $options = [
                'limit' => $batchSize,
                'force' => $force,
                'dry-run' => $dryRun
            ];

            if ( $this->hasOption( 'pages' ) ) {

                $options[ 'pages' ] = explode( '|', $this->getOption( 'pages' ) );

            }

            $totalProcessed = 0;

            do {

                // Adjust batch size if we're approaching the total limit
                if ( $totalLimit > 0 ) {

                    if ( ( $remaining = $totalLimit - $totalProcessed ) <= 0 ) break;

                    $options[ 'limit' ] = min( $batchSize, $remaining );

                }

                // Run the batch task
                if (
                    ! ( $result = $task->runTask( $options ) ) ||
                    ( $processed = array_sum( $result[ 'result' ] ) ) < $options[ 'limit' ] ||
                    ( ( $totalProcessed += $processed ) >= $totalLimit && $totalLimit > 0 )
                ) break;

                // Wait for replication
                $this->output( 'Waiting for replication ...' . PHP_EOL );
                $this->waitForReplication();

            } while ( true );

            // Final summary
            $this->output( 'Total processed: ' . $totalProcessed . ' entries.' . PHP_EOL );

        }

    }

    // Define the maintenance class
    $maintClass = CountWords::class;

    // Run the maintenance script if this file is executed directly
    require_once RUN_MAINTENANCE_IF_MAIN;

?>
