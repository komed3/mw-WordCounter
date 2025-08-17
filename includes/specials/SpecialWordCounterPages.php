<?php

    namespace MediaWiki\Extension\WordCounterSpecials;

    use MediaWiki\SpecialPage\QueryPage;
    use MediaWiki\Title\Title;
    use Skin;

    class SpecialWordCounterPages extends QueryPage {

        public function __construct () {

            parent::__construct( 'WordCounterPages', '', true );

            $this->mIncludable = true;

        }

        public function isExpensive () {

            return true;

        }

        public function isSyndicated () {

            return false;

        }

        public function getQueryInfo () {

            return [
                'tables' => [
                    'wordcounter',
                    'page'
                ],
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
                    'page' => [
                        'INNER JOIN',
                        'page_id = wc_page_id'
                    ]
                ]
            ];

        }

        public function getOrderFields () {

            return [ 'wc_word_count' ];

        }

        public function sortDescending () {

            return true;

        }

        public function formatResult (
            $skin, $result
        ) {

            if ( ! ( $title = Title::makeTitleSafe(
                $result->namespace,
                $result->title
            ) ) ) return false;

            $link = $this->getLinkRenderer()->makeLink( $title );
            $wordCount = $this->getLanguage()->formatNum( $result->value );

            return $this
                ->msg( 'wordcounter-special-wcp-line' )
                ->rawParams( $link )
                ->numParams( $result->value )
                ->params( $wordCount )
                ->escaped();

        }

        public function getPageHeader () {

            return $this->msg( 'wordcounter-special-wcp-header' )->parseAsBlock();

        }

        protected function getGroupName () {

            return 'pages';

        }

        public function getDescription () {

            return $this->msg( 'wordcounter-special-wcp-title' );

        }

    }

?>
