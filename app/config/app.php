<?php

declare(strict_types=1);

const APP_NAME = 'Numéria';
const APP_VERSION = '0.1.0';

function asset(string $path): string
{
    return '/' . ltrim($path, '/');
}

function app_url(string $path = ''): string
{
    $base = '/';
    return $base . ltrim($path, '/');
}
