<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * A small regex router: literal segments plus {name} placeholders, with a list
 * of middleware names per route.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,params:list<string>,handler:array{0:class-string,1:string},middleware:list<string>,defaults:array<string,string>}> */
    private array $routes = [];

    /** @var array<string, class-string<Middleware>> */
    private array $middlewareMap = [];

    /** @var array<string, string> */
    private array $groupDefaults = [];

    /** @var list<string> */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    /**
     * @param array<string, class-string<Middleware>> $map
     */
    public function registerMiddleware(array $map): void
    {
        $this->middlewareMap = $map + $this->middlewareMap;
    }

    /**
     * Routes declared inside the callback inherit the prefix, middleware and
     * defaults given here.
     *
     * @param array{prefix?:string,middleware?:list<string>,defaults?:array<string,string>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix     = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $previousDefaults   = $this->groupDefaults;

        $this->groupPrefix     = $previousPrefix . ($attributes['prefix'] ?? '');
        $this->groupMiddleware = [...$previousMiddleware, ...($attributes['middleware'] ?? [])];
        $this->groupDefaults   = [...$previousDefaults, ...($attributes['defaults'] ?? [])];

        $callback($this);

        $this->groupPrefix     = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupDefaults   = $previousDefaults;
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param list<string>                   $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param list<string>                   $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param list<string>                   $middleware
     */
    public function add(string $method, string $path, array $handler, array $middleware = []): void
    {
        $path = $this->groupPrefix . $path;
        $path = '/' . trim($path, '/');

        $params = [];
        $regex  = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '([^/]+)';
            },
            $path,
        );

        $this->routes[] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => [...$this->groupMiddleware, ...$middleware],
            'defaults'   => $this->groupDefaults,
        ];
    }

    public function dispatch(Request $request): void
    {
        $path          = $request->path();
        $method        = $request->method();
        $pathMatched   = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            array_shift($matches);

            /** @var array<string, string> $params */
            $params = $route['defaults'] + array_combine($route['params'], $matches);

            foreach ($route['middleware'] as $definition) {
                [$name, $argument] = array_pad(explode(':', $definition, 2), 2, null);

                $class = $this->middlewareMap[$name] ?? null;

                if ($class === null) {
                    throw new \RuntimeException(sprintf('Middleware [%s] is not registered.', (string) $name));
                }

                (new $class())->handle($request, $params, $argument);
            }

            [$controller, $action] = $route['handler'];

            (new $controller())->{$action}($request, $params);

            return;
        }

        if ($pathMatched) {
            throw new HttpException(405, 'That action is not allowed on this URL.');
        }

        throw HttpException::notFound();
    }
}
