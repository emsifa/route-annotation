<?php namespace Emsifa\RouteAnnotation;

use Illuminate\Routing\Router;
use Illuminate\Routing\Route;
use ReflectionClass;
use ReflectionMethod;

class RouteAnnotator {

	/**
	 * An application router
	 *
	 * @var Illuminate\Routing\Router
	 */
	protected $router;

	/**
	 * List of annotated controllers
	 *
	 * @var array 
	 */
	protected $annotated_controllers = [];

	public function __construct(Router $router)
	{
		$this->router = $router;
	}

	/**
	 * Get Annotated Controllers
	 *
	 * @return array controller classes
	 */
	public function getAnnotatedControllers()
	{
		return array_keys($this->annotated_controllers);
	}

	/**
	 * Check if controller was annotated
	 */
	public function hasAnnotate($controller)
	{
		return array_key_exists($controller, $this->annotated_controllers);
	}

	/**
	 * Get Annotated Routes from Annotated Controllers
	 *
	 * @return array Illuminate\Routing\Route
	 */
	public function getAnnotatedRoutes($controllers = null)
	{
		$annotated_routes = [];

		if(empty($controller)) {
			$controllers = $this->getAnnotatedControllers();	
		} else {
			$controllers = (array) $controllers;
		}

		foreach($controllers as $controller) {
			$routes = $this->annotated_controllers[$controller];
			$annotated_routes = array_merge($annotated_routes, $routes);
		}

		return $annotated_routes;
	}

	/**
	 * Check if route was registered by annotator
	 * 
	 * @param Illuminate\Routing\Route
	 * @return boolean
	 */
	public function hasRoute(Route $route)
	{
		$routes = $this->getAnnotatedRoutes();
		return in_array($route, $routes);
	}

	/**
	 * Register Routes via Method Annotations in Controller Class
	 *
	 * @param string $controller
	 * @return void
	 */
	public function annotateController($controller)
	{
		$controller_class = $this->resolveController($controller);
		$controller_reflection = new ReflectionClass($controller_class);

		$methods = $controller_reflection->getMethods();

		$routes = [];

		foreach($methods as $method) {
			$route_data = $this->parseMethodAnnotation($method);

			if($route_data) {
				$routes[] = $this->makeAndRegisterRoute($controller, $method, $route_data);
			}
		}

		$this->annotated_controllers[$controller_class] = $routes;
	}

	/**
	 * Parse Annotation from Method in Controller
	 *
	 * @param ReflectionMethod $method
	 * @return array route data
	 */
	public function parseMethodAnnotation(ReflectionMethod $method)
	{
		$doc = $method->getDocComment();

		$route_data = array(
			'methods' => null,
			'uri' => null,
			'name' => null,
			'conditions' => array(),
			'middleware' => array()
		);

		// get method description
		preg_match_all('/\* (?<desc>[a-zA-Z0-9][^\n]+)/', $doc, $match);
		$description = implode("\n", $match['desc']);

		preg_match_all('/\* \@(?<name>[a-zA-Z]+)([ \t]+)(?<value>[^\n]+)/', $doc, $match);
	
		$annotations = array();
		
		foreach($match[0] as $i => $m) {
			$name = $match['name'][$i];
			$value = $match['value'][$i];
			if( ! array_key_exists($name, $annotations)) {
				$annotations[$name] = array();
			}
			
			$annotations[$name][] = $value;
		}

		if(!array_key_exists('route', $annotations)) {
			return NULL;
		}

		$route_data = array_merge($route_data, $annotations);

		list($methods, $uri) = explode(' ', $annotations['route'][0], 2);

		$route_data['methods'] = explode('|', $methods);
		$route_data['uri'] = $uri;
		$route_data['conditions'] = array();
		$route_data['description'] = $description;

		if(isset($annotations['name'])) {
			$route_data['name'] = $annotations['name'][0];
		}

		if(isset($annotations['param'])) {
			foreach($annotations['param'] as $annotation) {
				preg_match('/\$(?<param>[^ ]+)([ \t]+)?(?<regex>\/.*\/)?/', $annotation, $match);
				if(isset($match['regex'])) {
					$route_data['conditions'][$match['param']] = preg_replace('/(^\/|\/$)/', '', $match['regex']);
				}
			}
		}
		
		return $route_data;
	}

	/**
	 * Make route from route data and register it into router
	 *
	 * @param array $route_data
	 * @param string $controller
	 * @return Illuminate\Routing\Route
	 */
	protected function makeAndRegisterRoute($controller, ReflectionMethod $method, array $route_data)
	{
		$uses = $controller.'@'.$method->getName();
		$methods = $route_data['methods'];
		$uri = $route_data['uri'];
		$action = [
			'uses' => $uses
		];

		if(!empty($route_data['middleware'])) $action['middleware'] = $route_data['middleware'];
		if(!empty($route_data['name'])) $action['as'] = $route_data['name'];


		$route = $this->router->match($methods, $uri,  $action);

		foreach($route_data['conditions'] as $param => $regex) {
			$route->where($param, $regex);
		}

		// Laravel Route doesn't provide method for get route conditions, 
		// so we need to declare new attribute for generating(transforming) purpose
		$route->conditions = $route_data['conditions'];

		$route->description = $route_data['description'];

		return $route;
	}

	/**
	 * Resolve controller class name
	 *
	 * @param string $controller
	 * @return string resolved class name
	 */
	protected function resolveController($controller)
	{
		return "App\\Http\\Controllers\\".ltrim($controller, "\\");
	}

}
