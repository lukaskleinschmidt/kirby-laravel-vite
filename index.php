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

            $body = $response->send();

            $insert = function (string $body, string $tags, array $anchors, string $fallback): string {
                foreach ($anchors as $anchor) {
                    $result = preg_replace_callback($anchor, fn ($matches) =>
                        $matches[0] . $tags
                    , $body, 1, $count);

                    if ($count > 0) {
                        return $result;
                    }
                }

                return str_replace($fallback, $tags . $fallback, $body);
            };

            if ($style instanceof Vite) {
                // Kirby 5 bundles plugin styles in plugins/index.css. Kirby 6 links
                // each plugin's styles separately, so add ours after all of them.
                $body = $insert($body, (string) $style, [
                    '/<link.*plugins\/index\.css.*>/',
                ], '</head>');
            }

            if ($script instanceof Vite) {
                // Load scripts where Kirby loads a custom panel.js: after the
                // plugins/index.js bundle in Kirby 5 and after the plugin registry
                // in Kirby 6, both before the Panel's own index module.
                $body = $insert($body, (string) $script, [
                    '/<script.*plugins\/index\.js.*script>/',
                    '/<script[^>]*\/js\/plugins\.js[^>]*><\/script>/',
                ], '</body>');
            }

            return new class ($body) extends Response
            {
                public function send(): string
                {
                    return $this->body();
                }
            };
        }
    ],
]);
