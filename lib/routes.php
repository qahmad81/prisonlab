<?php
declare(strict_types=1);

const LAB_PUBLIC_ROUTES = [
    'session' => 'session_id',
    'profile' => 'profile',
    'investigator' => 'investigator',
    'sponsor' => 'sponsor',
    'funder' => 'funder',
    'model' => 'model',
    'case_file' => 'case_file',
];

function labRequestPath(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

function labPublicRouteId(string $scope, string $id): bool
{
    return $scope === 'model'
        ? preg_match('~^\~?[A-Za-z0-9_-]+/[A-Za-z0-9_.:-]+$~D', $id) === 1
        : preg_match('/^[A-Za-z0-9_-]+$/D', $id) === 1;
}

/** Decode individual segments so an encoded slash cannot create an additional route. */
function labParsePagePath(string $path): ?array
{
    if (!preg_match('~^/([a-z]{2,3}(?:-[a-z0-9]{2,8})*)/(home|privacy|public)(?:/([^/]+)/([^/]+(?:/[^/]+)?))?/?$~D', $path, $parts)) {
        return null;
    }
    [, $locale, $page] = $parts;
    $params = [];
    if (isset($parts[3])) {
        $scope = $parts[3];
        if ($page !== 'public' || !isset(LAB_PUBLIC_ROUTES[$scope])) {
            return null;
        }
        $segments = array_map('rawurldecode', explode('/', $parts[4]));
        if (count($segments) !== ($scope === 'model' ? 2 : 1) ||
            array_filter($segments, fn($segment) => str_contains($segment, '/'))) {
            return null;
        }
        $id = implode('/', $segments);
        if (!labPublicRouteId($scope, $id)) {
            return null;
        }
        $params[LAB_PUBLIC_ROUTES[$scope]] = $scope === 'case_file' ? $id . '.md' : $id;
    }
    return ['locale' => $locale, 'page' => $page, 'params' => $params];
}

function labPageUrl(string $page, array $params = [], ?string $locale = null): string
{
    $path = '/' . ($locale ?? labRequestLocale()) . '/' . $page;
    unset($params['lang']);
    if ($page === 'public') {
        foreach (LAB_PUBLIC_ROUTES as $scope => $key) {
            $value = $params[$key] ?? '';
            if (!is_string($value)) {
                continue;
            }
            $id = $scope === 'case_file' && str_ends_with($value, '.md') ? substr($value, 0, -3) : $value;
            if (labPublicRouteId($scope, $id)) {
                $path .= '/' . $scope . '/' . implode('/', array_map('rawurlencode', explode('/', $id)));
                unset($params[$key]);
                break;
            }
        }
    }
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return $path . ($query !== '' ? '?' . $query : '');
}

function labPageHref(string $page, array $params = []): string
{
    return htmlspecialchars(labPageUrl($page, $params), ENT_QUOTES, 'UTF-8');
}

function labLoginHref(): string
{
    return '/auth.php?action=login&amp;lang=' . labRequestLocale();
}

function labBeginPage(string $page): void
{
    $path = labRequestPath();
    $legacy = ['home' => ['/', '/index.php'], 'public' => ['/public.php'], 'privacy' => ['/privacy.php']];
    if (!in_array($path, $legacy[$page], true)) {
        $route = labParsePagePath($path);
        if (!$route || $route['page'] !== $page || !isset(labLanguagePacks()[$route['locale']])) {
            http_response_code(404);
            exit(labT('router.router.1'));
        }
        // A query string cannot override the identity in the canonical path.
        $_GET = array_replace($_GET, $route['params']);
    }
    $GLOBALS['lab_page'] = $page;
    labSetLocaleCookie();
    if ($path === '/' && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        header('Cache-Control: private, no-store');
        header('Location: ' . labPageUrl('home', $_GET), true, 302);
        exit;
    }
}
