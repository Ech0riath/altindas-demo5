#!/bin/sh
# Hostinger'a yüklenecek paketi hazırlar: build/hostinger klasörü ve
# build/altindas-demo-hostinger.zip. Zip'in içeriği public_html/demo
# klasörüne çıkarılır; sunucudaki app/config.php ve uploads/ korunur.
set -eu
cd "$(dirname "$0")/.."
out="${1:-build/hostinger}"
rm -rf "$out"
mkdir -p "$out/app" "$out/assets"
cp -R public/. "$out/"
cp -R web/. "$out/"
cp app/.htaccess app/*.php "$out/app/"
cp src/content.json "$out/app/content.json"
cp src/site.css src/site.js src/admin.js src/admin.css src/content-rules.js "$out/assets/"
zip="$(cd "$(dirname "$out")" && pwd)/altindas-demo-hostinger.zip"
rm -f "$zip"
(cd "$out" && zip -qr -X "$zip" .)
echo "Hostinger paketi hazır: $out ve $zip"
