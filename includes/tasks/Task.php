<?php

    namespace MediaWiki\Extension\WordCounter\Tasks;

    class Task {

        private $outputCallback = null;

        protected function output (
            string $msg
        ) : void {

            if ( is_callable( $this->outputCallback ) ) {

                ( $this->outputCallback )( $msg );

            }

        }

        public function setOutputCallback (
            ?callable $callback
        ) : void {

            $this->outputCallback = $callback;

        }

    }

?>
