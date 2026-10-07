const menu = document.querySelector(".menu-toggle");
const nav = document.querySelector("#main-nav");
const closeMenu = () => {
  nav?.classList.remove("open");
  menu?.setAttribute("aria-expanded", "false");
  menu?.setAttribute("aria-label", "Menüyü aç");
};
menu?.addEventListener("click", () => {
  const open = menu.getAttribute("aria-expanded") !== "true";
  nav.classList.toggle("open", open);
  menu.setAttribute("aria-expanded", String(open));
  menu.setAttribute("aria-label", open ? "Menüyü kapat" : "Menüyü aç");
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && menu?.getAttribute("aria-expanded") === "true") {
    closeMenu();
    menu.focus();
  }
});
nav
  ?.querySelectorAll("a")
  .forEach((a) => a.addEventListener("click", closeMenu));
window.matchMedia("(min-width:961px)").addEventListener("change", closeMenu);
const panelData = {
  trafo: {
    labels: ["Trafo yükü", "Test gerilimi", "Güç faktörü"],
    values: [
      ["72", "%"],
      ["5", "kV"],
      ["0,99", "cos φ"],
    ],
    note: "Ölçüm → analiz → bakım planı",
  },
  kompanzasyon: {
    labels: ["Aktif güç", "Reaktif güç", "Güç faktörü"],
    values: [
      ["160", "kW"],
      ["23", "kVAr"],
      ["0,99", "cos φ"],
    ],
    note: "Enerji analizi → kademe kontrolü → takip",
  },
  otomasyon: {
    labels: ["Girişler", "Çıkışlar", "Protokol"],
    values: [
      ["16", "DI"],
      ["12", "DO"],
      ["TCP", "IP"],
    ],
    note: "PLC → saha kontrolü → SCADA",
  },
};
document.querySelectorAll("[data-panel]").forEach((button) =>
  button.addEventListener("click", () => {
    document.querySelectorAll("[data-panel]").forEach((b) => {
      const selected = b === button;
      b.classList.toggle("active", selected);
      b.setAttribute("aria-pressed", String(selected));
    });
    const d = panelData[button.dataset.panel];
    d.labels.forEach((label, i) => {
      document.getElementById("reading-label-" + (i + 1)).textContent = label;
      const strong = document.getElementById("reading-value-" + (i + 1));
      strong.replaceChildren(document.createTextNode(d.values[i][0]));
      const unit = document.createElement("small");
      unit.textContent = d.values[i][1];
      strong.append(unit);
    });
    document.getElementById("panel-note").textContent = d.note;
  }),
);
document.querySelectorAll(".motion-toggle").forEach((b) => {
  if (window.matchMedia("(prefers-reduced-motion:reduce)").matches) {
    b.closest(".reference-column").classList.add("is-paused");
    b.setAttribute("aria-pressed", "true");
    b.setAttribute("aria-label", "Logo hareketini başlat");
    b.textContent = "▷";
  }
  b.addEventListener("click", () => {
    const pause = b.getAttribute("aria-pressed") !== "true";
    b.closest(".reference-column").classList.toggle("is-paused", pause);
    b.closest(".reference-column").classList.toggle("motion-enabled", !pause);
    b.setAttribute("aria-pressed", String(pause));
    b.setAttribute(
      "aria-label",
      pause ? "Logo hareketini sürdür" : "Logo hareketini durdur",
    );
    b.textContent = pause ? "▷" : "Ⅱ";
  });
});
document.querySelectorAll("[data-filter]").forEach((b) =>
  b.addEventListener("click", () => {
    document.querySelectorAll("[data-filter]").forEach((other) => {
      const selected = b === other;
      other.classList.toggle("active", selected);
      other.setAttribute("aria-pressed", String(selected));
    });
    let visible = 0;
    document.querySelectorAll("[data-category]").forEach((card) => {
      const show =
        b.dataset.filter === "all" ||
        card.dataset.category === b.dataset.filter;
      card.hidden = !show;
      if (show) visible++;
    });
    const status = document.querySelector(".filter-status");
    if (status)
      status.textContent =
        visible + " " + (status.dataset.noun || "proje") + " gösteriliyor.";
  }),
);
const form = document.getElementById("discovery-form");
if (form) {
  const service = document.getElementById("service");
  const requested = new URLSearchParams(location.search).get("hizmet");
  if ([...service.options].some((o) => o.value === requested))
    service.value = requested;
  const preview = document.getElementById("message-preview");
  form.addEventListener("input", () => {
    preview.hidden = true;
  });
  form.addEventListener("change", () => {
    preview.hidden = true;
  });
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    if (!form.reportValidity()) return;
    const fd = new FormData(form);
    const read = (name) => String(fd.get(name) || "").trim();
    const phone = read("telephone").replace(/\D/g, "");
    if (phone.length < 10 || phone.length > 15) {
      const input = document.getElementById("telephone");
      input.setCustomValidity(
        "Lütfen alan koduyla birlikte geçerli bir telefon numarası yazın.",
      );
      input.reportValidity();
      input.addEventListener("input", () => input.setCustomValidity(""), {
        once: true,
      });
      return;
    }
    const lines = [
      "Merhaba, ücretsiz keşif için görüşmek istiyorum.",
      "",
      `Ad soyad: ${read("name")}`,
      read("company") ? `Firma / site: ${read("company")}` : "",
      `Telefon: ${read("telephone")}`,
      `Konum: ${read("location")}`,
      `Hizmet: ${service.selectedOptions[0].textContent}`,
      read("message") ? `İhtiyaç: ${read("message")}` : "",
    ];
    const message = lines.filter((l, i) => l || i === 1).join("\n");
    document.getElementById("prepared-message").textContent = message;
    document.getElementById("whatsapp-send").href =
      "https://wa.me/905384475676?text=" + encodeURIComponent(message);
    preview.hidden = false;
    document.getElementById("preview-title").focus();
    preview.scrollIntoView({
      behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches
        ? "instant"
        : "smooth",
      block: "center",
    });
  });
}
document.querySelectorAll("[data-order]").forEach((order) => {
  const qty = order.querySelector("input[name=qty]");
  const link = order.querySelector("[data-order-link]");
  const update = () => {
    const n = Math.min(9999, Math.max(1, Math.floor(Number(qty.value) || 1)));
    link.href =
      "https://wa.me/905384475676?text=" +
      encodeURIComponent(order.dataset.template.replace("{qty}", String(n)));
  };
  qty.closest(".order-qty").hidden = false;
  qty.addEventListener("input", update);
  order.addEventListener("submit", (e) => {
    e.preventDefault();
    update();
    link.click();
  });
});
const galleryLinks = document.querySelectorAll("[data-lightbox]");
if (galleryLinks.length && window.HTMLDialogElement) {
  const dialog = document.createElement("dialog");
  dialog.className = "lightbox";
  dialog.setAttribute("aria-label", "Fotoğraf görüntüleyici");
  const image = document.createElement("img");
  const caption = document.createElement("p");
  const close = document.createElement("button");
  close.type = "button";
  close.textContent = "Kapat ✕";
  close.addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (e) => {
    if (e.target === dialog) dialog.close();
  });
  dialog.append(close, image, caption);
  document.body.append(dialog);
  galleryLinks.forEach((link) =>
    link.addEventListener("click", (e) => {
      const thumb = link.querySelector("img");
      e.preventDefault();
      image.src = link.href;
      image.alt = thumb?.alt || "";
      caption.textContent = thumb?.alt || "";
      dialog.showModal();
    }),
  );
}
