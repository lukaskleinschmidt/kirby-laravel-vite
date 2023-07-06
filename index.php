<?php

namespace LukasKleinschmidt;

use Kirby\Cms\App;
use Kirby\Http\Response;
use Kirby\Http\Route;

@include_once __DIR__ . '/helpers.php';
@include_once __DIR__ . '/Vite.php';

App::plugin('lukaskleinschmidt/laravel-vite', [
    'options' => $options = [
        'buildDirectory'       => null,
        'hotFile'              => null,
        'integrity'            => null,
        'manifest'             => null,
        'nonce'                => null,
        'preloadTagAttributes' => [],
        'scriptTagAttributes'  => [],
        'styleTagAttributes'   => [],
    ],
    'hooks' => [
        'system.loadPlugins:after' => function () use ($options) {
            foreach ($options as $key => $defaultValue) {
                $value   = option('lukaskleinschmidt.kirby-laravel-vite.' . $key);
                $value ??= option('lukaskleinschmidt.laravel-vite.' . $key);
                $method  = 'use' . ucfirst($key);

                if (is_null($defaultValue) && is_callable($value)) {
                    $value = $value(kirby());
                }

                if ($value && method_exists($vite = Vite::instance(), $method)) {
                    $vite->{$method}($value);
                }
            }
        },
        'panel.route:after' => function (Route $route, Response $response) {
            $type = $route->attributes()['type'] ?? 'view';

            if ($type !== 'view') {
                return $response;
            }

            $style  = option('panel.css');
            $script = option('panel.js');

            $body = $response->body();

            if ($style instanceof Vite) {
                $body = preg_replace_callback('/<link.*plugins\/index\.css.*>/', fn ($matches) =>
                    $matches[0] . $style
                , $body);
            }

            if ($script instanceof Vite) {
                $body = preg_replace_callback('/<script.*plugins\/index\.js.*script>/', fn ($matches) =>
                    $matches[0] . $script
                , $body);
            }

            return new Response(
                $body,
                $response->type(),
                $response->code(),
                $response->headers(),
                $response->charset()
            );
        }
    ],
]);
