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

    namespace MediaWiki\Extension\WordCounter\Specials;

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
         * Sets the special page to be expensive based on configuration.
         * This indicates that the page may take a long time to load and
         * should be cached on large sites.
         * 
         * @return bool - true if the page is expensive and should be cached
         */
        public function isExpensive () {

            return (bool) WordCounterUtils::getConfig( 'WordCounterCacheSpecialPages', true );

        }

        /**
         * Get the maximum age of cached results in seconds.
         * 
         * @return int - Cache age in seconds
         */
        public function getCacheExpiry () {

            return (int) WordCounterUtils::getConfig( 'WordCounterSpecialPageCacheExpiry', 3600 );

        }

        /**
         * Get the maximum number of results to cache.
         * 
         * @return int - Maximum cached results
         */
        public function getMaxResults () {

            return (int) WordCounterUtils::getConfig( 'WordCounterSpecialPageMaxResults', 5000 );

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

            // If the title is invalid, return an error message
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

            // Create a link to the page history
            $hlink = $this->msg( 'parentheses' )->rawParams(
                $linkRenderer->makeKnownLink(
                    $title, $this->msg( 'hist' )->text(),
                    [], [ 'action' => 'history' ]
                )
            )->escaped();

            // Create a link to the page itself
            if ( $this->isCached() ) {

                $plink = $linkRenderer->makeLink( $title );
                $exists = $title->exists();

            } else {

                $plink = $linkRenderer->makeKnownLink( $title );
                $exists = true;

            }

            // Format the word count
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

            $header = $this->msg( 'wordcounter-special-wcp-header' )->parseAsBlock();

            // Add cache information if caching is enabled
            if (
                $this->isExpensive() && $this->isCached() &&
                $cacheTS = $this->getCachedTimestamp()
            ) {

                $lang = $this->getLanguage();

                $lastUpdated = $lang->userTimeAndDate( $cacheTS, $this->getUser() );
                $maxResults = $lang->formatNum( $this->getMaxResults() );

                $cacheInfo = $this->msg( 'wordcounter-special-wcp-cache-info' )
                    ->params( $lastUpdated, $maxResults )
                    ->parseAsBlock();

                $header .= Html::rawElement( 'div', 
                    [ 'class' => 'mw-wordcounter-cache-info' ], 
                    $cacheInfo 
                );

            }

            return $header;

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
