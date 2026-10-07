<?php
// Sitenin bütün sayfa şablonları. Aynı kod iki yerde çalışır:
// Hostinger'da her istekte (veri MySQL'den), GitHub Pages için ise
// scripts/export.php ile statik dosya üretiminde (veri content.json'dan).
declare(strict_types=1);

namespace Altindas;

function esc(mixed $s): string
{
    return strtr((string) ($s ?? ''), [
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
        '"' => '&quot;',
        "'" => '&#39;',
    ]);
}

// encodeURIComponent eşdeğeri.
function uri_component(string $s): string
{
    return strtr(rawurlencode($s), ['%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')']);
}

function tr_upper(string $s): string
{
    return mb_strtoupper(strtr($s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
}

function price_text(float|int $price): string
{
    return '₺' . number_format((float) $price, 2, ',', '.');
}

final class Site
{
    public const PHONE = '+90 538 447 56 76';
    public const WA = 'https://wa.me/905384475676';
    private const ICONS = [
        'bolt' => 'm13 2-9 12h7l-1 8 10-12h-7l1-8Z',
        'trafo' => 'M5 3v3m14-3v3M5 18v3m14-3v3M3 6h18v12H3zM8 9v6m4-6v6m4-6v6',
        'shield' => 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-6',
        'circuit' => 'M4 4h6v6H4zM14 14h6v6h-6zM14 4h6v6h-6zM4 14h6v6H4zM10 7h4M7 10v4m10-4v4m-7 3h4',
        'building' => 'M4 21V3h16v18M2 21h20M8 7h2m4 0h2M8 11h2m4 0h2M8 15h2m4 0h2M10 21v-3h4v3',
        'camera' => 'M3 6h13v12H3zM16 10l5-3v10l-5-3',
        'sun' => 'M12 3v2m0 14v2M3 12h2m14 0h2M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'arrow' => 'M5 12h14m-6-6 6 6-6 6',
        'arrowUp' => 'M6 18 18 6M6 6h12v12',
        'check' => 'm5 12 4 4L19 6',
        'phone' => 'M6 3H3v4c0 8 6 14 14 14h4v-4l-5-2-2 2-7-7 2-2-3-5Z',
        'pin' => 'M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'mail' => 'M3 5h18v14H3zM3 5l9 8 9-8',
        'wave' => 'M2 12c4-14 6-14 10 0s6 14 10 0',
    ];
    private const SERVICE_ICONS = [
        'kompanzasyon' => 'wave',
        'trafo-bakimi' => 'trafo',
        'isletme-sorumlulugu' => 'shield',
        'endustriyel-otomasyon' => 'circuit',
        'elektrik-taahhut-proje' => 'bolt',
        'kamera-sistemleri' => 'camera',
        'gunes-enerji-sistemleri' => 'sun',
    ];
    private const STEPS = [
        ['Keşif', 'İhtiyacınızı sahada dinler, tesisin mevcut durumunu birlikte belirleriz.'],
        ['Ölçüm ve analiz', 'Ölçüm sonuçlarını ve teknik ihtiyaçları değerlendiririz.'],
        ['Teklif', 'Keşiften sonra 2–3 iş gününde çözüm ve fiyatı sunarız.'],
        ['Uygulama', 'İş programını ve gerekli enerji kesintilerini birlikte planlarız.'],
        ['Test ve teslim', 'Testleri yapar, rapor ve dokümantasyonla teslim ederiz.'],
        ['Periyodik takip', 'Hizmet ve bakım sözleşmesine uygun kontrol planı oluştururuz.'],
    ];

    private array $projects;
    private array $products;
    private bool $hasStore;
    private array $refs;
    private string $base;
    private string $origin;

    /**
     * @param array $data    services, projects, references, products, about, faqs
     * @param array $options base, origin, backend ('github'|'php'), host,
     *                       robots, repo (github), adminApi (php)
     */
    public function __construct(private array $data, private array $options)
    {
        $this->base = $options['base'];
        $this->origin = $options['origin'];
        // Taslaklar sitede gösterilmez.
        $this->projects = array_values(array_filter($data['projects'] ?? [], fn ($p) => empty($p['draft'])));
        $this->products = array_values(array_filter($data['products'] ?? [], fn ($p) => empty($p['draft'])));
        $this->hasStore = count($this->products) > 0;
        $slugs = array_column($this->projects, 'slug');
        $this->refs = array_values(array_filter(
            $data['references'] ?? [],
            fn ($r) => empty($r['project']) || in_array($r['project'], $slugs, true),
        ));
    }

    // ------------------------------------------------------------ helpers
    public function url(string $p): string
    {
        return $this->base . ltrim($p, '/');
    }

    private function asset(mixed $src): string
    {
        return $this->url((string) $src);
    }

    // CSS/JS adresine içerik özeti ekler; güncellemeden sonra tarayıcı eski dosyayı kullanmaz.
    private function versioned(string $path): string
    {
        $v = isset($this->options['assetVersion']) ? ($this->options['assetVersion'])($path) : null;
        return $this->url($path) . ($v ? '?v=' . $v : '');
    }

    private function icon(?string $name, string $cls = ''): string
    {
        $d = self::ICONS[$name ?? ''] ?? self::ICONS['bolt'];
        return '<svg class="icon ' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
    }

    private function btn(string $label, string $href, string $kind = 'primary'): string
    {
        return '<a class="btn btn-' . $kind . '" href="' . $href . '">' . $label . $this->icon('arrow') . '</a>';
    }

    private static function map(array $items, callable $fn): string
    {
        $out = '';
        foreach (array_values($items) as $i => $item) {
            $out .= $fn($item, $i);
        }
        return $out;
    }

    private function brand(): string
    {
        return '<a class="brand" href="' . $this->url('') . '" aria-label="Altındaş Mühendislik ve Elektrik, ana sayfa"><span class="brand-mark"><img src="' . $this->url('assets/logo.png') . '" width="32" height="37" alt=""></span><span><b>ALTINDAŞ</b><small>MÜHENDİSLİK &amp; ELEKTRİK</small></span></a>';
    }

    private function nav(string $active): string
    {
        $links = [['Hizmetler', 'hizmetler/'], ['Projeler', 'projeler/']];
        if ($this->hasStore) {
            $links[] = ['Mağaza', 'magaza/'];
        }
        $links[] = ['Kurumsal', 'kurumsal/'];
        $links[] = ['İletişim', 'iletisim/'];
        $items = self::map($links, fn ($l) => '<a href="' . $this->url($l[1]) . '"' . ($active === $l[1] ? ' aria-current="page"' : '') . '>' . $l[0] . '</a>');
        return '<div class="utility"><span>' . $this->icon('pin') . 'Aliağa, İzmir <span class="utility-extra">/ Sahada mühendislik, güvenle enerji.</span></span><a href="tel:+905384475676">' . $this->icon('phone') . self::PHONE . '</a></div><header class="site-header"><div class="nav-wrap">' . $this->brand() . '<button class="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Menüyü aç">' . $this->icon('menu') . '</button><nav id="main-nav" aria-label="Ana menü">' . $items . '</nav><a class="nav-cta" href="' . $this->url('kesif-talebi/') . '">Keşif isteyin' . $this->icon('arrowUp') . '</a></div></header>';
    }

    private function footer(): string
    {
        $store = $this->hasStore
            ? '<a href="' . $this->url('magaza/') . '">Mağaza</a>'
            : '<a href="https://altindasmuhendislik.com/shop/">Online mağaza ↗</a>';
        return '<footer class="footer wrap"><div class="footer-grid"><div>' . $this->brand() . '<p class="slogan">Enerjiniz Güvende,<br><span>Geleceğiniz Aydınlık.</span></p><p>Aliağa’dan sanayi tesislerine, işletmelere ve yaşam alanlarına.</p></div><div><h2>İletişim</h2><p>Yeni Mah. Anadolu Cad. No: 24A<br>35800 Aliağa / İzmir</p><a href="tel:+905384475676">' . self::PHONE . '</a><a href="mailto:info@altindasmuhendislik.com">info@altindasmuhendislik.com</a><p>Pazartesi–Cumartesi<br>08:30–18:00</p></div><div><h2>Keşfedin</h2><a href="' . $this->url('hizmetler/') . '">Hizmetlerimiz</a><a href="' . $this->url('projeler/') . '">Proje dosyaları</a><a href="' . $this->url('referanslar/') . '">Referanslarımız</a><a href="' . $this->url('sikca-sorulan-sorular/') . '">Sıkça sorulan sorular</a>' . $store . '</div><div><h2>Bizi takip edin</h2><a href="https://www.instagram.com/altindasmuhendislik/" target="_blank" rel="noopener noreferrer">Instagram ↗</a><a href="https://www.linkedin.com/company/altindasmuhendislik/" target="_blank" rel="noopener noreferrer">LinkedIn ↗</a><a href="https://www.facebook.com/altindasmuhendislik" target="_blank" rel="noopener noreferrer">Facebook ↗</a><a href="' . self::WA . '" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a></div></div><div class="footer-bottom"><span>© ' . gmdate('Y') . ' Altındaş Mühendislik &amp; Elektrik</span><a href="' . $this->url('gizlilik/') . '">Gizlilik ve iletişim bilgileri</a><a href="#top">Yukarı dön ↑</a></div></footer>';
    }

    private function page(string $route, string $title, string $description, string $body, string $active = '', ?array $schema = null, array $opts = []): string
    {
        $head = $opts['head'] ?? '';
        $assets = $opts['assets'] ?? '';
        $robots = $opts['robots'] ?? ($this->options['robots'] ?? 'noindex,follow');
        $canonical = $this->origin . $this->url($route);
        $schema ??= [
            '@context' => 'https://schema.org',
            '@type' => 'Electrician',
            'name' => 'Altındaş Mühendislik & Elektrik',
            'url' => $this->origin . $this->base,
            'telephone' => self::PHONE,
            'email' => 'info@altindasmuhendislik.com',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Yeni Mah. Anadolu Cad. No: 24A',
                'addressLocality' => 'Aliağa',
                'addressRegion' => 'İzmir',
                'postalCode' => '35800',
                'addressCountry' => 'TR',
            ],
        ];
        $json = str_replace('<', '\\' . 'u003c', json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $head . '<meta name="theme-color" content="#1B222B"><title>' . esc($title) . ' | Altındaş Mühendislik &amp; Elektrik</title><meta name="description" content="' . esc($description) . '"><meta name="robots" content="' . $robots . '"><link rel="canonical" href="' . $canonical . '"><meta property="og:type" content="website"><meta property="og:locale" content="tr_TR"><meta property="og:title" content="' . esc($title) . '"><meta property="og:description" content="' . esc($description) . '"><meta property="og:url" content="' . $canonical . '"><meta property="og:image" content="' . $this->origin . $this->url('assets/share.png') . '"><link rel="icon" href="' . $this->url('favicon.svg') . '" type="image/svg+xml"><link rel="preload" href="' . $this->url('assets/fonts/archivo-tr.woff2') . '" as="font" type="font/woff2" crossorigin><link rel="stylesheet" href="' . $this->versioned('assets/site.css') . '"><script type="module" src="' . $this->versioned('assets/site.js') . '"></script>' . $assets . '<script type="application/ld+json">' . $json . '</script></head><body id="top"><a class="skip-link" href="#main">İçeriğe geç</a><div class="page-shell">' . $this->nav($active) . '<main id="main">' . $body . '</main>' . $this->footer() . '</div></body></html>';
    }

    private function sectionHead(string $label, string $title, string $sub = '', string $link = ''): string
    {
        return '<div class="section-heading"><div><p class="eyebrow">' . $label . '</p><h2>' . $title . '</h2>' . ($sub !== '' ? '<p class="section-intro">' . $sub . '</p>' : '') . '</div>' . $link . '</div>';
    }

    private function intro(?array $parent, string $title, string $desc): string
    {
        return '<div class="wrap"><div class="breadcrumb"><a href="' . $this->url('') . '">Ana sayfa</a><span>/</span>' . ($parent ? '<a href="' . $this->url($parent[1]) . '">' . $parent[0] . '</a><span>/</span>' : '') . '<span>' . esc($title) . '</span></div><header class="page-intro"><span class="section-tick" aria-hidden="true"></span><h1>' . esc($title) . '</h1><p>' . esc($desc) . '</p></header></div>';
    }

    private function cta(): string
    {
        return '<section class="wrap cta-wrap"><div class="cta"><div><p class="eyebrow">Bir sonraki adım</p><h2>Tesisinizi birlikte<br><span>değerlendirelim.</span></h2><p>İhtiyacınızı anlatın. Sahanıza uygun çözümü ücretsiz keşifle birlikte planlayalım.</p></div><div class="cta-actions">' . $this->btn('Ücretsiz keşif isteyin', $this->url('kesif-talebi/')) . '<a class="cta-phone" href="tel:+905384475676">' . self::PHONE . $this->icon('phone') . '</a><small>İzmir · Manisa · Bergama · Balıkesir · Aydın</small></div></div></section>';
    }

    private function checkList(array $items): string
    {
        return '<ul class="check-list">' . self::map($items, fn ($t) => '<li>' . $this->icon('check') . '<span>' . esc($t) . '</span></li>') . '</ul>';
    }

    private function faqs(array $items): string
    {
        return '<div class="faq-list">' . self::map($items, fn ($f) => '<details><summary>' . esc($f['q']) . '<span aria-hidden="true">+</span></summary><div class="faq-answer"><p>' . esc($f['a']) . '</p></div></details>') . '</div>';
    }

    private function serviceCard(array $s, bool $featured = false): string
    {
        return '<a class="service-card ' . ($featured ? 'featured' : '') . '" href="' . $this->url('hizmetler/' . $s['slug'] . '/') . '"><div class="service-card-top">' . $this->icon(self::SERVICE_ICONS[$s['slug']] ?? null) . $this->icon('arrowUp') . '</div><div>' . ($featured ? '<p class="card-kicker">Sahanın merkezinde</p>' : '') . '<h3>' . esc($s['title']) . '</h3><p>' . esc($s['short']) . '</p><span class="text-link">Hizmeti inceleyin ' . $this->icon('arrow') . '</span></div>' . ($featured ? '<svg class="trafo-drawing" viewBox="0 0 260 200" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-width="1.4"><path d="M40 50h180v115H40zM55 165v15m150-15v15M70 50V27m60 23V27m60 23V27M55 27h30m30 0h30m30 0h30M30 185h200M60 70v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75"/><path d="m135 86-15 24h17l-7 23 25-30h-17l6-17" stroke="#FDB81D"/></g></svg>' : '') . '</a>';
    }

    private function drawing(?string $type = null, string $label = 'Şematik gösterim'): string
    {
        return '<div class="project-drawing">' . $this->icon($type ?? 'building') . '<span>' . $label . '</span><i aria-hidden="true"></i></div>';
    }

    private function projectCard(array $p): string
    {
        $picture = !empty($p['image'])
            ? '<img src="' . $this->asset($p['image']) . '" width="800" height="600" loading="lazy" alt="' . esc($p['imageAlt'] ?? '') . '">'
            : $this->drawing($p['drawing'] ?? null);
        $ongoing = str_contains(mb_strtolower((string) $p['status'], 'UTF-8'), 'devam') ? 'ongoing' : '';
        return '<a class="project-card" href="' . $this->url('projeler/' . $p['slug'] . '/') . '" data-category="' . esc($p['category']) . '"><div class="project-cover">' . $picture . '<span class="project-go" aria-hidden="true">' . $this->icon('arrowUp') . '</span></div><div class="project-body"><div class="project-meta"><span>' . esc($p['category']) . '</span><span class="status ' . $ongoing . '">' . esc($p['status']) . '</span></div><h3>' . esc($p['title']) . '</h3><p>' . esc($p['summary']) . '</p><span class="project-link">Proje dosyası ' . $this->icon('arrow') . '</span></div></a>';
    }

    private function galleryItem(array $g, string $loading = ' loading="lazy"'): string
    {
        return '<a class="gallery-item" href="' . $this->asset($g['src']) . '" data-lightbox><img src="' . $this->asset($g['src']) . '" alt="' . esc($g['alt'] ?? '') . '" width="' . ($g['width'] ?? null ?: 1600) . '" height="' . ($g['height'] ?? null ?: 1200) . '"' . $loading . '></a>';
    }

    private function gallery(?array $items, string $title): string
    {
        if (!$items) {
            return '';
        }
        return '<section class="media-gallery" aria-label="' . esc($title) . '"><h2>' . esc($title) . '</h2><div class="gallery-grid">' . self::map($items, fn ($g) => $this->galleryItem($g)) . '</div></section>';
    }

    private static function hasPrice(array $p): bool
    {
        return (is_int($p['price'] ?? null) || is_float($p['price'] ?? null)) && $p['price'] >= 0;
    }

    private function priceHTML(array $p): string
    {
        return self::hasPrice($p)
            ? '<strong>' . price_text($p['price']) . '</strong><small>KDV ' . esc(($p['vat'] ?? '') ?: 'dahil') . ' · ' . esc(($p['unit'] ?? '') ?: 'adet') . ' fiyatı</small>'
            : '<strong class="price-ask">Fiyat için sorun</strong><small>Güncel fiyatı WhatsApp’tan iletiyoruz</small>';
    }

    private static function stockClass(array $p): string
    {
        return ['Stokta' => '', 'Sipariş üzerine' => 'ongoing', 'Tükendi' => 'out'][$p['stock'] ?? ''] ?? 'ongoing';
    }

    private function productCard(array $p): string
    {
        $picture = !empty($p['image'])
            ? '<img src="' . $this->asset($p['image']) . '" width="800" height="600" loading="lazy" alt="' . esc($p['imageAlt'] ?? '') . '">'
            : $this->drawing('bolt', 'Ürün görseli eklenmedi');
        return '<a class="product-card" href="' . $this->url('magaza/' . $p['slug'] . '/') . '" data-category="' . esc($p['category']) . '"><div class="product-cover">' . $picture . '</div><div class="project-body"><div class="project-meta"><span>' . esc($p['category']) . '</span><span class="status ' . self::stockClass($p) . '">' . esc(($p['stock'] ?? '') ?: 'Sipariş üzerine') . '</span></div><h3>' . esc($p['title']) . '</h3><p>' . esc($p['summary']) . '</p><div class="product-price">' . $this->priceHTML($p) . '</div></div></a>';
    }

    private function orderMessage(array $p, string $qty): string
    {
        $unit = ($p['unit'] ?? '') ?: 'adet';
        $lines = [
            'Merhaba, mağazanızdaki ürün için sipariş vermek istiyorum.',
            '',
            'Ürün: ' . $p['title'],
            !empty($p['sku']) ? 'Stok kodu: ' . $p['sku'] : '',
            'Miktar: ' . $qty . ' ' . $unit,
            self::hasPrice($p) ? 'Listelenen fiyat: ' . price_text($p['price']) . ' (KDV ' . (($p['vat'] ?? '') ?: 'dahil') . ', ' . $unit . ' başına)' : '',
            'Ürün sayfası: ' . $this->origin . $this->url('magaza/' . $p['slug'] . '/'),
        ];
        $kept = [];
        foreach ($lines as $i => $l) {
            if ($l !== '' || $i === 1) {
                $kept[] = $l;
            }
        }
        return implode("\n", $kept);
    }

    private function wordmark(array $r): string
    {
        return '<span class="reference-wordmark">' . esc(($r['wordmark'] ?? '') ?: tr_upper((string) $r['name'])) . (!empty($r['wordmarkSmall']) ? '<small>' . esc($r['wordmarkSmall']) . '</small>' : '') . '</span>';
    }

    private function refItems(bool $clone = false): string
    {
        return '<div class="reference-group"' . ($clone ? ' aria-hidden="true"' : '') . '>' . self::map($this->refs, fn ($r) => '<div class="reference-logo">' . (!empty($r['logo']) ? '<img src="' . $this->asset($r['logo']) . '" width="180" height="80" loading="lazy" alt="' . ($clone ? '' : esc($r['name'])) . '">' : $this->wordmark($r)) . '</div>') . '</div>';
    }

    // Logo başına hız, ilk tasarımdaki 48 sn / 5 logo çevrimiyle aynı kalır.
    private function referenceColumn(): string
    {
        return '<aside class="reference-column" aria-label="Referanslarımız" style="--reference-cycle:' . number_format(count($this->refs) * 9.6, 1, '.', '') . 's"><div class="reference-head"><span class="eyebrow">Birlikte çalıştığımız kurumlar</span><h3>Güven, sahada<br>kazanılır.</h3></div><div class="reference-window"><div class="reference-track">' . $this->refItems() . $this->refItems(true) . '</div></div><div class="reference-bottom"><a href="' . $this->url('referanslar/') . '">Tüm referanslar ' . $this->icon('arrowUp') . '</a></div></aside>';
    }

    private function panel(): string
    {
        return '<div class="instrument"><i class="screw tl"></i><i class="screw tr"></i><i class="screw bl"></i><i class="screw br"></i><div class="instrument-top"><span>' . $this->icon('circuit') . 'SAHA KONTROL PANELİ</span><span class="sample-tag">TEMSİLÎ</span></div><div class="panel-tabs" role="group" aria-label="Panel görünümü"><button type="button" class="active" aria-pressed="true" data-panel="trafo">Trafo</button><button type="button" aria-pressed="false" data-panel="kompanzasyon">Kompanzasyon</button><button type="button" aria-pressed="false" data-panel="otomasyon">Otomasyon</button></div><div class="panel-readouts"><div><span id="reading-label-1">Trafo yükü</span><strong id="reading-value-1">72<small>%</small></strong></div><div><span id="reading-label-2">Test gerilimi</span><strong id="reading-value-2">5<small>kV</small></strong></div><div><span id="reading-label-3">Güç faktörü</span><strong id="reading-value-3" class="mint">0,99<small>cos φ</small></strong></div></div><div class="panel-diagram"><div class="diagram-grid"></div><svg viewBox="0 0 480 100" fill="none" aria-hidden="true"><path d="M0 50h480" stroke="#53616d" stroke-dasharray="3 5"/><path d="M0 50C20 50 20 12 40 12s20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 38 40 38" stroke="#FDB81D" stroke-width="2"/><path d="M0 65C20 65 20 27 40 27s20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 38 40 38" stroke="#66BF93" stroke-width="1" opacity=".5"/></svg><span>U / I</span></div><div class="panel-bottom"><span id="panel-note">Ölçüm → analiz → bakım planı</span><span class="panel-dots"><i></i><i></i><i></i></span></div><p class="panel-caption">Örnek gösterimdir; canlı tesis verisi değildir.</p></div>';
    }

    private function stepsHTML(): string
    {
        return '<ol class="steps">' . self::map(self::STEPS, fn ($s, $i) => '<li><span class="step-number">0' . ($i + 1) . '</span><h3>' . $s[0] . '</h3><p>' . $s[1] . '</p></li>') . '</ol>';
    }

    private function relatedServices(array $p): array
    {
        return array_values(array_filter($this->data['services'], fn ($s) => in_array($s['slug'], $p['services'] ?? [], true)));
    }

    // ------------------------------------------------------------ routes
    /**
     * Sıralı rota listesi: rota => ['sitemap' => bool, 'render' => fn(): string].
     * Sıra, site haritasının sırasını belirler.
     */
    public function routes(): array
    {
        $r = [];
        $r[''] = fn () => $this->home();
        $r['hizmetler/'] = fn () => $this->servicesPage();
        foreach ($this->data['services'] as $s) {
            $r['hizmetler/' . $s['slug'] . '/'] = fn () => $this->servicePage($s);
        }
        $r['projeler/'] = fn () => $this->projectsPage();
        foreach ($this->projects as $p) {
            $r['projeler/' . $p['slug'] . '/'] = fn () => $this->projectPage($p);
        }
        if ($this->hasStore) {
            $r['magaza/'] = fn () => $this->storePage();
            foreach ($this->products as $p) {
                $r['magaza/' . $p['slug'] . '/'] = fn () => $this->productPage($p);
            }
        }
        $r['kurumsal/'] = fn () => $this->aboutPage();
        $r['referanslar/'] = fn () => $this->referencesPage();
        $r['sikca-sorulan-sorular/'] = fn () => $this->faqPage();
        $r['iletisim/'] = fn () => $this->contactPage();
        $r['kesif-talebi/'] = fn () => $this->discoveryPage();
        $r['gizlilik/'] = fn () => $this->privacyPage();
        $out = [];
        foreach ($r as $route => $render) {
            $out[$route] = ['sitemap' => true, 'render' => $render];
        }
        $out['admin/'] = ['sitemap' => false, 'render' => fn () => $this->adminPage()];
        return $out;
    }

    /** Site kabuğunda, arama motorlarına kapalı tek sayfa (ör. kurulum). */
    public function standalone(string $route, string $title, string $description, string $body): string
    {
        return $this->page($route, $title, $description, $this->intro(null, $title, $description) . $body, '', null, [
            'robots' => 'noindex,nofollow',
            'head' => '<meta name="referrer" content="no-referrer">',
            'assets' => '<link rel="stylesheet" href="' . $this->versioned('assets/admin.css') . '">',
        ]);
    }

    public function sitemap(): string
    {
        $locs = '';
        foreach ($this->routes() as $route => $spec) {
            if ($spec['sitemap']) {
                $locs .= '<url><loc>' . $this->origin . $this->url($route) . '</loc></url>';
            }
        }
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $locs . '</urlset>';
    }

    public function robots(): string
    {
        return "User-agent: *\nAllow: /\nSitemap: " . $this->origin . $this->base . "sitemap.xml\n";
    }

    public function notFound(): string
    {
        return $this->page(
            '404.html',
            'Sayfa bulunamadı',
            'Aradığınız sayfa bulunamadı. Altındaş Mühendislik ana sayfasına veya hizmetlerine ulaşabilirsiniz.',
            '<section class="wrap error-page"><span class="error-number">404</span><h1>Bu bağlantı açık devre.</h1><p>Aradığınız sayfa taşınmış olabilir. Ana sayfadan devam edebilirsiniz.</p><div class="hero-actions">' . $this->btn('Ana sayfaya dönün', $this->url('')) . $this->btn('Hizmetleri keşfedin', $this->url('hizmetler/'), 'secondary') . '</div></section>',
        );
    }

    // ------------------------------------------------------------ pages
    private function home(): string
    {
        $services = [
            ...array_filter($this->data['services'], fn ($s) => $s['slug'] === 'trafo-bakimi'),
            ...array_filter($this->data['services'], fn ($s) => $s['slug'] !== 'trafo-bakimi'),
        ];
        $bento = self::map($services, fn ($s, $i) => $this->serviceCard($s, $i === 0));
        $equipment = self::map(['Sonel MPI-540', 'MIC-5001', 'MMR-650', 'MRF-TTR3', 'Unit UTi712S'], fn ($t) => '<span>' . $t . '</span>');
        $projects = self::map(array_slice($this->projects, 0, 6), fn ($p) => $this->projectCard($p));
        return $this->page(
            '',
            'Elektrik mühendisliği, sahada güven',
            'Aliağa merkezli Altındaş Mühendislik & Elektrik. Trafo bakımı, YG işletme sorumluluğu, kompanzasyon ve elektrik taahhüt çözümleri.',
            '<div class="wrap"><section class="hero"><div class="hero-copy"><p class="hero-kicker"><span></span>Aliağa / İzmir — Elektrik mühendisliği</p><h1>Enerjiniz güvende.<br><em>Tesisiniz aydınlık.</em></h1><p class="lead">Trafodan üretim hattına, panodan yaşam alanına. Ölçüyor, projelendiriyor, uyguluyor ve sorumluluğunu alıyoruz.</p><div class="hero-actions">' . $this->btn('Ücretsiz keşif isteyin', $this->url('kesif-talebi/')) . $this->btn('Hizmetleri keşfedin', $this->url('hizmetler/'), 'secondary') . '</div><p class="hero-footnote"><span></span>10 yılı aşkın saha deneyimi. Her aşamada mühendislik.</p></div>' . $this->panel() . '</section><div class="trust-strip"><div>' . $this->icon('shield') . '<span>EMO üyesi<br><b>Elektrik mühendisi</b></span></div><div>' . $this->icon('check') . '<span>İşçilik ve ekipman<br><b>2 yıl garanti</b></span></div><div>' . $this->icon('phone') . '<span>Sözleşmeli müşteriye<br><b>7/24 arıza desteği</b></span></div><div>' . $this->icon('pin') . '<span>İzmir ve çevre illerde<br><b>Ücretsiz keşif</b></span></div></div><section class="section" id="hizmetler">' . $this->sectionHead('Uzmanlık alanlarımız', 'Tek çatı altında,<br><em>ölçüme dayalı mühendislik.</em>', 'Tesisinizin ihtiyacına özel, birbiriyle uyumlu elektrik çözümleri.', '<a class="inline-link" href="' . $this->url('hizmetler/') . '">Tüm hizmetler ' . $this->icon('arrowUp') . '</a>') . '<div class="services-bento">' . $bento . '</div></section><section class="section feature-section">' . $this->sectionHead('Ölçümden sorumluluğa', 'Tesisinizin kalbi trafo.<br><em>Kontrolü birlikte sağlayalım.</em>') . '<div class="feature-grid"><div class="detail-box"><h3>' . $this->icon('trafo') . 'Trafo bakımı</h3>' . $this->checkList(['Periyodik trafo ve OG hücre kontrolü', 'Anlaşmalı laboratuvarda yağ analizi', 'İzolasyon, sargı direnci ve dönüştürme oranı testleri', 'Termal tarama ve sekonder bağlantı kontrolü']) . '<a class="inline-link" href="' . $this->url('hizmetler/trafo-bakimi/') . '">Bakım kapsamını inceleyin ' . $this->icon('arrow') . '</a></div><div class="detail-box"><h3>' . $this->icon('shield') . 'YG işletme sorumluluğu</h3>' . $this->checkList(['Aylık periyodik kontrol ve teknik takip', 'Dağıtım şirketiyle yazışmalar', 'Yıllık işletme raporu', 'Sözleşmeli müşteriler için 7/24 arıza müdahalesi']) . '<a class="inline-link" href="' . $this->url('hizmetler/isletme-sorumlulugu/') . '">Sorumluluk kapsamını inceleyin ' . $this->icon('arrow') . '</a></div></div><div class="equipment"><span>ÖLÇÜM EKİPMANLARIMIZ</span><div>' . $equipment . '</div></div></section><section class="section" id="projeler">' . $this->sectionHead('Sahadan proje dosyaları', 'Çizimden sahaya.<br><em>İşiyle konuşan projeler.</em>', 'Konutlardan üretim tesislerine, üstlendiğimiz işlerden seçkiler.', '<a class="inline-link" href="' . $this->url('projeler/') . '">Tüm projeler ' . $this->icon('arrowUp') . '</a>') . '<div class="projects-layout">' . $this->referenceColumn() . '<div class="project-grid">' . $projects . '</div></div></section><section class="section" id="surec">' . $this->sectionHead('Nasıl çalışıyoruz?', 'İlk görüşmeden<br><em>güvenli işletmeye.</em>') . $this->stepsHTML() . '</section><section class="section about-strip"><div><p class="eyebrow">Altındaş Mühendislik &amp; Elektrik</p><h2>Her bağlantıda<br>mühendislik var.</h2></div><div><p>Elektrik mühendisi Caner Altındaş’ın kurduğu Altındaş Mühendislik &amp; Elektrik olarak, Aliağa merkezli çalışmalarımızda sahayı dinliyor, ihtiyacı ölçüyor ve çözümü birlikte planlıyoruz.</p><a class="inline-link" href="' . $this->url('kurumsal/') . '">Bizi tanıyın ' . $this->icon('arrowUp') . '</a></div></section><section class="section faq-section">' . $this->sectionHead('Aklınızdaki sorular', 'Başlamadan önce.', 'Keşif, bakım ve çalışma biçimimiz hakkında.', '<a class="inline-link" href="' . $this->url('sikca-sorulan-sorular/') . '">Tüm sorular ' . $this->icon('arrowUp') . '</a>') . $this->faqs(array_slice($this->data['faqs'], 0, 4)) . '</section></div>' . $this->cta(),
        );
    }

    private function servicesPage(): string
    {
        return $this->page(
            'hizmetler/',
            'Hizmetlerimiz',
            'Kompanzasyon, trafo bakımı, YG işletme sorumluluğu, otomasyon, elektrik taahhüt, kamera ve güneş enerjisi çözümleri.',
            $this->intro(null, 'Tesisiniz için bütünlüklü mühendislik.', 'Birbirine bağlı yedi uzmanlık alanı. Mevcut tesisinizden yeni yatırımınıza kadar ihtiyacınız olan çözümü birlikte belirleyelim.') . '<section class="wrap section compact-top"><div class="service-list-grid">' . self::map($this->data['services'], fn ($s) => $this->serviceCard($s)) . '</div></section>' . $this->cta(),
            'hizmetler/',
        );
    }

    private function servicePage(array $s): string
    {
        $related = array_values(array_filter($this->projects, fn ($p) => in_array($s['slug'], $p['services'] ?? [], true)));
        $process = self::map($s['process'], fn ($p, $i) => '<li><span>0' . ($i + 1) . '</span><p>' . esc($p) . '</p></li>');
        $relatedHTML = $related
            ? '<section class="wrap section">' . $this->sectionHead('İlgili çalışmalar', 'Sahadaki karşılığı.') . '<div class="project-grid three-cols">' . self::map(array_slice($related, 0, 3), fn ($p) => $this->projectCard($p)) . '</div></section>'
            : '';
        return $this->page(
            'hizmetler/' . $s['slug'] . '/',
            $s['title'],
            $s['short'],
            $this->intro(['Hizmetler', 'hizmetler/'], $s['title'], $s['intro']) . '<div class="wrap detail-layout"><article><section class="detail-box"><h2>Hizmet kapsamı</h2>' . $this->checkList($s['scope']) . '</section><section class="section small-section"><h2>Nasıl ilerliyoruz?</h2><ol class="service-process">' . $process . '</ol></section><section class="section small-section"><h2>Sıkça sorulan sorular</h2>' . $this->faqs($s['faqs']) . '</section></article><aside class="detail-aside"><div class="aside-illustration">' . $this->icon(self::SERVICE_ICONS[$s['slug']] ?? null) . '<span>SAHA / ' . esc(tr_upper($s['title'])) . '</span></div><h2>Önce ihtiyacınızı dinleyelim.</h2><p>Mevcut tesisinizi ya da planladığınız yatırımı birlikte değerlendirelim.</p>' . $this->btn('Keşif isteyin', $this->url('kesif-talebi/?hizmet=' . $s['slug'])) . '<a href="tel:+905384475676" class="aside-phone">' . self::PHONE . '</a><small>Ücretsiz keşif: İzmir, Manisa, Bergama, Balıkesir ve Aydın.</small></aside></div>' . $relatedHTML . $this->cta(),
            'hizmetler/',
        );
    }

    private function filters(array $categories, string $label, string $noun = ''): string
    {
        return '<div class="project-filters" role="group" aria-label="' . $label . '"><button type="button" data-filter="all" class="active" aria-pressed="true">Tümü</button>' . self::map($categories, fn ($c) => '<button type="button" data-filter="' . esc($c) . '" aria-pressed="false">' . esc($c) . '</button>') . '</div><p class="filter-status sr-only" role="status"' . ($noun !== '' ? ' data-noun="' . $noun . '"' : '') . '></p>';
    }

    private function projectsPage(): string
    {
        $categories = array_values(array_unique(array_column($this->projects, 'category')));
        return $this->page(
            'projeler/',
            'Projelerimiz',
            'Altındaş Mühendislik proje dosyaları. Konut, fabrika, trafo, kompanzasyon ve işletme sorumluluğu uygulamaları.',
            $this->intro(null, 'Her projenin bir saha hikâyesi var.', 'İhtiyaçtan uygulamaya; tamamlanan çalışmalarımız ve devam eden sorumluluklarımız.') . '<section class="wrap section compact-top">' . $this->filters($categories, 'Projeleri filtrele') . '<div class="project-grid three-cols">' . self::map($this->projects, fn ($p) => $this->projectCard($p)) . '</div></section>' . $this->cta(),
            'projeler/',
        );
    }

    private function projectPage(array $p): string
    {
        $results = !empty($p['results'])
            ? '<h2>Sonuç ve güncel durum</h2>' . self::map($p['results'], fn ($r) => '<p>' . esc($r) . '</p>')
            : '';
        $cover = !empty($p['image'])
            ? '<img src="' . $this->asset($p['image']) . '" alt="' . esc($p['imageAlt'] ?? '') . '" width="1600" height="1200" fetchpriority="high">'
            : $this->drawing($p['drawing'] ?? null);
        return $this->page(
            'projeler/' . $p['slug'] . '/',
            $p['title'],
            $p['summary'],
            $this->intro(['Projeler', 'projeler/'], $p['title'], $p['summary']) . '<div class="wrap"><div class="project-detail-meta"><span>' . esc($p['category']) . '</span><span class="status">' . esc($p['status']) . '</span>' . (!empty($p['location']) ? '<span>' . $this->icon('pin') . esc($p['location']) . '</span>' : '') . (!empty($p['year']) ? '<span>' . esc($p['year']) . '</span>' : '') . '</div><div class="project-detail-cover">' . $cover . '</div><div class="project-story"><article><p class="eyebrow">Proje dosyası</p><h2>İhtiyaçtan uygulamaya.</h2><p class="story-intro">' . esc($p['intro']) . '</p><h2>Üstlendiğimiz işler</h2>' . $this->checkList($p['scope'] ?? []) . $results . '</article><aside class="project-facts"><h2>Proje künyesi</h2><dl>' . self::map($p['facts'] ?? [], fn ($f) => '<div><dt>' . esc($f['label']) . '</dt><dd>' . esc($f['value']) . '</dd></div>') . '</dl><h3>İlgili hizmetler</h3><div class="tag-links">' . self::map($this->relatedServices($p), fn ($s) => '<a href="' . $this->url('hizmetler/' . $s['slug'] . '/') . '">' . esc($s['title']) . ' ' . $this->icon('arrowUp') . '</a>') . '</div></aside></div>' . $this->gallery($p['gallery'] ?? [], 'Sahadan fotoğraflar') . '<div class="project-navigation"><a class="inline-link" href="' . $this->url('projeler/') . '">← Tüm proje dosyaları</a>' . $this->btn('Benzer bir proje konuşalım', $this->url('kesif-talebi/'), 'secondary') . '</div></div>' . $this->cta(),
            'projeler/',
        );
    }

    private function storePage(): string
    {
        $categories = array_values(array_unique(array_column($this->products, 'category')));
        $notice = '<p class="store-notice">' . $this->icon('check') . '<span>Bu sitede ödeme alınmaz. Siparişinizi WhatsApp’tan iletirsiniz; stok, teslimat ve ödeme bilgisini sizinle doğrudan teyit ederiz. Fiyatlar ürünün yanında belirtilen KDV durumuyla listelenir.</span></p>';
        return $this->page(
            'magaza/',
            'Mağaza',
            'Altındaş Mühendislik & Elektrik mağazası. Ürünleri ve fiyatları inceleyin, siparişinizi WhatsApp üzerinden iletin.',
            $this->intro(null, 'Ürünler ve fiyatlar.', 'Ürünleri inceleyin, siparişinizi WhatsApp üzerinden iletin. Stok ve teslimat bilgisini sizinle doğrudan teyit ediyoruz.') . '<section class="wrap section compact-top">' . (count($categories) > 1 ? $this->filters($categories, 'Ürünleri filtrele', 'ürün') : '') . '<div class="product-grid">' . self::map($this->products, fn ($p) => $this->productCard($p)) . '</div>' . $notice . '</section>',
            'magaza/',
        );
    }

    private function productPage(array $p): string
    {
        $images = [];
        if (!empty($p['image'])) {
            $images[] = ['src' => $p['image'], 'alt' => $p['imageAlt'] ?? '', 'width' => 1600, 'height' => 1200];
        }
        foreach ($p['gallery'] ?? [] as $g) {
            $images[] = $g;
        }
        $pageUrl = $this->origin . $this->url('magaza/' . $p['slug'] . '/');
        $availability = ['Stokta' => 'InStock', 'Sipariş üzerine' => 'MadeToOrder', 'Tükendi' => 'OutOfStock'][$p['stock'] ?? ''] ?? null;
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['title'],
            'description' => $p['summary'],
            'url' => $pageUrl,
        ];
        if ($images) {
            $schema['image'] = array_map(fn ($i) => $this->origin . $this->asset($i['src']), $images);
        }
        if (!empty($p['sku'])) {
            $schema['sku'] = $p['sku'];
        }
        if (!empty($p['brand'])) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $p['brand']];
        }
        if (self::hasPrice($p)) {
            $offer = ['@type' => 'Offer', 'price' => number_format((float) $p['price'], 2, '.', ''), 'priceCurrency' => 'TRY', 'url' => $pageUrl];
            if ($availability) {
                $offer['availability'] = 'https://schema.org/' . $availability;
            }
            $offer['seller'] = ['@type' => 'Organization', 'name' => 'Altındaş Mühendislik & Elektrik'];
            $schema['offers'] = $offer;
        }
        $specs = array_values(array_filter($p['specs'] ?? [], fn ($s) => !empty($s['label']) && !empty($s['value'])));
        $related = array_slice(array_values(array_filter($this->products, fn ($o) => $o['slug'] !== $p['slug'] && $o['category'] === $p['category'])), 0, 3);
        $unit = ($p['unit'] ?? '') ?: 'adet';
        $media = $images
            ? '<a class="product-main gallery-item" href="' . $this->asset($images[0]['src']) . '" data-lightbox><img src="' . $this->asset($images[0]['src']) . '" alt="' . esc($images[0]['alt'] ?? '') . '" width="' . ($images[0]['width'] ?? null ?: 1600) . '" height="' . ($images[0]['height'] ?? null ?: 1200) . '" fetchpriority="high"></a>' . (count($images) > 1 ? '<div class="product-thumbs">' . self::map(array_slice($images, 1), fn ($g) => $this->galleryItem($g)) . '</div>' : '')
            : '<div class="product-main">' . $this->drawing('bolt', 'Ürün görseli eklenmedi') . '</div>';
        $paragraphs = self::map(preg_split('/\n\s*\n/u', (($p['description'] ?? '') ?: $p['summary'])), fn ($para) => '<p>' . esc(trim($para)) . '</p>');
        return $this->page(
            'magaza/' . $p['slug'] . '/',
            $p['title'],
            $p['summary'],
            $this->intro(['Mağaza', 'magaza/'], $p['title'], $p['summary']) . '<div class="wrap"><div class="project-detail-meta"><span>' . esc($p['category']) . '</span><span class="status ' . self::stockClass($p) . '">' . esc(($p['stock'] ?? '') ?: 'Sipariş üzerine') . '</span>' . (!empty($p['brand']) ? '<span>' . esc($p['brand']) . '</span>' : '') . (!empty($p['sku']) ? '<span>Stok kodu: ' . esc($p['sku']) . '</span>' : '') . '</div><div class="product-layout"><div class="product-media">' . $media . '</div><aside class="buy-box" aria-labelledby="buy-title"><h2 id="buy-title" class="sr-only">Fiyat ve sipariş</h2><div class="product-price large">' . $this->priceHTML($p) . '</div><form class="order-form" data-order data-template="' . esc($this->orderMessage($p, '{qty}')) . '"><div class="field order-qty" hidden><label for="order-qty">Miktar <span>(' . esc($unit) . ')</span></label><input id="order-qty" name="qty" type="number" inputmode="numeric" min="1" max="9999" step="1" value="1"></div><a class="btn btn-primary" href="' . self::WA . '?text=' . uri_component($this->orderMessage($p, '1')) . '" target="_blank" rel="noopener noreferrer" data-order-link>' . (($p['stock'] ?? '') === 'Tükendi' ? 'Stok durumunu sorun' : 'WhatsApp ile sipariş verin') . ' ' . $this->icon('arrowUp') . '</a></form><p class="buy-note">Mesaj WhatsApp’ta açılır; gönderimi siz tamamlarsınız. Stok, teslimat ve ödeme sipariş sırasında teyit edilir.</p><a href="tel:+905384475676" class="aside-phone">' . self::PHONE . '</a><small>Pazartesi–Cumartesi / 08:30–18:00</small></aside></div><div class="project-story"><article><p class="eyebrow">Ürün bilgisi</p><h2>Ürün açıklaması</h2>' . $paragraphs . '</article><aside class="project-facts"><h2>Teknik özellikler</h2>' . ($specs ? '<dl>' . self::map($specs, fn ($f) => '<div><dt>' . esc($f['label']) . '</dt><dd>' . esc($f['value']) . '</dd></div>') . '</dl>' : '<p class="facts-empty">Teknik ayrıntılar için WhatsApp veya telefonla ulaşabilirsiniz.</p>') . '</aside></div>' . ($related ? '<section class="section small-section">' . $this->sectionHead('Aynı kategoriden', 'Diğer ürünler.') . '<div class="product-grid">' . self::map($related, fn ($o) => $this->productCard($o)) . '</div></section>' : '') . '<div class="project-navigation"><a class="inline-link" href="' . $this->url('magaza/') . '">← Tüm ürünler</a>' . $this->btn('Teknik soru sorun', $this->url('iletisim/'), 'secondary') . '</div></div>',
            'magaza/',
            $schema,
        );
    }

    private function aboutPage(): string
    {
        $credentials = [
            ['EMO üyeliği', 'TMMOB Elektrik Mühendisleri Odası İzmir Şubesi üyeliği.'],
            ['SMM', 'Serbest Müşavir Mühendislik belgesi.'],
            ['YG işletme sorumluluğu', 'Yüksek gerilim tesislerinde işletme sorumluluğu yetkinliği.'],
            ['TS EN 61439 ve İSG', 'Pano ve iş sağlığı güvenliği süreçlerini destekleyen belgeler.'],
        ];
        return $this->page(
            'kurumsal/',
            'Kurumsal',
            'Aliağa merkezli Altındaş Mühendislik & Elektrik ve elektrik mühendisi Caner Altındaş hakkında.',
            $this->intro(null, 'Sahaya yakın. Çözümün içinde.', $this->data['about']['intro']) . '<section class="wrap about-body"><div class="about-panel">' . $this->icon('bolt') . '<h2>Enerjiniz Güvende,<br><span>Geleceğiniz Aydınlık.</span></h2><p>ALTINDAŞ<br>MÜHENDİSLİK &amp; ELEKTRİK</p></div><article>' . self::map($this->data['about']['paragraphs'], fn ($p) => '<p>' . esc($p) . '</p>') . '<h2>Nasıl bir iş ortaklığı?</h2>' . $this->checkList(['İhtiyaca göre keşif, ölçüm ve teknik değerlendirme.', 'Kapsamı, takvimi ve bedeli belli teklif.', 'Uygulama sonrası test, raporlama ve planlı takip.']) . '</article></section><section class="wrap section">' . $this->sectionHead('Yetkinliklerimiz', 'Yetkinlik, sorumlulukla birlikte.') . '<div class="credential-grid">' . self::map($credentials, fn ($c) => '<div>' . $this->icon('shield') . '<h3>' . $c[0] . '</h3><p>' . $c[1] . '</p></div>') . '</div></section><section class="wrap section">' . $this->sectionHead('Çalışma biçimimiz', 'Planlı adımlar. Açık iletişim.') . $this->stepsHTML() . '</section>' . $this->cta(),
            'kurumsal/',
        );
    }

    private function referencesPage(): string
    {
        $tiles = self::map($this->refs, fn ($r) => '<div class="reference-tile"><div>' . (!empty($r['logo']) ? '<img src="' . $this->asset($r['logo']) . '" alt="' . esc($r['name']) . '" width="200" height="90">' : $this->wordmark($r)) . '</div><h2>' . esc($r['name']) . '</h2>' . (!empty($r['project']) ? '<a class="inline-link" href="' . $this->url('projeler/' . $r['project'] . '/') . '">İlgili proje dosyası ' . $this->icon('arrow') . '</a>' : '') . '</div>');
        return $this->page(
            'referanslar/',
            'Referanslarımız',
            'Altındaş Mühendislik & Elektrik’in birlikte çalıştığı kurumlar ve ilgili proje dosyaları.',
            $this->intro(null, 'Güven, birlikte çalışarak kurulur.', 'Farklı sektörlerde, farklı ihtiyaçlarda. Sahada sorumluluk aldığımız kurumlarla tanışın.') . '<section class="wrap section compact-top"><div class="reference-page-grid">' . $tiles . '</div></section>' . $this->cta(),
            'projeler/',
        );
    }

    private function faqPage(): string
    {
        return $this->page(
            'sikca-sorulan-sorular/',
            'Sıkça sorulan sorular',
            'Ücretsiz keşif, teklif süreci, trafo bakımı, işletme sorumluluğu ve hizmet bölgemiz hakkında sorular.',
            $this->intro(null, 'Aklınızdaki sorular.', 'Birlikte çalışmaya başlamadan önce keşif, uygulama ve takip sürecimizi tanıyın.') . '<section class="wrap section compact-top narrow">' . $this->faqs($this->data['faqs']) . '</section>' . $this->cta(),
        );
    }

    private function contactPage(): string
    {
        $cards = '<div class="contact-cards"><a href="tel:+905384475676">' . $this->icon('phone') . '<span>Telefon<strong>' . self::PHONE . '</strong></span>' . $this->icon('arrowUp') . '</a><a href="' . self::WA . '" target="_blank" rel="noopener noreferrer">' . $this->icon('mail') . '<span>WhatsApp<strong>Mesaj gönderin</strong></span>' . $this->icon('arrowUp') . '</a><a href="mailto:info@altindasmuhendislik.com">' . $this->icon('mail') . '<span>E-posta<strong>info@altindasmuhendislik.com</strong></span>' . $this->icon('arrowUp') . '</a></div>';
        return $this->page(
            'iletisim/',
            'İletişim',
            'Aliağa Yeni Mahalle, Anadolu Caddesi No: 24A. Telefon +90 538 447 56 76. Altındaş Mühendislik & Elektrik iletişim bilgileri.',
            $this->intro(null, 'İlk bağlantıyı kuralım.', 'Yeni bir proje, bakım ihtiyacı ya da tesisinizle ilgili bir soru. Doğrudan bize ulaşın.') . '<section class="wrap contact-layout">' . $cards . '<div class="address-panel">' . $this->icon('pin') . '<h2>Aliağa’dan, sahanıza.</h2><p>Yeni Mah. Anadolu Cad. No: 24A<br>35800 Aliağa / İzmir</p><p>Pazartesi–Cumartesi<br><strong>08:30–18:00</strong></p><a class="inline-link" href="https://www.google.com/maps/search/?api=1&amp;query=Alt%C4%B1nda%C5%9F+M%C3%BChendislik+Alia%C4%9Fa" target="_blank" rel="noopener noreferrer">Yol tarifi alın ' . $this->icon('arrowUp') . '</a></div></section><section class="wrap section"><div class="detail-box"><h2>Bakım ve işletme sözleşmeli müşterilerimize</h2><p>7/24 arıza müdahalesi sunuyoruz. Acil durum bildirimleri için doğrudan telefonla ulaşın.</p><a class="inline-link" href="tel:+905384475676">Hemen arayın ' . $this->icon('phone') . '</a></div></section>' . $this->cta(),
            'iletisim/',
        );
    }

    private function discoveryPage(): string
    {
        $options = self::map($this->data['services'], fn ($s) => '<option value="' . $s['slug'] . '">' . esc($s['title']) . '</option>');
        return $this->page(
            'kesif-talebi/',
            'Ücretsiz keşif talebi',
            'Tesisiniz veya projeniz için ücretsiz keşif talebinizi hazırlayın ve WhatsApp üzerinden Altındaş Mühendislik ile paylaşın.',
            $this->intro(null, 'Projenizden bahsedin.', 'İhtiyacınızı birkaç adımda paylaşın. İzmir, Manisa, Bergama, Balıkesir ve Aydın’da ücretsiz keşif için görüşelim.') . '<div class="wrap form-layout"><form id="discovery-form" class="discovery-form"><div class="form-heading"><h2>Keşif talebiniz</h2><p>Bilgileri doldurun; WhatsApp mesajınızı birlikte hazırlayalım.</p></div><div class="form-row"><div class="field"><label for="name">Adınız soyadınız <span>(zorunlu)</span></label><input id="name" name="name" autocomplete="name" required maxlength="100"></div><div class="field"><label for="company">Firma / site adı <span>(isteğe bağlı)</span></label><input id="company" name="company" autocomplete="organization" maxlength="150"></div></div><div class="form-row"><div class="field"><label for="telephone">Telefonunuz <span>(zorunlu)</span></label><input id="telephone" name="telephone" type="tel" autocomplete="tel" inputmode="tel" required maxlength="20" aria-describedby="phone-help"><small id="phone-help">Örnek: 0538 447 56 76</small></div><div class="field"><label for="location">İl / ilçe <span>(zorunlu)</span></label><input id="location" name="location" autocomplete="address-level2" required maxlength="100"></div></div><div class="field"><label for="service">İlgilendiğiniz hizmet</label><select id="service" name="service"><option value="">Birlikte belirleyelim</option>' . $options . '</select></div><div class="field"><label for="message">İhtiyacınız <span>(isteğe bağlı)</span></label><textarea id="message" name="message" rows="4" maxlength="1500" placeholder="Mevcut tesisiniz, projeniz veya yaşadığınız sorun…"></textarea></div><p class="form-notice">Bu form bilgileri sunucuya kaydetmez. Mesajınız aşağıda hazırlanır; gönderimi WhatsApp’ta siz tamamlarsınız. <a href="' . $this->url('gizlilik/') . '">Gizlilik bilgileri</a></p><button class="btn btn-primary" type="submit">WhatsApp mesajını hazırla ' . $this->icon('arrow') . '</button><noscript><p>Mesaj hazırlamak için JavaScript gerekir. <a href="' . self::WA . '">Doğrudan WhatsApp üzerinden yazabilirsiniz.</a></p></noscript><section id="message-preview" hidden aria-labelledby="preview-title"><h2 id="preview-title" tabindex="-1">Mesajınız hazır</h2><p>Henüz gönderilmedi. Kontrol edip WhatsApp’ta paylaşabilirsiniz.</p><pre id="prepared-message"></pre><a id="whatsapp-send" class="btn btn-primary" href="' . self::WA . '" target="_blank" rel="noopener noreferrer">WhatsApp’ta aç ' . $this->icon('arrowUp') . '</a><p class="preview-note">Formu değiştirirseniz mesajı yeniden hazırlayın.</p></section></form><aside class="form-aside"><span class="eyebrow">Keşiften önce</span><h2>İyi bir başlangıç,<br>doğru sorularla.</h2><p>Elinizde varsa elektrik faturası, tek hat şeması veya pano fotoğrafı görüşmemize yardımcı olur. Bunları WhatsApp üzerinden paylaşabilirsiniz.</p><div class="response-note">' . $this->icon('check') . '<span>Talebinize <strong>24 saat içinde</strong> dönüş yapıyoruz.</span></div><div class="response-note">' . $this->icon('check') . '<span>Keşif sonrası teklif:<br><strong>2–3 iş günü</strong></span></div><div class="aside-contact"><p>Doğrudan konuşmak isterseniz</p><a href="tel:+905384475676">' . self::PHONE . '</a><small>Pazartesi–Cumartesi / 08:30–18:00</small></div></aside></div>',
            'iletisim/',
        );
    }

    private function privacyPage(): string
    {
        $host = $this->options['host'] ?? 'GitHub Pages';
        $store = $this->hasStore
            ? '<h2>Mağaza siparişleri</h2><p>Mağaza sayfalarındaki sipariş düğmesi, seçtiğiniz ürün ve miktarla bir WhatsApp mesajı hazırlar. Site sipariş bilgisini kaydetmez; mesajı gönderme kararını siz verirsiniz. Stok, teslimat ve ödeme bilgisi sizinle doğrudan teyit edilir.</p>'
            : '';
        return $this->page(
            'gizlilik/',
            'Gizlilik ve iletişim bilgileri',
            'Bu web sitesindeki keşif talebi formunun, dış bağlantıların ve iletişim kanallarının kullanımı hakkında bilgi.',
            $this->intro(null, 'Gizlilik ve iletişim bilgileri.', 'Bu sitede paylaştığınız bilgilerin nasıl kullanıldığını açıkça anlatıyoruz.') . '<article class="wrap prose narrow"><h2>Web sitesi ve ziyaret</h2><p>Bu site ' . $host . ' üzerinde yayınlanır. Site kodu reklam, analiz çerezi veya ziyaretçi takip aracı kullanmaz. Barındırma sağlayıcısı, hizmetin çalışması için IP adresi ve teknik erişim kayıtlarını kendi politikalarına göre işleyebilir.</p><h2>Keşif talebi formu</h2><p>Formdaki bilgiler tarayıcınızda bir mesaj oluşturmak için kullanılır. Site bu bilgileri bir veri tabanına veya e-posta sunucusuna göndermez, yerel depolamaya kaydetmez. “WhatsApp’ta aç” bağlantısını seçtiğinizde hazırladığınız metin WhatsApp’a aktarılır; mesajı gönderme kararını siz verirsiniz. WhatsApp’ın kendi gizlilik koşulları geçerlidir.</p><h2>İletişim</h2><p>Paylaştığınız iletişim ve proje bilgileri, talebinize yanıt vermek ve keşif/teklif görüşmesini yürütmek için kullanılır. Bilgilerinizle ilgili sorularınız veya talepleriniz için <a href="mailto:info@altindasmuhendislik.com">info@altindasmuhendislik.com</a> adresinden bize ulaşabilirsiniz.</p><p>Altındaş Mühendislik &amp; Elektrik<br>Yeni Mah. Anadolu Cad. No: 24A, 35800 Aliağa / İzmir<br><a href="tel:+905384475676">' . self::PHONE . '</a></p>' . $store . '<h2>Dış bağlantılar</h2><p>WhatsApp, harita, sosyal medya' . ($this->hasStore ? '' : ' ve mevcut mağaza') . ' bağlantıları başka hizmetlere gider. Bu hizmetlerde ilgili sağlayıcının koşulları uygulanır. Bu sitede ödeme veya kart bilgisi alınmaz.</p></article>',
        );
    }

    private function adminPage(): string
    {
        $assets = '<link rel="stylesheet" href="' . $this->versioned('assets/admin.css') . '"><script type="module" src="' . $this->versioned('assets/admin.js') . '"></script>';
        $status = '<p id="admin-status" class="admin-status" role="status" aria-live="polite"></p><noscript><p class="admin-status" data-kind="error">Panel için JavaScript gerekir.</p></noscript>';
        if (($this->options['backend'] ?? 'github') === 'php') {
            $body = $this->intro(null, 'Yönetim paneli', 'Projeleri ve mağaza ürünlerini buradan ekleyin. Kaydettiğiniz değişiklik sitede hemen yayına girer.') . '<div class="wrap admin-shell" id="admin-app" data-backend="php" data-api="' . $this->url('admin/api') . '" data-base="' . $this->base . '" data-site="' . $this->origin . $this->base . '">' . $status . '<div id="admin-login" class="form-layout admin-login"><form id="login-form" class="discovery-form" novalidate><div class="form-heading"><h2>Giriş yapın</h2><p>Kurulumda belirlediğiniz kullanıcı adı ve şifreyle giriş yapın.</p></div><div class="field"><label for="username">Kullanıcı adı</label><input id="username" name="username" autocomplete="username" spellcheck="false" required maxlength="60"></div><div class="field"><label for="password">Şifre</label><input id="password" name="password" type="password" autocomplete="current-password" required maxlength="200"></div><button class="btn btn-primary" type="submit">Giriş yap ' . $this->icon('arrow') . '</button></form><aside class="form-aside"><span class="eyebrow">Güvenlik</span><h2>Oturum bu tarayıcıda açılır.</h2><p>Ortak kullanılan bilgisayarlarda işiniz bitince “Çıkış yap” düğmesini kullanın. Art arda hatalı denemelerde giriş bir süre kilitlenir.</p></aside></div><div id="admin-workspace" hidden></div></div>';
            return $this->page('admin/', 'Yönetim paneli', 'Altındaş Mühendislik sitesine proje ve mağaza ürünü eklemek için yönetim paneli.', $body, '', null, [
                'robots' => 'noindex,nofollow',
                'head' => '<meta name="referrer" content="no-referrer">',
                'assets' => $assets,
            ]);
        }
        $repo = $this->options['repo'];
        $tokenUrl = 'https://github.com/settings/personal-access-tokens/new?' . http_build_query([
            'name' => 'Altındaş site paneli',
            'description' => $repo['name'] . ' yönetim paneli: proje ve ürün kayıtları',
            'target_name' => $repo['owner'],
            'expires_in' => '90',
            'contents' => 'write',
            'actions' => 'read',
        ]);
        $body = $this->intro(null, 'Yönetim paneli', 'Projeleri ve mağaza ürünlerini buradan ekleyin. Kaydettiğinizde değişiklik GitHub deposuna işlenir ve site birkaç dakika içinde yeniden yayınlanır.') . '<div class="wrap admin-shell" id="admin-app" data-owner="' . $repo['owner'] . '" data-repo="' . $repo['name'] . '" data-branch="' . $repo['branch'] . '" data-base="' . $this->base . '" data-site="' . $this->origin . $this->base . '">' . $status . '<div id="admin-login" class="form-layout admin-login"><form id="login-form" class="discovery-form" novalidate><div class="form-heading"><h2>Panele bağlanın</h2><p>Panel, sitenin GitHub deposuna sizin erişim anahtarınızla kaydeder. Anahtar başka bir sunucuya gönderilmez; yalnız bu tarayıcıdan GitHub’a iletilir.</p></div><div class="field"><label for="token">GitHub erişim anahtarı</label><input id="token" name="token" type="password" autocomplete="off" spellcheck="false" required aria-describedby="token-help"><small id="token-help">“github_pat_” ile başlayan ince ayarlı (fine-grained) anahtar.</small></div><div class="admin-check"><input id="remember" name="remember" type="checkbox"><label for="remember">Bu cihazda hatırla <span>(paylaşılan bilgisayarlarda işaretlemeyin)</span></label></div><button class="btn btn-primary" type="submit">Bağlan ' . $this->icon('arrow') . '</button></form><aside class="form-aside"><span class="eyebrow">İlk kurulum</span><h2>Erişim anahtarı oluşturun.</h2><ol class="admin-steps"><li><a href="' . esc($tokenUrl) . '" target="_blank" rel="noopener noreferrer">GitHub’da anahtar oluşturma sayfasını açın ↗</a> Ad, süre ve izinler hazır gelir.</li><li><b>Repository access</b> bölümünde <b>Only select repositories</b> seçip <b>' . $repo['name'] . '</b> deposunu işaretleyin.</li><li>İzinlerin <b>Contents: Read and write</b> ve <b>Actions: Read-only</b> olduğunu kontrol edin.</li><li><b>Generate token</b> ile oluşturun, anahtarı kopyalayıp bu sayfaya yapıştırın.</li></ol><p>Anahtar 90 gün sonra sona erer; aynı adımlarla yenisini oluşturabilirsiniz. Anahtarı kimseyle paylaşmayın.</p></aside></div><div id="admin-workspace" hidden></div></div>';
        return $this->page('admin/', 'Yönetim paneli', 'Altındaş Mühendislik sitesine proje ve mağaza ürünü eklemek için yönetim paneli.', $body, '', null, [
            'robots' => 'noindex,nofollow',
            'head' => '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' blob: data: https://raw.githubusercontent.com; font-src \'self\'; connect-src https://api.github.com; base-uri \'none\'; form-action \'none\'; object-src \'none\'"><meta name="referrer" content="no-referrer">',
            'assets' => $assets,
        ]);
    }
}
