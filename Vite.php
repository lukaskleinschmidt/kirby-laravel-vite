<?php

namespace LukasKleinschmidt;

use Exception;
use Kirby\Cms\App;
use Kirby\Toolkit\Html;
use Kirby\Toolkit\Str;
use Stringable;

class Vite implements Stringable
{
    /**
     * The Vite instance.
     */
	protected static Vite $instance;

    /**
     * The Content Security Policy nonce to apply to all generated tags.
     */
    protected ?string $nonce = null;

    /**
     * The key to check for integrity hashes within the manifest.
     */
    protected string|false $integrity = 'integrity';

    /**
     * The configured entry points.
     */
    protected array $entries = [];

    /**
     * The path to the "hot" file.
     */
    protected ?string $hotFile = null;

    /**
     * The path to the build directory.
     */
    protected string $buildDirectory = 'build';

    /**
     * The name of the manifest file.
     */
    protected string $manifest = 'manifest.json';

    /**
     * The preload tag attributes.
     */
    protected array $preloadTagAttributesResolvers = [];

    /**
     * The script tag attributes.
     */
    protected array $scriptTagAttributesResolvers = [];

    /**
     * The style tag attributes.
     */
    protected array $styleTagAttributesResolvers = [];

    /**
     * The preloaded assets.
     */
    protected array $preloadedAssets = [];

    /**
     * The cached manifest files.
     */
    protected static array $manifests = [];

    /**
     * Create a new Vite instance.
     */
    public function __construct(
        protected App $app,
    ) {}

    /**
     * Generate Vite tags for an entrypoint.
     *
     * @param  string|string[]  $entries
     *
     * @throws \Exception
     */
    public function __invoke(array|string $entries, string $buildDirectory = null): ?string
    {
        $entries = (array) $entries;
        $exists  = [];

        foreach ($entries as $key => $value) {
            $query = Str::template($value, [
                'kirby' => $this->app,
                'site'  => $this->app->site(),
                'page'  => $this->app->site()->page(),
            ]);

            if ($optional = str_starts_with($query, '@')) {
                $query = substr($query, 1);
            }

            if ($optional || $query !== $value) {
                if ($this->exists($query)) {
                    $entries[$key] = $query;
                    $exists[]      = $query;
                } else {
                    unset($entries[$key]);
                }
            }
        }

        if ($this->isRunningHot()) {
            array_unshift($entries, '@vite/client');

            return join('', array_map(fn (string $value) =>
                $this->makeTag($value, $this->hotAsset($value))
            , $entries));
        }

        $manifest = $this->manifest($buildDirectory ??= $this->buildDirectory);

        $preloads = [];
        $assets   = [];

        foreach ($entries as $key) {
            try {
                $chunk = $this->chunk($manifest, $key);
            } catch (Exception $e) {
                if (! in_array($key, $exists)) throw $e;
            }

            if (! isset($chunk)) {
                continue;
            }

            $args = [
                $key,
                url($buildDirectory . '/'. $file = $chunk['file']),
                $chunk,
                $manifest,
            ];

            if (! isset($preloads[$file])) {
                $preloads[$file] = $this->makePreloadTag(...$args);
            }

            if (! isset($assets[$file])) {
                $assets[$file] = $this->makeTag(...$args);
            }

            $this->resolveImports($chunk, $buildDirectory, $manifest, $assets, $preloads);

            $this->resolveCss($chunk, $buildDirectory, $manifest, $assets, $preloads);
        }

        uksort($preloads, fn ($a, $b) =>
            $this->isStylePath($a) === $this->isStylePath($b) ? 0 : 1
        );

        uksort($assets, fn ($a, $b) =>
            $this->isStylePath($a) === $this->isStylePath($b) ? 0 : 1
        );

        return join('', $preloads) . join('', $assets);
    }

    /**
     * Create a new Vite instance.
     */
    public static function instance(App $app = null): static
    {
        return static::$instance ??= new static($app ?? App::instance());
    }

    /**
     * Returns a copy of the current Vite instance.
     */
    public static function copy(): static
    {
        return clone static::instance();
    }

    /**
     * Get the chunk for the given entry point / asset.
     *
     * @throws \Exception
     */
    protected function chunk(array $manifest, string $file): array
    {
        if (! isset($manifest[$file])) {
            throw new Exception("Unable to locate file in Vite manifest: {$file}");
        }

        return $manifest[$file];
    }

    /**
     * Check if a source file exists.
     */
    protected function exists(string $file): bool
    {
        $base  = $this->app->root('vite:base') ?? $this->app->root('base');
        $index = $this->app->root('index');

        $paths = [
            $base ?? $index,
            dirname($index),
        ];

        foreach ($paths as $path) {
            if (file_exists(rtrim($path, '/') . '/' . $file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the the manifest file for the given build directory.
     *
     * @throws \Exception
     */
    protected function manifest(string $buildDirectory): array
    {
        $path = $this->manifestPath($buildDirectory);

        if (! isset(static::$manifests[$path])) {
            if (! is_file($path)) {
                throw new Exception("Vite manifest not found at: {$path}");
            }

            static::$manifests[$path] = json_decode(file_get_contents($path), true);
        }

        return static::$manifests[$path];
    }

    /**
     * Get the path to the manifest file for the given build directory.
     */
    protected function manifestPath(string $buildDirectory): string
    {
        return $this->app->root('index') . '/' . $buildDirectory . '/' . $this->manifest;
    }

    /**
     * Make a tag for the given chunk.
     */
    protected function makeTag(string $src, string $url, array $chunk = [], array $manifest = []): string
    {
        if ($this->isStylePath($url)) {
            return $this->makeStyleTag(
                $url,
                $this->resolveStyleTagAttributes($src, $url, $chunk, $manifest)
            );
        }

        return $this->makeScriptTag(
            $url,
            $this->resolveScriptTagAttributes($src, $url, $chunk, $manifest)
        );
    }

    /**
     * Generate a script tag with attributes for the given URL.
     */
    protected function makeScriptTag(string $url, array $attributes = []): string
    {
        return Html::tag('script', '', array_merge([
            'type'  => 'module',
            'src'   => $url,
            'nonce' => $this->nonce(),
        ], $attributes));
    }

    /**
     * Generate a link tag with attributes for the given URL.
     */
    protected function makeStyleTag(string $url, array $attributes = []): string
    {
        return Html::tag('link', '', array_merge([
            'rel'   => 'stylesheet',
            'href'  => $url,
            'nonce' => $this->nonce(),
        ], $attributes));
    }

    /**
     * Make a preload tag for the given chunk.
     */
    protected function makePreloadTag(string $src, string $url, array $chunk = [], array $manifest = []): string
    {
        $attributes = $this->resolvePreloadTagAttributes($src, $url, $chunk, $manifest);

        return $this->preloadedAssets[$url] ??= Html::tag('link', '', $attributes);
    }

    /**
     * Resolve the attributes for the chunks generated script tag.
     */
    protected function resolveScriptTagAttributes(string $src, string $url, array $chunk = [], array $manifest = []): array
    {
        $attributes = $this->integrity !== false
            ? ['integrity' => $chunk[$this->integrity] ?? false]
            : [];

        foreach ($this->scriptTagAttributesResolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }

        return $attributes;
    }

    /**
     * Resolve the attributes for the chunks generated stylesheet tag.
     */
    protected function resolveStyleTagAttributes(string $src, string $url, array $chunk = [], array $manifest = []): array
    {
        $attributes = $this->integrity !== false
            ? ['integrity' => $chunk[$this->integrity] ?? false]
            : [];

        foreach ($this->styleTagAttributesResolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }

        return $attributes;
    }

    /**
     * Resolve the attributes for the chunks generated preload tag.
     */
    protected function resolvePreloadTagAttributes(string $src, string $url, array $chunk = [], array $manifest = []): array
    {
        $attributes = $this->isStylePath($url) ? [
            'rel'         => 'preload',
            'as'          => 'style',
            'href'        => $url,
            'nonce'       => $this->nonce(),
            'crossorigin' => $this->resolveStyleTagAttributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false,
        ] : [
            'rel'         => 'modulepreload',
            'href'        => $url,
            'nonce'       => $this->nonce(),
            'crossorigin' => $this->resolveScriptTagAttributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false,
        ];

        $attributes = $this->integrity !== false
            ? array_merge($attributes, ['integrity' => $chunk[$this->integrity] ?? false])
            : $attributes;

        foreach ($this->preloadTagAttributesResolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }

        return $attributes;
    }

    /**
     * Get the preloaded assets.
     */
    public function preloadedAssets(): array
    {
        return $this->preloadedAssets;
    }

    /**
     * Get the URL for an asset.
     */
    public function asset(string $asset, string $buildDirectory = null): string
    {
        $buildDirectory ??= $this->buildDirectory;

        if ($this->isRunningHot()) {
            return $this->hotAsset($asset);
        }

        $chunk = $this->chunk($this->manifest($buildDirectory), $asset);

        return url($buildDirectory . '/' . $chunk['file']);
    }

    /**
     * Generate React refresh runtime script.
     */
    public function reactRefresh(): string
    {
        if (! $this->isRunningHot()) {
            return '';
        }

        $attributes = Html::attr([
            'nonce' => $this->nonce(),
        ]);

        return sprintf(
            <<<'HTML'
            <script type="module" %s>
                import RefreshRuntime from '%s'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.$RefreshReg$ = () => {}
                window.$RefreshSig$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            </script>
            HTML,
            $attributes,
            $this->hotAsset('@react-refresh')
        );
    }

    /**
     * Determine whether the given path is a style file.
     */
    public function isStylePath(string $path): bool
    {
        return preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)$/', $path) === 1;
    }

    /**
     * Determine if the HMR server is running.
     */
    public function isRunningHot(): bool
    {
        return is_file($this->hotFile());
    }

    /**
     * Get the Vite "hot" file path.
     */
    public function hotFile(): string
    {
        return $this->hotFile ?? $this->app->root('index') . '/hot';
    }

    /**
     * Get the path to a given asset when running in HMR mode.
     */
    public function hotAsset(string $asset): string
    {
        return rtrim(file_get_contents($this->hotFile())) . '/' . $asset;
    }

    /**
     * Get the Content Security Policy nonce applied to all generated tags.
     */
    public function nonce(): ?string
    {
        return $this->nonce;
    }

    /**
     * Resolve related css files.
     */
    public function resolveCss(array $chunk, string $buildDirectory, array $manifest, array &$assets, array &$preloads): void
    {
        foreach ($chunk['css'] ?? [] as $key) {
            $chunks = array_filter($manifest, fn ($value) =>
                $value['file'] === $key
            );

            $key   = array_key_first($chunks);
            $chunk = current($chunks);
            $file  = $chunk['file'];

            $args = [
                $key,
                url($buildDirectory . '/'. $file),
                $chunk,
                $manifest,
            ];

            if (! isset($assets[$file])) {
                $assets[$file] = $this->makeTag(...$args);
            }

            if (! isset($preloads[$file])) {
                $preloads[$file] = $this->makePreloadTag(...$args);
            }
        }
    }

    /**
     * Resolve related imports.
     */
    public function resolveImports(array $chunk, string $buildDirectory, array $manifest, array &$assets, array &$preloads): void
    {
        foreach ($chunk['imports'] ?? [] as $key) {
            $chunk = $this->chunk($manifest, $key);
            $file  = $chunk['file'];

            if (! isset($preloads[$file])) {
                $preloads[$file] = $this->makePreloadTag(
                    $key,
                    url($buildDirectory . '/'. $file),
                    $chunk,
                    $manifest,
                );
            }

            $this->resolveCss($chunk, $buildDirectory, $manifest, $assets, $preloads);
        }
    }

    /**
     * Generate or set a Content Security Policy nonce to apply to all generated tags.
     */
    public function useNonce(string $nonce = null): static
    {
        $this->nonce = $nonce ?? Str::random(40);

        return $this;
    }

    /**
     * Set the filename for the manifest file.
     */
    public function useManifest(string $name): static
    {
        $this->manifest = $name;

        return $this;
    }

    /**
     * Use the given key to detect integrity hashes in the manifest.
     */
    public function useIntegrity(string|false $key): static
    {
        $this->integrity = $key;

        return $this;
    }

    /**
     * Set the Vite "hot" file path.
     */
    public function useHotFile(string $path): static
    {
        $this->hotFile = $path;

        return $this;
    }

    /**
     * Set the Vite build directory.
     */
    public function useBuildDirectory(string $path): static
    {
        $this->buildDirectory = $path;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for script tags.
     */
    public function useScriptTagAttributes(array|callable $attributes): static
    {
        if (! is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->scriptTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for style tags.
     */
    public function useStyleTagAttributes(array|callable $attributes): static
    {
        if (! is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->styleTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for preload tags.
     */
    public function usePreloadTagAttributes(array|callable $attributes): static
    {
        if (! is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->preloadTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Set the Vite entry points.
     */
    public function withEntries(array $entries): static
    {
        $this->entries = $entries;

        return $this;
    }

    /**
     * Get the Vite tag content as a string of HTML.
     */
    public function __toString(): string
    {
        return $this->__invoke($this->entries);
    }
}
