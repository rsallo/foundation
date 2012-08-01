<?php namespace Illuminate\Foundation;

use Closure;
use Illuminate\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Provider\ServiceProvider;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Application extends Container implements HttpKernelInterface {

	/**
	 * The application middlewares.
	 *
	 * @var array
	 */
	protected $middlewares = array();

	/**
	 * The pattern to middleware bindings.
	 *
	 * @var array
	 */
	protected $patternMiddlewares = array();

	/**
	 * The global middlewares for the application.
	 *
	 * @var array
	 */
	protected $globalMiddlewares = array();

	/**
	 * The current requests being executed.
	 *
	 * @var array
	 */
	protected $requestStack = array();

	/**
	 * Create a new Illuminate application instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this['router'] = new Router;

		$this['request'] = Request::createFromGlobals();
	}

	/**
	 * Register exception handling for the application.
	 *
	 * @return void
	 */
	public function startExceptionHandling()
	{
		ErrorHandler::register(-1);

		$me = $this;

		// We'll register a custom exception handler that will wrap the usage of the
		// HTTP Kernel component's exception handlers. This gives us a chance to
		// trigger any other events that may take care of things like logging.
		set_exception_handler(function($exception) use ($me)
		{
			$handler = new ExceptionHandler($me['debug']);

			$handler->handle($exception);
		});
	}

	/**
	 * Detect the application's current environment.
	 *
	 * @param  array   $environments
	 * @return string
	 */
	public function detectEnvironment(array $environments)
	{
		$base = $this['request']->getHost();

		foreach ($environments as $environment => $hosts)
		{
			// To determine the current environment, we'll simply iterate through
			// the possible environments and look for a host that matches our
			// host in the requests context, then return that environment.
			foreach ($hosts as $host)
			{
				if (str_is($host, $base))
				{
					return $this['env'] = $environment;
				}
			}
		}

		return $this['env'] = 'default';
	}

	/**
	 * Register a service provider with the application.
	 *
	 * @param  Illuminate\Foundation\Provider\ServiceProvider  $provider
	 * @param  array  $options
	 * @return void
	 */
	public function register(ServiceProvider $provider, array $options = array())
	{
		$provider->register($this);

		// Once we have registered the service, we will iterate through the options
		// and set each of them on the application so they will be available on
		// the actual loading of the service objects and for developer usage.
		foreach ($options as $key => $value)
		{
			$this[$key] = $value;
		}
	}

	/**
	 * Register a "before" application middleware.
	 *
	 * @param  Closure  $callback
	 * @return void
	 */
	public function before(Closure $callback)
	{
		$this->globalMiddlewares['before'][] = $callback;
	}

	/**
	 * Register an "after" application middleware.
	 *
	 * @param  Closure  $callback
	 * @return void
	 */
	public function after(Closure $callback)
	{
		$this->globalMiddlewares['after'][] = $callback;
	}

	/**
	 * Register a "finish" application middleware.
	 *
	 * @param  Closure  $callback
	 * @return void
	 */
	public function finish(Closure $callback)
	{
		$this->globalMiddlewares['finish'][] = $callback;
	}

	/**
	 * Get the evaluated contents of the given view.
	 *
	 * @param  string  $view
	 * @param  array   $parameters
	 * @return string
	 */
	public function show($view, array $parameters = array())
	{
		return $this['blade']->show($view, $parameters);
	}

	/**
	 * Return a new response from the application.
	 *
	 * @param  string  $content
	 * @param  int     $status
	 * @param  array   $headers
	 * @return Symfony\Component\HttpFoundation\Response
	 */
	public function respond($content = '', $status = 200, $headers = array())
	{
		return new Response($content, $status, $headers);
	}

	/**
	 * Generate a RedirectResponse to another URL.
	 *
	 * @param  string   $url
	 * @param  int      $status
	 * @return Illuminate\RedirectResponse
	 */
	public function redirect($url, $status = 302)
	{
		$redirect = new RedirectResponse($url, $status);

		if (isset($this['session']))
		{
			$redirect->setSession($this['session']);
		}

		return $redirect;
	}

	/**
	 * Create a redirect response to a named route.
	 *
	 * @param  string  $route
	 * @param  array   $parameters
	 * @param  int     $status
	 * @return Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function redirectToRoute($route, $parameters = array(), $status = 302)
	{
		//

		return $this->redirect($url, $status);
	}

	/**
	 * Register a new middleware with the application.
	 *
	 * @param  string   $name
	 * @param  Closure  $callback
	 * @return void
	 */
	public function addMiddleware($name, Closure $callback)
	{
		$this->middlewares[$name] = $callback;
	}

	/**
	 * Get a registered middleware callback.
	 *
	 * @param  string   $name
	 * @return Closure
	 */
	public function getMiddleware($name)
	{
		if (array_key_exists($name, $this->middlewares))
		{
			return $this->middlewares[$name];
		}
	}

	/**
	 * Tie a registered middleware to a URI pattern.
	 *
	 * @param  string  $pattern
	 * @param  string|array  $name
	 * @return void
	 */
	public function matchMiddleware($pattern, $names)
	{
		foreach ((array) $names as $name)
		{
			$this->patternMiddlewares[$pattern][] = $name;
		}
	}

	/**
	 * Handles the given request and delivers the response.
	 *
	 * @param  Illuminate\Foundation\Request  $request
	 * @return void
	 */
	public function run(Request $request = null)
	{
		// If no requests are given, we'll simply create one from PHP's global
		// variables and send it into the handle method, which will create
		// a Response that we can now send back to the client's browser.
		if (is_null($request))
		{
			$request = $this['request'];
		}

		$this->prepareRequest($request);

		// First we will call the "before" global middlware, which we'll give
		// a chance to override the normal request process when a response
		// is returned by the middlewares. Otherwise we call the routes.
		$response =  $this->callGlobalMiddleware('before');

		if (is_null($response))
		{
			$response = $this->getResponse($request);
		}
		else
		{
			$response = $this->prepareResponse($response);
		}

		$this->callAfterMiddleware($response);

		// Once we have executed the route and called the "after" middleware
		// we are ready to return the Response back to the consumer so it
		// may be sent back to the client and displayed by the browsers.
		$response->send();

		$this->callFinishMiddleware($response);
	}

	/**
	 * Handle the given request and get the response.
	 *
	 * @param  Illuminate\Foundation\Request  $request
	 * @return Symfony\Component\HttpFoundation\Response
	 */
	public function getResponse(Request $request)
	{
		$route = $this['router']->match($request);

		// Once we have the route and before middlewares, we'll iterate through them
		// and call each one. If one of them returns a response, we will let that
		// value overrides the rest of the request process and return that out.
		$before = $this->getBeforeMiddlewares($route, $request);

		$response = null;

		foreach ($before as $middleware)
		{
			$response = $this->callMiddleware($middleware);
		}

		// If none of the before middlewares returned a response, we'll just execute
		// the route that matched the request, then call the after filters for it
		// and return the responses back out so it will get sent to the clients.
		if (is_null($response))
		{
			$response = $route->run($request);
		}

		foreach ($route->getAfterMiddlewares() as $middleware)
		{
			$this->callMiddleware($middleware, array($response));
		}

		// Once all of the after middlewares are called we should be able to return
		// the completed response object back to the consumer so it may be given
		// to the client as a response. The Responses should be in final form.
		return $this->prepareResponse($response);
	}

	/**
	 * Handle the given request and get the response.
	 *
	 * @implements HttpKernelInterface::handle
	 *
	 * @param  Illuminate\Foundation\Request  $request
	 * @param  int   $type
	 * @param  bool  $catch
	 * @return Symfony\Component\HttpFoundation\Response
	 */
	public function handle(SymfonyRequest $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
	{
		return $this->getResponse($request);
	}

	/**
	 * Get the before middlewares for a request and route.
	 *
	 * @param  Illuminate\Routing\Route  $route
	 * @param  Illuminate\Foundation\Request  $request
	 * @return array
	 */
	protected function getBeforeMiddlewares(Route $route, Request $request)
	{
		$before = $route->getBeforeMiddlewares();

		return array_merge($before, $this->findPatternMiddlewares($request));
	}

	/**
	 * Find the patterned middlewares matching a request.
	 *
	 * @param  Illuminate\Foundation\Request  $request
	 * @return array
	 */
	protected function findPatternMiddlewares(Request $request)
	{
		$middlewares = array();

		foreach ($this->patternMiddlewares as $pattern => $values)
		{
			// To find the pattern middlewares for a request, we just need to check the
			// registered patterns against the path info for the current request to
			// the application, and if it matches we'll merge in the middlewares.
			if (str_is('/'.$pattern, $request->getPathInfo()))
			{
				$middlewares = array_merge($middlewares, $values);
			}
		}

		return $middlewares;
	}

	/**
	 * Prepare the request by injecting any services.
	 *
	 * @param  Illuminate\Foundation\Request  $request
	 * @return Illuminate\Foundation\Request
	 */
	public function prepareRequest(Request $request)
	{
		if (isset($this['session']))
		{
			$request->setSessionStore($this['session']);
		}

		return $request;
	}

	/**
	 * Prepare the given value as a Response object.
	 *
	 * @param  mixed  $value
	 * @return Symfony\Component\HttpFoundation\Response
	 */
	public function prepareResponse($value)
	{
		if ( ! $value instanceof Response) $value = new Response($value);

		return $value;
	}

	/**
	 * Call the "before" global middlware.
	 *
	 * @return mixed
	 */
	public function callAfterMiddleware(Response $response)
	{
		return $this->callGlobalMiddleware('after', array($response));
	}

	/**
	 * Call the "finish" global middlware.
	 *
	 * @return mixed
	 */
	public function callFinishMiddleware(Response $response)
	{
		return $this->callGlobalMiddleware('finish', array($response));
	}

	/**
	 * Call a given middleware with the parameters.
	 *
	 * @param  string  $name
	 * @param  array   $parameters
	 * @return mixed
	 */
	protected function callMiddleware($name, array $parameters = array())
	{
		array_unshift($parameters, $this['request']);

		if (isset($this->middlewares[$name]))
		{
			return call_user_func_array($this->middlewares[$name], $parameters);
		}
	}

	/**
	 * Call a given global middleware with the parameters.
	 *
	 * @param  string  $name
	 * @param  array   $parameters
	 * @return mixed
	 */
	protected function callGlobalMiddleware($name, array $parameters = array())
	{
		array_unshift($parameters, $this['request']);

		if (isset($this->globalMiddlewares[$name]))
		{
			foreach ($this->globalMiddlewares[$name] as $middleware)
			{
				return call_user_func_array($middleware, $parameters);
			}
		}
	}

	/**
	 * Dynamically access application services.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this[$key];
	}

	/**
	 * Dynamically set application services.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function __set($key, $value)
	{
		$this[$key] = $value;
	}

	/**
	 * Dynamically handle application method calls.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		if (strpos($method, 'redirectTo') === 0)
		{
			array_unshift($parameters, strtolower(substr($method, 10)));

			return call_user_func_array(array($this, 'redirectToRoute'), $parameters);
		}

		throw new \BadMethodCallException("Call to undefined method {$method}.");
	}

}