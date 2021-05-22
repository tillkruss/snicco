<?php


	declare( strict_types = 1 );


	namespace WPEmerge\ExceptionHandling;

    use WPEmerge\Events\IncomingAjaxRequest;
    use WPEmerge\Events\ResponseSent;

    class ShutdownHandler {


        /**
         * @var string
         */
        private $request_type;

        public function __construct( string $request_type )
        {
            $this->request_type = $request_type;
        }

        public function unrecoverableException () {

		  $this->terminate();

		}

		public function handle( ResponseSent $response_sent) {

		    if ( $this->request_type === IncomingAjaxRequest::class  ) {

		        $this->terminate();

            }


        }


		private function terminate() {

		    exit();

        }

	}