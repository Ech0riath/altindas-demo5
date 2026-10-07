#!/bin/sh
# Hostinger'a yüklenecek paketleri hazırlar:
# - build/hostinger: site klasörü
# - build/altindas-demo-hostinger.zip: güncelleme paketi; içeriği mevcut
#   public_html/demo klasörünün üzerine açılır (app/config.php ve uploads/ korunur)
# - build/altindas-sifirdan-kurulum.zip: sıfırdan kurulum; "demo" klasörü ve
#   docs/KURULUM.txt rehberi
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
release="$(dirname "$zip")/altindas-sifirdan-kurulum.zip"
stage="$(mktemp -d)"
cp -R "$out" "$stage/demo"
cp docs/KURULUM.txt "$stage/KURULUM.txt"
rm -f "$release"
(cd "$stage" && zip -qr -X "$release" KURULUM.txt demo)
rm -rf "$stage"
echo "Hostinger paketleri hazır: $out, $zip, $release"
