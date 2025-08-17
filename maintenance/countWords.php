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

        public function execute () {

            $this->output( 'WordCounter 0.1.0 © MIT Paul Köhler (komed3)' . PHP_EOL . PHP_EOL );

            $force = $this->hasOption( 'force' );
            $limit = $this->getOption( 'limit', 0 );
            $specificPage = $this->getOption( 'page' );
            $processed = 0;

            if ( $specificPage ) {

                $processed += (int) $this->processPage( $specificPage );

            } else {



            }

            WordCounterUtils::clearTotalWordCountCache();

            $this->output( PHP_EOL . 'Completed! Processed ' . $processed . ' pages.' . PHP_EOL );

        }

    }

    $maintClass = CountWords::class;

    require_once RUN_MAINTENANCE_IF_MAIN;

?>