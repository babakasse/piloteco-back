<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// --- Gestion des Trusted Proxies (Azure App Service) ---
// --- Trusted proxies pour Azure ---
if ($_SERVER['APP_ENV'] === 'prod') {
    Request::setTrustedProxies(
        ['0.0.0.0/0'], // faire confiance à tous les proxies
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PORT
    );
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
