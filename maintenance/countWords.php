<?php

    /**
     * WordCounter Maintenance Script
     *
     * This script is part of the WordCounter extension for MediaWiki.
     * It provides a maintenance task to count words in articles and update the database.
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
    use MediaWiki\Extension\WordCounter\WordCounterDatabase;
    use MediaWiki\Extension\WordCounter\WordCounterUtils;
    use MediaWiki\MediaWikiServices;
    use MediaWiki\Revision\RevisionLookup;
    use MediaWiki\Title\Title;
    use Wikimedia\Rdbms\IResultWrapper;

    class CountWords extends Maintenance {

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
         * Count of processed pages.
         * 
         * @var int
         */
        private int $processedCount = 0;

        /**
         * Count of errors encountered.
         * 
         * @var int
         */
        private int $errorCount = 0;

        /**
         * Constructor
         * Initializes the maintenance script with options and descriptions.
         */
        public function __construct () {

            parent::__construct();

            $this->requireExtension( 'WordCounter' );
            $this->addDescription( 'Count words in articles and update the database' );
            $this->addOption( 'force', 'Recount words for all articles, even if already counted' );
            $this->addOption( 'limit', 'Maximum number of pages to process', false, true );
            $this->addOption( 'pages', 'Process only those pages, separated by “|”.', false, true );
            $this->addOption( 'dry-run', 'Show what would be done without making changes' );
            $this->addOption( 'ci', 'CI mode: minimal output, only show errors and final results' );
            $this->setBatchSize( 100 );

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
         * Process a specific page by ID.
         * Counts words in the specified page and updates the database.
         * 
         * @param int $pageId - The ID of the page to process
         * @param string $pageTitle - The title of the page (for display purposes)
         * @return bool - True if successful, false otherwise
         */
        private function processPageById (
            int $pageId,
            string $pageTitle
        ) : bool {

            $revLookup = MediaWikiServices::getInstance()->getRevisionLookup();

            $this->outputMessage(
                'Processing page <' . $pageTitle . '> (#' . $pageId . ')' .
                ( $this->dryRun ? ' [DRY RUN]' : '' ) . ' ...'
            );

            // Check if the Title can be created from the ID
            if ( ! ( $title = Title::newFromID( $pageId ) ) ) {
                $this->outputMessage( 'Could not create title from ID <' . $pageId . '>', 'error' );
                return false;
            }

            // Check if the title exists and is not a redirect
            if ( ! $title->exists() || $title->isRedirect() ) {
                $this->outputMessage(
                    'Page <' . $pageTitle . '> does not exist or is a redirect',
                    'error'
                );
                return false;
            }

            // Check if the namespace is supported
            if ( ! WordCounterUtils::supportsNamespace( $title->getNamespace() ) ) {
                $this->outputMessage(
                    'Page <' . $pageTitle . '> namespace is not supported',
                    'error'
                );
                return false;
            }

            // Check if the revision can be loaded
            if ( ! ( $revision = $revLookup->getRevisionByTitle( $title ) ) ) {
                $this->outputMessage(
                    'Could not load revision for page <' . $pageTitle . '>',
                    'error'
                );
                return false;
            }

            // Check if the word count can be determined
            if ( ( $wordCount = WordCounterUtils::countWordsFromRevision( $revision ) ) === null ) {
                $this->outputMessage(
                    'Could not count words for page <' . $pageTitle . '>',
                    'error'
                );
                return false;
            }

            // Only update database if not in dry-run mode
            if ( ! $this->dryRun ) WordCounterDatabase::updateWordCount(
                $title->getArticleID(), $wordCount
            );

            $this->outputMessage(
                'Page <' . $pageTitle . '> has ' . $wordCount . ' words ' .
                ( $this->dryRun ? '[WOULD UPDATE]' : '[UPDATED]' ),
                'success'
            );

            return true;

        }

        /**
         * Process a specific page by title.
         * 
         * @param string $pageName - The name of the page to process
         * @return bool - True if successful, false otherwise
         */
        private function processPageByTitle (
            string $pageName
        ) : bool {

            // Check if the Title can be created from the name
            if ( ! ( $title = Title::newFromText( trim( $pageName ) ) ) ) {
                $this->outputMessage(
                    'Invalid page title <' . $pageName . '>',
                    'error'
                );
                return false;
            }

            // Process the page by ID
            return $this->processPageById(
                $title->getArticleID(),
                $title->getPrefixedText()
            );

        }

        /**
         * Get pages that need processing using optimized database queries
         * 
         * @param bool $force - Whether to reprocess all pages
         * @param int $limit - Maximum number of pages to return
         * @param int $offset - Offset for pagination
         * @return IResultWrapper
         */
        private function getPagesToProcess (
            bool $force,
            int $limit,
            int $offset = 0
        ) {

            if ( $force ) {

                // If forcing, get all supported pages
                return WordCounterDatabase::getAllSupportedPages( $limit, $offset );

            } else {

                // Otherwise, get only uncounted pages
                return WordCounterDatabase::getUncountedPages( $limit );

            }

        }

        /**
         * Execute the maintenance task
         * This method processes either specific pages or all pages in the
         * main namespace, counting words and updating the database.
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
            $force = $this->hasOption( 'force' );
            $limit = (int) $this->getOption( 'limit', 0 );
            $pages = $this->getOption( 'pages' );

            // Process only specific pages
            if ( $pages ) {

                $this->outputMessage( 'Processing specific pages ...', true );

                foreach ( explode( '|', $pages ) as $page ) {

                    if ( $this->processPageByTitle( $page ) ) $this->processedCount++;
                    else $this->errorCount++;

                }

            }

            // Process pages whose words have not yet been counted
            // All pages if ‘--force’ is specified
            else {

                $this->outputMessage(
                    'Processing pages in batches ' .
                    ( $force ? '(forced recount)' : '(uncounted only)' ) .
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

                    // Get the next batch of pages
                    $res = $this->getPagesToProcess( $force, $currentBatchSize, $offset );

                    // If no pages returned, we're done
                    if ( ! $res || $res->numRows() === 0 ) {

                        $this->outputMessage( 'No more pages to process' );
                        break;

                    }

                    $batchProcessed = $batchErrors = 0;

                    // Process each page in the batch
                    foreach ( $res as $row ) {

                        $title = Title::makeTitle( $row->page_namespace, $row->page_title );
                        $fullTitle = $title ? $title->getPrefixedText() : $row->page_title;

                        if ( $this->processPageById( $row->page_id, $fullTitle ) ) {

                            $this->processedCount++;
                            $batchProcessed++;

                        } else {

                            $this->errorCount++;
                            $batchErrors++;

                        }

                        $totalProcessed++;
                        $batchCount++;

                        // Check if we've hit the overall limit
                        if ( $limit > 0 && $totalProcessed >= $limit ) {

                            $this->outputMessage( 'Reached specified limit of ' . $limit . ' pages', true );
                            break 2; // Break out of both loops

                        }

                    }

                    // Progress update after each batch
                    $this->outputMessage(
                        'Batch completed: ' . $batchProcessed . ' processed, ' . $batchErrors .
                        ' errors. Total so far: ' . $totalProcessed . ' pages'
                    );

                    // Wait for replication after each batch (only if not dry-run)
                    if ( ! $this->dryRun && $batchCount >= $batchSize ) {

                        $this->outputMessage( 'Waiting for replication ...' );
                        $this->waitForReplication();

                        $batchCount = 0;

                    }

                    // In force mode, we need to increment offset
                    if ( $force ) $offset += $currentBatchSize;

                } while ( true );

            }

            // Clear cache only if not in dry-run mode
            if ( ! $this->dryRun ) {

                WordCounterUtils::clearCache();
                $this->outputMessage( 'Cache cleared' );

            }

            // Final summary (always shown)
            $this->outputMessage( '', true );
            $this->outputMessage( '=== SUMMARY ===', true );
            $this->outputMessage( 'Successfully processed: ' . $this->processedCount . ' pages', true );
            $this->outputMessage( 'Errors encountered: ' . $this->errorCount . ' pages', true );
            $this->outputMessage( 'Completed!', true );

            // Exit with error code if there were errors
            if ( $this->errorCount > 0 ) exit( 1 );

        }

    }

    // Define the maintenance class
    $maintClass = CountWords::class;

    // Run the maintenance script if this file is executed directly
    require_once RUN_MAINTENANCE_IF_MAIN;

?>
