<?php

    /**
     * Maintenance Script: Purge Orphaned Entries
     * 
     * Removes all entries from the wordcounter table that no longer belong
     * to existing, supported pages (deleted pages, incorrect namespace,
     * redirects etc.).
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
     * Class PurgeOrphaned
     * 
     * Runs the orphaned word counter entry cleanup task.
     */
    class PurgeOrphaned extends Maintenance {

        /**
         * Constructor
         * Initializes the maintenance script with options and descriptions.
         */
        public function __construct () {

            parent::__construct();

            $this->requireExtension( 'WordCounter' );
            $this->setBatchSize( 1000 );

            $this->addDescription( 'Remove orphaned or invalid wordcounter entries (no matching page, wrong namespace, redirect etc.)' );
            $this->addOption( 'limit', 'Maximum number of orphaned or invalid wordcounter entries to delete', false, true );
            $this->addOption( 'dry-run', 'Show what would be deleted, but do not actually delete' );

        }

        /**
         * Executes the maintenance script.
         * 
         * Runs the PurgeOrphaned task in batches until all orphaned entries
         * are processed or the specified limit is reached.
         */
        public function execute () {

            // Set up the task
            $task = new Tasks\PurgeOrphaned ();

            $task->setOutputCallback( function ( $msg ) {
                $this->output( $msg . PHP_EOL );
            } );

            // Get options
            $limit = (int) $this->getOption( 'limit', 0 );
            $batchSize = $this->getBatchSize();

            $options = [
                'limit' => $batchSize,
                'dry-run' => $this->hasOption( 'dry-run' )
            ];

            $proceeded = 0;

            do {

                // Run the batch task
                if (
                    ! ( $result = $task->runTask( $options ) ) ||
                    ( $deleted = $result[ 'result' ][ 'deleted' ] ) < $batchSize ||
                    ( $proceeded += $deleted ) >= $limit
                ) break;

                // Wait for replication
                $this->output( 'Waiting for replication ...' . PHP_EOL );
                $this->waitForReplication();

            } while ( true );

        }

    }

    // Define the maintenance class
    $maintClass = PurgeOrphaned::class;

    // Run the maintenance script if this file is executed directly
    require_once RUN_MAINTENANCE_IF_MAIN;

?>
