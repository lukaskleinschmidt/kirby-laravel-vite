# Kirby Laravel Vite
After installation, you can use the `vite()` helper to include Vite assets in your Kirby project.
This plugin works best with the [laravel-vite-plugin](https://github.com/laravel/vite-plugin).

```php
<!doctype html>
<head>
  <?= vite(['assets/css/app.css', 'assets/js/app.js']) ?>
</head>
```

> **Note**  
> Some features are not properly documented yet. Feel free to skim through the [source code](https://github.com/lukaskleinschmidt/kirby-laravel-vite/blob/main/Vite.php) if you think something is missing.

## Installation

### Composer
```
composer require lukaskleinschmidt/kirby-laravel-vite
```

### Git Submodule
```
git submodule add https://github.com/lukaskleinschmidt/kirby-laravel-vite.git site/plugins/laravel-vite
```

### Download
Download and copy this repository to `/site/plugins/laravel-vite`.

## Installing The Laravel Vite Plugin
Documentation for the Laravel Vite plugin can be found on the [Laravel website](https://laravel.com/docs/vite).

```bash
npm install --save-dev laravel-vite-plugin
```

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel(['assets/css/app.css', 'assets/js/app.js']),
  ],
});
```

## Refreshing On Save
When your application is built using traditional server-side rendering, Vite can improve your development workflow by automatically refreshing the browser when you make changes to template or snippet files in your application. To get started, you can simply specify the refresh option.

```js
export default defineConfig({
  laravel({
    input: [
      'assets/css/app.css',
      'assets/js/app.js',
    ],
    refresh: [
      'site/templates/**',
      'site/snippets/**',
    ],
  }),
});
```

## Autoloading Template Specific Assets
If you used the `@auto` option for your assets, you can do the same with [optional assets](#optional-assets).

## Optional Assets
When you use the `Kirby Query Language` or prepend an `@` to an asset, those assets are treated as optional.
Meaning the plugin will only include assets that actually exist at the given source path.

```php
<!doctype html>
<head>
  <?= vite([
    // Using the Kirby Query Language
    'assets/css/templates/{{ page.template }}.css',
    'assets/js/templates/{{ page.template }}.js',

    // Equivalent to this
    '@assets/css/templates/' . $page->template() . '.css',
    '@assets/js/templates/' . $page->template() . '.js',
  ]) ?>
</head>
```

> **Note**  
> Remember to include the optional assets in vite as well so that they are actually available once bundled.
> Assets not included in vite **will work in development** mode but **not when bundled**.

```js
export default defineConfig({
  laravel([
    'assets/css/app.css',
    'assets/css/templates/home.css',
    'assets/js/app.js',
    'assets/js/templates/home.js',
  ]),
});
```

## Custom Panel Scripts And Styles
> **Note** Available since [1.1.0](https://github.com/lukaskleinschmidt/kirby-laravel-vite/releases/tag/1.1.0)

You can use vite for your [`panel.css`](https://getkirby.com/docs/reference/system/options/panel#custom-panel-css) or [`panel.js`](https://getkirby.com/docs/reference/system/options/panel#custom-panel-js) too. Since the plugin requires the Kirby instance to work you need to define the assets in the [ready](https://getkirby.com/docs/reference/system/options/ready) callback to be able to use the `vite()` helper.

```php
return [
    'ready' => fn () => [
        'panel' => [
            'css' => vite('assets/css/panel.css'),
            'js'  => vite([
                'assets/js/feature.js',
                'assets/js/panel.js',
            ]),
        ],
    ]
];
```

> **Note**  
> Remember to include the optional panel assets in vite as well so that they are actually available once bundled.
> Assets not included in vite **will work in development** mode but **not when bundled**.

```js
export default defineConfig({
  laravel([
    'assets/css/panel.css',
    'assets/js/feature.js',
    'assets/js/panel.js',
  ]),
});
```


## React
If you build your front-end using the React framework you will also need to call the additional `vite()->reactRefresh()` method alongside your existing `vite()` call.

```php
<?= vite()->reactRefresh() ?>
<?= vite('assets/js/app.jsx') ?>
```

The `vite()->reactRefresh()` method must be called before the `vite()` call.

## Processing Static Assets With Vite
When referencing assets in your JavaScript or CSS, Vite automatically processes and versions them.
In addition, when your application is built using traditional server-side rendering, Vite can also process and version static assets that you reference in your templates.

However, in order to accomplish this, you need to make Vite aware of your assets by importing the static assets into the application's entry point.
For example, if you want to process and version all images stored in `assets/images` and all fonts stored in `assets/fonts`, you should add the following in your application's `assets/js/app.js` entry point:

```js
import.meta.glob([
  '../images/**',
  '../fonts/**',
]);
```

These assets will now be processed by Vite when running `npm run build`. You can then reference these assets in your templates using the `vite()->asset()` method, which will return the versioned URL for a given asset:

```php
<img src="<?= vite()->asset('assets/images/logo.png') ?>">
```

## Arbitrary Attributes
If you need to include additional attributes on your script and style tags, such as the `data-turbo-track` attribute, you may specify them via the plugin options.

```php
return [
    'lukaskleinschmidt.laravel-vite' => [
        'scriptTagAttributes' => [
            'data-turbo-track' => 'reload', // Specify a value for the attribute...
            'async'            => true,     // Specify an attribute without a value...
            'integrity'        => false,    // Exclude an attribute that would otherwise be included...
        ],
        'styleTagAttributes' => [
            'data-turbo-track' => 'reload',
        ],
    ],
];
```

If you need to conditionally add attributes, you may pass a callback that will receive the asset source path, its URL, its manifest chunk, and the entire manifest:

```php
return [
    'lukaskleinschmidt.laravel-vite' => [
        'scriptTagAttributes' => fn (string $src, string $url, array $chunk, array $manifest) => [
            'data-turbo-track' => $src === 'assets/js/app.js' ? 'reload' : false,
        ],
        'styleTagAttributes' => fn (string $src, string $url, array $chunk, array $manifest) => [
            'data-turbo-track' => $chunk && $chunk['isEntry'] ? 'reload' : false,
        ],
    ],
];
```

> **Note**  
> The `$chunk` and `$manifest` arguments will be empty while the Vite development server is running.

## Advanced Customization
Out of the box, Laravel's Vite plugin uses sensible conventions that should work for the majority of applications.
However, sometimes you may need to customize Vite's behavior.
To enable additional customization options, you can use the following options:

```php
return [
    'lukaskleinschmidt.laravel-vite' => [
        'hotFile'        => fn () => kirby()->root('storage') . '/vite.hot',
        'buildDirectory' => 'bundle',
        'manifest'       => 'assets.json',
    ],
];
```

> **Note**
> If you need access to the Kirby instance, you can use a callback function to define the option.
> Alternatively, you can configure Vite in Kirby's [ready callback](https://getkirby.com/docs/reference/system/options/ready) or directly in the template.

```php
use LukasKleinschmidt\Vite;

return [
    'ready' => function () {
        Vite::instance()
            ->useHotFile(kirby()->root('storage') . '/vite.hot')
            ->useBuildDirectory('bundle')
            ->useManifest('assets.json');
    },
];
```

```php
<!doctype html>
<head>
  <?=
    vite()->useHotFile($kirby->root('storage') . '/vite.hot')
          ->useBuildDirectory('bundle')
          ->useManifest('assets.json')
          ->withEntries(['assets/js/app.js'])
  ?>
</head>
```

Within the `vite.config.js` file, you should then specify the same configuration:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            hotFile: 'storage/vite.hot',
            buildDirectory: 'bundle',
            input: [
              'assets/js/app.js',
            ],
        }),
    ],
    build: {
      manifest: 'assets.json',
    },
});
```

## Commercial Usage
This plugin is free if charge, but please consider a [donation](https://www.paypal.me/lukaskleinschmidt/5EUR) if you use it in a commercial project.

## License
MIT

## Credits
- [Lukas Kleinschmidt](https://github.com/lukaskleinschmidt)
- [The Laravel Framework](https://github.com/laravel)

> A good portion of the documentation has been copied from the [Laravel website](https://laravel.com/docs/vite) and adapted to the Kirby implementation.
