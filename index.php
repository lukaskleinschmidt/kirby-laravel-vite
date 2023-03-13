<?php

use Kirby\Cms\App;

@include_once __DIR__ . '/helpers.php';
@include_once __DIR__ . '/Vite.php';

App::plugin('lukaskleinschmidt/kirby-laravel-vite', [
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
                $value  = option('lukaskleinschmidt.kirby-laravel-vite.' . $key);
                $method = 'use' . ucfirst($key);

                if (is_null($defaultValue) && is_callable($value)) {
                    $value = $value(kirby());
                }

                if ($value && method_exists($vite ??= vite(), $method)) {
                    $vite->{$method}($value);
                }
            }
        },
    ],
]);
