<?php namespace Illuminate\Foundation\Providers;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\ExceptionHandler;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ExceptionHandler as KernelHandler;

class ExceptionServiceProvider extends ServiceProvider {

	/**
	 * Start the error handling facilities.
	 *
	 * @param  Illuminate\Foundation\Application  $app
	 * @return void
	 */
	public function startHandling(Application $app)
	{
		// By registering the error handler with a level of -1, we state that we want
		// all PHP errors converted to ErrorExceptions and thrown, which provides
		// a quite strict development environment, but prevents unseen errors.
		$app['kernel.error']->register(-1);

		$this->setExceptionHandler($app['exception.function']);
	}

	/**
	 * Register the service provider.
	 *
	 * @param  Illuminate\Foundation\Application  $app
	 * @return void
	 */
	public function register(Application $app)
	{
		$this->registerKernelHandlers($app);

		$app['exception'] = function()
		{
			return new ExceptionHandler;
		};

		$this->registerExceptionHandler($app);
	}

	/**
	 * Register the HttpKernel error and exception handlers.
	 *
	 * @param  Illuminate\Foundation\Application  $app
	 * @return void
	 */
	protected function registerKernelHandlers($app)
	{
		$app['kernel.error'] = function()
		{
			return new ErrorHandler;
		};

		$app['kernel.exception'] = function()
		{
			return new KernelHandler;
		};
	}

	/**
	 * Register the PHP exception handler function.
	 *
	 * @param  Illuminate\Foundation\Application  $app
	 * @return void
	 */
	protected function registerExceptionHandler($app)
	{
		$app['exception.function'] = function() use ($app)
		{
			return function($exception) use ($app)
			{
				$response = $app['exception']->handle($exception);

				// If one of the custom error handlers returned a response, we will send that
				// response back to the client after preparing it. This allows a specific
				// type of exceptions to handled by a Closure giving great flexibility.
				if ( ! is_null($response))
				{
					$response = $app->prepareResponse($response, $app['request']);

					$response->send();
				}
				else
				{
					$app['kernel.exception']->handle($exception);
				}
			};
		};
	}

	/**
	 * Set the given Closure as the exception handler.
	 *
	 * This function is mainly needed for mocking purposes.
	 *
	 * @param  Closure  $handler
	 * @return mixed
	 */
	protected function setExceptionHandler(Closure $handler)
	{
		return set_exception_handler($handler);
	}

}