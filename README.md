# Altındaş Mühendislik & Elektrik — demo

Site ve yönetim paneli **yalnız Hostinger’da** çalışır (PHP + MySQL), alan adının `/demo/` klasöründe. GitHub bu deponun kod deposudur; canlı sitenin GitHub’a hiçbir bağı yoktur. Eski GitHub Pages adresi yalnız “taşındı” sayfası gösterir.

`ornek3.html` saha panosu tasarımından geliştirilen Türkçe kurumsal web sitesi. Yedi hizmet, proje dosyaları, mağaza, referanslar, kurumsal, iletişim, keşif ve SSS sayfaları aynı tasarım sistemini kullanır. Proje ve ürünler `/demo/admin/` yönetim panelinden eklenir.

## Yapı

- `app/site.php`: bütün sayfa şablonları. `app/store.php`: MySQL tabloları ve kayıt işlemleri. `app/admin.php`: oturum, giriş sınırı, CSRF, panel API’si, kurulum sihirbazı. `app/bootstrap.php`: rota çözümü ve önbellek başlıkları.
- `web/`: giriş noktası (`index.php`), `.htaccess` kuralları, `uploads/` koruması.
- `src/site.css`, `src/site.js`: tasarım sistemi ve etkileşimler. `src/admin.js`, `src/admin.css`: yönetim paneli.
- `src/content.json`: hizmet, kurumsal ve SSS metinleri; ayrıca kurulumda veritabanına aktarılan ilk proje, ürün ve referans verisi.
- `src/content-rules.js`: yasaklı ifade kuralları; hem `check.mjs` hem panel kullanır.
- `public/`: gerçek saha fotoğrafları, marka varlıkları ve lisanslı yerel fontlar.
- `pages/`: eski GitHub Pages adresindeki “taşındı” sayfası.

## Geliştirme ve paket

```sh
npm run build              # PHP 8.1+ ile dist/ (yerel denetim çıktısı)
npm run check              # bağlantı, semantik ve içerik denetimi (Node 22+)
npm run preview            # http://127.0.0.1:4325/demo/ (dist önizlemesi)
npm run package:hostinger  # build/hostinger, güncelleme ve sıfırdan kurulum zip'leri
```

Hostinger çıktısını canlı veriyle denetlemek için PHP’nin yerleşik sunucusu kullanılabilir: paketi `kök/demo` klasörüne açıp `php -S 127.0.0.1:4330 -t kök kök/demo/index.php`; taranan sayfalar `SITE_BASE=/demo/ SITE_ORIGIN=http://127.0.0.1:4330 node scripts/check.mjs <klasör>` ile denetlenir.

## Hostinger’a yükleme

Her güncelleme `build/altindas-demo-hostinger.zip` olarak hazırlanır. hPanel **Dosya Yöneticisi**’nde zip `public_html/demo` klasörüne yüklenip **Çıkart** ile üzerine açılır (ya da içeriği FTP ile aynı klasöre yüklenir). Pakette `app/config.php` ve panel fotoğrafları bulunmaz; sunucudakiler korunur.

Sıfırdan kurulum için `build/altindas-sifirdan-kurulum.zip` kullanılır: içindeki `demo` klasörü FTP ile `public_html`’e yüklenir; adım adım rehber `docs/KURULUM.txt` (zip’te de var). İlk kurulumda `/demo/admin/` kurulum sihirbazını açar: veritabanı bilgileri ve yönetici hesabı girilir; sihirbaz tabloları oluşturur, ilk veriyi aktarır, `app/config.php` dosyasını yazar ve kurulumdan sonra kilitlenir. PHP 8.2 veya 8.3 önerilir.

Hostinger’ın otomatik önbelleği (LiteSpeed) PHP yanıtlarını da saklar. Bu yüzden bütün PHP yanıtları `X-LiteSpeed-Cache-Control: no-cache` gönderir; giriş ve kayıt yanıtları ayrıca önbelleği temizler (`X-LiteSpeed-Purge`). Panel istekleri benzersiz adresle yapılır ve eskimiş CSRF anahtarını kendisi yeniler.

Yedekleme: MySQL verisi phpMyAdmin’den dışa aktarılır; panel yüklemeleri `demo/uploads/` klasöründedir. İkisi de yalnız sunucudadır.

## İşlevler

Referanslar tek dikey sütunda sürekli kayar (logo başına yaklaşık 9,6 saniye). Kullanıcı kararıyla durdurma düğmesi ve fareyle durma yoktur. İşletim sisteminde “hareketi azalt” tercihi açık ziyaretçilere logolar sabit liste olarak gösterilir.

Proje kartları ilgili ayrıntı sayfalarına gider. Proje dizini kategoriye göre filtrelenebilir. Ana sayfadaki ölçüm paneli etkileşimli bir temsilî gösterimdir; canlı tesis verisi değildir.

Keşif formu mesajı yalnız tarayıcıda hazırlar. Gönderilmeden önce önizleme gösterilir; ziyaretçi WhatsApp üzerinden gönderir. Gönderilmemiş talep için başarı bildirimi kullanılmaz.

Gerçek saha fotoğrafı olmayan projelerde şematik çizimler açıkça etiketlidir. Dört Mevsim'in sağlanmış logosu bulunmadığından adı metin olarak yer alır. Müşteri yorumu, teyitsiz başarı sayacı ve fiyat üretilmez.

## Yönetim paneli

Adres: `/demo/admin/` (arama motorlarına kapalı, site haritasında yok). Kurulumda belirlenen kullanıcı adı ve şifreyle giriş yapılır.

- **Projeler:** ad, kategori, durum, yıl, konum, kısa özet, anlatım, üstlenilen işler, sonuçlar, künye satırları, ilgili hizmetler, kapak fotoğrafı, galeri (en fazla 12), fotoğraf yoksa şematik simge, isteğe bağlı müşteri logosu (referans sütunu ve Referanslar sayfası). İlk 6 yayındaki proje ana sayfada görünür; sıralama panelden değiştirilir.
- **Mağaza ürünleri:** ad, kategori, marka, stok kodu, fiyat (TL, `1.250,00` yazımı), KDV dahil/hariç, birim, stok durumu, kısa açıklama, açıklama, teknik özellikler, fotoğraflar. Fiyat boşsa “Fiyat için sorun” gösterilir.
- Taslak olarak saklama, düzenleme, silme. Kayıt öncesi zorunlu alan, benzersiz başlık/açıklama, fotoğraf açıklaması ve `content-rules.js` yasaklı ifade denetimleri; sunucu da kayıtları ve fotoğrafları ayrıca doğrular.
- Fotoğraflar tarayıcıda en fazla 1600 px’e küçültülür, WebP’ye çevrilir (konum gibi kamera bilgileri silinir) ve `uploads/` altına kaydedilir; kullanılmayan yüklemeler silinir. Kayıtlar hemen yayına girer.

Mağaza `/demo/magaza/` altında, yayında en az bir ürün olduğunda otomatik oluşur; menüye “Mağaza” eklenir. Ödeme alınmaz: ürün sayfası seçilen miktarla okunabilir bir WhatsApp sipariş mesajı hazırlar, gönderimi ziyaretçi tamamlar.

Güvenlik: oturum çerezi `HttpOnly` ve `SameSite=Strict`; her değişiklik isteği CSRF anahtarı ister; 15 dakikada 5 hatalı girişte o IP kilitlenir; `app/` klasörü ve gizli dosyalar web’den erişilemez; `uploads/` içinde betik çalışmaz; panel sayfası sıkı bir Content-Security-Policy ile yüklenir.

## Tasarım çalışma kaynakları

- [Claude Code Frontend Design Toolkit](https://github.com/wilwaldon/Claude-Code-Frontend-Design-Toolkit), kaynak revizyonu `2a6d095`.
- [Anthropic frontend-design](https://github.com/anthropics/claude-code/tree/main/plugins/frontend-design), `fbe20e0`.
- [Addy Osmani web-quality-skills](https://github.com/addyosmani/web-quality-skills), `afa8da9`; accessibility ve web-quality-audit.

Toolkit, içerik, varlıklar, uygulama ve kalite görevlerinin ayrımında kullanılmıştır. Kullanıcının seçtiği görsel referans, becerilerin genel estetik tercihlerinden önceliklidir. Ham kaynak dokümanlar ve şirket içi notlar bu depoda yer almaz.

## Doğrulama sınırı

Statik kontroller tüm üretilen sayfaları kapsar. Tarayıcı kontrolleri ana akışları ve seçili masaüstü/mobil görünümlerini kapsar; otomatik kontroller tek başına WCAG uygunluğu veya saha performansı garantisi değildir. Mağaza bir ürün kataloğu ve WhatsApp sipariş akışıdır; ödeme altyapısı bu deponun parçası değildir. Hostinger sürümü yerelde PHP 8.3 + MariaDB 10.11 ile tarayıcıda uçtan uca test edilir; sahte bir LiteSpeed önbelleğiyle panel oturumunun önbelleğe takılmadığı ayrıca denenir.
