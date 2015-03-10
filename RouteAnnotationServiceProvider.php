<?php namespace Emsifa\RouteAnnotation;

use Illuminate\Support\ServiceProvider;
use Emsifa\RouteAnnotation\Commands\StandarizeRouteCommand;

class RouteAnnotationServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		
	}

	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$app = $this->app;

		// register singleton RouteAnnotator
		$app->singleton('route_annotation.route_annotator', function($app) {
			return new RouteAnnotator($app['router']);
		});

		// register singleton StandarizeRouteCommand
		$app->singleton('route_annotation.commands.transform_annotation', function($app) {
			return new StandarizeRouteCommand($app, $app['router'], $app['route_annotation.route_annotator']);
		});

		// register command StandarizeRouteCommand
		$this->commands('route_annotation.commands.transform_annotation');

		// register macro Route::annotate
		$app['router']->macro('annotate', function($controller) use ($app) {
			$app['route_annotation.route_annotator']->annotateController($controller);
		});
	}

}
