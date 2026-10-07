<?php
// Hostinger giriş noktası. Bütün sayfa istekleri .htaccess ile buraya gelir.
declare(strict_types=1);

// PHP'nin yerleşik sunucusuyla yerel denemede gerçek dosyaları doğrudan sunar.
if (PHP_SAPI === 'cli-server') {
    $file = realpath($_SERVER['DOCUMENT_ROOT'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($file && is_file($file) && !str_ends_with($file, '.php') && !str_contains($file, DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';
Altindas\run(__DIR__);
