<?php

    /**
     * Class SpecialWordCounterPages
     * 
     * Special page for displaying pages with the most words.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounterSpecials;

    use MediaWiki\Extension\WordCounter\WordCounterUtils;
    use MediaWiki\Html\Html;
    use MediaWiki\Linker\Linker;
    use MediaWiki\SpecialPage\QueryPage;
    use MediaWiki\Title\Title;
    use Skin;

    /**
     * Class SpecialWordCounterPages
     * 
     * This class implements a special page that lists pages
     * with the most words in descending order.
     */
    class SpecialWordCounterPages extends QueryPage {

        /**
         * Constructor for the special page
         */
        public function __construct () {

            parent::__construct( 'WordCounterPages', '', true );

            $this->mIncludable = true;

        }

        /**
         * Sets the special page to be expensive.
         * This indicates that the page may take a long time to load and
         * should be cached on large sites.
         * 
         * @return bool - true if the page is expensive
         */
        public function isExpensive () {

            return true;

        }

        /**
         * Indicates that this special page is not syndicated.
         * 
         * @return bool - false, as this page is not syndicated
         */
        public function isSyndicated () {

            return false;

        }

        /**
         * Returns the query info for the special page.
         * This method defines the database tables and fields that will be
         * queried to retrieve the word counts.
         * 
         * @return array - the query information
         */
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
                    'page_namespace' => WordCounterUtils::supportedNamespaces(),
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

        /**
         * Returns the order fields for sorting the results.
         * This method specifies that the results should be sorted by the
         * word count in descending order.
         * 
         * @return array - the order fields
         */
        public function getOrderFields () {

            return [ 'wc_word_count' ];

        }

        /**
         * Indicates that the results should be sorted in descending order.
         * 
         * @return bool - true, as the results should be sorted descending
         */
        public function sortDescending () {

            return true;

        }

        /**
         * Formats the result for display.
         * This method generates the output for each page, including the link
         * to the page and its word count.
         * 
         * @param Skin $skin - the skin object for rendering
         * @param object $result - the result object containing page data
         * @return string - formatted output for the page
         */
        public function formatResult (
            $skin, $result
        ) {

            $title = Title::makeTitleSafe( $result->namespace, $result->title );

            if ( ! $title ) {

                return Html::element(
                    'span', [ 'class' => 'mw-invalidtitle' ],
                    Linker::getInvalidTitleDescription(
                        $this->getContext(),
                        $result->namespace,
                        $result->title
                    )
                );

            }

            $linkRenderer = $this->getLinkRenderer();

            $hlink = $this->msg( 'parentheses' )->rawParams(
                $linkRenderer->makeKnownLink(
                    $title, $this->msg( 'hist' )->text(),
                    [], [ 'action' => 'history' ]
                )
            )->escaped();

            if ( $this->isCached() ) {

                $plink = $linkRenderer->makeLink( $title );
                $exists = $title->exists();

            } else {

                $plink = $linkRenderer->makeKnownLink( $title );
                $exists = true;

            }

            $wordCount = $this->getLanguage()->formatNum( $result->value );

            return $this
                ->msg( 'wordcounter-special-wcp-line' )
                ->rawParams( $hlink )
                ->rawParams( $exists ? $plink : Html::rawElement( 'del', [], $plink ) )
                ->params( $wordCount )
                ->numParams( $result->value )
                ->escaped();

        }

        /**
         * Returns the header for the special page.
         * This method provides the title and description for the special page.
         * 
         * @return string - the header text
         */
        public function getPageHeader () {

            return $this->msg( 'wordcounter-special-wcp-header' )->parseAsBlock();

        }

        /**
         * Returns the group name for the special page.
         * This method specifies the group under which this special page
         * will be categorized.
         * 
         * @return string - the group name
         */
        protected function getGroupName () {

            return 'pages';

        }

        /**
         * Returns the description for the special page.
         * This method provides a brief description of what the special page does.
         * 
         * @return string - the description text
         */
        public function getDescription () {

            return $this->msg( 'wordcounter-special-wcp-title' );

        }

    }

?>
