// Yönetim paneli (Hostinger). Kullanıcı adı/şifreyle oturum açar; kayıtlar
// admin/api üzerinden MySQL'e, fotoğraflar uploads/ klasörüne yazılır ve
// hemen yayına girer. Sunucu tarafı: app/admin.php, app/store.php.
import { bannedLabelsTr, findBannedPhrase } from "./content-rules.js";

const app = document.getElementById("admin-app");
const cfg = { ...app.dataset };
const statusEl = document.getElementById("admin-status");
const loginEl = document.getElementById("admin-login");
const loginForm = document.getElementById("login-form");
const workspace = document.getElementById("admin-workspace");

const KINDS = {
  projects: {
    noun: "proje",
    title: "Projeler",
    route: "projeler/",
    add: "Yeni proje ekle",
  },
  products: {
    noun: "ürün",
    title: "Mağaza ürünleri",
    route: "magaza/",
    add: "Yeni ürün ekle",
  },
};
// Panel yüklemeleri uploads/ altına gider; paketle gelen assets/ dosyalarına dokunulmaz.
const DIRS = {
  projects: "uploads/projects/",
  products: "uploads/products/",
  references: "uploads/references/",
};
const DRAWINGS = [
  ["building", "Bina"],
  ["shield", "Kalkan (işletme / bakım)"],
  ["trafo", "Trafo"],
  ["bolt", "Enerji"],
  ["circuit", "Devre / otomasyon"],
  ["camera", "Kamera"],
  ["sun", "Güneş enerjisi"],
];
const PROJECT_STATUSES = [
  "Tamamlandı",
  "Devam ediyor",
  "İşletme sorumluluğu sürüyor",
  "Referans proje",
];
const STOCK = ["Stokta", "Sipariş üzerine", "Tükendi"];
const UNITS = ["adet", "metre", "takım", "paket", "kg"];
const HOME_COUNT = 6;
const MAX_GALLERY = 12;
const PHOTO = { maxWidth: 1600, maxHeight: 1600 };
const LOGO = { maxWidth: 480, maxHeight: 200, logo: true };

const session = { user: null, csrf: null };
let repoState = null;
const ui = { tab: "projects", mode: "list", form: null, order: null };
let busy = false;
let uid = 0;
// Photos uploaded in this session, shown before the rebuilt site serves them.
const localPreviews = new Map();

// ---------------------------------------------------------------- helpers
function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs || {})) {
    if (value == null || value === false) continue;
    if (key === "class") el.className = value;
    else if (key.startsWith("on")) el.addEventListener(key.slice(2), value);
    else if (value === true) el.setAttribute(key, "");
    else el.setAttribute(key, value);
  }
  for (const child of children.flat(Infinity)) {
    if (child == null || child === false) continue;
    el.append(child instanceof Node ? child : document.createTextNode(child));
  }
  return el;
}
const button = (label, onclick, cls = "btn btn-secondary", extra = {}) =>
  h("button", { type: "button", class: cls, onclick, ...extra }, label);
const norm = (s) =>
  String(s ?? "")
    .trim()
    .toLocaleLowerCase("tr");
const lines = (text) =>
  String(text ?? "")
    .split("\n")
    .map((l) => l.trim())
    .filter(Boolean);
const priceFormat = new Intl.NumberFormat("tr-TR", {
  style: "currency",
  currency: "TRY",
});
const stamp = () =>
  Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
const pageUrl = (kind, item) =>
  cfg.site + KINDS[kind].route + (item ? item.slug + "/" : "");

function slugify(text) {
  return String(text ?? "")
    .toLocaleLowerCase("tr")
    .replace(/ç/g, "c")
    .replace(/ğ/g, "g")
    .replace(/ı/g, "i")
    .replace(/ö/g, "o")
    .replace(/ş/g, "s")
    .replace(/ü/g, "u")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 60)
    .replace(/-+$/, "");
}

// Accepts Turkish ("1.250,50") and plain ("1250.50") notation.
function parsePrice(raw) {
  let s = String(raw ?? "")
    .trim()
    .replace(/\s|₺|TL/gi, "");
  if (!s) return null;
  if (s.includes(",")) s = s.replace(/\./g, "").replace(",", ".");
  else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, "");
  if (!/^\d+(\.\d{1,2})?$/.test(s)) return NaN;
  return Math.round(Number(s) * 100) / 100;
}

function setStatus(message = "", kind = "info", link = null) {
  statusEl.dataset.kind = message ? kind : "";
  statusEl.replaceChildren(
    ...(message ? [message] : []),
    ...(link
      ? [
          " ",
          h(
            "a",
            { href: link.href, target: "_blank", rel: "noopener noreferrer" },
            link.label,
          ),
        ]
      : []),
  );
}
let restoreFocus = null;
function setBusy(value) {
  busy = value;
  if (value) restoreFocus = document.activeElement;
  workspace.inert = value;
  workspace.classList.toggle("is-busy", value);
  workspace.setAttribute("aria-busy", String(value));
  if (
    !value &&
    restoreFocus?.isConnected &&
    document.activeElement === document.body
  )
    restoreFocus.focus();
}

// ---------------------------------------------------------------- API
class ApiError extends Error {
  constructor(status, message) {
    super(message);
    this.status = status;
  }
}
class InputError extends Error {}

// Sunucudaki panel API'si (app/admin.php).
async function server(action, { method = "GET", json, form } = {}, retried = false) {
  let res;
  try {
    // GET adresi her seferinde benzersizdir; sunucu ya da ara önbellek eski
    // bir oturum yanıtını (ve eskimiş CSRF anahtarını) döndüremez.
    const url = `${cfg.api}/${action}` + (method === "GET" ? `?_=${Date.now()}` : "");
    res = await fetch(url, {
      method,
      cache: "no-store",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(method === "GET" ? {} : { "X-CSRF-Token": session.csrf ?? "" }),
        ...(json ? { "Content-Type": "application/json" } : {}),
      },
      body: json ? JSON.stringify(json) : form,
    });
  } catch {
    throw new ApiError(
      0,
      "Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.",
    );
  }
  const info = await res.json().catch(() => ({}));
  if (info.csrf) session.csrf = info.csrf;
  // Anahtar eskimişse güncelini alıp işlemi bir kez daha dener. Oturum
  // kapanmışsa ikinci deneme 401 döner ve panel giriş ekranına geçer.
  if (res.status === 403 && method !== "GET" && !retried) {
    await server("state").catch((err) => {
      if (!(err instanceof ApiError && err.status === 401)) throw err;
    });
    return server(action, { method, json, form }, true);
  }
  if (!res.ok)
    throw new ApiError(
      res.status,
      res.status === 413
        ? "Fotoğraflar sunucunun kabul ettiği boyutu aşıyor. Daha az fotoğrafla tekrar deneyin."
        : info.error || `Sunucu hatası (${res.status}). Lütfen tekrar deneyin.`,
    );
  return info;
}

function explain(err) {
  if (err instanceof InputError) return err.message;
  if (!(err instanceof ApiError)) {
    console.error(err);
    return "Beklenmeyen bir hata oluştu: " + err.message;
  }
  return err.message;
}

// ---------------------------------------------------------------- images
async function processImage(file, { maxWidth, maxHeight, logo = false }) {
  if (!file.type.startsWith("image/"))
    throw new InputError(`${file.name}: yalnız fotoğraf dosyaları eklenebilir.`);
  if (file.size > 30 * 1024 * 1024)
    throw new InputError(`${file.name}: dosya 30 MB’tan büyük.`);
  let source;
  let objectUrl = null;
  try {
    source = await createImageBitmap(file, { imageOrientation: "from-image" });
  } catch {
    objectUrl = URL.createObjectURL(file);
    source = new Image();
    source.src = objectUrl;
    try {
      await source.decode();
    } catch {
      URL.revokeObjectURL(objectUrl);
      throw new InputError(
        `${file.name}: bu fotoğraf biçimi tarayıcıda açılamadı. JPG, PNG veya WebP deneyin.`,
      );
    }
  }
  const w0 = source.naturalWidth || source.width;
  const h0 = source.naturalHeight || source.height;
  const scale = Math.min(1, maxWidth / w0, maxHeight / h0);
  const width = Math.max(1, Math.round(w0 * scale));
  const height = Math.max(1, Math.round(h0 * scale));
  const draw = (background) => {
    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d");
    if (background) {
      ctx.fillStyle = background;
      ctx.fillRect(0, 0, width, height);
    }
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(source, 0, 0, width, height);
    return canvas;
  };
  const toBlob = (canvas, type, quality) =>
    new Promise((resolve) => canvas.toBlob(resolve, type, quality));
  // Re-encoding also strips camera metadata such as GPS location.
  let blob = await toBlob(draw(), "image/webp", logo ? 0.9 : 0.82);
  let ext = "webp";
  if (!blob || blob.type !== "image/webp") {
    if (logo) {
      blob = await toBlob(draw(), "image/png");
      ext = "png";
    } else {
      blob = await toBlob(draw("#ffffff"), "image/jpeg", 0.85);
      ext = "jpg";
    }
  }
  source.close?.();
  if (objectUrl) URL.revokeObjectURL(objectUrl);
  if (!blob) throw new InputError(`${file.name}: fotoğraf hazırlanamadı.`);
  return { blob, ext, width, height, preview: URL.createObjectURL(blob) };
}

function preview(entry) {
  const img = h("img", { alt: "", width: 160, height: 120 });
  const local = entry.upload?.preview ?? localPreviews.get(entry.src);
  if (local) img.src = local;
  else {
    img.src = cfg.base + entry.src;
    img.addEventListener("error", () =>
      img.replaceWith(h("span", { class: "admin-thumb-empty" }, "Önizleme yok")),
    );
  }
  return img;
}

// ---------------------------------------------------------------- session
async function loadState() {
  const state = await server("state");
  session.user = state.user;
  return { data: state.data };
}

async function connect() {
  setStatus("Bağlanılıyor…", "progress");
  repoState = await loadState();
  showWorkspace();
}

function showWorkspace() {
  loginEl.hidden = true;
  workspace.hidden = false;
  ui.mode = "list";
  ui.form = null;
  ui.order = null;
  render();
  setStatus("");
  focusHeading();
}

function showLogin(message, kind = "info") {
  session.user = null;
  repoState = null;
  ui.mode = "list";
  ui.form = null;
  ui.order = null;
  workspace.replaceChildren();
  workspace.hidden = true;
  loginEl.hidden = false;
  loginForm.reset();
  setStatus(message, kind);
  loginForm.querySelector("input")?.focus();
}

async function logout() {
  if (!leaveEditor()) return;
  try {
    await server("logout", { method: "POST" });
    // Yeni girişte kullanılacak CSRF anahtarını alır (yanıt 401'dir).
    await server("state").catch(() => {});
  } catch (err) {
    // Oturum zaten kapanmışsa çıkış tamamlanmış sayılır.
    if (!(err instanceof ApiError && err.status === 401)) {
      fail(err);
      return;
    }
  }
  showLogin("Çıkış yapıldı.");
}

// Hata mesajını gösterir; oturum düştüyse giriş ekranına döner.
function fail(err) {
  if (err instanceof ApiError && err.status === 401) {
    if (ui.form) ui.form.dirty = false;
    showLogin("Oturumun süresi doldu. Lütfen yeniden giriş yapın.", "error");
    return;
  }
  setStatus(explain(err), "error");
}

// Bir işlemi (ekle/güncelle, sil, sırala) fotoğraflarıyla birlikte sunucuya yazar.
async function persist(op, uploads = []) {
  const body = new FormData();
  body.append("op", JSON.stringify(op));
  body.append("paths", JSON.stringify(uploads.map((u) => u.path)));
  for (const u of uploads) body.append("files[]", u.blob, u.path.split("/").pop());
  setStatus(
    uploads.length ? "Fotoğraflar yükleniyor ve kaydediliyor…" : "Değişiklik kaydediliyor…",
    "progress",
  );
  const state = await server("save", { method: "POST", form: body });
  repoState = { data: state.data };
}

function published(href) {
  setStatus("Kaydedildi; değişiklik sitede yayında.", "success", {
    href,
    label: "Sayfayı aç ↗",
  });
}

async function refresh() {
  if (busy || !leaveEditor()) return;
  setBusy(true);
  try {
    setStatus("İçerik yeniden yükleniyor…", "progress");
    repoState = await loadState();
    ui.order = null;
    setStatus("Güncel içerik yüklendi.", "success");
  } catch (err) {
    fail(err);
  } finally {
    setBusy(false);
  }
  render();
  focusHeading();
}

function leaveEditor() {
  if (ui.mode === "edit" && ui.form?.dirty)
    return window.confirm(
      "Kaydedilmemiş değişiklikler kaybolacak. Devam edilsin mi?",
    );
  return true;
}

// ---------------------------------------------------------------- views
function focusHeading() {
  workspace.querySelector("h2[tabindex]")?.focus();
}

function render() {
  if (!repoState) return;
  const counts = {
    projects: repoState.data.projects.length,
    products: repoState.data.products.length,
  };
  workspace.replaceChildren(
    h(
      "div",
      { class: "admin-toolbar" },
      h(
        "p",
        {},
        h("strong", {}, session.user.login),
        " olarak giriş yaptınız",
      ),
      h(
        "div",
        { class: "admin-toolbar-actions" },
        button("Yenile", refresh, "admin-plain"),
        button("Çıkış yap", logout, "admin-plain"),
      ),
    ),
    h(
      "div",
      {
        class: "project-filters admin-tabs",
        role: "group",
        "aria-label": "İçerik türü",
      },
      Object.entries(KINDS).map(([kind, k]) =>
        button(
          `${k.title} (${counts[kind]})`,
          () => switchTab(kind),
          ui.tab === kind ? "active" : "",
          { "aria-pressed": String(ui.tab === kind) },
        ),
      ),
    ),
    ui.mode === "edit" ? renderEditor(ui.form) : renderList(ui.tab),
  );
}

function switchTab(kind) {
  if (busy || kind === ui.tab || !leaveEditor()) return;
  ui.tab = kind;
  ui.mode = "list";
  ui.form = null;
  ui.order = null;
  render();
  focusHeading();
}

function orderedItems(kind) {
  const list = repoState.data[kind];
  if (ui.order?.kind !== kind) return list;
  return ui.order.slugs.map((s) => list.find((x) => x.slug === s));
}

function renderList(kind) {
  const k = KINDS[kind];
  const items = orderedItems(kind);
  let homeLeft = HOME_COUNT;
  const rows = items.map((item, i) => {
    const onHome = kind === "projects" && !item.draft && homeLeft-- > 0;
    const meta =
      kind === "projects"
        ? [item.category, item.status, item.year].filter(Boolean).join(" · ")
        : [
            item.category,
            typeof item.price === "number"
              ? priceFormat.format(item.price)
              : "Fiyat için sorun",
            item.stock,
          ]
            .filter(Boolean)
            .join(" · ");
    const thumb = item.image
      ? preview({ src: item.image })
      : h("span", { class: "admin-thumb-empty" }, "Görsel yok");
    return h(
      "li",
      { class: "admin-item" },
      h("div", { class: "admin-thumb" }, thumb),
      h(
        "div",
        { class: "admin-item-text" },
        h("h3", {}, item.title),
        h("p", {}, meta),
        h(
          "div",
          { class: "admin-badges" },
          item.draft ? h("span", { class: "admin-badge draft" }, "Taslak") : null,
          onHome ? h("span", { class: "admin-badge" }, "Ana sayfada") : null,
        ),
      ),
      h(
        "div",
        { class: "admin-item-actions" },
        button("↑", () => move(kind, i, -1), "admin-icon", {
          "aria-label": `${item.title}: yukarı taşı`,
          "data-focus": `up-${item.slug}`,
          disabled: i === 0,
        }),
        button("↓", () => move(kind, i, 1), "admin-icon", {
          "aria-label": `${item.title}: aşağı taşı`,
          "data-focus": `down-${item.slug}`,
          disabled: i === items.length - 1,
        }),
        item.draft
          ? null
          : h(
              "a",
              {
                class: "admin-plain",
                href: pageUrl(kind, item),
                target: "_blank",
                rel: "noopener noreferrer",
                "aria-label": `${item.title}: sitede görüntüle`,
              },
              "Görüntüle ↗",
            ),
        button("Düzenle", () => openEditor(kind, item.slug), "admin-plain", {
          "aria-label": `${item.title}: düzenle`,
        }),
        button("Sil", () => removeItem(kind, item), "admin-plain danger", {
          "aria-label": `${item.title}: sil`,
        }),
      ),
    );
  });
  const help =
    kind === "projects"
      ? "Sıralama sitede de geçerlidir. Yeni projeler listenin başına eklenir; ilk 6 yayındaki proje ana sayfada gösterilir."
      : "Mağaza sayfası ve menüdeki “Mağaza” bağlantısı, yayında en az bir ürün olduğunda otomatik oluşur.";
  return h(
    "section",
    { class: "admin-panel", "aria-labelledby": "list-title" },
    h(
      "div",
      { class: "admin-list-head" },
      h(
        "div",
        {},
        h("h2", { id: "list-title", tabindex: "-1" }, k.title),
        h("p", {}, help),
      ),
      button(k.add, () => openEditor(kind, null), "btn btn-primary"),
    ),
    ui.order?.kind === kind
      ? h(
          "div",
          {
            class: "admin-orderbar",
            role: "region",
            "aria-label": "Kaydedilmemiş sıralama",
          },
          h("p", {}, "Sıralama değişti ve henüz kaydedilmedi."),
          button("Sıralamayı kaydet", () => saveOrder(kind), "btn btn-primary"),
          button("Vazgeç", () => {
            ui.order = null;
            render();
            focusHeading();
          }),
        )
      : null,
    rows.length
      ? h("ol", { class: "admin-list" }, rows)
      : h(
          "p",
          { class: "admin-empty" },
          kind === "products"
            ? "Henüz ürün yok. İlk ürünü eklediğinizde mağaza sayfası otomatik oluşur."
            : "Henüz proje yok.",
        ),
  );
}

function move(kind, index, delta) {
  if (busy) return;
  const slugs = orderedItems(kind).map((x) => x.slug);
  const target = index + delta;
  if (target < 0 || target >= slugs.length) return;
  [slugs[index], slugs[target]] = [slugs[target], slugs[index]];
  const original = repoState.data[kind].map((x) => x.slug);
  ui.order = slugs.join() === original.join() ? null : { kind, slugs };
  const focusKey = `${delta < 0 ? "up" : "down"}-${slugs[target]}`;
  render();
  const again = workspace.querySelector(`[data-focus="${focusKey}"]`);
  (again && !again.disabled
    ? again
    : workspace.querySelector(
        `[data-focus="${delta < 0 ? "down" : "up"}-${slugs[target]}"]`,
      )
  )?.focus();
}

async function saveOrder(kind) {
  if (busy || ui.order?.kind !== kind) return;
  const slugs = ui.order.slugs;
  setBusy(true);
  try {
    await persist({ type: "reorder", kind, slugs });
  } catch (err) {
    fail(err);
    return;
  } finally {
    setBusy(false);
  }
  ui.order = null;
  render();
  focusHeading();
  published(pageUrl(kind));
}

async function removeItem(kind, item) {
  if (busy) return;
  const k = KINDS[kind];
  if (
    !window.confirm(
      `“${item.title}” silinsin mi? ${k.noun === "proje" ? "Proje" : "Ürün"} sayfası ve fotoğrafları siteden kaldırılır.`,
    )
  )
    return;
  setBusy(true);
  try {
    await persist({ type: "delete", kind, slug: item.slug });
  } catch (err) {
    fail(err);
    return;
  } finally {
    setBusy(false);
  }
  ui.order = null;
  render();
  focusHeading();
  published(pageUrl(kind));
}

// ---------------------------------------------------------------- editor
const media = (src, alt, width, height) =>
  src ? { src, alt: alt || "", width, height } : null;

function toModel(kind, item) {
  const common = {
    title: item?.title ?? "",
    slug: item?.slug ?? "",
    category: item?.category ?? "",
    summary: item?.summary ?? "",
    cover: media(item?.image, item?.imageAlt),
    gallery: (item?.gallery ?? []).map((g) =>
      media(g.src, g.alt, g.width, g.height),
    ),
    draft: Boolean(item?.draft),
  };
  if (kind === "projects") {
    const ref = item
      ? repoState.data.references.find((r) => r.project === item.slug)
      : null;
    return {
      ...common,
      status: item?.status ?? "Tamamlandı",
      year: item?.year ?? "",
      location: item?.location ?? "",
      intro: item?.intro ?? "",
      scope: (item?.scope ?? []).join("\n"),
      results: (item?.results ?? []).join("\n"),
      facts: (item?.facts ?? []).map((f) => ({ ...f })),
      services: [...(item?.services ?? [])],
      drawing: item?.drawing ?? "building",
      reference: {
        enabled: Boolean(ref),
        name: ref?.name ?? "",
        logo: media(ref?.logo, ref?.name),
      },
    };
  }
  return {
    ...common,
    brand: item?.brand ?? "",
    sku: item?.sku ?? "",
    price:
      typeof item?.price === "number"
        ? item.price.toFixed(2).replace(".", ",")
        : "",
    vat: item?.vat ?? "dahil",
    unit: item?.unit ?? "adet",
    stock: item?.stock ?? "Stokta",
    description: item?.description ?? "",
    specs: (item?.specs ?? []).map((s) => ({ ...s })),
  };
}

function openEditor(kind, slug) {
  if (busy || !leaveEditor()) return;
  if (ui.order) ui.order = null;
  const original = slug
    ? repoState.data[kind].find((x) => x.slug === slug)
    : null;
  ui.mode = "edit";
  ui.form = {
    kind,
    original: original ? structuredClone(original) : null,
    model: toModel(kind, original),
    dirty: false,
    pending: 0,
    slugTouched: Boolean(original),
  };
  render();
  focusHeading();
}

function closeEditor() {
  if (!leaveEditor()) return;
  ui.mode = "list";
  ui.form = null;
  render();
  focusHeading();
}

const fid = (key) => `f-${key}`;
function field(form, key, label, opts = {}) {
  const {
    required,
    max,
    help,
    list,
    rows,
    inputmode,
    placeholder,
    counter,
    onInput,
  } = opts;
  const id = fid(key);
  const describedBy = [help || counter ? id + "-help" : ""]
    .filter(Boolean)
    .join(" ");
  const control = rows
    ? h("textarea", {
        id,
        name: key,
        rows,
        maxlength: max,
        placeholder,
        "aria-describedby": describedBy || null,
        required,
      })
    : h("input", {
        id,
        name: key,
        maxlength: max,
        list: list ? id + "-list" : null,
        inputmode,
        placeholder,
        autocomplete: "off",
        "aria-describedby": describedBy || null,
        required,
      });
  control.value = form.model[key] ?? "";
  const helpEl =
    help || counter ? h("small", { id: id + "-help" }, help ?? "") : null;
  const updateCounter = () => {
    if (counter)
      helpEl.textContent = `${help ? help + " " : ""}${control.value.length}/${max} karakter.`;
  };
  updateCounter();
  control.addEventListener("input", () => {
    form.model[key] = control.value;
    form.dirty = true;
    updateCounter();
    onInput?.(control.value);
  });
  return h(
    "div",
    { class: "field", "data-field": key },
    h(
      "label",
      { for: id },
      label,
      required ? h("span", {}, " (zorunlu)") : null,
    ),
    control,
    list
      ? h(
          "datalist",
          { id: id + "-list" },
          list.map((v) => h("option", { value: v })),
        )
      : null,
    helpEl,
  );
}

function selectField(form, key, label, options, help) {
  const id = fid(key);
  const select = h(
    "select",
    { id, name: key, "aria-describedby": help ? id + "-help" : null },
    options.map(([value, text]) => h("option", { value }, text)),
  );
  select.value = form.model[key];
  select.addEventListener("change", () => {
    form.model[key] = select.value;
    form.dirty = true;
  });
  return h(
    "div",
    { class: "field", "data-field": key },
    h("label", { for: id }, label),
    select,
    help ? h("small", { id: id + "-help" }, help) : null,
  );
}

function checkField(form, key, label, help, onChange) {
  const id = fid(key);
  const input = h("input", {
    id,
    type: "checkbox",
    name: key,
    "aria-describedby": help ? id + "-help" : null,
  });
  input.checked = Boolean(form.model[key]);
  input.addEventListener("change", () => {
    form.model[key] = input.checked;
    form.dirty = true;
    onChange?.(input.checked);
  });
  return h(
    "div",
    { class: "admin-check", "data-field": key },
    input,
    h("label", { for: id }, label),
    help ? h("small", { id: id + "-help" }, help) : null,
  );
}

function slugField(form, k) {
  if (form.original)
    return h(
      "div",
      { class: "field" },
      h("p", { class: "admin-label" }, "Sayfa adresi"),
      h("p", { class: "admin-slug" }, `${k.route}${form.model.slug}/`),
      h("small", {}, "Yayınlanmış sayfanın adresi değiştirilemez."),
    );
  const id = fid("slug");
  const input = h("input", {
    id,
    name: "slug",
    maxlength: 60,
    autocomplete: "off",
    spellcheck: "false",
    "aria-describedby": id + "-help",
  });
  input.value = form.model.slug;
  input.addEventListener("input", () => {
    form.slugTouched = true;
    form.model.slug = input.value;
    form.dirty = true;
  });
  form.syncSlug = (title) => {
    if (form.slugTouched) return;
    form.model.slug = slugify(title);
    input.value = form.model.slug;
  };
  return h(
    "div",
    { class: "field", "data-field": "slug" },
    h("label", { for: id }, "Sayfa adresi", h("span", {}, " (zorunlu)")),
    h("div", { class: "admin-prefix" }, h("span", {}, k.route), input),
    h(
      "small",
      { id: id + "-help" },
      "Addan otomatik oluşur. Küçük harf, rakam ve tire kullanılır; kayıttan sonra değiştirilemez.",
    ),
  );
}

function pairsField(form, key, legend, { help, max = 10, labelName, valueName }) {
  const rowsBox = h("div", { class: "admin-pairs" });
  const add = button(
    "+ Satır ekle",
    () => {
      form.model[key].push({ label: "", value: "" });
      form.dirty = true;
      draw();
      rowsBox.querySelector(".admin-pair:last-child input")?.focus();
    },
    "admin-plain",
  );
  const draw = () => {
    rowsBox.replaceChildren(
      ...form.model[key].map((row, i) => {
        const a = h("input", {
          "aria-label": `${labelName} ${i + 1}`,
          placeholder: labelName,
          maxlength: 40,
          autocomplete: "off",
        });
        const b = h("input", {
          "aria-label": `${valueName} ${i + 1}`,
          placeholder: valueName,
          maxlength: 80,
          autocomplete: "off",
        });
        a.value = row.label ?? "";
        b.value = row.value ?? "";
        a.addEventListener("input", () => {
          row.label = a.value;
          form.dirty = true;
        });
        b.addEventListener("input", () => {
          row.value = b.value;
          form.dirty = true;
        });
        return h(
          "div",
          { class: "admin-pair" },
          a,
          b,
          button(
            "Kaldır",
            () => {
              form.model[key].splice(i, 1);
              form.dirty = true;
              draw();
              add.focus();
            },
            "admin-plain danger",
            { "aria-label": `${i + 1}. satırı kaldır` },
          ),
        );
      }),
    );
    add.disabled = form.model[key].length >= max;
  };
  draw();
  return h(
    "fieldset",
    { class: "admin-subset", "data-field": key, id: "field-" + key },
    h("legend", {}, legend),
    help ? h("p", { class: "admin-help" }, help) : null,
    rowsBox,
    add,
  );
}

async function pickImages(form, files, opts, onReady, statusBox) {
  const list = [...files];
  if (!list.length) return;
  form.pending++;
  statusBox.textContent = "Fotoğraf hazırlanıyor…";
  const problems = [];
  for (const file of list) {
    try {
      onReady(await processImage(file, opts));
      form.dirty = true;
    } catch (err) {
      problems.push(explain(err));
    }
  }
  form.pending--;
  statusBox.textContent = problems.join(" ");
}

function altInput(entry, label, form) {
  const id = `alt-${++uid}`;
  const input = h("input", {
    id,
    maxlength: 160,
    autocomplete: "off",
    "aria-describedby": id + "-help",
  });
  input.value = entry.alt ?? "";
  input.addEventListener("input", () => {
    entry.alt = input.value;
    form.dirty = true;
  });
  return h(
    "div",
    { class: "field" },
    h("label", { for: id }, label, h("span", {}, " (zorunlu)")),
    input,
    h(
      "small",
      { id: id + "-help" },
      "Fotoğrafta ne göründüğünü kısaca yazın; görme engelli ziyaretçiler için okunur.",
    ),
  );
}

function coverField(form) {
  const box = h("div", { class: "admin-subset", id: "field-cover", "data-field": "cover" });
  const status = h("p", { class: "admin-help", role: "status" });
  const draw = () => {
    const id = `file-${++uid}`;
    const input = h("input", { id, type: "file", accept: "image/*" });
    input.addEventListener("change", () =>
      pickImages(
        form,
        input.files,
        PHOTO,
        (upload) => {
          form.model.cover = {
            alt:
              form.model.cover?.alt ||
              (form.model.title ? `${form.model.title} fotoğrafı` : ""),
            upload,
          };
          draw();
        },
        status,
      ),
    );
    const cover = form.model.cover;
    box.replaceChildren(
      h("h3", {}, "Kapak fotoğrafı"),
      h(
        "p",
        { class: "admin-help" },
        form.kind === "projects"
          ? "Kartta ve proje sayfasının üstünde görünür. Fotoğraf eklenmezse proje şematik çizimle gösterilir."
          : "Ürün kartında ve ürün sayfasında görünür.",
      ),
      cover
        ? h(
            "div",
            { class: "media-tile" },
            h("div", { class: "media-preview" }, preview(cover)),
            h(
              "div",
              {},
              altInput(cover, "Fotoğraf açıklaması", form),
              button(
                "Kapak fotoğrafını kaldır",
                () => {
                  form.model.cover = null;
                  form.dirty = true;
                  draw();
                },
                "admin-plain danger",
              ),
            ),
          )
        : null,
      h(
        "div",
        { class: "field admin-file" },
        h("label", { for: id }, cover ? "Fotoğrafı değiştir" : "Fotoğraf seçin"),
        input,
      ),
      status,
    );
  };
  draw();
  return box;
}

function galleryField(form) {
  const box = h("div", { class: "admin-subset", id: "field-gallery", "data-field": "gallery" });
  const status = h("p", { class: "admin-help", role: "status" });
  const draw = () => {
    const id = `file-${++uid}`;
    const input = h("input", { id, type: "file", accept: "image/*", multiple: true });
    const room = MAX_GALLERY - form.model.gallery.length;
    input.disabled = room <= 0;
    input.addEventListener("change", () => {
      const files = [...input.files].slice(0, room);
      pickImages(
        form,
        files,
        PHOTO,
        (upload) => {
          form.model.gallery.push({
            alt: form.model.title ? `${form.model.title} fotoğrafı` : "",
            upload,
          });
        },
        status,
      ).then(draw);
    });
    box.replaceChildren(
      h("h3", {}, "Galeri"),
      h(
        "p",
        { class: "admin-help" },
        `${form.kind === "projects" ? "Proje" : "Ürün"} sayfasında ek fotoğraflar olarak gösterilir. En fazla ${MAX_GALLERY} fotoğraf.`,
      ),
      h(
        "div",
        { class: "media-list" },
        form.model.gallery.map((entry, i) =>
          h(
            "div",
            { class: "media-tile", "data-field": `gallery-${i}`, id: `field-gallery-${i}` },
            h("div", { class: "media-preview" }, preview(entry)),
            h(
              "div",
              {},
              altInput(entry, `${i + 1}. fotoğrafın açıklaması`, form),
              h(
                "div",
                { class: "admin-inline-actions" },
                button(
                  "← Öne al",
                  () => {
                    const g = form.model.gallery;
                    [g[i - 1], g[i]] = [g[i], g[i - 1]];
                    form.dirty = true;
                    draw();
                  },
                  "admin-plain",
                  { disabled: i === 0, "aria-label": `${i + 1}. fotoğrafı öne al` },
                ),
                button(
                  "Kaldır",
                  () => {
                    form.model.gallery.splice(i, 1);
                    form.dirty = true;
                    draw();
                  },
                  "admin-plain danger",
                  { "aria-label": `${i + 1}. fotoğrafı kaldır` },
                ),
              ),
            ),
          ),
        ),
      ),
      h(
        "div",
        { class: "field admin-file" },
        h("label", { for: id }, room > 0 ? "Fotoğraf ekleyin (birden çok seçebilirsiniz)" : "Galeri dolu"),
        input,
      ),
      status,
    );
  };
  draw();
  return box;
}

function referenceField(form) {
  const ref = form.model.reference;
  const box = h("div", { class: "admin-reference" });
  const status = h("p", { class: "admin-help", role: "status" });
  const draw = () => {
    if (!ref.enabled) {
      box.replaceChildren();
      return;
    }
    const nameId = fid("reference-name");
    const name = h("input", { id: nameId, maxlength: 80, autocomplete: "off" });
    name.value = ref.name;
    name.addEventListener("input", () => {
      ref.name = name.value;
      form.dirty = true;
    });
    const fileId = `file-${++uid}`;
    const file = h("input", { id: fileId, type: "file", accept: "image/*" });
    file.addEventListener("change", () =>
      pickImages(
        form,
        file.files,
        LOGO,
        (upload) => {
          ref.logo = { alt: ref.name, upload };
          draw();
        },
        status,
      ),
    );
    box.replaceChildren(
      h(
        "div",
        { class: "field", "data-field": "reference-name" },
        h("label", { for: nameId }, "Müşteri adı", h("span", {}, " (zorunlu)")),
        name,
      ),
      ref.logo
        ? h(
            "div",
            { class: "media-tile logo" },
            h("div", { class: "media-preview" }, preview(ref.logo)),
            button(
              "Logoyu kaldır",
              () => {
                ref.logo = null;
                form.dirty = true;
                draw();
              },
              "admin-plain danger",
            ),
          )
        : null,
      h(
        "div",
        { class: "field admin-file" },
        h("label", { for: fileId }, ref.logo ? "Logoyu değiştir" : "Logo seçin (isteğe bağlı)"),
        file,
        h(
          "small",
          {},
          "Şeffaf zeminli PNG en iyi sonucu verir. Logo yoksa müşteri adı yazı olarak gösterilir.",
        ),
      ),
      status,
    );
  };
  draw();
  const toggleId = fid("reference-enabled");
  const toggle = h("input", { id: toggleId, type: "checkbox" });
  toggle.checked = ref.enabled;
  toggle.addEventListener("change", () => {
    ref.enabled = toggle.checked;
    if (ref.enabled && !ref.name) ref.name = form.model.title;
    form.dirty = true;
    draw();
  });
  return h(
    "div",
    { class: "admin-subset", id: "field-reference", "data-field": "reference" },
    h(
      "div",
      { class: "admin-check" },
      toggle,
      h(
        "label",
        { for: toggleId },
        "Müşteriyi referanslar sütununda ve Referanslar sayfasında göster",
      ),
    ),
    box,
  );
}

function servicesField(form) {
  return h(
    "fieldset",
    { class: "admin-subset", "data-field": "services" },
    h("legend", {}, "İlgili hizmetler"),
    h(
      "p",
      { class: "admin-help" },
      "Seçilen hizmetlerin sayfalarında bu proje “İlgili çalışmalar” olarak görünür.",
    ),
    h(
      "div",
      { class: "admin-checks" },
      repoState.data.services.map((s) => {
        const id = fid("service-" + s.slug);
        const input = h("input", { id, type: "checkbox", value: s.slug });
        input.checked = form.model.services.includes(s.slug);
        input.addEventListener("change", () => {
          const set = new Set(form.model.services);
          input.checked ? set.add(s.slug) : set.delete(s.slug);
          form.model.services = repoState.data.services
            .map((x) => x.slug)
            .filter((slug) => set.has(slug));
          form.dirty = true;
        });
        return h("div", { class: "admin-check" }, input, h("label", { for: id }, s.title));
      }),
    ),
  );
}

function priceField(form) {
  const id = fid("price");
  const input = h("input", {
    id,
    name: "price",
    inputmode: "decimal",
    autocomplete: "off",
    maxlength: 16,
    placeholder: "Örnek: 1.250,00",
    "aria-describedby": id + "-help",
  });
  input.value = form.model.price;
  const help = h("small", { id: id + "-help" });
  const update = () => {
    const value = parsePrice(input.value);
    help.textContent =
      value === null
        ? "Boş bırakılırsa sitede “Fiyat için sorun” yazar."
        : Number.isNaN(value)
          ? "Fiyat anlaşılamadı. Örnek yazım: 1.250,00"
          : `Sitede görünecek: ${priceFormat.format(value)}`;
  };
  update();
  input.addEventListener("input", () => {
    form.model.price = input.value;
    form.dirty = true;
    update();
  });
  return h(
    "div",
    { class: "field", "data-field": "price" },
    h("label", { for: id }, "Fiyat (TL)"),
    input,
    help,
  );
}

function section(title, ...children) {
  return h("fieldset", { class: "admin-section" }, h("legend", {}, title), children);
}

function renderEditor(form) {
  const k = KINDS[form.kind];
  const data = repoState.data;
  const isProject = form.kind === "projects";
  const categories = [
    ...new Set(data[form.kind].map((x) => x.category).filter(Boolean)),
  ];
  const saveBtn = h(
    "button",
    { type: "submit", class: "btn btn-primary" },
    form.model.draft ? "Taslağı kaydet" : "Kaydet ve yayınla",
  );
  const errorsBox = h("div", {
    class: "admin-errors",
    id: "form-errors",
    tabindex: "-1",
    hidden: true,
  });
  const title = field(form, "title", isProject ? "Proje adı" : "Ürün adı", {
    required: true,
    max: 80,
    onInput: (v) => form.syncSlug?.(v),
  });
  const body = isProject
    ? [
        section(
          "Temel bilgiler",
          title,
          slugField(form, k),
          h(
            "div",
            { class: "form-row" },
            field(form, "category", "Kategori", {
              required: true,
              max: 40,
              list: categories,
              help: "Proje listesinde filtre olarak görünür.",
            }),
            field(form, "status", "Durum", {
              required: true,
              max: 60,
              list: PROJECT_STATUSES,
              help: "“Devam” içeren durumlar sarı işaretle gösterilir.",
            }),
          ),
          h(
            "div",
            { class: "form-row" },
            field(form, "year", "Yıl", { max: 20, help: "Örnek: 2024 veya 2024–2026" }),
            field(form, "location", "Konum", { max: 80, help: "Örnek: Aliağa / İzmir" }),
          ),
        ),
        section(
          "Metinler",
          field(form, "summary", "Kısa özet", {
            required: true,
            max: 200,
            rows: 2,
            counter: true,
            help: "Kartta ve arama sonuçlarında görünür; her sayfa için farklı olmalı.",
          }),
          field(form, "intro", "Proje anlatımı", {
            required: true,
            max: 1500,
            rows: 5,
            help: "Proje sayfasında “İhtiyaçtan uygulamaya” başlığı altında görünür.",
          }),
          field(form, "scope", "Üstlendiğimiz işler", {
            required: true,
            rows: 5,
            max: 3000,
            help: "Her satıra bir iş yazın.",
          }),
          field(form, "results", "Sonuç ve güncel durum", {
            rows: 3,
            max: 2000,
            help: "İsteğe bağlı. Her satır ayrı paragraf olur. Yalnız gerçekleşmiş sonuçları yazın.",
          }),
        ),
        section(
          "Proje künyesi",
          pairsField(form, "facts", "Künye satırları", {
            help: "Örnek: Trafo — 630 kVA. Proje sayfasının yan sütununda görünür.",
            max: 8,
            labelName: "Başlık",
            valueName: "Değer",
          }),
          servicesField(form),
        ),
        section(
          "Fotoğraflar",
          h(
            "p",
            { class: "admin-help" },
            "Yalnız size ait gerçek saha fotoğraflarını yükleyin. Fotoğraflar tarayıcıda küçültülür, WebP’ye dönüştürülür ve konum gibi kamera bilgileri silinir.",
          ),
          coverField(form),
          galleryField(form),
          selectField(
            form,
            "drawing",
            "Fotoğraf yoksa şematik simge",
            DRAWINGS,
            "Kapak fotoğrafı olmayan projede “Şematik gösterim” etiketiyle kullanılır.",
          ),
        ),
        section("Referans logosu", referenceField(form)),
      ]
    : [
        section(
          "Temel bilgiler",
          title,
          slugField(form, k),
          h(
            "div",
            { class: "form-row" },
            field(form, "category", "Kategori", {
              required: true,
              max: 40,
              list: categories,
              help: "Mağazada filtre olarak görünür.",
            }),
            field(form, "brand", "Marka", { max: 60 }),
          ),
          h(
            "div",
            { class: "form-row" },
            field(form, "sku", "Stok kodu", { max: 40, help: "İsteğe bağlı; siparişte ürünü ayırt etmeye yarar." }),
            selectField(form, "stock", "Stok durumu", STOCK.map((s) => [s, s])),
          ),
        ),
        section(
          "Fiyat",
          h(
            "div",
            { class: "form-row three" },
            priceField(form),
            selectField(form, "vat", "KDV", [
              ["dahil", "KDV dahil"],
              ["hariç", "KDV hariç"],
            ]),
            field(form, "unit", "Birim", { required: true, max: 20, list: UNITS }),
          ),
        ),
        section(
          "Metinler",
          field(form, "summary", "Kısa açıklama", {
            required: true,
            max: 200,
            rows: 2,
            counter: true,
            help: "Ürün kartında ve arama sonuçlarında görünür; her sayfa için farklı olmalı.",
          }),
          field(form, "description", "Ürün açıklaması", {
            rows: 6,
            max: 4000,
            help: "Paragrafları boş bir satırla ayırın.",
          }),
          pairsField(form, "specs", "Teknik özellikler", {
            help: "Örnek: Anma akımı — 63 A",
            max: 16,
            labelName: "Özellik",
            valueName: "Değer",
          }),
        ),
        section("Fotoğraflar", coverField(form), galleryField(form)),
      ];
  const formEl = h(
    "form",
    { class: "admin-editor", novalidate: true },
    errorsBox,
    body,
    section(
      "Yayın",
      checkField(
        form,
        "draft",
        "Taslak olarak sakla",
        "Taslaklar depoya kaydedilir ama sitede gösterilmez.",
        (draft) => {
          saveBtn.textContent = draft ? "Taslağı kaydet" : "Kaydet ve yayınla";
        },
      ),
    ),
    h(
      "div",
      { class: "admin-actions" },
      saveBtn,
      button("Vazgeç", closeEditor),
    ),
  );
  formEl.addEventListener("submit", (e) => {
    e.preventDefault();
    save(form, formEl);
  });
  return h(
    "section",
    { class: "admin-panel", "aria-labelledby": "editor-title" },
    h(
      "div",
      { class: "admin-list-head" },
      h(
        "div",
        {},
        h(
          "h2",
          { id: "editor-title", tabindex: "-1" },
          form.original
            ? `${form.original.title} — düzenle`
            : isProject
              ? "Yeni proje"
              : "Yeni ürün",
        ),
        h(
          "p",
          {},
          "Zorunlu alanlar işaretlidir. Kaydettiğinizde değişiklik sitede hemen görünür.",
        ),
      ),
      button("← Listeye dön", closeEditor, "admin-plain"),
    ),
    formEl,
  );
}

// ---------------------------------------------------------------- validation
const FIELD_LABELS = {
  title: "Ad",
  category: "Kategori",
  status: "Durum",
  summary: "Kısa özet",
  intro: "Proje anlatımı",
  scope: "Üstlendiğimiz işler",
  unit: "Birim",
};

function validate(form, data) {
  const { kind, model, original } = form;
  const errors = [];
  const add = (key, message) => errors.push({ key, message });
  const required =
    kind === "projects"
      ? ["title", "category", "status", "summary", "intro", "scope"]
      : ["title", "category", "summary", "unit"];
  for (const key of required)
    if (!String(model[key] ?? "").trim())
      add(key, `${FIELD_LABELS[key]} boş bırakılamaz.`);
  if (!original) {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(model.slug))
      add("slug", "Sayfa adresi yalnız küçük harf, rakam ve tire içerebilir.");
    else if (data[kind].some((x) => x.slug === model.slug))
      add("slug", "Bu sayfa adresi başka bir kayıtta kullanılıyor.");
  }
  const others = [
    ...data.services.map((s) => ({ kind: "services", slug: s.slug, title: s.title, summary: s.short })),
    ...data.projects.map((p) => ({ kind: "projects", ...p })),
    ...data.products.map((p) => ({ kind: "products", ...p })),
  ].filter((o) => !(o.kind === kind && o.slug === original?.slug));
  if (model.title.trim() && others.some((o) => norm(o.title) === norm(model.title)))
    add("title", "Bu ad başka bir sayfada kullanılıyor; sayfa başlıkları benzersiz olmalı.");
  if (model.summary.trim() && others.some((o) => norm(o.summary) === norm(model.summary)))
    add("summary", "Bu metin başka bir sayfada kullanılıyor; her sayfanın kısa açıklaması farklı olmalı.");
  const pairsKey = kind === "projects" ? "facts" : "specs";
  if (model[pairsKey].some((p) => Boolean(p.label?.trim()) !== Boolean(p.value?.trim())))
    add(pairsKey, "Her satırda başlık ve değer birlikte yazılmalı.");
  if (kind === "products" && Number.isNaN(parsePrice(model.price)))
    add("price", "Fiyat anlaşılamadı. Örnek yazım: 1.250,00");
  if (model.cover && !model.cover.alt.trim())
    add("cover", "Kapak fotoğrafı için açıklama yazın.");
  model.gallery.forEach((g, i) => {
    if (!g.alt.trim()) add(`gallery-${i}`, `${i + 1}. galeri fotoğrafı için açıklama yazın.`);
  });
  if (kind === "projects" && model.reference.enabled && !model.reference.name.trim())
    add("reference-name", "Referans için müşteri adını yazın.");
  const texts = [
    ["title", model.title],
    ["category", model.category],
    ["summary", model.summary],
    ["intro", model.intro],
    ["scope", model.scope],
    ["results", model.results],
    ["description", model.description],
    ["status", model.status],
    ["location", model.location],
    ["brand", model.brand],
    [pairsKey, model[pairsKey].map((p) => `${p.label} ${p.value}`).join("\n")],
    ["cover", model.cover?.alt],
    ...model.gallery.map((g, i) => [`gallery-${i}`, g.alt]),
    ["reference-name", model.reference?.enabled ? model.reference.name : ""],
  ];
  for (const [key, text] of texts) {
    const hit = text && findBannedPhrase(text);
    if (hit)
      add(
        key,
        `Bu alanda site denetiminin engellediği bir ifade var (${bannedLabelsTr[hit] ?? hit}). Metni düzeltin.`,
      );
  }
  return errors;
}

function showErrors(formEl, errors) {
  const box = formEl.querySelector("#form-errors");
  formEl.querySelectorAll(".field-error").forEach((e) => e.remove());
  formEl.querySelectorAll("[aria-invalid]").forEach((e) => {
    e.removeAttribute("aria-invalid");
    e.setAttribute(
      "aria-describedby",
      (e.getAttribute("aria-describedby") || "")
        .split(" ")
        .filter((id) => !id.endsWith("-error"))
        .join(" "),
    );
  });
  if (!errors.length) {
    box.hidden = true;
    return;
  }
  const items = errors.map(({ key, message }, i) => {
    const container = formEl.querySelector(`[data-field="${key}"]`);
    const errorId = `error-${i}-${key}`.replace(/[^\w-]/g, "") + "-error";
    if (container) {
      container.append(h("p", { class: "field-error", id: errorId }, message));
      const control = container.querySelector("input, textarea, select");
      if (control) {
        control.setAttribute("aria-invalid", "true");
        control.setAttribute(
          "aria-describedby",
          [control.getAttribute("aria-describedby"), errorId].filter(Boolean).join(" "),
        );
      }
    }
    return h(
      "li",
      {},
      h(
        "a",
        {
          href: "#",
          onclick: (e) => {
            e.preventDefault();
            const target = container?.querySelector("input, textarea, select, button");
            container?.scrollIntoView({ block: "center" });
            target?.focus({ preventScroll: true });
          },
        },
        message,
      ),
    );
  });
  box.replaceChildren(h("h3", {}, "Kaydetmeden önce şunları düzeltin:"), h("ul", {}, items));
  box.hidden = false;
  box.focus({ preventScroll: true });
  box.scrollIntoView({ block: "start" });
}

// ---------------------------------------------------------------- save
function buildItem(form) {
  const { kind, model } = form;
  const uploads = [];
  const slug = model.slug;
  const place = (entry, dir) => {
    if (!entry) return "";
    if (entry.upload) {
      entry.path ||= `${dir}${slug}-${stamp()}.${entry.upload.ext}`;
      uploads.push({ path: entry.path, blob: entry.upload.blob });
      localPreviews.set(entry.path, entry.upload.preview);
      return entry.path;
    }
    return entry.src;
  };
  const image = place(model.cover, DIRS[kind]);
  const gallery = model.gallery.map((g) => ({
    src: place(g, DIRS[kind]),
    alt: g.alt.trim(),
    width: g.upload?.width ?? g.width ?? 1600,
    height: g.upload?.height ?? g.height ?? 1200,
  }));
  const pairs = (rows) =>
    rows
      .map((r) => ({ label: r.label.trim(), value: r.value.trim() }))
      .filter((r) => r.label && r.value);
  let item;
  let reference = null;
  if (kind === "projects") {
    item = {
      slug,
      title: model.title.trim(),
      category: model.category.trim(),
      status: model.status.trim(),
      year: model.year.trim(),
      location: model.location.trim(),
      summary: model.summary.trim(),
      intro: model.intro.trim(),
      scope: lines(model.scope),
      results: lines(model.results),
      facts: pairs(model.facts),
      image,
      imageAlt: image ? model.cover.alt.trim() : "",
      gallery,
      drawing: model.drawing,
      services: model.services,
    };
    const ref = model.reference;
    reference = ref.enabled
      ? { name: ref.name.trim(), logo: place(ref.logo, DIRS.references) }
      : null;
  } else {
    item = {
      slug,
      title: model.title.trim(),
      category: model.category.trim(),
      brand: model.brand.trim(),
      sku: model.sku.trim(),
      price: parsePrice(model.price),
      vat: model.vat,
      unit: model.unit.trim(),
      stock: model.stock,
      summary: model.summary.trim(),
      description: model.description.trim(),
      specs: pairs(model.specs),
      image,
      imageAlt: image ? model.cover.alt.trim() : "",
      gallery,
    };
  }
  if (model.draft) item.draft = true;
  return { item, uploads, reference };
}

async function save(form, formEl) {
  if (busy) return;
  if (form.pending) {
    setStatus("Fotoğraflar hâlâ hazırlanıyor; birkaç saniye sonra tekrar deneyin.", "info");
    return;
  }
  const errors = validate(form, repoState.data);
  showErrors(formEl, errors);
  if (errors.length) {
    setStatus(`${errors.length} alanın düzeltilmesi gerekiyor.`, "error");
    return;
  }
  const { item, uploads, reference } = buildItem(form);
  setBusy(true);
  try {
    await persist(
      {
        type: "upsert",
        kind: form.kind,
        original: form.original?.slug ?? null,
        item,
        reference,
      },
      uploads,
    );
    form.dirty = false;
    ui.mode = "list";
    ui.tab = form.kind;
    ui.form = null;
    setBusy(false);
    render();
    focusHeading();
    if (item.draft) {
      setStatus(
        "Taslak kaydedildi. Sitede gösterilmez; yayımlamak için taslak işaretini kaldırıp kaydedin.",
        "success",
      );
    } else published(pageUrl(form.kind, item));
  } catch (err) {
    setBusy(false);
    fail(err);
  }
}

// ---------------------------------------------------------------- start
async function login() {
  const username = loginForm.querySelector("#username");
  const password = loginForm.querySelector("#password");
  if (!username.value.trim() || !password.value) {
    setStatus("Kullanıcı adı ve şifreyi yazın.", "error");
    (username.value.trim() ? password : username).focus();
    return;
  }
  const submit = loginForm.querySelector("[type=submit]");
  submit.disabled = true;
  setStatus("Giriş yapılıyor…", "progress");
  try {
    const state = await server("login", {
      method: "POST",
      json: { username: username.value.trim(), password: password.value },
    });
    session.user = state.user;
    repoState = { data: state.data };
    password.value = "";
    showWorkspace();
  } catch (err) {
    setStatus(explain(err), "error");
    password.value = "";
    password.focus();
  } finally {
    submit.disabled = false;
  }
}

loginForm.addEventListener("submit", (e) => {
  e.preventDefault();
  login();
});

window.addEventListener("beforeunload", (e) => {
  if (ui.mode === "edit" && ui.form?.dirty) {
    e.preventDefault();
    e.returnValue = "";
  }
});

// Açık oturum varsa doğrudan panele geçer; yoksa giriş için CSRF anahtarını alır.
connect().catch((err) => {
  loginEl.hidden = false;
  workspace.hidden = true;
  if (err instanceof ApiError && err.status === 401) setStatus("");
  else setStatus(explain(err), "error");
});
