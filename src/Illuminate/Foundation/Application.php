<?php namespace Illuminate\Foundation;

use Closure;
use ArrayAccess;
use Illuminate\Container\Container;
use Silex\Provider\UrlGeneratorServiceProvider;

class Application extends \Silex\Application implements ArrayAccess {

	/**
	 * The Illuminate container instance.
	 *
	 * @var Illuminate\Container
	 */
	public $container;

	/**
	 * Create a new Illuminate application.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->container = new Container;

		parent::__construct();

		$app = $this;

		$this->register(new UrlGeneratorServiceProvider);

		$this['controllers'] = $this->share(function() use ($app)
		{
			return new ControllerCollection($app->container);
		});
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
		$controller = parent::match($pattern, $to);

		if (is_array($to) and isset($to['on']))
		{
			$controller->method(strtoupper($to['on']));
		}

		return $controller;
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
		return $this['controllers']->group($this, $attributes, $callback);
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
		return $this['controllers']->modelBinder($wildcard, $binder);
	}

	/**
	 * Register an array of model binders with the application.
	 *
	 * @param  array  $binders
	 * @return void
	 */
	public function modelBinders(array $binders)
	{
		return $this['controllers']->modelBinders($binders);
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
		return $this['controllers']->middleware($name, $middleware);
	}

	/**
	 * Determine if a value exists by offset.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return isset($this->container[$key]);
	}

	/**
	 * Get a value by offset.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		return $this->container[$key];
	}

	/**
	 * Set a value by offset.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->container[$key] = $value;
	}

	/**
	 * Unset a value by offset.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function offsetUnset($key)
	{
		unset($this->container[$key]);
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