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
> Some features are not yet properly documented. Feel free to skim through the [source code](https://github.com/lukaskleinschmidt/kirby-laravel-vite/blob/main/Vite.php) for if you think something is missing.

## Installing the Laravel Vite Plugin
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

## Refreshing on save
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

## Autoloading template specific assets
If you used the `@auto` option for your assets, you can do something similar with [optional assets](#optional-assets).

## Optional Assets
When you use the Kirby Query Language or prepend an `@` to an asset, those assets are treated as optional.
Meaning the plugin will only include assets that actually exist at the given source path.

```php
<!doctype html>
<head>
  <?= vite([
    // Using a kirby query
    'assets/css/templates/{{ page.template }}.css',
    'assets/js/templates/{{ page.template }}.js',

    // Would be equivalent
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

## Commercial Usage
This plugin is free if charge, but please consider a [donation](https://www.paypal.me/lukaskleinschmidt/5EUR) if you use it in a commercial project.

## Installation

### Download
Download and copy this repository to `/site/plugins/laravel-vite`.

### Git submodule
```
git submodule add https://github.com/lukaskleinschmidt/kirby-laravel-vite.git site/plugins/laravel-vite
```

### Composer
```
composer require lukaskleinschmidt/kirby-laravel-vite
```

## License
MIT

## Credits
- [Lukas Kleinschmidt](https://github.com/lukaskleinschmidt)
- [The Laravel Framework](https://github.com/laravel)
