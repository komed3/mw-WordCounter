<?php

    /**
     * Class ApiQueryWordCounter
     * 
     * This class handles API queries related to word counting in MediaWiki.
     * It provides methods to retrieve total word counts, page-specific word counts,
     * and lists of pages ordered by word count or needing word counting.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Api;

    use MediaWiki\Api\ApiBase;
    use MediaWiki\Api\ApiMain;
    use MediaWiki\Extension\WordCounter\WordCounterDatabase;
    use MediaWiki\Extension\WordCounter\WordCounterUtils;
    use MediaWiki\Title\Title;

    /**
     * Class ApiQueryWordCounter
     * 
     * This class handles API queries related to word counting in MediaWiki.
     */
    class ApiQueryWordCounter extends ApiBase {

        /**
         * Execute the API query.
         * This method processes the request parameters and retrieves
         * the requested word count data based on the specified properties.
         */
        public function execute () {

            $params = $this->extractRequestParams();

            $prop = array_flip( $params[ 'prop' ] );
            $data = [];

            // Handle total statistics
            if ( isset( $prop[ 'totals' ] ) )
                $data[ 'totals' ] = $this->getTotals();

            // Handle page-specific word count
            if ( isset( $prop[ 'pagewords' ] ) )
                $data[ 'pagewords' ] = $this->getPageWords( $params );

            // Handle page list
            if ( isset( $prop[ 'pages' ] ) )
                $data[ 'pages' ] = $this->getPages( $params );

            // Handle uncounted pages
            if ( isset( $prop[ 'uncounted' ] ) )
                $data[ 'uncounted' ] = $this->getUncountedPages( $params );

            $this->getResult()->addValue(
                null, $this->getModuleName(), $data
            );

        }

        /**
         * Get total word counts and page counts.
         * This method returns the total word count, total page count,
         * and the number of pages that need to be counted.
         *
         * @return array - An array containing total word count, total page count,
         *                 and uncounted pages.
         */
        private function getTotals () : array {

            return [
                'totalWords' => WordCounterUtils::getTotalWordCount(),
                'totalPages' => WordCounterUtils::getTotalPageCount(),
                'uncountedPages' => WordCounterDatabase::getPagesNeedingCount()
            ];

        }

        /**
         * Get word counts for specific pages.
         * This method accepts either titles or page IDs and returns
         * the word count for each specified page.
         *
         * @param array $params - The parameters containing titles or page IDs.
         * @return array - An array of results with word counts for each page.
         */
        private function getPageWords (
            array $params
        ) : array {

            $titles = $results = [];
            $totalWords = 0;

            // Validate that only one method is used
            $methods = array_filter( [
                ! empty( $params[ 'titles' ] ),
                ! empty( $params[ 'pageids' ] )
            ] );

            if ( count( $methods ) === 0 )
                $this->dieWithError( 'wordcounter-api-error-no-page-specified', 'wc--no-page-specified' );
            else if ( count( $methods ) > 1 )
                $this->dieWithError( 'wordcounter-api-error-multi-methods', 'wc--multi-methods' );

            // Handle multiple page titles
            if ( ! empty( $params[ 'titles' ] ) ) {

                foreach ( $params[ 'titles' ] as $titleText ) {

                    if ( ( $title = Title::newFromText( $titleText ) ) && $title->exists() ) $titles[] = $title;
                    else $this->addWarning( [ 'wordcounter-api-warning-invalid-title', $titleText ] );

                }

            }

            // Handle multiple page IDs
            if ( ! empty( $params[ 'pageids' ] ) ) {

                foreach ( $params[ 'pageids' ] as $pageId ) {

                    if ( ( $title = Title::newFromID( $pageId ) ) && $title->exists() ) $titles[] = $title;
                    else $this->addWarning( [ 'wordcounter-api-warning-invalid-pageid', $pageId ] );

                }

            }

            // Loop through titles
            foreach ( $titles as $title ) {

                // Check if the title is valid and supported
                if ( ! WordCounterUtils::supportsNamespace( $title->getNamespace() ) ) {

                    $this->addWarning( [ 'wordcounter-api-warning-invalid-ns', $title->getPrefixedText() ] );
                    continue;

                }

                // Get word count for the title
                $wordCount = WordCounterUtils::getWordCountByTitle( $title );
                $totalWords += $wordCount;

                $results[] = [
                    'pageId' => $title->getArticleID(),
                    'pageTitle' => $title->getPrefixedText(),
                    'namespace' => $title->getNamespace(),
                    'wordCount' => $wordCount,
                    'exists' => $wordCount > 0
                ];

            }

            // Return results
            return [
                'results' => $results,
                'count' => count( $results ),
                'totalWords' => $totalWords
            ];

        }

        /**
         * Get a list of pages ordered by word count.
         * This method retrieves pages ordered by their word count,
         * with options for sorting and pagination.
         *
         * @param array $params - The parameters containing limit, offset, and sort order.
         * @return array - An array of results with page details and word counts.
         */
        private function getPages (
            array $params
        ) : array {

            $limit = $params[ 'limit' ];
            $offset = $params[ 'offset' ];
            $desc = $params[ 'sort' ] === 'desc';
            $results = [];

            // Fetch pages ordered by word count from the database
            if ( $res = WordCounterDatabase::getPagesOrderedByWordCount( $limit, $offset, $desc ) ) {

                foreach ( $res as $row ) {

                    if ( $title = Title::makeTitle( $row->page_namespace, $row->page_title ) ) {

                        $results[] = [
                            'pageId' => (int) $row->page_id,
                            'pageTitle' => $title->getPrefixedText(),
                            'namespace' => (int) $row->page_namespace,
                            'wordCount' => (int) $row->wc_word_count
                        ];

                    }

                }

            }

            // Return results
            return [
                'results' => $results,
                'count' => count( $results ),
                'offset' => $offset,
                'limit' => $limit,
                'sort' => $params[ 'sort' ]
            ];

        }

        /**
         * Get a list of pages that have not been counted yet.
         * This method retrieves pages that need word counting,
         * with options for pagination.
         *
         * @param array $params - The parameters containing limit.
         * @return array - An array of results with page details that need counting.
         */
        private function getUncountedPages (
            array $params
        ) : array {

            $limit = $params[ 'limit' ];
            $results = [];

            // Fetch uncounted pages from the database
            if ( $res = WordCounterDatabase::getUncountedPages( $limit ) ) {

                foreach ( $res as $row ) {

                    if ( $title = Title::makeTitle( $row->page_namespace, $row->page_title ) ) {

                        $results[] = [
                            'pageId' => (int) $row->page_id,
                            'pageTitle' => $title->getPrefixedText(),
                            'namespace' => (int) $row->page_namespace
                        ];

                    }

                }

            }

            // Return results
            return [
                'results' => $results,
                'count' => count( $results ),
                'limit' => $limit,
                'total' => WordCounterDatabase::getPagesNeedingCount()
            ];

        }

        /**
         * Get the allowed parameters for this API module.
         * This method defines the parameters that can be used in API requests.
         *
         * @return array - An array of allowed parameters with their types and requirements.
         */
        public function getAllowedParams () : array {

            return [
                'prop' => [
                    ApiBase::PARAM_ISMULTI => true,
                    ApiBase::PARAM_TYPE => [
                        'totals',
                        'pagewords',
                        'pages',
                        'uncounted'
                    ],
                    ApiBase::PARAM_REQUIRED => true,
                    ApiBase::PARAM_HELP_MSG_PER_VALUE => []
                ],
                'titles' => [
                    ApiBase::PARAM_TYPE => 'string',
                    ApiBase::PARAM_ISMULTI => true,
                    ApiBase::PARAM_REQUIRED => false
                ],
                'pageids' => [
                    ApiBase::PARAM_TYPE => 'integer',
                    ApiBase::PARAM_ISMULTI => true,
                    ApiBase::PARAM_REQUIRED => false
                ],
                'limit' => [
                    ApiBase::PARAM_TYPE => 'limit',
                    ApiBase::PARAM_DFLT => 50,
                    ApiBase::PARAM_MIN => 1,
                    ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
                    ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
                    ApiBase::PARAM_REQUIRED => false
                ],
                'offset' => [
                    ApiBase::PARAM_TYPE => 'integer',
                    ApiBase::PARAM_DFLT => 0,
                    ApiBase::PARAM_MIN => 0,
                    ApiBase::PARAM_REQUIRED => false
                ],
                'sort' => [
                    ApiBase::PARAM_TYPE => [ 'desc', 'asc' ],
                    ApiBase::PARAM_DFLT => 'desc',
                    ApiBase::PARAM_REQUIRED => false
                ]
            ];

        }

        /**
         * Get the examples messages for this API module.
         * This method provides example API requests and their corresponding messages.
         *
         * @return array - An array of example API requests with their messages.
         */
        protected function getExamplesMessages () : array {

            return [
                'action=wordcounter&prop=totals'
                    => 'apihelp-wordcounter-example-totals',
                'action=wordcounter&prop=pagewords&titles=Main_Page'
                    => 'apihelp-wordcounter-example-pagewords',
                'action=wordcounter&prop=pagewords&pageids=1|2|3'
                    => 'apihelp-wordcounter-example-pageids',
                'action=wordcounter&prop=pages&limit=10&sort=desc'
                    => 'apihelp-wordcounter-example-pages',
                'action=wordcounter&prop=uncounted&limit=100'
                    => 'apihelp-wordcounter-example-uncounted',
            ];

        }

        /**
         * Get the name of this API module.
         * This method returns the name used to identify this module in API requests.
         *
         * @return string - The name of the API module.
         */
        public function getHelpUrls () : array {

            return [ 'https://github.com/komed3/mw-WordCounter' ];

        }

    }

?>
