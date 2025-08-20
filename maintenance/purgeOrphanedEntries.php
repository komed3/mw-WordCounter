<?php

    /**
     * WordCounter Maintenance Script: Purge Orphaned Word Counts
     * 
     * Removes all entries from the wordcounter table that no longer
     * belong to existing, supported pages (e.g., deleted pages,
     * incorrect namespace, redirects).
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

    use Maintenance;
    use MediaWiki\Extension\WordCounter\Database;
    use MediaWiki\Extension\WordCounter\Utils;
    use MediaWiki\MediaWikiServices;

    /**
     * Class PurgeOrphanedWordCounts
     * 
     * This maintenance script checks the wordcounter table for orphaned
     * entries and removes them. It supports batch processing and can
     * run in dry-run mode to preview changes without modifying the database.
     */
    class PurgeOrphanedWordCounts extends Maintenance {

        /**
         * Whether we're in CI mode (minimal output).
         * 
         * @var bool
         */
        private bool $ciMode = false;

        /**
         * Whether we're in dry-run mode (no database writes).
         * 
         * @var bool
         */
        private bool $dryRun = false;

        /**
         * Count of checked entries.
         * 
         * @var int
         */
        private int $checkedCount = 0;

        /**
         * Count of deleted entries.
         * 
         * @var int
         */
        private int $deletedCount = 0;

        /**
         * Constructor
         * Initializes the maintenance script with options and descriptions.
         */
        public function __construct() {

            parent::__construct();

            $this->requireExtension( 'WordCounter' );
            $this->addDescription( 'Remove orphaned or invalid wordcounter entries (no matching page, wrong namespace, redirect, etc.)' );
            $this->addOption( 'limit', 'Maximum number of rows to process per batch', false, true );
            $this->addOption( 'dry-run', 'Show what would be deleted, but do not actually delete' );
            $this->addOption( 'ci', 'CI mode: minimal output' );
            $this->setBatchSize( 1000 );

        }

        /**
         * Output a message, respecting CI mode settings.
         * 
         * @param string $message - The message to output
         * @param string|bool $level - The level of the message (info, warning, error, success)
         * If ‘level’ is true, always output the message
         */
        private function outputMessage (
            string $message,
            string|bool $level = 'info'
        ) : void {

            // In CI mode, only show errors and forced messages
            if ( $this->ciMode && $level !== true && $level !== 'error' ) return;

            // Add prefix for different message types
            $prefix = match ( $level ) {
                'error' =>   '[ERROR] ',
                'warning' => '[WARNING] ',
                'success' => '[SUCCESS] ',
                default =>   ''
            };

            // Output message
            $this->output( $prefix . $message . PHP_EOL );

        }

        /**
         * Execute the maintenance task
         * This script will check the wordcounter table for orphaned entries
         * and delete them if they do not match any existing page, are in an
         * unsupported namespace, are redirects, or are not in wikitext format.
         */
        public function execute () {

            // Set mode flags
            $this->dryRun = $this->hasOption( 'dry-run' );
            $this->ciMode = $this->hasOption( 'ci' );

            // Show header (always shown, even in CI mode)
            $this->outputMessage( 'WordCounter vers. 0.1.0 / (c) MIT Paul Köhler (komed3)', true );
            $this->outputMessage( 'Visit: https://github.com/komed3/mw-WordCounter', true );
            $this->outputMessage( '', true );

            if ( $this->dryRun ) $this->outputMessage(
                '[DRY RUN MODE] No changes will be made to the database',
                true
            );

            if ( $this->ciMode ) $this->outputMessage(
                '[CI MODE] Minimal output enabled',
                true
            );

            $this->outputMessage( '', true );

            // Get options
            $limit = (int) $this->getOption( 'limit', 0 );

            $this->outputMessage(
                'Starting orphaned entry cleanup in batches' .
                ( $limit > 0 ? ' (limit: ' . $limit . ')' : '' ) . ' ...',
                true
            );

            $totalProcessed = $batchCount = $offset = 0;
            $batchSize = $this->getBatchSize();

            do {

                // Calculate how many pages to fetch in this batch
                $currentBatchSize = min( $batchSize, $limit > 0 ? $limit - $totalProcessed : $batchSize );

                // If no pages to process or limit reached, break
                if ( $currentBatchSize <= 0 ) break;

                // Select a batch of wordcounter entries with LEFT JOIN to page
                $res = Database::getWordCounterEntriesWithPageJoin( $currentBatchSize, $offset );

                // If no results or empty result set, we're done
                if ( ! $res || $res->numRows() === 0 ) {

                    $this->outputMessage( 'No more entries to check' );
                    break;

                }

                // Process the results
                $toDelete = [];

                foreach ( $res as $row ) {

                    $this->checkedCount++;
                    $batchCount++;
                    $reason = null;

                    // Orphaned: page does not exist
                    if ( $row->page_id === null )
                        $reason = 'no matching page';

                    // Wrong namespace
                    else if ( ! Utils::supportsNamespace( (int) $row->page_namespace ) )
                        $reason = 'unsupported namespace (' . $row->page_namespace . ')';

                    // Redirect
                    else if ( $row->page_is_redirect )
                        $reason = 'redirect page';

                    // Not wikitext
                    else if ( $row->page_content_model !== CONTENT_MODEL_WIKITEXT )
                        $reason = 'not wikitext (' . $row->page_content_model . ')';

                    if ( $reason ) {

                        $toDelete[] = $row->wc_page_id;

                        $this->outputMessage(
                            'Deleting wordcounter entry for page ID #' . $row->wc_page_id . ' (' . $reason . ')',
                            'success'
                        );

                    }

                }

                // If we have entries to delete, process them
                if ( $toDelete ) {

                    if ( !$dryRun ) Database::deleteWordCounts( $toDelete );
                    $this->deletedCount += count( $toDelete );

                }

                // Progress update after each batch
                $this->outputMessage(
                    'Batch completed: checked ' . $batchCount . ', found ' . count( $toDelete ) .
                    ' orphaned entries. Total progress: ' . $this->checkedCount . ' checked, ' .
                    $this->deletedCount . ' deleted'
                );

                $offset += $batchSize;

                // Wait for replication after each batch (only if not dry-run)
                if ( ! $this->dryRun && $batchCount >= $batchSize ) {

                    $this->outputMessage( 'Waiting for replication ...' );
                    $this->waitForReplication();

                    $batchCount = 0;

                }

            } while ( true );

            // Clear cache only if not in dry-run mode and if there were deletions
            if ( ! $this->dryRun && $this->deletedCount ) {

                Utils::clearCache();
                $this->outputMessage( 'Cache cleared' );

            }

            // Final summary (always shown)
            $this->outputMessage( '', true );
            $this->outputMessage( '=== SUMMARY ===', true );
            $this->outputMessage( 'Checked entries: ' . $this->checkedCount, true );
            $this->outputMessage( 'Deleted entries: ' . $this->deletedCount, true );
            $this->outputMessage( 'Completed!', true );

        }

    }

    // Define the maintenance class
    $maintClass = PurgeOrphanedWordCounts::class;

    // Run the maintenance script if this file is executed directly
    require_once RUN_MAINTENANCE_IF_MAIN;

?>
