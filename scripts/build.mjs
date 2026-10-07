import fs from "node:fs";
import path from "node:path";
const data = JSON.parse(fs.readFileSync("src/content.json", "utf8"));
const base = "/altindas-demo5/";
const origin = "https://ech0riath.github.io";
const url = (p) => base + p.replace(/^\/+/, "");
const esc = (s) =>
  String(s ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
const phone = "+90 538 447 56 76";
const wa = "https://wa.me/905384475676";
const iconPaths = {
  bolt: "m13 2-9 12h7l-1 8 10-12h-7l1-8Z",
  trafo: "M5 3v3m14-3v3M5 18v3m14-3v3M3 6h18v12H3zM8 9v6m4-6v6m4-6v6",
  shield: "m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-6",
  circuit:
    "M4 4h6v6H4zM14 14h6v6h-6zM14 4h6v6h-6zM4 14h6v6H4zM10 7h4M7 10v4m10-4v4m-7 3h4",
  building:
    "M4 21V3h16v18M2 21h20M8 7h2m4 0h2M8 11h2m4 0h2M8 15h2m4 0h2M10 21v-3h4v3",
  camera: "M3 6h13v12H3zM16 10l5-3v10l-5-3",
  sun: "M12 3v2m0 14v2M3 12h2m14 0h2M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0",
  arrow: "M5 12h14m-6-6 6 6-6 6",
  arrowUp: "M6 18 18 6M6 6h12v12",
  check: "m5 12 4 4L19 6",
  phone: "M6 3H3v4c0 8 6 14 14 14h4v-4l-5-2-2 2-7-7 2-2-3-5Z",
  pin: "M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0",
  menu: "M4 6h16M4 12h16M4 18h16",
  mail: "M3 5h18v14H3zM3 5l9 8 9-8",
  wave: "M2 12c4-14 6-14 10 0s6 14 10 0",
};
const icon = (name, cls = "") =>
  `<svg class="icon ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${iconPaths[name] || iconPaths.bolt}"/></svg>`;
const btn = (label, href, kind = "primary") =>
  `<a class="btn btn-${kind}" href="${href}">${label}${icon("arrow")}</a>`;
const brand = () =>
  `<a class="brand" href="${url("")}" aria-label="Altındaş Mühendislik ve Elektrik, ana sayfa"><span class="brand-mark"><img src="${url("assets/logo.png")}" width="32" height="37" alt=""></span><span><b>ALTINDAŞ</b><small>MÜHENDİSLİK &amp; ELEKTRİK</small></span></a>`;
function nav(active) {
  return `<div class="utility"><span>${icon("pin")}Aliağa, İzmir <span class="utility-extra">/ Sahada mühendislik, güvenle enerji.</span></span><a href="tel:+905384475676">${icon("phone")}${phone}</a></div><header class="site-header"><div class="nav-wrap">${brand()}<button class="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Menüyü aç">${icon("menu")}</button><nav id="main-nav" aria-label="Ana menü">${[
    ["Hizmetler", "hizmetler/"],
    ["Projeler", "projeler/"],
    ["Kurumsal", "kurumsal/"],
    ["İletişim", "iletisim/"],
  ]
    .map(
      ([n, p]) =>
        `<a href="${url(p)}"${active === p ? ' aria-current="page"' : ""}>${n}</a>`,
    )
    .join(
      "",
    )}</nav><a class="nav-cta" href="${url("kesif-talebi/")}">Keşif isteyin${icon("arrowUp")}</a></div></header>`;
}
function footer() {
  return `<footer class="footer wrap"><div class="footer-grid"><div>${brand()}<p class="slogan">Enerjiniz Güvende,<br><span>Geleceğiniz Aydınlık.</span></p><p>Aliağa’dan sanayi tesislerine, işletmelere ve yaşam alanlarına.</p></div><div><h2>İletişim</h2><p>Yeni Mah. Anadolu Cad. No: 24A<br>35800 Aliağa / İzmir</p><a href="tel:+905384475676">${phone}</a><a href="mailto:info@altindasmuhendislik.com">info@altindasmuhendislik.com</a><p>Pazartesi–Cumartesi<br>08:30–18:00</p></div><div><h2>Keşfedin</h2><a href="${url("hizmetler/")}">Hizmetlerimiz</a><a href="${url("projeler/")}">Proje dosyaları</a><a href="${url("referanslar/")}">Referanslarımız</a><a href="${url("sikca-sorulan-sorular/")}">Sıkça sorulan sorular</a><a href="https://altindasmuhendislik.com/shop/">Online mağaza ↗</a></div><div><h2>Bizi takip edin</h2><a href="https://www.instagram.com/altindasmuhendislik/" target="_blank" rel="noopener noreferrer">Instagram ↗</a><a href="https://www.linkedin.com/company/altindasmuhendislik/" target="_blank" rel="noopener noreferrer">LinkedIn ↗</a><a href="https://www.facebook.com/altindasmuhendislik" target="_blank" rel="noopener noreferrer">Facebook ↗</a><a href="${wa}" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a></div></div><div class="footer-bottom"><span>© ${new Date().getUTCFullYear()} Altındaş Mühendislik &amp; Elektrik</span><a href="${url("gizlilik/")}">Gizlilik ve iletişim bilgileri</a><a href="#top">Yukarı dön ↑</a></div></footer>`;
}
const routes = [];
function page(
  route,
  title,
  description,
  body,
  active = "",
  extraSchema = null,
) {
  const canonical = origin + url(route);
  const schema = {
    "@context": "https://schema.org",
    "@type": "Electrician",
    name: "Altındaş Mühendislik & Elektrik",
    url: origin + base,
    telephone: phone,
    email: "info@altindasmuhendislik.com",
    address: {
      "@type": "PostalAddress",
      streetAddress: "Yeni Mah. Anadolu Cad. No: 24A",
      addressLocality: "Aliağa",
      addressRegion: "İzmir",
      postalCode: "35800",
      addressCountry: "TR",
    },
  };
  const html = `<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#1B222B"><title>${esc(title)} | Altındaş Mühendislik &amp; Elektrik</title><meta name="description" content="${esc(description)}"><meta name="robots" content="noindex,follow"><link rel="canonical" href="${canonical}"><meta property="og:type" content="website"><meta property="og:locale" content="tr_TR"><meta property="og:title" content="${esc(title)}"><meta property="og:description" content="${esc(description)}"><meta property="og:url" content="${canonical}"><meta property="og:image" content="${origin + url("assets/share.png")}"><link rel="icon" href="${url("favicon.svg")}" type="image/svg+xml"><link rel="preload" href="${url("assets/fonts/archivo-tr.woff2")}" as="font" type="font/woff2" crossorigin><link rel="stylesheet" href="${url("assets/site.css")}"><script type="module" src="${url("assets/site.js")}"></script><script type="application/ld+json">${JSON.stringify(extraSchema || schema).replace(/</g, "\\u003c")}</script></head><body id="top"><a class="skip-link" href="#main">İçeriğe geç</a><div class="page-shell">${nav(active)}<main id="main">${body}</main>${footer()}</div></body></html>`;
  const file =
    route === "404.html" ? "404.html" : path.join(route, "index.html");
  fs.mkdirSync(path.dirname(path.join("dist", file)), { recursive: true });
  fs.writeFileSync(path.join("dist", file), html);
  if (route !== "404.html") routes.push(canonical);
}
const sectionHead = (label, title, sub = "", link = "") =>
  `<div class="section-heading"><div><p class="eyebrow">${label}</p><h2>${title}</h2>${sub ? `<p class="section-intro">${sub}</p>` : ""}</div>${link}</div>`;
const intro = (parent, title, desc) =>
  `<div class="wrap"><div class="breadcrumb"><a href="${url("")}">Ana sayfa</a><span>/</span>${parent ? `<a href="${url(parent[1])}">${parent[0]}</a><span>/</span>` : ""}<span>${esc(title)}</span></div><header class="page-intro"><span class="section-tick" aria-hidden="true"></span><h1>${esc(title)}</h1><p>${esc(desc)}</p></header></div>`;
const cta = () =>
  `<section class="wrap cta-wrap"><div class="cta"><div><p class="eyebrow">Bir sonraki adım</p><h2>Tesisinizi birlikte<br><span>değerlendirelim.</span></h2><p>İhtiyacınızı anlatın. Sahanıza uygun çözümü ücretsiz keşifle birlikte planlayalım.</p></div><div class="cta-actions">${btn("Ücretsiz keşif isteyin", url("kesif-talebi/"))}<a class="cta-phone" href="tel:+905384475676">${phone}${icon("phone")}</a><small>İzmir · Manisa · Bergama · Balıkesir · Aydın</small></div></div></section>`;
const list = (items) =>
  `<ul class="check-list">${items.map((t) => `<li>${icon("check")}<span>${esc(t)}</span></li>`).join("")}</ul>`;
const faqs = (items) =>
  `<div class="faq-list">${items.map((f) => `<details><summary>${esc(f.q)}<span aria-hidden="true">+</span></summary><div class="faq-answer"><p>${esc(f.a)}</p></div></details>`).join("")}</div>`;
const serviceIcon = (s) =>
  ({
    kompanzasyon: "wave",
    "trafo-bakimi": "trafo",
    "isletme-sorumlulugu": "shield",
    "endustriyel-otomasyon": "circuit",
    "elektrik-taahhut-proje": "bolt",
    "kamera-sistemleri": "camera",
    "gunes-enerji-sistemleri": "sun",
  })[s];
const serviceCard = (s, featured = false) =>
  `<a class="service-card ${featured ? "featured" : ""}" href="${url("hizmetler/" + s.slug + "/")}"><div class="service-card-top">${icon(serviceIcon(s.slug))}${icon("arrowUp")}</div><div>${featured ? '<p class="card-kicker">Sahanın merkezinde</p>' : ""}<h3>${esc(s.title)}</h3><p>${esc(s.short)}</p><span class="text-link">Hizmeti inceleyin ${icon("arrow")}</span></div>${featured ? '<svg class="trafo-drawing" viewBox="0 0 260 200" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-width="1.4"><path d="M40 50h180v115H40zM55 165v15m150-15v15M70 50V27m60 23V27m60 23V27M55 27h30m30 0h30m30 0h30M30 185h200M60 70v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75m17-75v75"/><path d="m135 86-15 24h17l-7 23 25-30h-17l6-17" stroke="#FDB81D"/></g></svg>' : ""}</a>`;
const drawing = (type = "building") =>
  `<div class="project-drawing">${icon(type)}<span>Şematik gösterim</span><i aria-hidden="true"></i></div>`;
function projectCard(p) {
  const picture = p.image
    ? `<img src="${url(p.image.replace(/^\//, ""))}" width="800" height="600" loading="lazy" alt="${esc(p.imageAlt)}">`
    : drawing(p.slug === "ramada" ? "shield" : "building");
  return `<a class="project-card" href="${url("projeler/" + p.slug + "/")}" data-category="${esc(p.category)}"><div class="project-cover">${picture}<span class="project-go" aria-hidden="true">${icon("arrowUp")}</span></div><div class="project-body"><div class="project-meta"><span>${esc(p.category)}</span><span class="status ${p.status.toLowerCase().includes("devam") ? "ongoing" : ""}">${esc(p.status)}</span></div><h3>${esc(p.title)}</h3><p>${esc(p.summary)}</p><span class="project-link">Proje dosyası ${icon("arrow")}</span></div></a>`;
}
const refs = [
  { name: "Niğsa Metal", image: "nigsa-metal" },
  { name: "Özdenizcilik", image: "ozdenizcilik" },
  { name: "Saka Beton", image: "saka-beton" },
  { name: "Ramada by Wyndham", image: "ramada" },
  { name: "Dört Mevsim Konut Yapı Kooperatifi", image: "" },
];
const refItems = (clone = false) =>
  `<div class="reference-group"${clone ? ' aria-hidden="true"' : ""}>${refs.map((r) => `<div class="reference-logo">${r.image ? `<img src="${url("assets/references/" + r.image + ".webp")}" width="180" height="80" loading="lazy" alt="${clone ? "" : r.name}">` : `<span class="reference-wordmark">DÖRT MEVSİM<small>KONUT YAPI KOOPERATİFİ</small></span>`}</div>`).join("")}</div>`;
const referenceColumn = () =>
  `<aside class="reference-column" aria-label="Referanslarımız"><div class="reference-head"><span class="eyebrow">Birlikte çalıştığımız kurumlar</span><h3>Güven, sahada<br>kazanılır.</h3></div><div class="reference-window"><div class="reference-track">${refItems()}${refItems(true)}</div></div><div class="reference-bottom"><a href="${url("referanslar/")}">Tüm referanslar ${icon("arrowUp")}</a><button type="button" class="motion-toggle" aria-label="Logo hareketini durdur" aria-pressed="false">Ⅱ</button></div></aside>`;
function panel() {
  return `<div class="instrument"><i class="screw tl"></i><i class="screw tr"></i><i class="screw bl"></i><i class="screw br"></i><div class="instrument-top"><span>${icon("circuit")}SAHA KONTROL PANELİ</span><span class="sample-tag">TEMSİLÎ</span></div><div class="panel-tabs" role="group" aria-label="Panel görünümü"><button type="button" class="active" aria-pressed="true" data-panel="trafo">Trafo</button><button type="button" aria-pressed="false" data-panel="kompanzasyon">Kompanzasyon</button><button type="button" aria-pressed="false" data-panel="otomasyon">Otomasyon</button></div><div class="panel-readouts"><div><span id="reading-label-1">Trafo yükü</span><strong id="reading-value-1">72<small>%</small></strong></div><div><span id="reading-label-2">Test gerilimi</span><strong id="reading-value-2">5<small>kV</small></strong></div><div><span id="reading-label-3">Güç faktörü</span><strong id="reading-value-3" class="mint">0,99<small>cos φ</small></strong></div></div><div class="panel-diagram"><div class="diagram-grid"></div><svg viewBox="0 0 480 100" fill="none" aria-hidden="true"><path d="M0 50h480" stroke="#53616d" stroke-dasharray="3 5"/><path d="M0 50C20 50 20 12 40 12s20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 76 40 76 20-76 40-76 20 38 40 38" stroke="#FDB81D" stroke-width="2"/><path d="M0 65C20 65 20 27 40 27s20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 48 40 48 20-48 40-48 20 38 40 38" stroke="#66BF93" stroke-width="1" opacity=".5"/></svg><span>U / I</span></div><div class="panel-bottom"><span id="panel-note">Ölçüm → analiz → bakım planı</span><span class="panel-dots"><i></i><i></i><i></i></span></div><p class="panel-caption">Örnek gösterimdir; canlı tesis verisi değildir.</p></div>`;
}
const steps = [
  [
    "Keşif",
    "İhtiyacınızı sahada dinler, tesisin mevcut durumunu birlikte belirleriz.",
  ],
  [
    "Ölçüm ve analiz",
    "Ölçüm sonuçlarını ve teknik ihtiyaçları değerlendiririz.",
  ],
  ["Teklif", "Keşiften sonra 2–3 iş gününde çözüm ve fiyatı sunarız."],
  [
    "Uygulama",
    "İş programını ve gerekli enerji kesintilerini birlikte planlarız.",
  ],
  ["Test ve teslim", "Testleri yapar, rapor ve dokümantasyonla teslim ederiz."],
  [
    "Periyodik takip",
    "Hizmet ve bakım sözleşmesine uygun kontrol planı oluştururuz.",
  ],
];
const stepsHTML = () =>
  `<ol class="steps">${steps.map(([t, d], i) => `<li><span class="step-number">0${i + 1}</span><h3>${t}</h3><p>${d}</p></li>`).join("")}</ol>`;
fs.mkdirSync("dist", { recursive: true });
fs.cpSync("public", "dist", { recursive: true });
fs.copyFileSync("src/site.css", "dist/assets/site.css");
fs.copyFileSync("src/site.js", "dist/assets/site.js");
page(
  "",
  "Elektrik mühendisliği, sahada güven",
  "Aliağa merkezli Altındaş Mühendislik & Elektrik. Trafo bakımı, YG işletme sorumluluğu, kompanzasyon ve elektrik taahhüt çözümleri.",
  `<div class="wrap"><section class="hero"><div class="hero-copy"><p class="hero-kicker"><span></span>Aliağa / İzmir — Elektrik mühendisliği</p><h1>Enerjiniz güvende.<br><em>Tesisiniz aydınlık.</em></h1><p class="lead">Trafodan üretim hattına, panodan yaşam alanına. Ölçüyor, projelendiriyor, uyguluyor ve sorumluluğunu alıyoruz.</p><div class="hero-actions">${btn("Ücretsiz keşif isteyin", url("kesif-talebi/"))}${btn("Hizmetleri keşfedin", url("hizmetler/"), "secondary")}</div><p class="hero-footnote"><span></span>10 yılı aşkın saha deneyimi. Her aşamada mühendislik.</p></div>${panel()}</section><div class="trust-strip"><div>${icon("shield")}<span>EMO üyesi<br><b>Elektrik mühendisi</b></span></div><div>${icon("check")}<span>İşçilik ve ekipman<br><b>2 yıl garanti</b></span></div><div>${icon("phone")}<span>Sözleşmeli müşteriye<br><b>7/24 arıza desteği</b></span></div><div>${icon("pin")}<span>İzmir ve çevre illerde<br><b>Ücretsiz keşif</b></span></div></div><section class="section" id="hizmetler">${sectionHead("Uzmanlık alanlarımız", "Tek çatı altında,<br><em>ölçüme dayalı mühendislik.</em>", "Tesisinizin ihtiyacına özel, birbiriyle uyumlu elektrik çözümleri.", `<a class="inline-link" href="${url("hizmetler/")}">Tüm hizmetler ${icon("arrowUp")}</a>`)}<div class="services-bento">${[...data.services.filter((s) => s.slug === "trafo-bakimi"), ...data.services.filter((s) => s.slug !== "trafo-bakimi")].map((s, i) => serviceCard(s, i === 0)).join("")}</div></section><section class="section feature-section">${sectionHead("Ölçümden sorumluluğa", "Tesisinizin kalbi trafo.<br><em>Kontrolü birlikte sağlayalım.</em>")}<div class="feature-grid"><div class="detail-box"><h3>${icon("trafo")}Trafo bakımı</h3>${list(["Periyodik trafo ve OG hücre kontrolü", "Anlaşmalı laboratuvarda yağ analizi", "İzolasyon, sargı direnci ve dönüştürme oranı testleri", "Termal tarama ve sekonder bağlantı kontrolü"])}<a class="inline-link" href="${url("hizmetler/trafo-bakimi/")}">Bakım kapsamını inceleyin ${icon("arrow")}</a></div><div class="detail-box"><h3>${icon("shield")}YG işletme sorumluluğu</h3>${list(["Aylık periyodik kontrol ve teknik takip", "Dağıtım şirketiyle yazışmalar", "Yıllık işletme raporu", "Sözleşmeli müşteriler için 7/24 arıza müdahalesi"])}<a class="inline-link" href="${url("hizmetler/isletme-sorumlulugu/")}">Sorumluluk kapsamını inceleyin ${icon("arrow")}</a></div></div><div class="equipment"><span>ÖLÇÜM EKİPMANLARIMIZ</span><div>${["Sonel MPI-540", "MIC-5001", "MMR-650", "MRF-TTR3", "Unit UTi712S"].map((t) => `<span>${t}</span>`).join("")}</div></div></section><section class="section" id="projeler">${sectionHead("Sahadan proje dosyaları", "Çizimden sahaya.<br><em>İşiyle konuşan projeler.</em>", "Konutlardan üretim tesislerine, üstlendiğimiz işlerden seçkiler.", `<a class="inline-link" href="${url("projeler/")}">Tüm projeler ${icon("arrowUp")}</a>`)}<div class="projects-layout">${referenceColumn()}<div class="project-grid">${data.projects.map(projectCard).join("")}</div></div></section><section class="section" id="surec">${sectionHead("Nasıl çalışıyoruz?", "İlk görüşmeden<br><em>güvenli işletmeye.</em>")}${stepsHTML()}</section><section class="section about-strip"><div><p class="eyebrow">Altındaş Mühendislik &amp; Elektrik</p><h2>Her bağlantıda<br>mühendislik var.</h2></div><div><p>Elektrik mühendisi Caner Altındaş’ın kurduğu Altındaş Mühendislik &amp; Elektrik olarak, Aliağa merkezli çalışmalarımızda sahayı dinliyor, ihtiyacı ölçüyor ve çözümü birlikte planlıyoruz.</p><a class="inline-link" href="${url("kurumsal/")}">Bizi tanıyın ${icon("arrowUp")}</a></div></section><section class="section faq-section">${sectionHead("Aklınızdaki sorular", "Başlamadan önce.", "Keşif, bakım ve çalışma biçimimiz hakkında.", `<a class="inline-link" href="${url("sikca-sorulan-sorular/")}">Tüm sorular ${icon("arrowUp")}</a>`)}${faqs(data.faqs.slice(0, 4))}</section></div>${cta()}`,
);
page(
  "hizmetler/",
  "Hizmetlerimiz",
  "Kompanzasyon, trafo bakımı, YG işletme sorumluluğu, otomasyon, elektrik taahhüt, kamera ve güneş enerjisi çözümleri.",
  `${intro(null, "Tesisiniz için bütünlüklü mühendislik.", "Birbirine bağlı yedi uzmanlık alanı. Mevcut tesisinizden yeni yatırımınıza kadar ihtiyacınız olan çözümü birlikte belirleyelim.")}<section class="wrap section compact-top"><div class="service-list-grid">${data.services.map((s) => serviceCard(s)).join("")}</div></section>${cta()}`,
  "hizmetler/",
);
for (const s of data.services) {
  const related = data.projects.filter((p) => p.services?.includes(s.slug));
  page(
    "hizmetler/" + s.slug + "/",
    s.title,
    s.short,
    `${intro(["Hizmetler", "hizmetler/"], s.title, s.intro)}<div class="wrap detail-layout"><article><section class="detail-box"><h2>Hizmet kapsamı</h2>${list(s.scope)}</section><section class="section small-section"><h2>Nasıl ilerliyoruz?</h2><ol class="service-process">${s.process.map((p, i) => `<li><span>0${i + 1}</span><p>${esc(p)}</p></li>`).join("")}</ol></section><section class="section small-section"><h2>Sıkça sorulan sorular</h2>${faqs(s.faqs)}</section></article><aside class="detail-aside"><div class="aside-illustration">${icon(serviceIcon(s.slug))}<span>SAHA / ${esc(s.title.toLocaleUpperCase("tr"))}</span></div><h2>Önce ihtiyacınızı dinleyelim.</h2><p>Mevcut tesisinizi ya da planladığınız yatırımı birlikte değerlendirelim.</p>${btn("Keşif isteyin", url("kesif-talebi/?hizmet=" + s.slug))}<a href="tel:+905384475676" class="aside-phone">${phone}</a><small>Ücretsiz keşif: İzmir, Manisa, Bergama, Balıkesir ve Aydın.</small></aside></div>${related.length ? `<section class="wrap section">${sectionHead("İlgili çalışmalar", "Sahadaki karşılığı.")}<div class="project-grid three-cols">${related.slice(0, 3).map(projectCard).join("")}</div></section>` : ""}${cta()}`,
    "hizmetler/",
  );
}
const categories = [...new Set(data.projects.map((p) => p.category))];
page(
  "projeler/",
  "Projelerimiz",
  "Altındaş Mühendislik proje dosyaları. Konut, fabrika, trafo, kompanzasyon ve işletme sorumluluğu uygulamaları.",
  `${intro(null, "Her projenin bir saha hikâyesi var.", "İhtiyaçtan uygulamaya; tamamlanan çalışmalarımız ve devam eden sorumluluklarımız.")}<section class="wrap section compact-top"><div class="project-filters" role="group" aria-label="Projeleri filtrele"><button type="button" data-filter="all" class="active" aria-pressed="true">Tümü</button>${categories.map((c) => `<button type="button" data-filter="${esc(c)}" aria-pressed="false">${esc(c)}</button>`).join("")}</div><p class="filter-status sr-only" role="status"></p><div class="project-grid three-cols">${data.projects.map(projectCard).join("")}</div></section>${cta()}`,
  "projeler/",
);
for (const p of data.projects) {
  const relatedServices = data.services.filter((s) =>
    p.services?.includes(s.slug),
  );
  page(
    "projeler/" + p.slug + "/",
    p.title,
    p.summary,
    `${intro(["Projeler", "projeler/"], p.title, p.summary)}<div class="wrap"><div class="project-detail-meta"><span>${esc(p.category)}</span><span class="status">${esc(p.status)}</span>${p.location ? `<span>${icon("pin")}${esc(p.location)}</span>` : ""}${p.year ? `<span>${esc(p.year)}</span>` : ""}</div><div class="project-detail-cover">${p.image ? `<img src="${url(p.image.replace(/^\//, ""))}" alt="${esc(p.imageAlt)}" width="1600" height="1200" fetchpriority="high">` : drawing(p.slug === "ramada" ? "shield" : "building")}</div><div class="project-story"><article><p class="eyebrow">Proje dosyası</p><h2>İhtiyaçtan uygulamaya.</h2><p class="story-intro">${esc(p.intro)}</p><h2>Üstlendiğimiz işler</h2>${list(p.scope)}${p.results?.length ? `<h2>Sonuç ve güncel durum</h2>${p.results.map((r) => `<p>${esc(r)}</p>`).join("")}` : ""}</article><aside class="project-facts"><h2>Proje künyesi</h2><dl>${p.facts.map((f) => `<div><dt>${esc(f.label)}</dt><dd>${esc(f.value)}</dd></div>`).join("")}</dl><h3>İlgili hizmetler</h3><div class="tag-links">${relatedServices.map((s) => `<a href="${url("hizmetler/" + s.slug + "/")}">${esc(s.title)} ${icon("arrowUp")}</a>`).join("")}</div></aside></div><div class="project-navigation"><a class="inline-link" href="${url("projeler/")}">← Tüm proje dosyaları</a>${btn("Benzer bir proje konuşalım", url("kesif-talebi/"), "secondary")}</div></div>${cta()}`,
    "projeler/",
  );
}
page(
  "kurumsal/",
  "Kurumsal",
  "Aliağa merkezli Altındaş Mühendislik & Elektrik ve elektrik mühendisi Caner Altındaş hakkında.",
  `${intro(null, "Sahaya yakın.<br>Çözümün içinde.".replace("<br>", " "), data.about.intro)}<section class="wrap about-body"><div class="about-panel">${icon("bolt")}<h2>Enerjiniz Güvende,<br><span>Geleceğiniz Aydınlık.</span></h2><p>ALTINDAŞ<br>MÜHENDİSLİK &amp; ELEKTRİK</p></div><article>${data.about.paragraphs.map((p) => `<p>${esc(p)}</p>`).join("")}<h2>Nasıl bir iş ortaklığı?</h2>${list(["İhtiyaca göre keşif, ölçüm ve teknik değerlendirme.", "Kapsamı, takvimi ve bedeli belli teklif.", "Uygulama sonrası test, raporlama ve planlı takip."])}</article></section><section class="wrap section">${sectionHead("Yetkinliklerimiz", "Yetkinlik, sorumlulukla birlikte.")}<div class="credential-grid">${[
    ["EMO üyeliği", "TMMOB Elektrik Mühendisleri Odası İzmir Şubesi üyeliği."],
    ["SMM", "Serbest Müşavir Mühendislik belgesi."],
    [
      "YG işletme sorumluluğu",
      "Yüksek gerilim tesislerinde işletme sorumluluğu yetkinliği.",
    ],
    [
      "TS EN 61439 ve İSG",
      "Pano ve iş sağlığı güvenliği süreçlerini destekleyen belgeler.",
    ],
  ]
    .map(([h, p]) => `<div>${icon("shield")}<h3>${h}</h3><p>${p}</p></div>`)
    .join(
      "",
    )}</div></section><section class="wrap section">${sectionHead("Çalışma biçimimiz", "Planlı adımlar. Açık iletişim.")}${stepsHTML()}</section>${cta()}`,
  "kurumsal/",
);
page(
  "referanslar/",
  "Referanslarımız",
  "Altındaş Mühendislik & Elektrik’in birlikte çalıştığı kurumlar ve ilgili proje dosyaları.",
  `${intro(null, "Güven, birlikte çalışarak kurulur.", "Farklı sektörlerde, farklı ihtiyaçlarda. Sahada sorumluluk aldığımız kurumlarla tanışın.")}<section class="wrap section compact-top"><div class="reference-page-grid">${refs.map((r) => `<div class="reference-tile"><div>${r.image ? `<img src="${url("assets/references/" + r.image + ".webp")}" alt="${r.name}" width="200" height="90">` : '<span class="reference-wordmark">DÖRT MEVSİM<small>KONUT YAPI KOOPERATİFİ</small></span>'}</div><h2>${r.name}</h2><a class="inline-link" href="${url("projeler/" + (r.image || "dort-mevsim") + "/")}">İlgili proje dosyası ${icon("arrow")}</a></div>`).join("")}</div></section>${cta()}`,
  "projeler/",
);
page(
  "sikca-sorulan-sorular/",
  "Sıkça sorulan sorular",
  "Ücretsiz keşif, teklif süreci, trafo bakımı, işletme sorumluluğu ve hizmet bölgemiz hakkında sorular.",
  `${intro(null, "Aklınızdaki sorular.", "Birlikte çalışmaya başlamadan önce keşif, uygulama ve takip sürecimizi tanıyın.")}<section class="wrap section compact-top narrow">${faqs(data.faqs)}</section>${cta()}`,
);
const contactCards = () =>
  `<div class="contact-cards"><a href="tel:+905384475676">${icon("phone")}<span>Telefon<strong>${phone}</strong></span>${icon("arrowUp")}</a><a href="${wa}" target="_blank" rel="noopener noreferrer">${icon("mail")}<span>WhatsApp<strong>Mesaj gönderin</strong></span>${icon("arrowUp")}</a><a href="mailto:info@altindasmuhendislik.com">${icon("mail")}<span>E-posta<strong>info@altindasmuhendislik.com</strong></span>${icon("arrowUp")}</a></div>`;
page(
  "iletisim/",
  "İletişim",
  "Aliağa Yeni Mahalle, Anadolu Caddesi No: 24A. Telefon +90 538 447 56 76. Altındaş Mühendislik & Elektrik iletişim bilgileri.",
  `${intro(null, "İlk bağlantıyı kuralım.", "Yeni bir proje, bakım ihtiyacı ya da tesisinizle ilgili bir soru. Doğrudan bize ulaşın.")}<section class="wrap contact-layout">${contactCards()}<div class="address-panel">${icon("pin")}<h2>Aliağa’dan, sahanıza.</h2><p>Yeni Mah. Anadolu Cad. No: 24A<br>35800 Aliağa / İzmir</p><p>Pazartesi–Cumartesi<br><strong>08:30–18:00</strong></p><a class="inline-link" href="https://www.google.com/maps/search/?api=1&amp;query=Alt%C4%B1nda%C5%9F+M%C3%BChendislik+Alia%C4%9Fa" target="_blank" rel="noopener noreferrer">Yol tarifi alın ${icon("arrowUp")}</a></div></section><section class="wrap section"><div class="detail-box"><h2>Bakım ve işletme sözleşmeli müşterilerimize</h2><p>7/24 arıza müdahalesi sunuyoruz. Acil durum bildirimleri için doğrudan telefonla ulaşın.</p><a class="inline-link" href="tel:+905384475676">Hemen arayın ${icon("phone")}</a></div></section>${cta()}`,
  "iletisim/",
);
page(
  "kesif-talebi/",
  "Ücretsiz keşif talebi",
  "Tesisiniz veya projeniz için ücretsiz keşif talebinizi hazırlayın ve WhatsApp üzerinden Altındaş Mühendislik ile paylaşın.",
  `${intro(null, "Projenizden bahsedin.", "İhtiyacınızı birkaç adımda paylaşın. İzmir, Manisa, Bergama, Balıkesir ve Aydın’da ücretsiz keşif için görüşelim.")}<div class="wrap form-layout"><form id="discovery-form" class="discovery-form"><div class="form-heading"><h2>Keşif talebiniz</h2><p>Bilgileri doldurun; WhatsApp mesajınızı birlikte hazırlayalım.</p></div><div class="form-row"><div class="field"><label for="name">Adınız soyadınız <span>(zorunlu)</span></label><input id="name" name="name" autocomplete="name" required maxlength="100"></div><div class="field"><label for="company">Firma / site adı <span>(isteğe bağlı)</span></label><input id="company" name="company" autocomplete="organization" maxlength="150"></div></div><div class="form-row"><div class="field"><label for="telephone">Telefonunuz <span>(zorunlu)</span></label><input id="telephone" name="telephone" type="tel" autocomplete="tel" inputmode="tel" required maxlength="20" aria-describedby="phone-help"><small id="phone-help">Örnek: 0538 447 56 76</small></div><div class="field"><label for="location">İl / ilçe <span>(zorunlu)</span></label><input id="location" name="location" autocomplete="address-level2" required maxlength="100"></div></div><div class="field"><label for="service">İlgilendiğiniz hizmet</label><select id="service" name="service"><option value="">Birlikte belirleyelim</option>${data.services.map((s) => `<option value="${s.slug}">${esc(s.title)}</option>`).join("")}</select></div><div class="field"><label for="message">İhtiyacınız <span>(isteğe bağlı)</span></label><textarea id="message" name="message" rows="4" maxlength="1500" placeholder="Mevcut tesisiniz, projeniz veya yaşadığınız sorun…"></textarea></div><p class="form-notice">Bu form bilgileri sunucuya kaydetmez. Mesajınız aşağıda hazırlanır; gönderimi WhatsApp’ta siz tamamlarsınız. <a href="${url("gizlilik/")}">Gizlilik bilgileri</a></p><button class="btn btn-primary" type="submit">WhatsApp mesajını hazırla ${icon("arrow")}</button><noscript><p>Mesaj hazırlamak için JavaScript gerekir. <a href="${wa}">Doğrudan WhatsApp üzerinden yazabilirsiniz.</a></p></noscript><section id="message-preview" hidden aria-labelledby="preview-title"><h2 id="preview-title" tabindex="-1">Mesajınız hazır</h2><p>Henüz gönderilmedi. Kontrol edip WhatsApp’ta paylaşabilirsiniz.</p><pre id="prepared-message"></pre><a id="whatsapp-send" class="btn btn-primary" href="${wa}" target="_blank" rel="noopener noreferrer">WhatsApp’ta aç ${icon("arrowUp")}</a><p class="preview-note">Formu değiştirirseniz mesajı yeniden hazırlayın.</p></section></form><aside class="form-aside"><span class="eyebrow">Keşiften önce</span><h2>İyi bir başlangıç,<br>doğru sorularla.</h2><p>Elinizde varsa elektrik faturası, tek hat şeması veya pano fotoğrafı görüşmemize yardımcı olur. Bunları WhatsApp üzerinden paylaşabilirsiniz.</p><div class="response-note">${icon("check")}<span>Talebinize <strong>24 saat içinde</strong> dönüş yapıyoruz.</span></div><div class="response-note">${icon("check")}<span>Keşif sonrası teklif:<br><strong>2–3 iş günü</strong></span></div><div class="aside-contact"><p>Doğrudan konuşmak isterseniz</p><a href="tel:+905384475676">${phone}</a><small>Pazartesi–Cumartesi / 08:30–18:00</small></div></aside></div>`,
  "iletisim/",
);
page(
  "gizlilik/",
  "Gizlilik ve iletişim bilgileri",
  "Bu web sitesindeki keşif talebi formunun, dış bağlantıların ve iletişim kanallarının kullanımı hakkında bilgi.",
  `${intro(null, "Gizlilik ve iletişim bilgileri.", "Bu sitede paylaştığınız bilgilerin nasıl kullanıldığını açıkça anlatıyoruz.")}<article class="wrap prose narrow"><h2>Web sitesi ve ziyaret</h2><p>Bu site GitHub Pages üzerinde yayınlanır. Site kodu reklam, analiz çerezi veya ziyaretçi takip aracı kullanmaz. Barındırma sağlayıcısı, hizmetin çalışması için IP adresi ve teknik erişim kayıtlarını kendi politikalarına göre işleyebilir.</p><h2>Keşif talebi formu</h2><p>Formdaki bilgiler tarayıcınızda bir mesaj oluşturmak için kullanılır. Site bu bilgileri bir veri tabanına veya e-posta sunucusuna göndermez, yerel depolamaya kaydetmez. “WhatsApp’ta aç” bağlantısını seçtiğinizde hazırladığınız metin WhatsApp’a aktarılır; mesajı gönderme kararını siz verirsiniz. WhatsApp’ın kendi gizlilik koşulları geçerlidir.</p><h2>İletişim</h2><p>Paylaştığınız iletişim ve proje bilgileri, talebinize yanıt vermek ve keşif/teklif görüşmesini yürütmek için kullanılır. Bilgilerinizle ilgili sorularınız veya talepleriniz için <a href="mailto:info@altindasmuhendislik.com">info@altindasmuhendislik.com</a> adresinden bize ulaşabilirsiniz.</p><p>Altındaş Mühendislik &amp; Elektrik<br>Yeni Mah. Anadolu Cad. No: 24A, 35800 Aliağa / İzmir<br><a href="tel:+905384475676">${phone}</a></p><h2>Dış bağlantılar</h2><p>WhatsApp, harita, sosyal medya ve mevcut mağaza bağlantıları başka hizmetlere gider. Bu hizmetlerde ilgili sağlayıcının koşulları uygulanır. Bu sitede ödeme veya kart bilgisi alınmaz.</p></article>`,
);
page(
  "404.html",
  "Sayfa bulunamadı",
  "Aradığınız sayfa bulunamadı. Altındaş Mühendislik ana sayfasına veya hizmetlerine ulaşabilirsiniz.",
  `<section class="wrap error-page"><span class="error-number">404</span><h1>Bu bağlantı açık devre.</h1><p>Aradığınız sayfa taşınmış olabilir. Ana sayfadan devam edebilirsiniz.</p><div class="hero-actions">${btn("Ana sayfaya dönün", url(""))}${btn("Hizmetleri keşfedin", url("hizmetler/"), "secondary")}</div></section>`,
);
fs.writeFileSync(
  "dist/sitemap.xml",
  `<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${routes.map((l) => `<url><loc>${l}</loc></url>`).join("")}</urlset>`,
);
fs.writeFileSync(
  "dist/robots.txt",
  "User-agent: *\nAllow: /\nSitemap: " + origin + base + "sitemap.xml\n",
);
fs.writeFileSync("dist/.nojekyll", "");
console.log(`Built ${routes.length + 1} pages with base ${base}`);
