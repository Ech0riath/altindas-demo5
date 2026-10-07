# Altındaş Mühendislik & Elektrik — demo5

- Hostinger (PHP + MySQL): alan adının `/demo/` klasörü — panelden yapılan kayıtlar anında yayına girer.
- [GitHub Pages](https://ech0riath.github.io/altindas-demo5/) (statik demo): `src/content.json` ile üretilir.

`ornek3.html` saha panosu tasarımından geliştirilen Türkçe kurumsal web sitesi. Yedi hizmet, proje dosyaları, mağaza, referanslar, kurumsal, iletişim, keşif ve SSS sayfaları aynı tasarım sistemini kullanır. Proje ve ürünler `/admin/` yönetim panelinden eklenir.

## Yapı

Bütün sayfa şablonları tek yerdedir: `app/site.php`. Aynı şablonlar iki biçimde çalışır:

- **Hostinger:** `web/index.php` her isteği karşılar; projeler, ürünler ve referans logoları MySQL’den, hizmet/kurumsal/SSS metinleri `content.json`’dan okunur.
- **GitHub Pages:** `scripts/export.php` şablonları `src/content.json` ile çalıştırıp `dist/` altına statik HTML yazar.

```sh
npm run build              # PHP 8.1+ ile dist/ (GitHub Pages çıktısı)
npm run check              # bağlantı, semantik ve içerik denetimi (Node 22+)
npm run preview            # http://127.0.0.1:4325/altindas-demo5/
npm run package:hostinger  # build/hostinger (Hostinger paketi)
```

- `app/site.php`: sayfa şablonları. `app/store.php`: MySQL tabloları ve kayıt işlemleri. `app/admin.php`: oturum, giriş sınırı, CSRF, panel API’si, kurulum sihirbazı. `app/bootstrap.php`: rota çözümü.
- `web/`: Hostinger giriş noktası, `.htaccess` kuralları, `uploads/` koruması.
- `src/content.json`: hizmet, proje, referans ve ürün verileri (Pages’in kaynağı, Hostinger’ın ilk verisi).
- `src/site.css`, `src/site.js`: tasarım sistemi ve etkileşimler. `src/admin.js`, `src/admin.css`: yönetim paneli.
- `src/content-rules.js`: yasaklı ifade kuralları; hem `check.mjs` hem panel kullanır.
- `scripts/check.mjs`: denetimler. Hostinger çıktısı için `SITE_BASE` ve `SITE_ORIGIN` ile çalıştırılır.
- `public/assets`: gerçek saha fotoğrafları, marka varlıkları ve lisanslı yerel fontlar.

`main` dalına gönderilen her değişiklikte iki iş akışı çalışır: `pages.yml` Pages’e yayınlar; `hostinger.yml` şablonları denetleyip Hostinger paketini `hostinger` dalına yazar. Hostinger bu dalı hPanel Git ile `public_html/demo` klasörüne çeker. İki adres de demo olduğundan arama motorlarına `noindex,follow` verilir; Hostinger’da `app/config.php` içindeki `indexable` ayarı açılınca dizinlenir.

## Hostinger kurulumu (bir kez)

1. hPanel → **Gelişmiş → PHP Yapılandırması**: PHP 8.2 veya 8.3.
2. hPanel → **Veritabanları → MySQL**: veritabanı ve kullanıcı oluşturun (hPanel şifresinden farklı bir şifreyle).
3. hPanel → **Gelişmiş → Git**: depo `https://github.com/Ech0riath/altindas-demo5.git`, dal `hostinger`, klasör `demo` → **Oluştur**, ardından **Dağıt**. **Otomatik Dağıtım** webhook adresini GitHub’da depo **Settings → Webhooks** bölümüne ekleyin.
4. `https://<alan-adı>/demo/admin/` adresi kurulum sihirbazını açar: veritabanı bilgileri ve yönetici hesabı girilir. Sihirbaz tabloları oluşturur, mevcut projeleri aktarır ve `app/config.php` dosyasını yazar; kurulumdan sonra kilitlenir.

Yedekleme: MySQL verisi phpMyAdmin’den dışa aktarılabilir; panel yüklemeleri `demo/uploads/` klasöründedir. Bu iki şey git’te değil, sunucudadır.

## İşlevler

Referanslar tek dikey sütunda sürekli kayar (logo başına yaklaşık 9,6 saniye). Kullanıcı kararıyla durdurma düğmesi ve fareyle durma yoktur. İşletim sisteminde “hareketi azalt” tercihi açık ziyaretçilere logolar sabit liste olarak gösterilir.

Proje kartları ilgili ayrıntı sayfalarına gider. Proje dizini kategoriye göre filtrelenebilir. Ana sayfadaki ölçüm paneli etkileşimli bir temsilî gösterimdir; canlı tesis verisi değildir.

Keşif formu mesajı yalnız tarayıcıda hazırlar. Gönderilmeden önce önizleme gösterilir; ziyaretçi WhatsApp üzerinden gönderir. Gönderilmemiş talep için başarı bildirimi kullanılmaz.

Gerçek saha fotoğrafı olmayan projelerde şematik çizimler açıkça etiketlidir. Dört Mevsim'in sağlanmış logosu bulunmadığından adı metin olarak yer alır. Müşteri yorumu, teyitsiz başarı sayacı ve fiyat üretilmez.

## Yönetim paneli

Panel her iki adreste de `/admin/` altındadır (arama motorlarına kapalı, site haritasında yok). Arayüz aynıdır (`src/admin.js`); kayıt yolu sunucuya göre değişir.

**Hostinger:** Kurulumda belirlenen kullanıcı adı ve şifreyle giriş yapılır. Kayıtlar `admin/api` üzerinden MySQL’e yazılır ve hemen yayına girer; fotoğraflar `uploads/` altına kaydedilir, kullanılmayan yüklemeler silinir, git ile gelen `assets/` dosyalarına dokunulmaz. Oturum çerezi `HttpOnly`, `SameSite=Strict`; her değişiklik isteği CSRF anahtarı ister; 15 dakikada 5 hatalı girişte o IP kilitlenir; yüklenen dosya türü sunucuda doğrulanır ve `uploads/` içinde betik çalıştırılmaz.

**GitHub Pages:** Sunucu tarafı kod çalışmadığı için panel kaydı doğrudan GitHub API ile yapar:

1. Panel, kullanıcının ince ayarlı (fine-grained) GitHub erişim anahtarıyla bağlanır. Paneldeki bağlantı anahtar oluşturma sayfasını ad, 90 gün süre, `Contents: Read and write` ve `Actions: Read-only` izinleriyle hazır açar; depo seçimi (`altindas-demo5`) elle yapılır.
2. Proje veya ürün kaydedildiğinde fotoğraflar tarayıcıda en fazla 1600 px’e küçültülür, WebP’ye çevrilir (konum gibi kamera bilgileri silinir) ve `public/assets/projects|products|references/` altına yazılır. `src/content.json` ile birlikte `main` dalına tek commit olarak işlenir. Artık kullanılmayan fotoğraflar aynı commit’te silinir.
3. Commit, mevcut Pages iş akışını tetikler: `build` → `check` → yayın. Panel iş akışını izler ve yalnız yayın gerçekten tamamlandığında “Yayında” der. Denetim başarısız olursa site önceki hâliyle kalır.

Panelin yaptıkları:

- **Projeler:** ad, kategori, durum, yıl, konum, kısa özet, anlatım, üstlenilen işler, sonuçlar, künye satırları, ilgili hizmetler, kapak fotoğrafı, galeri (en fazla 12), fotoğraf yoksa şematik simge, isteğe bağlı müşteri logosu (referans sütunu ve Referanslar sayfası). İlk 6 yayındaki proje ana sayfada görünür; sıralama panelden değiştirilir.
- **Mağaza ürünleri:** ad, kategori, marka, stok kodu, fiyat (TL, `1.250,00` yazımı), KDV dahil/hariç, birim, stok durumu, kısa açıklama, açıklama, teknik özellikler, fotoğraflar. Fiyat boşsa “Fiyat için sorun” gösterilir.
- Taslak olarak saklama, düzenleme, silme. Kayıt öncesi zorunlu alan, benzersiz başlık/açıklama, fotoğraf açıklaması ve `content-rules.js` yasaklı ifade denetimleri.

Mağaza `/magaza/` altında yayında en az bir ürün olduğunda otomatik oluşur; menüye “Mağaza” eklenir, alt bilgideki eski dış mağaza bağlantısının yerini alır. Ürün sayfası seçilen miktarla okunabilir bir WhatsApp sipariş mesajı hazırlar, gönderimi ziyaretçi tamamlar.

### Kartla ödeme (iyzico, yalnız Hostinger)

Panelde **Siparişler** sekmesinden iyzico test (sandbox) API anahtarı ve gizli anahtar girilip “Kartla satın alın” açılır. Anahtarlar `settings` tablosunda durur; gizli anahtar tarayıcıya geri gönderilmez. Yalnız `sandbox-` ile başlayan anahtarlar kabul edilir: **canlı ödeme bu sürümde kapalıdır** ve sayfalarda “Test modu” uyarısı görünür.

1. KDV dahil fiyatlı ve stoktaki ürünlerde ürün sayfası, seçilen miktarla `odeme/` sayfasına gider. Ziyaretçi ad, iletişim, T.C. kimlik no ve teslimat adresini yazar.
2. Sunucu siparişi `orders` tablosuna “Ödeme bekleniyor” olarak yazar, iyzico Ortak Ödeme Formu’nu başlatır (IYZWSv2 imzası, resmî `iyzipay-php` SDK’sıyla aynı; site bağımlılıksız kaldığı için SDK kopyalanmadı) ve ziyaretçiyi iyzico ödeme sayfasına yönlendirir. Kart bilgisi bu siteye gelmez.
3. iyzico ziyaretçiyi `odeme/sonuc/` adresine geri gönderir. Sunucu ödemeyi iyzico’dan sorgular; durum, sipariş numarası, tutar, para birimi, fraud durumu ve yanıt imzası tutarsa sipariş “Ödendi”, ödeme reddedildiyse “Ödeme başarısız”, ödeme alınmış ama bir kontrol tutmadıysa “Kontrol gerekiyor” olur. Aynı dönüş iki kez işlenmez.
4. Panel siparişleri alıcı ve adres bilgisiyle listeler; bekleyen bir siparişin durumu “Durumu sorgula” ile iyzico’dan yeniden alınabilir. Aynı IP’den saatte en fazla 10 sipariş başlatılabilir. T.C. kimlik no yalnız iyzico’ya iletilir, saklanmaz.

Canlıya geçmeden önce: iyzico üye iş yeri onayı, mesafeli satış sözleşmesi, ön bilgilendirme formu ve iade/teslimat koşulları sayfaları (iyzico başvurusunda istenir) ve kargo ücretinin nasıl alınacağı netleşmelidir. Canlı ortam bu adımlardan sonra ayrı bir değişiklikle açılır. Yerelde `ALTINDAS_IYZICO_BASE` ortam değişkeni (yalnız PHP yerleşik sunucusunda) sahte bir iyzico sunucusuna yönlendirir.

Güvenlik notları: Anahtar yalnız tarayıcıda (varsayılan olarak sekme kapanınca silinen `sessionStorage`, istenirse `localStorage`) tutulur ve yalnız `api.github.com`’a gönderilir. Panel sayfası sıkı bir Content-Security-Policy ile yüklenir ve kullanıcı içeriğini HTML olarak yorumlamaz. `ech0riath.github.io` alanı kullanıcının diğer Pages sitelerince paylaşıldığından “Bu cihazda hatırla” yalnız kişisel cihazlarda seçilmelidir. Anahtarı yalnız bu depoyla sınırlayın; sızdığından şüphelenirseniz GitHub ayarlarından iptal edin.

## Tasarım çalışma kaynakları

- [Claude Code Frontend Design Toolkit](https://github.com/wilwaldon/Claude-Code-Frontend-Design-Toolkit), kaynak revizyonu `2a6d095`.
- [Anthropic frontend-design](https://github.com/anthropics/claude-code/tree/main/plugins/frontend-design), `fbe20e0`.
- [Addy Osmani web-quality-skills](https://github.com/addyosmani/web-quality-skills), `afa8da9`; accessibility ve web-quality-audit.

Toolkit, içerik, varlıklar, uygulama ve kalite görevlerinin ayrımında kullanılmıştır. Kullanıcının seçtiği görsel referans, becerilerin genel estetik tercihlerinden önceliklidir. Ham kaynak dokümanlar ve şirket içi notlar bu depoda yer almaz.

## Doğrulama sınırı

Statik kontroller tüm üretilen sayfaları kapsar. Tarayıcı kontrolleri ana akışları ve seçili masaüstü/mobil görünümlerini kapsar; otomatik kontroller tek başına WCAG uygunluğu veya saha performansı garantisi değildir. Kartla ödeme akışı yerelde imzayı doğrulayan sahte bir iyzico sunucusuyla (başarılı, reddedilen, tutarı değiştirilmiş ve imzası bozuk yanıtlar) uçtan uca denenmiştir; gerçek iyzico test ortamıyla ilk deneme Hostinger’da anahtarlar girildikten sonra yapılmalıdır. PHP şablonlarının Pages çıktısı, önceki Node üreticisinin çıktısıyla bayt bayt karşılaştırılarak doğrulanmıştır. Hostinger sürümü yerelde PHP 8.3 + MariaDB 10.11 ile tarayıcıda uçtan uca test edilmiştir; Hostinger sunucusundaki ilk kurulum ayrıca doğrulanmalıdır.
