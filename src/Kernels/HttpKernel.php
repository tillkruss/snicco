<?php


	namespace WPEmerge\Kernels;

	use Contracts\ContainerAdapter;
	use Exception;
	use Illuminate\Support\Arr;
	use Psr\Http\Message\ResponseInterface;
	use WPEmerge\Application\GenericFactory;
	use WPEmerge\Contracts\HttpKernelInterface;
	use WPEmerge\Contracts\RouteInterface as Route;
	use WPEmerge\Exceptions\ConfigurationException;
	use WPEmerge\Contracts\ErrorHandlerInterface;
	use WPEmerge\Helpers\Handler;
	use WPEmerge\Helpers\HandlerFactory;
	use WPEmerge\Helpers\RoutingPipeline;
	use WPEmerge\Middleware\ExecutesMiddlewareTrait;
	use WPEmerge\Middleware\HasMiddlewareDefinitionsTrait;
	use WPEmerge\Contracts\RequestInterface;
	use WPEmerge\Middleware\SubstituteModelBindings;
	use WPEmerge\Responses\ConvertsToResponseTrait;
	use WPEmerge\Responses\RedirectResponse;
	use WPEmerge\Responses\ResponseService;
	use WPEmerge\Contracts\HasQueryFilterInterface;
	use WPEmerge\Routing\Router;
	use WPEmerge\Routing\SortsMiddlewareTrait;
	use WPEmerge\View\ViewService;

	class HttpKernel implements HttpKernelInterface {

		use HasMiddlewareDefinitionsTrait;
		use SortsMiddlewareTrait;
		use ConvertsToResponseTrait;
		use ExecutesMiddlewareTrait;


		/**
		 * Response service.
		 *
		 * @var ResponseService
		 */
		protected $response_service = null;

		/**
		 * Request.
		 *
		 * @var RequestInterface
		 */
		protected $request = null;

		/**
		 * Router.
		 *
		 * @var Router
		 */
		protected $router = null;

		/**
		 * View Service.
		 *
		 * @var ViewService
		 */
		protected $view_service = null;

		/**
		 * Error handler.
		 *
		 * @var ErrorHandlerInterface
		 */
		protected $error_handler = null;

		/**
		 * Template WordPress attempted to load.
		 *
		 * @var string
		 */
		protected $template = '';

		/**
		 * @var \WPEmerge\Helpers\RoutingPipeline
		 */
		private $route_pipeline;

		/**
		 * Constructor.
		 *
		 * @param  ResponseService  $response_service
		 * @param  RequestInterface  $request
		 * @param  Router  $router
		 * @param  ViewService  $view_service
		 * @param  ErrorHandlerInterface  $error_handler
		 *
		 */
		public function __construct(
			RequestInterface $request,
			ResponseService $response_service,
			RoutingPipeline $route_pipeline,
			Router $router,
			ViewService $view_service,
			ErrorHandlerInterface $error_handler
		) {

			$this->request          = $request;
			$this->response_service = $response_service;
			$this->route_pipeline   = $route_pipeline;
			$this->router           = $router;
			$this->view_service     = $view_service;
			$this->error_handler    = $error_handler;
		}

		/**
		 * Get the current response.
		 *
		 * @return ResponseInterface|null
		 */
		private function getResponse() {

			return isset( $this->container[ WPEMERGE_RESPONSE_KEY ] ) ? $this->container[ WPEMERGE_RESPONSE_KEY ] : null;

		}

		/**
		 * Get a Response Service instance.
		 *
		 * @return ResponseService
		 */
		private function getResponseService() {

			return $this->response_service;
		}


		/**
		 * Execute a handler.
		 *
		 *
		 * @param  Handler  $handler
		 * @param  array  $arguments
		 *
		 * @return ResponseInterface
		 * @throws \WPEmerge\Exceptions\ConfigurationException
		 */
		private function executeHandler( Handler $handler, RequestInterface $request ) : ?ResponseInterface {


			$response = call_user_func( [ $handler, 'execute' ],  $request,  ...array_values($request->route()->arguments()));

			$response = $this->toResponse( $response );

			if ( ! $response instanceof ResponseInterface && $response != null ) {
				throw new ConfigurationException(
					'Response returned by controller is not valid ' .
					'(expected ' . ResponseInterface::class . '; received ' . gettype( $response ) . ').'
				);
			}

			return $response;
		}

		public function _run( RequestInterface $request, $middleware, $handler, $arguments = [] ) {

			// whoops
			$this->error_handler->register();

			try {

				$middleware = array_merge( $middleware, $handler->controllerMiddleware() );
				$middleware = $this->applyGlobalMiddleware( $middleware );
				$middleware = $this->expandMiddleware( $middleware );
				$middleware = $this->uniqueMiddleware( $middleware );
				$middleware = $this->sortMiddleware( $middleware );

				$response = $this->executeMiddleware( $middleware, $request, function () use ( $handler, $arguments ) {

					return $this->executeHandler( $handler, $arguments );

				} );

			}
			catch ( Exception $exception ) {

				$response = $this->error_handler->getResponse( $request, $exception );

			}

			$this->error_handler->unregister();

			return $response;

		}

		public function run( Route $route ) {

			// whoops
			$this->error_handler->register();

			try {


				$middleware = array_merge( $this->applyGlobalMiddleware(), $route->middleware() );
				$middleware = $this->expandMiddleware( $middleware );
				$middleware = $this->uniqueMiddleware( $middleware );
				$middleware = $this->sortMiddleware( $middleware );

				$response = $this->route_pipeline
					->send($this->request)
					->through($middleware)
					->then(
						$route->run()
					);



			}
			catch ( Exception $exception ) {

				$foo = 'bar';

				$response = $this->error_handler->getResponse( $this->request, $exception );

			}

			$this->error_handler->unregister();

			return $response;

		}

		public function _handleRequest( RequestInterface $request, $arguments = [] ) {

			$arguments = Arr::wrap( $arguments );

			$view = $arguments[0] ?? null;

			$route = $this->router->hasMatchingRoute( $request );

			if ( $route === null ) {
				return null;
			}

			$route_arguments = $route->getArguments( $request );

			$request = $request->withAttribute( 'route', $route )
			                   ->withAttribute( 'arguments', $route_arguments );

			$middleware      = $route->getAttribute( 'middleware', [] );
			$handler         = $route->getAttribute( 'handler' );
			$route_arguments = array_merge( [ $request ], $route_arguments );

			$response = $this->_run( $request, $middleware, $handler, $route_arguments );

			if ( $response === null ) {

				return $view;

			}

			$this->container[ WPEMERGE_RESPONSE_KEY ] = $response;

			return $response;

		}

		public function handleRequest( RequestInterface $request, $arguments = [] ) {

			$arguments = Arr::wrap( $arguments );

			$view = $arguments[0] ?? null;

			$route = $this->router->hasMatchingRoute( $request );

			if ( $route === null ) {
				return null;
			}


			$response = $this->run( $route );

			if ( $response === null ) {

				return $view;

			}

			$this->container[ WPEMERGE_RESPONSE_KEY ] = $response;

			return $response;

		}

		/**
		 * Respond with the current response.
		 *
		 * @return void
		 */
		public function respond() {

			$response = $this->getResponse();

			if ( ! $response instanceof ResponseInterface ) {
				return;
			}

			$this->response_service->respond( $response );
		}

		/**
		 * Output the current view outside of the routing flow.
		 *
		 * @return void
		 */
		public function compose() {

			$view = $this->view_service->make( $this->template );

			echo $view->toString();

		}


		public function bootstrap() {

			// Web. Use 3100 so it's high enough and has uncommonly used numbers
			// before and after. For example, 1000 is too common and it would have 999 before it
			// which is too common as well.).
			add_filter( 'request', [ $this, 'filterRequest' ], 3100 );
			add_filter( 'template_include', [ $this, 'filterTemplateInclude' ], 3100 );

			// Ajax.
			add_action( 'admin_init', [ $this, 'registerAjaxAction' ] );

			// Admin.
			add_action( 'admin_init', [ $this, 'registerAdminAction' ] );

		}

		/**
		 * Filter the main query vars.
		 *
		 * @param  array  $query_vars
		 *
		 * @return array
		 * @throws \WPEmerge\Exceptions\ConfigurationException
		 */
		public function filterRequest( array $query_vars ) : array {

			$routes = $this->router->getRoutes();

			foreach ( $routes as $route ) {
				if ( ! $route instanceof HasQueryFilterInterface ) {
					continue;
				}

				if ( ! $route->isSatisfied( $this->request ) ) {
					continue;
				}

				$this->container[ WPEMERGE_APPLICATION_KEY ]
					->renderConfigExceptions( function () use ( $route, &$query_vars ) {

						$query_vars = $route->applyQueryFilter( $this->request, $query_vars );
					} );
				break;
			}

			return $query_vars;
		}

		/**
		 * Filter the main template file.
		 *
		 * @param  string  $template
		 *
		 * @return string
		 */
		public function filterTemplateInclude( string $template ) : string {

			global $wp_query;

			$this->template = $template;

			$response = $this->handleRequest( $this->request, [ $template ] );

			// A route has matched so we use its response.
			if ( $response instanceof ResponseInterface ) {

				if ( $response->getStatusCode() === 404 ) {

					$wp_query->set_404();
				}

				add_action( 'wpemerge.kernels.http_kernel.respond', [ $this, 'respond' ] );

				return WPEMERGE_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'view.php';
			}

			// No route has matched, but we still want to compose views.
			$composers = $this->view_service->getComposersForView( $template );

			if ( ! empty( $composers ) ) {

				add_action( 'wpemerge.kernels.http_kernel.respond', [ $this, 'compose' ] );

				return WPEMERGE_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'view.php';
			}

			return $template;
		}

		/**
		 * Register ajax action to hook into current one.
		 *
		 * @return void
		 */
		public function registerAjaxAction() {

			if ( ! wp_doing_ajax() ) {
				return;
			}

			$action = $this->request->body( 'action', $this->request->query( 'action' ) );
			$action = sanitize_text_field( $action );

			add_action( "wp_ajax_{$action}", [ $this, 'actionAjax' ] );
			add_action( "wp_ajax_nopriv_{$action}", [ $this, 'actionAjax' ] );
		}

		/**
		 * Act on ajax action.
		 *
		 * @return void
		 */
		public function actionAjax() {

			$response = $this->handleRequest( $this->request );

			if ( ! $response instanceof ResponseInterface ) {
				return;
			}

			$this->response_service->respond( $response );

			wp_die( '', '', [ 'response' => null ] );
		}

		/**
		 * Get page hook.
		 * Slightly modified version of code from wp-admin/admin.php.
		 *
		 * @return string
		 */
		private function getAdminPageHook() : string {

			global $pagenow, $typenow, $plugin_page;

			$page_hook = '';

			if ( isset( $plugin_page ) ) {
				$the_parent = $pagenow;

				if ( ! empty( $typenow ) ) {
					$the_parent = $pagenow . '?post_type=' . $typenow;
				}

				$page_hook = get_plugin_page_hook( $plugin_page, $the_parent );
			}

			return $page_hook;
		}

		/**
		 * Get admin page hook.
		 * Slightly modified version of code from wp-admin/admin.php.
		 *
		 * @param  string  $page_hook
		 *
		 * @return string
		 */
		private function getAdminHook( string $page_hook ) : string {

			global $pagenow, $plugin_page;

			if ( ! empty( $page_hook ) ) {
				return $page_hook;
			}

			if ( isset( $plugin_page ) ) {
				return $plugin_page;
			}

			if ( isset( $pagenow ) ) {
				return $pagenow;
			}

			return '';
		}

		/**
		 * Register admin action to hook into current one.
		 *
		 * @return void
		 */
		public function registerAdminAction() {

			$page_hook   = $this->getAdminPageHook();
			$hook_suffix = $this->getAdminHook( $page_hook );

			add_action( "load-{$hook_suffix}", [ $this, 'actionAdminLoad' ] );
			add_action( $hook_suffix, [ $this, 'actionAdmin' ] );

		}

		/**
		 * Act on admin action load.
		 *
		 * @return void
		 */
		public function actionAdminLoad() {

			$response = $this->handleRequest( $this->request );

			if ( ! $response instanceof ResponseInterface ) {
				return;
			}

			if ( ! headers_sent() ) {
				$this->response_service->sendHeaders( $response );
			}

			if ( $response instanceof RedirectResponse && $response->abort() ) {

				exit;

			}

		}

		/**
		 * Act on admin action.
		 *
		 * @return void
		 */
		public function actionAdmin() {

			$response = $this->getResponse();

			if ( ! $response instanceof ResponseInterface ) {
				return;
			}

			$this->response_service->sendBody( $response );

		}

	}
