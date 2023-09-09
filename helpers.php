<?php

use LukasKleinschmidt\Vite;

if (! function_exists('vite')) {
    function vite(array|string $entries = null): Vite
    {
        return ! is_null($entries)
            ? Vite::copy()->withEntries((array) $entries)
            : Vite::instance();
    }
}
