<?php

    namespace MediaWiki\Extension\WordCounterSpecials;

    use MediaWiki\SpecialPage\QueryPage;
    use MediaWiki\Title\Title;
    use Skin;

    /**
     * Special page that lists pages by word count
     */
    class SpecialWC_MostWords extends QueryPage {

        public function __construct( $name = 'WC_MostWords' ) {
            parent::__construct( $name );
        }

        public function isExpensive() {
            return true;
        }

        public function isSyndicated() {
            return false;
        }

        public function getQueryInfo() {
            return [
                'tables' => [ 'wordcounter', 'page' ],
                'fields' => [
                    'namespace' => 'page_namespace',
                    'title' => 'page_title',
                    'value' => 'wc_word_count'
                ],
                'conds' => [
                    'page_namespace' => NS_MAIN,
                    'page_is_redirect' => 0
                ],
                'join_conds' => [
                    'page' => [ 'INNER JOIN', 'page_id = wc_page_id' ]
                ]
            ];
        }

        public function getOrderFields() {
            return [ 'wc_word_count' ];
        }

        public function sortDescending() {
            return true;
        }

        public function formatResult( $skin, $result ) {
            $title = Title::makeTitleSafe( $result->namespace, $result->title );
            if ( !$title ) {
                return false;
            }

            $linkRenderer = $this->getLinkRenderer();
            $link = $linkRenderer->makeLink( $title );
            
            $wordCount = $this->getLanguage()->formatNum( $result->value );
            
            return $this->msg( 'wordcounter-special-line' )
                ->rawParams( $link )
                ->numParams( $result->value )
                ->params( $wordCount )
                ->escaped();
        }

        public function getPageHeader() {
            return $this->msg( 'wordcounter-special-header' )->parseAsBlock();
        }

        protected function getGroupName() {
            return 'pages';
        }

        public function getDescription() {
            return $this->msg( 'wordcounter-special-title' );
        }

    }

?>
