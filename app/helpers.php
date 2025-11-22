<?php

if (! function_exists('markdown')) {
    function markdown($text)
    {
        return '<div class="prose">'.(new Parsedown)->text($text).'</div>';
    }
}

if (! function_exists('menuRoute')) {
    function menuRoute($name, $parameters = [], $absolute = true)
    {
        return app('url')->menuRoute($name, $parameters, $absolute);
    }
}
