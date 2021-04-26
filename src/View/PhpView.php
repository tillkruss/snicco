<?php



	namespace WPEmerge\View;

	use GuzzleHttp\Psr7\Response;
	use GuzzleHttp\Psr7\Utils;
	use WPEmerge\Contracts\ViewInterface;
	use WPEmerge\Exceptions\ViewException;
	use WPEmerge\Support\Arr;

	/**
	 * Render a view file with php.
	 */
	class PhpView implements ViewInterface {


		/**
		 * PHP view engine.
		 *
		 * @var PhpViewEngine
		 */
		private $engine;

		/**
		 * Filepath to view.
		 *
		 * @var string
		 */
		private $filepath = '';

		/**
		 * @var ViewInterface|null
		 */
		private $layout;

		/**
		 * @var array
		 */
		private $context = [];

		/** @var string  */
		private $name = '';

		public function __construct( PhpViewEngine $engine ) {

			$this->engine = $engine;

		}

		public function getFilepath() : string {

			return $this->filepath;
		}

		public function setFilepath( string $filepath ) : PhpView {

			$this->filepath = $filepath;

			return $this;
		}

		public function getLayout() : ?ViewInterface {

			return $this->layout;
		}

		public function setLayout( ?ViewInterface $layout ) : PhpView {

			$this->layout = $layout;

			return $this;
		}

		public function toString() :string {

			if ( empty( $this->name ) ) {
				throw new ViewException( 'View must have a name.' );
			}

			if ( empty( $this->getFilepath() ) ) {
				throw new ViewException( 'View must have a filepath.' );
			}

			$this->engine->pushLayoutContent( $this );

			if ( $this->getLayout() !== null ) {
				return $this->getLayout()->toString();
			}

			return $this->engine->getLayoutContent();
		}

		public function toResponse() {

			return ( new Response() )
				->withHeader( 'Content-Type', 'text/html' )
				->withBody( Utils::streamFor( $this->toString() ) );
		}

		/**
		 *
		 * @param  string|null  $key
		 * @param  mixed|null  $default
		 *
		 * @return mixed
		 */
		public function getContext( $key = null, $default = null ) {

			if ( $key === null ) {
				return $this->context;
			}

			return Arr::get( $this->context, $key, $default );
		}

		/**
		 *
		 * @param  string|array<string, mixed>  $key
		 * @param  mixed  $value
		 *
		 * @return static                      $this
		 */
		public function with( $key, $value = null ) {

			if ( is_array( $key ) ) {
				$this->context = array_merge( $this->getContext(), $key );
			} else {
				$this->context[ $key ] = $value;
			}

			return $this;
		}

		public function getName() :string  {
			return $this->name;
		}

		public function setName( string $name ) :PhpView {
			$this->name = $name;
			return $this;
		}

	}
