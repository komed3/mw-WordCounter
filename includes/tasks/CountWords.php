<?php

    /**
     * Class CountWords
     * 
     * This task counts words for pages and updates the database.
     * It extends the Task class and implements the runTask method
     * to perform word counting operations.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Tasks;

    use MediaWiki\Extension\WordCounter\Database;
    use MediaWiki\Extension\WordCounter\Utils;
    use MediaWiki\MediaWikiServices;
    use MediaWiki\Revision\RevisionLookup;
    use MediaWiki\Title\Title;

    /**
     * Class CountWords
     * 
     * Implements the task to count words for pages.
     */
    class CountWords extends Task {

        /**
         * Revision lookup service.
         * 
         * This service is used to retrieve revisions for titles.
         * 
         * @var RevisionLookup
         */
        private RevisionLookup $revLookup;

        /**
         * Number of processed pages.
         * 
         * This variable keeps track of how many pages have been processed
         * during the word counting task.
         * 
         * @var int
         */
        private int $processed = 0;

        /**
         * Number of errors encountered.
         * 
         * This variable counts the number of errors that occur during
         * the word counting task, such as invalid titles or failed revisions.
         * 
         * @var int
         */
        private int $errors = 0;

        /**
         * Runs the task to count words for pages.
         * 
         * @param array $options - Options for the task
         * @return array - Result of the task execution
         */
        public function runTask (
            array $options
        ) : array {

            $this->output( 'Starting word counting task.' );

            $this->revLookup = MediaWikiServices::getInstance()->getRevisionLookup();
            $this->setDryRun( (bool) $options[ 'dry-run' ] );

            $force = (bool) ( $options[ 'force' ] ?? false );
            $limit = (int) ( $options[ 'limit' ] ?? 100 );
            $pages = $options[ 'pages' ] ?? null;

            // Process specific pages
            if ( $pages ) $this->processSpecificPages( $pages );
            // Process in batch
            else $this->processBatch( $force, $limit );

            // Clear cache if entries were deleted and not in dry-run mode
            if ( $this->processed && ! $this->isDryRun() ) {

                Utils::clearCache();
                $this->output( 'Cache cleared.' );

            }

            $this->output( 'Word counting task finished.' );

            return [
                'result' => [
                    'processed' => $this->processed,
                    'errors' => $this->errors
                ]
            ];

        }

        /**
         * Processes some specific pages by their titles for word counting.
         * 
         * @param array $pages - Array of page names to process.
         * @return array - Array containing the count of processed pages and errors.
         */
        private function processSpecificPages (
            array $pages
        ) : void {

            // Process each page title
            foreach ( $pages as $pageName ) {

                // Try to create a Title object from the page name
                if ( ! ( $title = Title::newFromText( trim( $pageName ) ) ) ) {

                    $this->output( 'Invalid page title: ' . $pageName );
                    $errors++;

                    continue;

                }

                if ( $this->processPage( $title ) ) $this->processed++;
                else $this->errors++;

            }

        }

        /**
         * Processes a batch of pages for word counting.
         * 
         * This method retrieves a batch of pages from the database and processes them
         * either in force mode or as uncounted pages, depending on the $force parameter.
         * 
         * @param bool $force - If true, processes all supported pages; otherwise, only uncounted pages.
         * @param int $limit - The maximum number of pages to process in this batch.
         */
        private function processBatch (
            bool $force, int $limit
        ) : void {

            // Retrieve pages to process
            $res = $force 
                ? Database::getAllSupportedPages( $limit )
                : Database::getUncountedPages( $limit );

            // Check if there are pages to process
            if ( ! $res || $res->numRows() === 0 ) {

                $this->output( 'No pages to process.' );
                return ;

            }

            // Process each page in the result set
            foreach ( $res as $row ) {

                $title = Title::makeTitle( $row->page_namespace, $row->page_title );

                if ( $this->processPage( $title ) ) $this->processed++;
                else $this->errors++;

            }

        }

        /**
         * Processes a single page for word counting.
         * 
         * This method checks if the page exists, is not a redirect, and supports the namespace.
         * It then retrieves the revision and counts the words, updating the database if not in dry-run mode.
         * 
         * @param Title $title - The title of the page to process.
         * @return bool - Returns true if the page was processed successfully, false otherwise.
         */
        private function processPage (
            Title $title
        ) : bool {

            $pageName = $title->getPrefixedText();

            // Check if the title exists or is a redirect
            if ( ! $title->exists() || $title->isRedirect() ) {
                $this->output( 'Skipping: ' . $pageName . ' (does not exist or is redirect)' );
                return false;
            }

            // Check if the namespace is supported
            if ( ! Utils::supportsNamespace( $title->getNamespace() ) ) {
                $this->output( 'Skipping: ' . $pageName . ' (unsupported namespace)' );
                return false;
            }

            // Load the revision for the title
            if ( ! ( $revision = $this->revLookup->getRevisionByTitle( $title ) ) ) {
                $this->output( 'Error: Could not load revision for ' . $pageName );
                return false;
            }

            // Count words from the revision
            if ( ( $wordCount = Utils::countWordsFromRevision( $revision ) ) === null ) {
                $this->output( 'Error: Could not count words for ' . $pageName );
                return false;
            }

            // Update the word count in the database if not in dry-run mode
            if ( ! $this->isDryRun() ) Database::updateWordCount(
                $title->getArticleID(), $wordCount
            );

            $this->output(
                ( $this->isDryRun() ? 'Would process ' : 'Processed ' ) .
                $pageName . ' (' . $wordCount . ' words)' 
            );

            return true;

        }

    }

?>
