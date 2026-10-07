# Altındaş Mühendislik & Elektrik — demo5

[Canlı site](https://ech0riath.github.io/altindas-demo5/)

`ornek3.html` saha panosu tasarımından geliştirilen Türkçe kurumsal web sitesi. Yedi hizmet, proje dosyaları, mağaza, referanslar, kurumsal, iletişim, keşif ve SSS sayfaları aynı tasarım sistemini kullanır. Proje ve ürünler `/admin/` yönetim panelinden eklenir.

## Çalıştırma

Node.js 22 veya üzeri yeterlidir. Üretim bağımlılığı yoktur.

```sh
npm run build
npm run check
npm run preview
```

Önizleme: `http://127.0.0.1:4325/altindas-demo5/`

- `src/content.json`: hizmet, proje, referans logosu ve mağaza ürünü verileri. Panel bu dosyayı yazar.
- `src/site.css`: responsive tasarım sistemi.
- `src/site.js`: mobil menü, panel seçimi, logo hareketi, proje/ürün filtresi, form önizlemesi, sipariş miktarı, fotoğraf görüntüleyici.
- `src/admin.js`, `src/admin.css`: yönetim paneli (yalnız `/admin/` sayfasında yüklenir).
- `src/content-rules.js`: yasaklı ifade kuralları; hem `check.mjs` hem panel kullanır.
- `scripts/build.mjs`: ortak sayfa şablonları ve statik üretim.
- `scripts/check.mjs`: bağlantı, varlık, semantik ve temel yayın denetimleri.
- `public/assets`: gerçek saha fotoğrafları, marka varlıkları ve lisanslı yerel fontlar.

`main` dalına gönderilen değişiklikler GitHub Actions üzerinden Pages'e yayınlanır. Taban yol tek merkezden `/altindas-demo5/` olarak tanımlanır. Bu bir demo adresidir; arama motorları için `noindex,follow` kullanılır.

## İşlevler

Referanslar tek dikey sütunda 48 saniyelik çevrimle kayar. Fare üzerine geldiğinde durur; durdurma/başlatma düğmesi vardır. Hareketi azaltma tercihinde varsayılan statiktir, ziyaretçi isterse başlatabilir.

Proje kartları ilgili ayrıntı sayfalarına gider. Proje dizini kategoriye göre filtrelenebilir. Ana sayfadaki ölçüm paneli etkileşimli bir temsilî gösterimdir; canlı tesis verisi değildir.

Keşif formu mesajı yalnız tarayıcıda hazırlar. Gönderilmeden önce önizleme gösterilir; ziyaretçi WhatsApp üzerinden gönderir. GitHub Pages üzerinde çalışmayan PHP formu veya gönderilmemiş talep için başarı bildirimi kullanılmaz.

Gerçek saha fotoğrafı olmayan projelerde şematik çizimler açıkça etiketlidir. Dört Mevsim'in sağlanmış logosu bulunmadığından adı metin olarak yer alır. Müşteri yorumu, teyitsiz başarı sayacı ve fiyat üretilmez.

## Yönetim paneli

Adres: `https://ech0riath.github.io/altindas-demo5/admin/` (arama motorlarına kapalı, site haritasında yok).

GitHub Pages sunucu tarafı kod çalıştırmadığı için panel tarayıcıda çalışır ve kaydı doğrudan GitHub API ile yapar:

1. Panel, kullanıcının ince ayarlı (fine-grained) GitHub erişim anahtarıyla bağlanır. Paneldeki bağlantı anahtar oluşturma sayfasını ad, 90 gün süre, `Contents: Read and write` ve `Actions: Read-only` izinleriyle hazır açar; depo seçimi (`altindas-demo5`) elle yapılır.
2. Proje veya ürün kaydedildiğinde fotoğraflar tarayıcıda en fazla 1600 px’e küçültülür, WebP’ye çevrilir (konum gibi kamera bilgileri silinir) ve `public/assets/projects|products|references/` altına yazılır. `src/content.json` ile birlikte `main` dalına tek commit olarak işlenir. Artık kullanılmayan fotoğraflar aynı commit’te silinir.
3. Commit, mevcut Pages iş akışını tetikler: `build` → `check` → yayın. Panel iş akışını izler ve yalnız yayın gerçekten tamamlandığında “Yayında” der. Denetim başarısız olursa site önceki hâliyle kalır.

Panelin yaptıkları:

- **Projeler:** ad, kategori, durum, yıl, konum, kısa özet, anlatım, üstlenilen işler, sonuçlar, künye satırları, ilgili hizmetler, kapak fotoğrafı, galeri (en fazla 12), fotoğraf yoksa şematik simge, isteğe bağlı müşteri logosu (referans sütunu ve Referanslar sayfası). İlk 6 yayındaki proje ana sayfada görünür; sıralama panelden değiştirilir.
- **Mağaza ürünleri:** ad, kategori, marka, stok kodu, fiyat (TL, `1.250,00` yazımı), KDV dahil/hariç, birim, stok durumu, kısa açıklama, açıklama, teknik özellikler, fotoğraflar. Fiyat boşsa “Fiyat için sorun” gösterilir.
- Taslak olarak saklama, düzenleme, silme. Kayıt öncesi zorunlu alan, benzersiz başlık/açıklama, fotoğraf açıklaması ve `content-rules.js` yasaklı ifade denetimleri.

Mağaza `/magaza/` altında yayında en az bir ürün olduğunda otomatik oluşur; menüye “Mağaza” eklenir, alt bilgideki eski dış mağaza bağlantısının yerini alır. Ödeme alınmaz: ürün sayfası seçilen miktarla okunabilir bir WhatsApp sipariş mesajı hazırlar, gönderimi ziyaretçi tamamlar.

Güvenlik notları: Anahtar yalnız tarayıcıda (varsayılan olarak sekme kapanınca silinen `sessionStorage`, istenirse `localStorage`) tutulur ve yalnız `api.github.com`’a gönderilir. Panel sayfası sıkı bir Content-Security-Policy ile yüklenir ve kullanıcı içeriğini HTML olarak yorumlamaz. `ech0riath.github.io` alanı kullanıcının diğer Pages sitelerince paylaşıldığından “Bu cihazda hatırla” yalnız kişisel cihazlarda seçilmelidir. Anahtarı yalnız bu depoyla sınırlayın; sızdığından şüphelenirseniz GitHub ayarlarından iptal edin.

## Tasarım çalışma kaynakları

- [Claude Code Frontend Design Toolkit](https://github.com/wilwaldon/Claude-Code-Frontend-Design-Toolkit), kaynak revizyonu `2a6d095`.
- [Anthropic frontend-design](https://github.com/anthropics/claude-code/tree/main/plugins/frontend-design), `fbe20e0`.
- [Addy Osmani web-quality-skills](https://github.com/addyosmani/web-quality-skills), `afa8da9`; accessibility ve web-quality-audit.

Toolkit, içerik, varlıklar, uygulama ve kalite görevlerinin ayrımında kullanılmıştır. Kullanıcının seçtiği görsel referans, becerilerin genel estetik tercihlerinden önceliklidir. Ham kaynak dokümanlar ve şirket içi notlar bu depoda yer almaz.

## Doğrulama sınırı

Statik kontroller tüm üretilen sayfaları kapsar. Tarayıcı kontrolleri ana akışları ve seçili masaüstü/mobil görünümlerini kapsar; otomatik kontroller tek başına WCAG uygunluğu veya saha performansı garantisi değildir. Mağaza bir ürün kataloğu ve WhatsApp sipariş akışıdır; ödeme altyapısı bu deponun parçası değildir. Panelin GitHub API akışı uçtan uca, bellekte çalışan sahte bir GitHub API’siyle tarayıcıda test edilmiştir; gerçek anahtarla ilk kayıt canlıda ayrıca doğrulanmalıdır.
