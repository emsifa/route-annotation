<?php namespace Emsifa\RouteAnnotation\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Routing\Route;
use Emsifa\RouteAnnotation\RouteAnnotator;

class StandarizeRouteCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'route:standarize';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Transform route annotations into Laravel standart routing.';

	/**
	 * Route Annotator
	 *
	 * @var Emsifa\Lararoute\RouteAnnotator
	 */
	protected $annotator;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct(Application $app, Router $router, RouteAnnotator $annotator)
	{
		parent::__construct();
		$this->app = $app;
		$this->annotator = $annotator;
		$this->router = $router;
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$route_file = $this->option('route-file') ?: app_path('Http/routes.php');

		if(! file_exists($route_file)) {
			throw new \Exception("Route file '{$route_file}' not found");
		}

		$controllers = $this->argument('controller') ?: $this->annotator->getAnnotatedControllers();

		if(is_string($controllers) AND !$this->annotator->hasAnnotate($controllers)) {
			throw new \Exception("Controller {$controllers} is not annotated", 1);
		}

		$annotated_routes = $this->annotator->getAnnotatedRoutes($controllers);
		$annotated_route_ids = $this->getRouteIdentities($annotated_routes);

		$app_routes = $this->router->getRoutes()->getRoutes();
		$app_route_ids = $this->getRouteIdentities($app_routes);

		$standart_routes_ids = array_diff($app_route_ids, $annotated_route_ids);

		foreach($annotated_routes as $route) {
			$route_id = $this->getRouteIdentity($route);
			if(in_array($route_id, $standart_routes_ids)) continue;

			$this->generateStandartRoute($route_file, $route);
			$this->info("+ Route ".implode('|', $route->getMethods())." ".$route->getUri());
		}
	}

	/**
	 * Get route identities
	 *
	 * @param array $routes (Illuminate\Routing\Route)
	 * @return array string route identity
	 */
	protected function getRouteIdentities(array $routes)
	{
		$route_ids = [];
		foreach($routes as $route) {
			$route_ids[] = $this->getRouteIdentity($route);
		}

		return $route_ids;
	}

	/**
	 * Transform route into string route identity
	 *
	 * @param Illuminate\Routing\Route $route
	 * @return string formatted '<route_methods> <route_regex>'
	 */
	protected function getRouteIdentity(Route $route)
	{
		$route->bind($this->app['request']);

		$regex = $route->getCompiled()->getRegex();
		$methods = implode('|', $route->getMethods());

		return $methods.' '.$regex;
	}

	/**
	 * Generate Route into standart routing in route file
	 *
	 * @param string $route_file
	 * @param Illuminate\Routing\Route $route
	 * @return void
	 */
	protected function generateStandartRoute($route_file, Route $route)
	{
		$route_methods = $route->getMethods();
		$method = '';
		$uri = $route->getUri();
		$extra_param = ''; // first param for Route::match
		switch($route_methods) {
			case ['GET','HEAD']: 
			case ['GET']:
				$method = 'get';
				break;
			case ['POST']:
			case ['PUT']:
			case ['PATCH']:
			case ['DELETE']:
				$method = strtolower($route_methods[0]);
				break;
			default: 
				$method = 'match';
				$arr_methods = array_map(function($val) {
					return "'".strtolower($val)."'";
				}, $route_methods);
				$extra_param = '['.implode(', ', $arr_methods).']';
		}

		$action = $route->getAction();
		$action_data = [];
		$uses = str_replace($action['namespace']."\\", "", $action['uses']);

		$action_data['uses'] = "'uses' => '{$uses}'";
		
		if(!empty($action['as'])) {
			$action_data['as'] = "'as' => '".$action['as']."'";
		}

		if(!empty($action['middleware'])) {
			$action_data['middleware'] = "'middleware' => '".implode('|', $action['middleware'])."'";
		}

		if(!empty($action['prefix'])) {
			$action_data['prefix'] = "'prefix' => '".$action['prefix']."'";
		}

		// if only action uses in action_data, just use string as action
		if(1 == count($action_data) and array_key_exists('uses', $action_data)) {
			$code_action_params = "'{$uses}'";
		} else {
			$code_action_params = "[\n\t".implode(",\n\t", $action_data)."\n]";
		}

		$route_params = [];
		if(!empty($extra_param)) {
			$route_params[] = $extra_param;
		}

		$route_params[] = "'{$uri}'";
		$route_params[] = $code_action_params;

		$route_code = "\nRoute::{$method}(".implode(', ', $route_params).")";

		$code_conditions = [];
		foreach($route->conditions as $param => $regex) {
			$code_conditions[] = "->where('{$param}', '{$regex}')";
		}

		$route_code .= implode("\n", $code_conditions).";";

		if(false == $this->option("no-comment")) {
			$code_comment = "\n\n/*"
			."\n | -----------------------------------------------------------"
			."\n | Route ".(isset($action['as'])? "'".$action['as']."'" : "")
			."\n | -----------------------------------------------------------"
			.( !empty($route->description)? "\n | ".str_replace("\n", "\n | ", $route->description)."\n | " : "")
			."\n | generated at: ".date('Y-m-d H:i:s')
			."\n |"
			."\n */";

			$route_code = $code_comment.$route_code;
		} else {
			$route_code = "\n".$route_code;
		}

		file_put_contents($route_file, file_get_contents($route_file).$route_code);		
	}
	
	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
			['controller', InputArgument::OPTIONAL, 'Specify controller name, default is all annotated controllers.'],
		];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['route-file', 'f', InputOption::VALUE_OPTIONAL, 'route file destination', null],
			['no-comment', null, InputOption::VALUE_NONE, 'disable generating comment above routing', null],
			['keep-annotations', null, InputOption::VALUE_NONE, 'keep Route::annotate[s] in routes.php', null],
		];
	}

}
