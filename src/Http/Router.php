<?php
// src/Http/Router.php

final class Router {
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void 
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        $paramNames = [];
        $regex = preg_replace_callback('/\:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($m) use (&$paramNames){
            $paramNames[] = $m[1];
            return '([^\/]+)';
        }, $path);

        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => $regex,
            'params' => $paramNames,
            'handler' => $handler
        ];
    } 

    public function dispatch(string $method, string $path) : void 
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        foreach($this->routes as $route){
            if($route['method'] !== $method) continue;

            if(preg_match($route['regex'], $path, $matches)){
                array_shift($matches);
                $params = [];
                foreach($route['params'] as $i => $name){
                    $params[$name] = $matches[$i] ?? null;
                }
                call_user_func($route['handler'], $params);
                return;
            }
        }
        http_response_code(404);
        echo json_encode(["error" => "Not found", "method" => $method, "path" => $path]);
    }

    private function normalizePath(string $path): string 
    {
        $path = parse_url($path, PHP_URL_PATH);
        $path = rtrim($path, "/");
        return $path === "" ? "/" : $path;
    }
}