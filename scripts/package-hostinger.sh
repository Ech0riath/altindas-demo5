#!/bin/sh
# Hostinger'a kurulacak paketi hazırlar (varsayılan: build/hostinger).
# Paket, hPanel Git ile public_html/demo klasörüne çekilen "hostinger" dalına yazılır.
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
printf '%s\n' 'app/config.php' 'uploads/*' '!uploads/.htaccess' > "$out/.gitignore"
echo "Hostinger paketi hazır: $out"
