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

    /**
     * Class CountWords
     * 
     * Maintenance script to count words in articles
     */
    class CountWords extends Maintenance {

        /**
         * Constructor
         *
         * Initializes the maintenance script with options and descriptions.
         */
        public function __construct () {

            parent::__construct();

            $this->requireExtension( 'WordCounter' );
            $this->addDescription( 'Count words in articles and update the database' );
            $this->addOption( 'force', 'Recount words for all articles, even if already counted' );
            $this->addOption( 'limit', 'Maximum number of pages to process', false, true );
            $this->addOption( 'page', 'Process only this specific page', false, true );
            $this->setBatchSize( 100 );

        }

        /**
         * Process a specific page
         *
         * Counts words in the specified page and updates the database.
         *
         * @param string $pageName The name of the page to process
         * @return bool True if successful, false otherwise
         */
        private function processPage (
            string $pageName
        ) : bool {

            $revLookup = MediaWikiServices::getInstance()->getRevisionLookup();

            $this->output( 'Count words for page <' . $pageName . '> ...' . PHP_EOL );

            if ( ! ( $title = Title::newFromText( $pageName ) )->exists() ) {
                $this->output( '  ... Error: Page does not exists.' . PHP_EOL );
                return false;
            }

            if ( $title->getNamespace() !== NS_MAIN ) {
                $this->output( '  ... Error: Page is not in the main namespace.' . PHP_EOL );
                return false;
            }

            if ( ! ( $revision = $revLookup->getRevisionByTitle( $title ) ) ) {
                $this->output( '  ... Error: Could not load revision.' . PHP_EOL );
                return false;
            }

            if ( ( $wordCount = WordCounterUtils::countWordsFromRevision( $revision ) ) == null ) {
                $this->output( '  ... Error: Could not count words.' . PHP_EOL );
                return false;
            }

            WordCounterDatabase::updateWordCount( $title->getArticleID(), $wordCount );

            $this->output( '  ... ' . $wordCount . ' words counted.' . PHP_EOL );
            return true;

        }

        /**
         * Execute the maintenance task
         *
         * This method processes either a specific page or all pages in the main namespace,
         * counting words and updating the database.
         */
        public function execute () {

            $this->output( 'WordCounter 0.1.0 © MIT Paul Köhler (komed3)' . PHP_EOL . PHP_EOL );

            $force = $this->hasOption( 'force' );
            $limit = $this->getOption( 'limit', 0 );
            $specificPage = $this->getOption( 'page' );
            $processed = 0;

            if ( $specificPage ) {

                $processed += (int) $this->processPage( $specificPage );

            } else {

                $dbr = $this->getDB( DB_REPLICA );

                // Build query conditions
                $conditions = [
                    'page_namespace' => NS_MAIN,
                    'page_is_redirect' => 0,
                    'page_content_model' => CONTENT_MODEL_WIKITEXT
                ];

                // Only process pages that haven't been counted yet
                if ( ! $force ) $conditions[] = 'page_id NOT IN (' .
                    $dbr->selectSQLText( 'wordcounter', 'wc_page_id' ) .
                ')';

                // Set query options, including limit if specified
                $options = [ 'ORDER BY' => 'page_id' ];
                if ( $limit > 0 ) $options[ 'LIMIT' ] = $limit;

                // Execute the query to get pages
                $res = $dbr->select( 'page', [ 'page_title' ], $conditions, __METHOD__, $options );

                $total = $res->numRows();
                $batchCount = 0;

                $this->output( 'Processing ' . $total . ' pages ...' . PHP_EOL . PHP_EOL );

                foreach ( $res as $row ) {

                    $processed += (int) $this->processPage( $row->page_title );
                    $batchCount++;

                    if ( $batchCount >= $this->getBatchSize() ) {

                        $batchCount = 0;

                        $this->output( PHP_EOL . 'Processed ' . $processed . ' of ' . $total . ' pages...' . PHP_EOL . PHP_EOL );
                        $this->waitForReplication();

                    }

                }

            }

            // Clear the total word count cache
            WordCounterUtils::clearTotalWordCountCache();

            $this->output( PHP_EOL . 'Completed! Processed ' . $processed . ' pages.' . PHP_EOL );

        }

    }

    // Define the maintenance class
    $maintClass = CountWords::class;

    // Run the maintenance script if this file is executed directly
    require_once RUN_MAINTENANCE_IF_MAIN;

?>