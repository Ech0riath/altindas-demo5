# Altındaş demo5 — uygulama planı

Kullanıcı kararı, 7 Ekim 2026: Website2/ornekler/ornek3.html temel alınır. Gri saha panosu estetiği, sarı vurgu ve koyu ölçüm paneli korunur. Referans logoları tek dikey sütunda yavaş ve sürekli kayar; durdurma düğmesi ve fareyle durma yoktur (kullanıcı kararı, 7 Ekim 2026). “Hareketi azalt” tercihinde sabit liste gösterilir. Her proje kısa özetli karttan kendi detay sayfasına açılır. Kaynak depo Ech0riath/altindas-demo5. Yayın yalnız Hostinger’da, alan adının `/demo/` klasöründe (PHP + MySQL; kullanıcı kararı, 7 Ekim 2026 — beğenilince ana site olacak). Kullanıcı kararı: site GitHub’dan tamamen ayrıdır; GitHub yalnız kod deposudur, GitHub Pages yalnız “taşındı” sayfasıdır (`pages/`).

## Tasarım

- Zemin #E9ECEF, yüzey #FFFFFF, gövde #1B222B, vurgu #FDB81D, vurgu metni #805200, ikincil metin #596574.
- Archivo başlık, Inter metin, JetBrains Mono teknik değerler. Fontlar yerel.
- Masaüstü: solda başlık ve keşif, sağda örnek trafo paneli. Projelerde solda tek referans sütunu, sağda iki kart sütunu. Mobilde tek akış.
- Örnekteki cihaz paneli karakteristik görseldir; canlı tesis bağlantısı ya da doğrulanmış ölçüm izlenimi yaratılmaz.
- Yalnız kullanıcıya ait gerçek proje fotoğrafları. Fotoğrafı olmayan proje için açıkça şematik çizim.

## Ajanlar ve beceriler

Araç seti yerel olarak indirildi: wilwaldon/Claude-Code-Frontend-Design-Toolkit.

- Ana ajan: Anthropic frontend-design; tasarım sistemi, sayfa şablonları, etkileşimler, yayın.
- İçerik ajanı: PDF + kaynak doğrulama; 7 hizmet, 6 proje ve kurumsal içerik.
- Varlık ajanı: gerçek görseller, logo ve yerel font optimizasyonu.
- Kalite ajanı: Addy Osmani accessibility + web-quality-audit; bağlantı, semantik ve yayın kontrolleri.
  Bu becerilerin proje kopyaları .agents/skills içinde. Global istemci ayarları değişmez. Kullanıcının seçtiği tasarım, becerilerin genel estetik tercihinden önceliklidir.

## Uygulama

Sayfa şablonları tek yerde: `app/site.php` (bağımlılıksız PHP 8.1+). Hostinger’da istek başına MySQL verisiyle çalışır (`web/index.php`, `app/bootstrap.php`); `scripts/export.php` aynı şablonları yerel denetim için `src/content.json` ile `dist/` altına yazar. Tasarım değişikliği yalnız `app/site.php`, `src/site.css`, `src/site.js` içinde yapılır. Aynı üstbilgi, altbilgi ve tasarım tokenları bütün sayfalarda kullanılır. Taban yol kurulduğu klasörden otomatik bulunur (yerel önizleme `/demo/`). Keşif formu okunabilir WhatsApp mesajı hazırlar; gönderme işlemini ziyaretçi WhatsApp'ta tamamlar. Sahte başarı bildirimi yoktur.

## Yönetim paneli

Kullanıcı kararı, 7 Ekim 2026: proje (bilgi + fotoğraf) ve mağaza ürünü (bilgi + fiyat) bir yönetim panelinden eklenir. Panel `/admin/` adresinde, bağımlılıksız tarayıcı kodudur (`src/admin.js`); kullanıcı adı/şifreyle `admin/api` üzerinden MySQL’e ve `uploads/` klasörüne yazar (`app/admin.php`, `app/store.php`). GitHub bağlantılı panel kaldırıldı. Hostinger veritabanı ve `app/config.php` git’e girmez; hPanel bilgileri depoya yazılmaz. Mağaza ödeme almaz, WhatsApp sipariş mesajı hazırlar. `src/content.json` elle düzenlenirken `JSON.stringify(data, null, 2)` biçimi korunur. Yasaklı ifade kuralları `src/content-rules.js` içinde tek yerdedir.

## Doğrulama ve yayın

Build → üretilen tüm bağlantı/varlık kontrolleri → masaüstü/mobil tarayıcı incelemesi → menü, filtre, SSS, logo kayması, form kontrolü → `npm run package:hostinger` ile zip paketi → kullanıcı paketi hPanel Dosya Yöneticisi ya da FTP ile `public_html/demo` klasörüne açar (kullanıcı kararı, 7 Ekim 2026; sunucuya otomatik yayın yok) → canlı URL doğrulama. Hostinger’ın otomatik sunucu önbelleği PHP yanıtlarını da saklar; PHP yanıtları `X-LiteSpeed-Cache-Control: no-cache` gönderir, panel istekleri önbelleğe takılmamalıdır. Şablon değişikliğinde `npm run build && npm run check` ve Hostinger çıktısı (`SITE_BASE`/`SITE_ORIGIN` ile) `check.mjs`’ten geçmelidir.

## Kaynak sınırları

PDF ve vault yerelde referans olarak kullanılır; genel depoya ham vault, özel kişi adı, PDF veya kurum içi fiyatlandırma taşınmaz. Güncel kullanıcı teyitleri eski PDF ve önceki site iddialarından önceliklidir. Sahte yorum, başarı sayacı, tahmini proje sonucu veya belgesiz sertifika eklenmez.
