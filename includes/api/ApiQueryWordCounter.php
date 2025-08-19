<?php

    namespace MediaWiki\Extension\WordCounter\Api;

    use MediaWiki\Api\ApiBase;
    use MediaWiki\Api\ApiMain;
    use MediaWiki\Extension\WordCounter\WordCounterDatabase;
    use MediaWiki\Extension\WordCounter\WordCounterUtils;
    use MediaWiki\Title\Title;

    class ApiQueryWordCounter extends ApiBase {

        public function __construct (
            ApiMain $mainModule,
            string $moduleName
        ) {

            parent::__construct( $mainModule, $moduleName, 'wc' );

        }

        public function execute () {

            $params = $this->extractRequestParams();

            $prop = array_flip( $params[ 'prop' ] );
            $result = $this->getResult();

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

            $result->addValue( null, $this->getModuleName(), $data );

        }

        private function getTotals () : array {

            return [
                'totalWords' => WordCounterUtils::getTotalWordCount(),
                'totalPages' => WordCounterUtils::getTotalPageCount(),
                'uncountedPages' => WordCounterDatabase::getPagesNeedingCount()
            ];

        }

        private function getPageWords (
            array $params
        ) : array {

            if ( empty( $params[ 'page' ] ) )
                $this->dieWithError( 'wordcounter-api-error-no-page', 'no-page' );

            if ( ! ( $title = Title::newFromText( $params[ 'page' ] ) ) || ! $title->exists() )
                $this->dieWithError( 'wordcounter-api-error-invalid-page', 'invalid-page' );

            if ( ! ( WordCounterUtils::supportsNamespace( $title->getNamespace() ) ) )
                $this->dieWithError( 'wordcounter-api-error-invalid-ns', 'invalid-namespace' );

            $wordCount = WordCounterUtils::getWordCountByTitle( $title );

            return [
                'pageId' => $title->getArticleID(),
                'pageTitle' => $title->getPrefixedText(),
                'namespace' => $title->getNamespace(),
                'wordcount' => $wordCount,
                'exists' => $wordCount > 0
            ];

        }

        private function getPages (
            array $params
        ) : array {

            $limit = $params[ 'limit' ];
            $offset = $params[ 'offset' ];
            $desc = $params[ 'sort' ] === 'desc';
            $pages = [];

            if ( $res = WordCounterDatabase::getPagesOrderedByWordCount( $limit, $offset, $desc ) ) {

                foreach ( $res as $row ) {

                    if ( $title = Title::makeTitle( $row->page_namespace, $row->page_title ) ) {

                        $pages[] = [
                            'pageId' => (int) $row->page_id,
                            'pageTitle' => $title->getPrefixedText(),
                            'namespace' => (int) $row->page_namespace,
                            'wordCount' => (int) $row->wc_word_count
                        ];

                    }

                }

            }

            return [
                'pages' => $pages,
                'count' => count( $pages ),
                'offset' => $offset,
                'limit' => $limit,
                'sort' => $params[ 'sort' ]
            ];

        }

        private function getUncountedPages (
            array $params
        ) : array {

            $limit = $params[ 'limit' ];
            $pages = [];

            if ( $res = WordCounterDatabase::getUncountedPages( $limit ) ) {

                foreach ( $res as $row ) {

                    if ( $title = Title::makeTitle( $row->page_namespace, $row->page_title ) ) {

                        $pages[] = [
                            'pageId' => (int) $row->page_id,
                            'pageTitle' => $title->getPrefixedText(),
                            'namespace' => (int) $row->page_namespace
                        ];

                    }

                }

            }

            return [
                'pages' => $pages,
                'count' => count( $pages ),
                'limit' => $limit,
                'total' => WordCounterDatabase::getPagesNeedingCount()
            ];

        }

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
                    ApiBase::PARAM_DFLT => 'totals',
                    ApiBase::PARAM_HELP_MSG_PER_VALUE => []
                ],
                'page' => [
                    ApiBase::PARAM_TYPE => 'string',
                    ApiBase::PARAM_REQUIRED => false
                ],
                'limit' => [
                    ApiBase::PARAM_TYPE => 'limit',
                    ApiBase::PARAM_DFLT => 50,
                    ApiBase::PARAM_MIN => 1,
                    ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
                    ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
                ],
                'offset' => [
                    ApiBase::PARAM_TYPE => 'integer',
                    ApiBase::PARAM_DFLT => 0,
                    ApiBase::PARAM_MIN => 0
                ],
                'sort' => [
                    ApiBase::PARAM_TYPE => [ 'desc', 'asc' ],
                    ApiBase::PARAM_DFLT => 'desc'
                ]
            ];

        }

    }

?>
