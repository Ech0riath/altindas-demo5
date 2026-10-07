# Altındaş Mühendislik & Elektrik — demo5

[Canlı site](https://ech0riath.github.io/altindas-demo5/)

`ornek3.html` saha panosu tasarımından geliştirilen, 23 sayfalık Türkçe kurumsal web sitesi. Yedi hizmet, altı proje dosyası, referanslar, kurumsal, iletişim, keşif ve SSS sayfaları aynı tasarım sistemini kullanır.

## Çalıştırma

Node.js 22 veya üzeri yeterlidir. Üretim bağımlılığı yoktur.

```sh
npm run build
npm run check
npm run preview
```

Önizleme: `http://127.0.0.1:4325/altindas-demo5/`

- `src/content.json`: hizmet ve proje metinleri.
- `src/site.css`: responsive tasarım sistemi.
- `src/site.js`: mobil menü, panel seçimi, logo hareketi, proje filtresi, form önizlemesi.
- `scripts/build.mjs`: ortak sayfa şablonları ve statik üretim.
- `scripts/check.mjs`: bağlantı, varlık, semantik ve temel yayın denetimleri.
- `public/assets`: gerçek saha fotoğrafları, marka varlıkları ve lisanslı yerel fontlar.

`main` dalına gönderilen değişiklikler GitHub Actions üzerinden Pages'e yayınlanır. Taban yol tek merkezden `/altindas-demo5/` olarak tanımlanır. Bu bir demo adresidir; arama motorları için `noindex,follow` kullanılır.

## İşlevler

Referanslar tek dikey sütunda 48 saniyelik çevrimle kayar. Fare üzerine geldiğinde durur; durdurma/başlatma düğmesi vardır. Hareketi azaltma tercihinde varsayılan statiktir, ziyaretçi isterse başlatabilir.

Proje kartları ilgili ayrıntı sayfalarına gider. Proje dizini kategoriye göre filtrelenebilir. Ana sayfadaki ölçüm paneli etkileşimli bir temsilî gösterimdir; canlı tesis verisi değildir.

Keşif formu mesajı yalnız tarayıcıda hazırlar. Gönderilmeden önce önizleme gösterilir; ziyaretçi WhatsApp üzerinden gönderir. GitHub Pages üzerinde çalışmayan PHP formu veya gönderilmemiş talep için başarı bildirimi kullanılmaz.

Gerçek saha fotoğrafı olmayan projelerde şematik çizimler açıkça etiketlidir. Dört Mevsim'in sağlanmış logosu bulunmadığından adı metin olarak yer alır. Müşteri yorumu, teyitsiz başarı sayacı ve fiyat üretilmez.

## Tasarım çalışma kaynakları

- [Claude Code Frontend Design Toolkit](https://github.com/wilwaldon/Claude-Code-Frontend-Design-Toolkit), kaynak revizyonu `2a6d095`.
- [Anthropic frontend-design](https://github.com/anthropics/claude-code/tree/main/plugins/frontend-design), `fbe20e0`.
- [Addy Osmani web-quality-skills](https://github.com/addyosmani/web-quality-skills), `afa8da9`; accessibility ve web-quality-audit.

Toolkit, içerik, varlıklar, uygulama ve kalite görevlerinin ayrımında kullanılmıştır. Kullanıcının seçtiği görsel referans, becerilerin genel estetik tercihlerinden önceliklidir. Ham kaynak dokümanlar ve şirket içi notlar bu depoda yer almaz.

## Doğrulama sınırı

Statik kontroller tüm üretilen sayfaları kapsar. Tarayıcı kontrolleri ana akışları ve seçili masaüstü/mobil görünümlerini kapsar; otomatik kontroller tek başına WCAG uygunluğu veya saha performansı garantisi değildir. Mağaza mevcut dış sisteme bağlantıdır; ödeme altyapısı bu deponun parçası değildir.
