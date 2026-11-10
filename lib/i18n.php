<?php
declare(strict_types=1);
require_once __DIR__ . '/routes.php';

const LAB_DEFAULT_LOCALE = 'en';
const LAB_SOURCE_LOCALE = 'ar';

function labLanguageDirectory(): string
{
    return dirname(__DIR__) . '/lang';
}

function labLanguagePacks(): array
{
    static $packs;
    if ($packs !== null) {
        return $packs;
    }

    $packs = [];
    foreach (glob(labLanguageDirectory() . '/*/locale.json') ?: [] as $path) {
        $code = basename(dirname($path));
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $code)) {
            continue;
        }
        $metadata = json_decode(file_get_contents($path), true);
        if (!is_string($metadata['name'] ?? null) || !in_array($metadata['dir'] ?? null, ['rtl', 'ltr'], true)) {
            continue;
        }
        $packs[$code] = ['name' => $metadata['name'], 'dir' => $metadata['dir']];
    }
    return $packs;
}

function labRequestLocale(): string
{
    $route = labParsePagePath(labRequestPath());
    foreach ([$route['locale'] ?? null, $_GET['lang'] ?? null, $_COOKIE['prisonlab_lang'] ?? null] as $locale) {
        if (is_string($locale) && isset(labLanguagePacks()[$locale])) {
            return $locale;
        }
    }
    return LAB_DEFAULT_LOCALE;
}

function labLocale(): string
{
    return $GLOBALS['lab_session_locale'] ?? labRequestLocale();
}

function labUseSessionLocale(array $session): void
{
    $locale = $session['locale'] ?? LAB_SOURCE_LOCALE;
    $GLOBALS['lab_session_locale'] = is_string($locale) && isset(labLanguagePacks()[$locale]) ? $locale : LAB_SOURCE_LOCALE;
}

function labSetLocaleCookie(?string $locale = null): void
{
    $locale ??= labRequestLocale();
    if (isset(labLanguagePacks()[$locale]) && ($_COOKIE['prisonlab_lang'] ?? null) !== $locale) {
        setcookie('prisonlab_lang', $locale, [
            'path' => '/', 'expires' => time() + 31536000, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
}

function labLanguageFile(string $relative, ?string $locale = null): string
{
    $locale ??= labLocale();
    $directory = labLanguageDirectory();
    if (isset(labLanguagePacks()[$locale])) {
        $path = $directory . '/' . $locale . '/' . $relative;
        if (is_file($path)) {
            return $path;
        }
    }
    return $directory . '/' . LAB_SOURCE_LOCALE . '/' . $relative;
}

function labLanguageJson(string $relative, ?string $locale = null): array
{
    static $cache = [];
    $path = labLanguageFile($relative, $locale);
    return $cache[$path] ??= json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function labMessages(string $file, ?string $locale = null): array
{
    static $catalogs = [];
    $locale ??= labLocale();
    return $catalogs[$locale][$file] ??= labLanguageJson($file, $locale) + labLanguageJson($file, LAB_SOURCE_LOCALE);
}

function labInterpolate(string $text, array $values): string
{
    $replacements = [];
    foreach ($values as $key => $value) {
        $replacements['{' . $key . '}'] = (string)$value;
    }
    return strtr($text, $replacements);
}

function labT(string $key, array $values = []): string
{
    return labInterpolate(labMessages('messages.json')[$key] ?? $key, $values);
}

function labE(string $key, array $values = []): string
{
    return htmlspecialchars(labT($key, $values), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function labPrompt(string $key, array $values = []): string
{
    static $cache = [];
    $path = labLanguageFile('promts/' . $key . '.txt');
    return labInterpolate($cache[$path] ??= file_get_contents($path), $values);
}

function labLocaleAttributes(): string
{
    $code = labRequestLocale();
    $dir = labLanguagePacks()[$code]['dir'] ?? 'ltr';
    return 'lang="' . $code . '" dir="' . $dir . '"';
}

function labLanguageMenu(): void
{
    $packs = labLanguagePacks();
    if (count($packs) < 2) {
        return;
    }
    $current = labRequestLocale();
    echo '<select class="language-select" aria-label="', labE('language.menu'), '" onchange="location.assign(this.value)">';
    foreach ($packs as $code => $pack) {
        $url = labPageUrl($GLOBALS['lab_page'] ?? 'home', $_GET, $code);
        echo '<option data-lab-language="', $code, '" lang="', $code, '" dir="', $pack['dir'], '" value="',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), '"',
            $code === $current ? ' selected' : '', '>',
            htmlspecialchars($pack['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '</option>';
    }
    echo '</select>';
}

function labLocaleScript(): void
{
    $catalog = ['locale' => labRequestLocale(), 'messages' => labMessages('browser.json'),
        'publicRoutes' => LAB_PUBLIC_ROUTES];
    echo '<script type="application/json" id="lab-i18n">',
        json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        '</script>';
}

function labCase(string $id): array
{
    $metadata = labMessages('data/cases.json')[$id] ?? null;
    if (!is_array($metadata) || !preg_match('/^[a-zA-Z0-9_-]+\.md$/D', $id)) {
        throw new RuntimeException(labT('case.invalid'));
    }
    $path = labLanguageFile('data/cases/' . $id);
    return ['filename' => $id, 'content' => file_get_contents($path),
        'difficulty' => labPrisonerCharacter($id)] + $metadata;
}

function labCases(): array
{
    return array_map('labCase', array_keys(labMessages('data/cases.json')));
}
