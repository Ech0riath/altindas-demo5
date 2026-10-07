<?php
// Hostinger'da her isteği karşılayan uygulama: rota çözümü, veri kaynağı
// seçimi (kurulumdan önce content.json, sonra MySQL) ve yanıt başlıkları.
declare(strict_types=1);

namespace Altindas;

require_once __DIR__ . '/site.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/admin.php';

final class App
{
    public readonly string $base;
    public readonly string $origin;
    public readonly ?array $config;
    private ?Store $store = null;
    private ?array $static = null;
    private ?Site $site = null;

    public function __construct(public readonly string $root)
    {
        $file = $root . '/app/config.php';
        $config = is_file($file) ? require $file : null;
        $this->config = is_array($config) ? $config : null;
        $this->base = $this->config['base'] ?? self::detectBase($root);
        $this->origin = rtrim($this->config['origin'] ?? self::detectOrigin(), '/');
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function detectOrigin(): string
    {
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return (self::isHttps() ? 'https' : 'http') . '://' . $host;
    }

    private static function detectBase(string $root): string
    {
        if (PHP_SAPI === 'cli-server') {
            $doc = realpath((string) $_SERVER['DOCUMENT_ROOT']);
            $dir = substr((string) realpath($root), strlen((string) $doc));
        } else {
            $dir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        }
        return rtrim(str_replace('\\', '/', $dir), '/') . '/';
    }

    public function configured(): bool
    {
        return isset($this->config['db']);
    }

    public function url(string $p): string
    {
        return $this->base . ltrim($p, '/');
    }

    public function staticContent(): array
    {
        return $this->static ??= json_decode((string) file_get_contents($this->root . '/app/content.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function store(): Store
    {
        return $this->store ??= Store::connect($this->config['db']);
    }

    public function site(): Site
    {
        return $this->site ??= new Site(
            $this->configured() ? $this->store()->load($this->staticContent()) : $this->staticContent(),
            [
                'base' => $this->base,
                'origin' => $this->origin,
                'host' => 'Hostinger',
                'robots' => empty($this->config['indexable']) ? 'noindex,follow' : 'index,follow',
                'assetVersion' => fn (string $path) => is_file($this->root . '/' . $path) ? substr(md5_file($this->root . '/' . $path), 0, 10) : null,
            ],
        );
    }
}

// Hostinger'ın "otomatik önbelleği" (LiteSpeed) PHP yanıtlarını da saklar.
// Sayfalar veritabanından anlık üretildiği ve panel oturuma bağlı olduğu için
// hiçbir PHP yanıtı sunucu önbelleğine alınmaz.
const NO_SERVER_CACHE = 'X-LiteSpeed-Cache-Control: no-cache';
const PRIVATE_HEADERS = [
    'Cache-Control: no-store, no-cache, must-revalidate, private',
    'Pragma: no-cache',
    NO_SERVER_CACHE,
];

function send(int $status, string $type, string $body, array $headers = []): void
{
    http_response_code($status);
    header('Content-Type: ' . $type);
    header(NO_SERVER_CACHE);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    foreach ($headers as $h) {
        header($h);
    }
    echo $body;
}

function redirect(string $location): void
{
    header('Location: ' . $location, true, 301);
}

function run(string $root): void
{
    $app = new App($root);
    $uri = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($uri . '/' === $app->base) {
        redirect($app->base);
        return;
    }
    $path = str_starts_with($uri, $app->base) ? substr($uri, strlen($app->base)) : null;
    if ($path !== null && (str_contains($path, '..') || str_contains($path, "\0"))) {
        $path = null;
    }
    if ($path !== null && str_starts_with($path, 'admin/api/')) {
        (new Admin($app))->api(substr($path, strlen('admin/api/')));
    }
    if ($path === 'admin/kurulum/') {
        (new Admin($app))->setup();
    }
    if ($path === 'admin/' && !$app->configured()) {
        header('Location: ' . $app->url('admin/kurulum/'), true, 302);
        return;
    }
    if ($path !== null && $path !== '' && !str_ends_with($path, '/') && !str_contains(basename($path), '.')) {
        redirect($app->url($path . '/') . ($query !== '' ? '?' . $query : ''));
        return;
    }
    try {
        $site = $app->site();
    } catch (\PDOException $e) {
        error_log('[altindas] ' . $e->getMessage());
        send(503, 'text/html; charset=utf-8', '<!doctype html><html lang="tr"><meta charset="utf-8"><title>Bakım</title><p>Site kısa bir bakımda. Lütfen birkaç dakika sonra tekrar deneyin.</p></html>', ['Retry-After: 300']);
        return;
    }
    if ($path === 'sitemap.xml') {
        send(200, 'application/xml; charset=utf-8', $site->sitemap());
        return;
    }
    if ($path === 'robots.txt') {
        send(200, 'text/plain; charset=utf-8', $site->robots());
        return;
    }
    $routes = $site->routes();
    if ($path !== null && isset($routes[$path])) {
        $headers = [];
        if ($path === 'admin/') {
            $headers = [
                ...PRIVATE_HEADERS,
                "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob: data:; font-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; object-src 'none'; frame-ancestors 'none'",
            ];
        }
        send(200, 'text/html; charset=utf-8', ($routes[$path]['render'])(), $headers);
        return;
    }
    send(404, 'text/html; charset=utf-8', $site->notFound());
}
