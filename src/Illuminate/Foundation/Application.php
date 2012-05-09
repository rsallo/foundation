<?php namespace Illuminate\Foundation;

use Closure, Silex\Controller, Silex\Provider\UrlGeneratorServiceProvider;

class Application extends \Silex\Application {

	/**
	 * The currently grouped route attributes.
	 *
	 * @var array
	 */
	public $grouped = array();

	/**
	 * A keyed colletion of model binders.
	 *
	 * @var array
	 */
	public $binders = array();

	/**
	 * A keyed collection of available middlewares.
	 *
	 * @var array
	 */
	public $middlewares = array();

	/**
	 * A keyed collection of wildcard assertion short-cuts.
	 *
	 * @var array
	 */
	public $patterns = array(
		'#' => '\d+',
	);

	/**
	 * Create a new Illuminate application.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();

		$this->register(new UrlGeneratorServiceProvider);
	}

	/**
	 * Register the root route for the application.
	 *
	 * @param  mixed             $to
	 * @return Silex\Controller
	 */
	public function root($to)
	{
		return $this->get('/', $to);
	}

	/**
	 * Maps a request URI to a Closure.
	 *
	 * @param  string            $pattern
	 * @param  mixed             $to
	 * @return Silex\Controller
	 */
	public function match($pattern, $to)
	{
		$controller = $this->registerRoute(__FUNCTION__, $pattern, $to);

		if (is_array($to) and isset($to['on']))
		{
			$controller->method(strtoupper($to['on']));
		}

		return $controller;
	}

	/**
	 * Maps a GET request URI to a Closure.
	 *
	 * @param  string            $pattern
	 * @param  mixed             $to
	 * @return Silex\Controller
	 */
	public function get($pattern, $to)
	{
		return $this->registerRoute(__FUNCTION__, $pattern, $to);
	}

	/**
	 * Maps a GET request URI to a Closure.
	 *
	 * @param  string            $pattern
	 * @param  mixed             $to
	 * @return Silex\Controller
	 */
	public function post($pattern, $to)
	{
		return $this->registerRoute(__FUNCTION__, $pattern, $to);
	}

	/**
	 * Maps a GET request URI to a Closure.
	 *
	 * @param  string            $pattern
	 * @param  mixed             $to
	 * @return Silex\Controller
	 */
	public function put($pattern, $to)
	{
		return $this->registerRoute(__FUNCTION__, $pattern, $to);
	}

	/**
	 * Maps a GET request URI to a Closure.
	 *
	 * @param  string            $pattern
	 * @param  mixed             $to
	 * @return Silex\Controller
	 */
	public function delete($pattern, $to)
	{
		return $this->registerRoute(__FUNCTION__, $pattern, $to);
	}

	/**
	 * Register a route group with shared attributes.
	 *
	 * @param  array    $attributes
	 * @param  Closure  $callback
	 * @return void
	 */
	public function group(array $attributes, Closure $callback)
	{
		$this->grouped[] = $attributes;

		$callback($this);

		array_pop($this->grouped);
	}

	/**
	 * Register a route with the application.
	 *
	 * @param  string            $method
	 * @param  string            $pattern
	 * @param  mixed             $to
	 * @return Silex\Controller
	 */
	protected function registerRoute($method, $pattern, $to)
	{
		list($pattern, $asserts) = $this->formatPattern($pattern);

		// If the given $to is just a Closure, we'll just go ahead and convert it
		// to an array so we can treat all registrations the same. This will
		// just make the logic more consistent and simpler on our side.
		if ($to instanceof Closure)
		{
			$to = array($to);
		}

		// Now that it is arrayed, it is being used to short-cut into the various
		// methods on the Silex\Controller. This allows for easy setting of
		// things like the route name and middlewares in terse syntax.
		if (count($this->grouped) > 0)
		{
			$to = array_merge(end($this->grouped), $to);
		}

		$callable = __($to)->find(function($value)
		{
			return $value instanceof Closure;
		});

		$controller = parent::$method($pattern, $callable);

		// The "https" flag specifies that the route should only respond to
		// secure HTTPS requests. HTTP requests are sent to the secure
		// version of the route when attempting to access the route.
		if (isset($to['https']))
		{
			$scheme = $to['https'] ? 'requireHttps' : 'requireHttp';

			$controller->$scheme();
		}

		// The "as" key on the array specifes the name of the routes, so we
		// will pass it along to the "bind" method on the controller to
		// set its name so it can be easily resolved by that name.
		if (isset($to['as']))
		{
			$controller->bind($to['as']);
		}

		// The "before" key on the array specifies the middlewares that are
		// attached to the route, so we will parse them and pass them to
		// the "middlewares" method on the controller to set them all.
		if (isset($to['before']))
		{
			foreach (explode('|', $to['before']) as $m)
			{
				$controller->middleware($this->middlewares[$m]);
			}
		}

		// Once the controller short-cuts have been registered we'll finish
		// up by registering the asserts about the parameters as well as
		// registering any model converters/binders for the controller.
		foreach ($asserts as $key => $pattern)
		{
			$controller->assert($key, $pattern);
		}

		$this->registerBinders($controller);

		return $controller;
	}

	/**
	 * Format the URI pattern for a route.
	 *
	 * @param  string  $pattern
	 * @return array
	 */
	protected function formatPattern($pattern)
	{
		$asserts = array();

		$pattern = str_replace('{id}', '{#id}', $pattern);

		preg_match_all('/\{(#)(.+)\}/', $pattern, $matches);

		// Once we have an array of all of the matches, we can simple trim off
		// the short-cut operator and add an assert with the proper regular
		// expression that performs the functionality of the short-cut.
		foreach ($matches[0] as $key => $match)
		{
			$pattern = str_replace($match, '{'.$matches[2][$key].'}', $pattern);

			$asserts[$matches[2][$key]] = $this->patterns[$matches[1][$key]];
		}

		return array($pattern, $asserts);		
	}

	/**
	 * Register the model binders as converters on the controller.
	 *
	 * @param  Silex\Controller  $controller
	 * @return void
	 */
	protected function registerBinders($controller)
	{
		foreach ($this->binders as $wildcard => $binder)
		{
			if (strstr($controller->getRoute()->getPattern(), $wildcard))
			{
				$binder = $this->binders[$wildcard];

				// If the binder is simply a Closure, we can register like any other
				// Silex converter as it simply follows the default expectations
				// of Silex and we don't need to do anything special for it.
				if ($binder instanceof Closure)
				{
					$controller->convert($wildcard, $binder);
				}

				// If the binder isn't a Closure, we'll assume it is a custom model
				// binder and register a special binder that will resolve the
				// IModelBinder for the type to convert the given value.
				else
				{
					$this->customBinder($controller, $wildcard, $binder);
				}
			}
		}
	}

	/**
	 * Build a custom wildcard converter for the given controller.
	 *
	 * @param  Silex\Controller  $controller
	 * @param  string            $wildcard
	 * @param  string            $binder
	 * @return void
	 */
	protected function customBinder($controller, $wildcard, $binder)
	{
		$app = $this;

		$controller->convert($wildcard, function($id, $request) use ($app, $binder)
		{
			$resolver = $app['ioc']->resolve($binder);

			// The IModelBinder interface defines a simple contract that specifies
			// the class can retrieve a model instance by the given ID. All of
			// the binders must implement this interface or we'll bail out.
			if ( ! $resolver instanceof IModelBinder)
			{
				throw new \RuntimeException("Model binders must implement IModelBinder.");
			}

			return $resolver->resolveBinding($id, $request);
		});
	}

	/**
	 * Register a model binder with the application.
	 *
	 * @param  string                  $wildcard
	 * @param  mixed                   $binder
	 * @return Illuminate\Application
	 */
	public function modelBinder($wildcard, $binder)
	{
		$this->binders[$wildcard] = $binder;
	}

	/**
	 * Register an array of model binders with the application.
	 *
	 * @param  array  $binders
	 * @return void
	 */
	public function modelBinders(array $binders)
	{
		foreach ($binders as $wildcard => $binder)
		{
			$this->modelBinder($wildcard, $binder);
		}
	}

	/**
	 * Register a middleware with the application.
	 *
	 * @param  string   $name
	 * @param  Closure  $middleware
	 * @return void
	 */
	public function middleware($name, Closure $middleware)
	{
		$this->middlewares[$name] = $middleware;
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

}