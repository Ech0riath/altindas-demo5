import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { bannedPhrases } from "../src/content-rules.js";

// Dependency-free structural smoke test. Browser/assistive-technology testing
// is still required; this deliberately does not claim WCAG conformance.
const project = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "..",
);
const root = path.resolve(project, process.argv[2] || "dist");
// Hostinger çıktısı denetlenirken SITE_BASE / SITE_ORIGIN ile değiştirilir.
const base = process.env.SITE_BASE || "/demo/";
const origin = process.env.SITE_ORIGIN || "http://127.0.0.1:4325";
const errors = [];
const counts = { pages: 0, references: 0, images: 0, structuredData: 0 };
const fail = (file, message) =>
  errors.push(`${path.relative(root, file)}: ${message}`);
const walk = (dir) =>
  fs
    .readdirSync(dir, { withFileTypes: true })
    .flatMap((e) =>
      e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)],
    );
const decode = (s) =>
  s
    .replace(/&amp;/g, "&")
    .replace(/&quot;/g, '"')
    .replace(/&#39;|&apos;/g, "'")
    .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(+n));
const attrs = (tag) =>
  Object.fromEntries(
    [...tag.matchAll(/([\w:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/g)].map(
      (m) => [m[1].toLowerCase(), decode(m[2] ?? m[3] ?? m[4])],
    ),
  );
const tags = (html, name) =>
  [...html.matchAll(new RegExp(`<${name}\\b[^>]*>`, "gi"))].map((m) => ({
    raw: m[0],
    ...attrs(m[0]),
  }));
const textOnly = (html) =>
  html
    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, "")
    .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, "")
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();

if (!fs.existsSync(root)) {
  console.error(`Build directory missing: ${root}`);
  process.exit(1);
}
const allFiles = walk(root);
const htmlFiles = allFiles.filter((f) => f.endsWith(".html"));
if (!htmlFiles.length) {
  console.error("No generated HTML pages found");
  process.exit(1);
}
const docs = new Map(
  htmlFiles.map((file) => [file, fs.readFileSync(file, "utf8")]),
);
const idsByFile = new Map();
const titles = new Map();
const descriptions = new Map();

for (const [file, html] of docs) {
  const ids = [...html.matchAll(/\bid\s*=\s*["']([^"']+)["']/g)].map((m) =>
    decode(m[1]),
  );
  idsByFile.set(file, new Set(ids));
  for (const id of new Set(ids))
    if (ids.filter((x) => x === id).length > 1)
      fail(file, `Duplicate id: ${id}`);
}

function reference(file, value, kind = "reference") {
  counts.references++;
  if (!value || value === "#") {
    fail(file, `Empty ${kind}`);
    return;
  }
  if (/^(?:mailto:|tel:|data:|blob:)/i.test(value)) return;
  if (/^javascript:/i.test(value)) {
    fail(file, `Unsafe javascript ${kind}`);
    return;
  }
  let url;
  const relative = path.relative(root, file).replaceAll(path.sep, "/");
  const pageUrl = `${origin}${base}${relative.replace(/index\.html$/, "")}`;
  try {
    url = new URL(value, pageUrl);
  } catch {
    fail(file, `Invalid URL: ${value}`);
    return;
  }
  if (url.origin !== origin) return; // External URLs require a separate live check.
  if (!url.pathname.startsWith(base)) {
    fail(file, `Missing deployment base: ${value}`);
    return;
  }
  if (url.pathname.slice(base.length).includes(base)) {
    fail(file, `Repeated deployment base: ${value}`);
    return;
  }
  let target;
  try {
    target = path.resolve(
      root,
      decodeURIComponent(url.pathname.slice(base.length)),
    );
  } catch {
    fail(file, `Malformed escaped URL: ${value}`);
    return;
  }
  if (target !== root && !target.startsWith(`${root}${path.sep}`)) {
    fail(file, `Path leaves output directory: ${value}`);
    return;
  }
  if (fs.existsSync(target) && fs.statSync(target).isDirectory())
    target = path.join(target, "index.html");
  if (!fs.existsSync(target)) {
    fail(file, `Missing ${kind}: ${value}`);
    return;
  }
  if (url.hash && target.endsWith(".html")) {
    let id;
    try {
      id = decodeURIComponent(url.hash.slice(1));
    } catch {
      fail(file, `Invalid fragment: ${value}`);
      return;
    }
    if (!idsByFile.get(target)?.has(id))
      fail(file, `Missing fragment: ${value}`);
  }
}

for (const [file, html] of docs) {
  counts.pages++;
  if (!/^\s*<!doctype html>/i.test(html)) fail(file, "Missing HTML5 doctype");
  if (!/^tr(?:-|$)/i.test(tags(html, "html")[0]?.lang || ""))
    fail(file, "Missing Turkish document language");
  const title = decode(
    html.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i)?.[1] || "",
  ).trim();
  if (!title) fail(file, "Empty title");
  else if (titles.has(title))
    fail(
      file,
      `Duplicate title shared with ${path.relative(root, titles.get(title))}`,
    );
  else titles.set(title, file);
  const metas = tags(html, "meta");
  const description = metas
    .find((m) => m.name?.toLowerCase() === "description")
    ?.content?.trim();
  if (!description) fail(file, "Missing meta description");
  else if (descriptions.has(description))
    fail(
      file,
      `Duplicate description shared with ${path.relative(root, descriptions.get(description))}`,
    );
  else descriptions.set(description, file);
  if (!metas.some((m) => m.name === "viewport"))
    fail(file, "Missing viewport metadata");
  for (const meta of metas.filter((m) =>
    ["og:image", "twitter:image"].includes(m.property || m.name),
  ))
    if (meta.content) reference(file, meta.content, "social image");
  if (tags(html, "h1").length !== 1)
    fail(file, "Expected exactly one primary heading (project convention)");
  if (tags(html, "main").length !== 1) fail(file, "Expected one main landmark");
  if (
    !tags(html, "a").some(
      (a) => a.href?.startsWith("#") && /(?:skip|atla)/i.test(a.raw),
    )
  )
    fail(file, "No skip link found");
  for (const img of tags(html, "img")) {
    counts.images++;
    if (!Object.hasOwn(img, "alt")) fail(file, `Image lacks alt: ${img.src}`);
    if (!img.width || !img.height)
      fail(file, `Image lacks explicit dimensions: ${img.src}`);
  }
  for (const tag of [...html.matchAll(/<[a-z][^>]*>/gi)]) {
    const a = attrs(tag[0]);
    for (const key of ["href", "src", "poster"])
      if (Object.hasOwn(a, key)) reference(file, a[key], key);
    if (a.srcset)
      for (const candidate of a.srcset.split(","))
        reference(file, candidate.trim().split(/\s+/)[0], "srcset");
    for (const key of ["aria-controls", "aria-labelledby", "aria-describedby"])
      if (a[key])
        for (const id of a[key].split(/\s+/))
          if (!idsByFile.get(file).has(id))
            fail(file, `${key} points to missing id: ${id}`);
    if (a.target === "_blank" && !/(?:noopener|noreferrer)/.test(a.rel || ""))
      fail(file, "External target=_blank link lacks explicit rel protection");
  }
  for (const input of [
    ...tags(html, "input"),
    ...tags(html, "select"),
    ...tags(html, "textarea"),
  ]) {
    if (input.pattern) {
      try {
        const pattern = new RegExp(`^(?:${input.pattern})$`, "v");
        if (input.type === "tel" && !pattern.test("0538 447 56 76"))
          fail(file, "Telephone pattern rejects displayed spaced example");
      } catch {
        fail(file, `Invalid HTML pattern (v flag): ${input.id || input.name}`);
      }
    }
    if (["hidden", "submit", "button", "reset"].includes(input.type)) continue;
    if (input["aria-label"] || input["aria-labelledby"]) continue;
    const associated =
      input.id && tags(html, "label").some((l) => l.for === input.id);
    const wrapped = [
      ...html.matchAll(/<label\b[^>]*>[\s\S]*?<\/label>/gi),
    ].some((m) => m[0].includes(input.raw));
    if (!associated && !wrapped)
      fail(file, `Unlabelled field: ${input.name || input.id || input.type}`);
  }
  for (const match of html.matchAll(
    /<script\b[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi,
  )) {
    try {
      const schema = JSON.parse(match[1]);
      counts.structuredData++;
      if (schema["@type"] === "ElectricalContractor")
        fail(
          file,
          "Unknown Schema.org type ElectricalContractor; use Electrician",
        );
    } catch {
      fail(file, "Invalid JSON-LD");
    }
  }
  const visible = textOnly(html);
  for (const [pattern, label] of bannedPhrases)
    if (pattern.test(visible)) fail(file, label);
  // Additional private phrases may be provided locally without saving them to a public repository.
  for (const phrase of (process.env.QA_PRIVATE_PHRASES || "")
    .split("|")
    .filter(Boolean))
    if (
      visible.toLocaleLowerCase("tr").includes(phrase.toLocaleLowerCase("tr"))
    )
      fail(file, "Private phrase found");
}

for (const file of allFiles) {
  const relative = path.relative(root, file).replaceAll(path.sep, "/");
  if (
    /(?:^|\/)(?:\.git|\.env(?:\.|$)|Private|research|\.agents|node_modules)(?:\/|$)/i.test(
      relative,
    ) ||
    /\.(?:pdf|md|map|pem|key)$/i.test(relative)
  )
    fail(file, "Source-only or sensitive file included in publish output");
  if (/\.(?:html|css|js|json|txt|xml)$/i.test(file)) {
    const data = fs.readFileSync(file, "utf8");
    if (
      /(?:ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|sk-[A-Za-z0-9]{40,})/.test(
        data,
      )
    )
      fail(file, "Potential secret detected");
    if (file.endsWith(".css"))
      for (const m of data.matchAll(/url\(\s*["']?([^\s)'"\n]+)["']?\s*\)/g))
        reference(file, m[1], "CSS resource");
  }
}
console.log(
  JSON.stringify(
    {
      status: errors.length ? "FAIL" : "PASS",
      ...counts,
      files: allFiles.length,
      failures: errors.length,
    },
    null,
    2,
  ),
);
if (errors.length) {
  console.error(errors.join("\n"));
  process.exitCode = 1;
}
