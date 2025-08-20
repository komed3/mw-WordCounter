<?php

    /**
     * Class Task
     * 
     * Base class for tasks in the WordCounter extension.
     * Provides methods for outputting messages and setting an output callback.
     * 
     * @author Paul Köhler (komed3)
     * @license MIT
     * @since 0.1.0
     */

    namespace MediaWiki\Extension\WordCounter\Tasks;

    /**
     * Class Task
     * 
     * This class serves as a base for all tasks in the WordCounter extension.
     */
    class Task {

        /**
         * Output callback function.
         * 
         * This function is called to output messages during task execution.
         * It can be set to a custom function that handles the output.
         * 
         * @var callable|null
         */
        private $outputCallback = null;

        /**
         * Outputs a message.
         * 
         * This method checks if an output callback is set and calls it with the provided message.
         * If no callback is set, it does nothing.
         * 
         * @param string $msg - The message to output.
         */
        protected function output (
            string $msg
        ) : void {

            if ( is_callable( $this->outputCallback ) ) {

                ( $this->outputCallback )( $msg );

            }

        }

        /**
         * Sets the output callback function.
         * 
         * This method allows setting a custom function that will be called to output messages.
         * 
         * @param callable|null $callback - The callback function to set. If null, no callback is set.
         */
        public function setOutputCallback (
            ?callable $callback
        ) : void {

            $this->outputCallback = $callback;

        }

    }

?>
