<?php

use LukasKleinschmidt\Vite;

if (! function_exists('vite')) {
    function vite(array|string $entries = null)
    {
        return ! is_null($entries)
            ? Vite::instance()($entries)
            : Vite::instance();
    }
}
