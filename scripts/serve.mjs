import http from "node:http";
import fs from "node:fs";
import path from "node:path";
const root = path.resolve("dist");
const types = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css",
  ".js": "text/javascript",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".webp": "image/webp",
  ".woff2": "font/woff2",
  ".xml": "application/xml",
  ".txt": "text/plain",
};
http
  .createServer((req, res) => {
    let url;
    try {
      url = decodeURIComponent(new URL(req.url, "http://localhost").pathname);
    } catch {
      res.writeHead(400).end();
      return;
    }
    if (url === "/") {
      res.writeHead(302, { Location: "/demo/" }).end();
      return;
    }
    let relative = url.replace(/^\/demo\/?/, "");
    let target = path.resolve(root, relative || "index.html");
    if (!target.startsWith(root + path.sep) && target !== root) {
      res.writeHead(403).end();
      return;
    }
    if (fs.existsSync(target) && fs.statSync(target).isDirectory())
      target = path.join(target, "index.html");
    if (!fs.existsSync(target)) {
      res
        .writeHead(404, { "Content-Type": "text/html; charset=utf-8" })
        .end(fs.readFileSync(path.join(root, "404.html")));
      return;
    }
    res.writeHead(200, {
      "Content-Type": types[path.extname(target)] || "application/octet-stream",
      "Cache-Control": "no-store",
    });
    fs.createReadStream(target).pipe(res);
  })
  .listen(4325, "127.0.0.1", () =>
    console.log("Preview: http://127.0.0.1:4325/demo/"),
  );
