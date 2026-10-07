<?php
// Yerel denetim çıktısı: app/site.php şablonlarını src/content.json verisiyle
// çalıştırıp her rotayı dist/ altına HTML dosyası olarak yazar (npm run check
// bu çıktıyı denetler). Canlı site Hostinger'dadır.
declare(strict_types=1);

require __DIR__ . '/../app/site.php';

use Altindas\Site;

$root = dirname(__DIR__);
$out = $root . '/' . ($argv[1] ?? 'dist');
$base = '/demo/';

function copy_tree(string $from, string $to): void
{
    if (is_dir($from)) {
        @mkdir($to, 0777, true);
        foreach (scandir($from) as $name) {
            if ($name !== '.' && $name !== '..') {
                copy_tree("$from/$name", "$to/$name");
            }
        }
        return;
    }
    copy($from, $to);
}

function write_file(string $file, string $content): void
{
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, $content);
}

$data = json_decode(file_get_contents("$root/src/content.json"), true, 512, JSON_THROW_ON_ERROR);
$site = new Site($data, [
    'base' => $base,
    'origin' => 'http://127.0.0.1:4325',
    'host' => 'Hostinger',
    'assetVersion' => fn (string $path) => is_file("$out/$path") ? substr(md5_file("$out/$path"), 0, 10) : null,
]);

copy_tree("$root/public", $out);
foreach (['site.css', 'site.js', 'admin.js', 'admin.css', 'content-rules.js'] as $file) {
    copy("$root/src/$file", "$out/assets/$file");
}
$count = 0;
foreach ($site->routes() as $route => $spec) {
    write_file("$out/{$route}index.html", ($spec['render'])());
    $count++;
}
write_file("$out/404.html", $site->notFound());
write_file("$out/sitemap.xml", $site->sitemap());
write_file("$out/robots.txt", $site->robots());
write_file("$out/.nojekyll", '');
echo 'Built ' . ($count + 1) . " pages with base $base\n";
