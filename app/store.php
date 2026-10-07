<?php
// MySQL deposu: projeler, ürünler ve referans logoları. Hizmet, kurumsal
// metin ve SSS gibi panelin yönetmediği içerik content.json'dan okunur.
declare(strict_types=1);

namespace Altindas;

use PDO;
use PDOException;

final class InputError extends \RuntimeException
{
}

final class Store
{
    public const DRAWINGS = ['building', 'shield', 'trafo', 'bolt', 'circuit', 'camera', 'sun'];
    public const STOCK = ['Stokta', 'Sipariş üzerine', 'Tükendi'];
    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const ASSET = '#^(?:assets|uploads)/[a-z0-9_/-]+\.(?:webp|jpe?g|png)$#i';
    private const SCHEMA = [
        'CREATE TABLE IF NOT EXISTS projects (
            slug VARCHAR(80) NOT NULL PRIMARY KEY,
            position INT NOT NULL DEFAULT 0,
            title VARCHAR(160) NOT NULL,
            category VARCHAR(80) NOT NULL,
            status VARCHAR(120) NOT NULL,
            year VARCHAR(40) NOT NULL DEFAULT \'\',
            location VARCHAR(160) NOT NULL DEFAULT \'\',
            summary TEXT NOT NULL,
            intro TEXT NOT NULL,
            scope LONGTEXT NOT NULL,
            results LONGTEXT NOT NULL,
            facts LONGTEXT NOT NULL,
            image VARCHAR(255) NOT NULL DEFAULT \'\',
            image_alt VARCHAR(255) NOT NULL DEFAULT \'\',
            gallery LONGTEXT NOT NULL,
            drawing VARCHAR(20) NOT NULL DEFAULT \'building\',
            services LONGTEXT NOT NULL,
            draft TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS products (
            slug VARCHAR(80) NOT NULL PRIMARY KEY,
            position INT NOT NULL DEFAULT 0,
            title VARCHAR(160) NOT NULL,
            category VARCHAR(80) NOT NULL,
            brand VARCHAR(120) NOT NULL DEFAULT \'\',
            sku VARCHAR(80) NOT NULL DEFAULT \'\',
            price DECIMAL(12,2) NULL,
            vat VARCHAR(10) NOT NULL DEFAULT \'dahil\',
            unit VARCHAR(40) NOT NULL DEFAULT \'adet\',
            stock VARCHAR(40) NOT NULL DEFAULT \'Stokta\',
            summary TEXT NOT NULL,
            description TEXT NOT NULL,
            specs LONGTEXT NOT NULL,
            image VARCHAR(255) NOT NULL DEFAULT \'\',
            image_alt VARCHAR(255) NOT NULL DEFAULT \'\',
            gallery LONGTEXT NOT NULL,
            draft TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS client_references (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            position INT NOT NULL DEFAULT 0,
            name VARCHAR(160) NOT NULL,
            logo VARCHAR(255) NOT NULL DEFAULT \'\',
            project VARCHAR(80) NULL,
            wordmark VARCHAR(120) NOT NULL DEFAULT \'\',
            wordmark_small VARCHAR(120) NOT NULL DEFAULT \'\',
            KEY project (project)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS admins (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS login_attempts (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL,
            KEY ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS settings (
            name VARCHAR(60) NOT NULL PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS orders (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(20) NOT NULL UNIQUE,
            status VARCHAR(20) NOT NULL DEFAULT \'bekliyor\',
            mode VARCHAR(10) NOT NULL,
            token VARCHAR(255) NULL,
            product_slug VARCHAR(80) NOT NULL,
            product_title VARCHAR(160) NOT NULL,
            sku VARCHAR(80) NOT NULL DEFAULT \'\',
            unit VARCHAR(40) NOT NULL DEFAULT \'adet\',
            quantity INT NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL,
            total DECIMAL(12,2) NOT NULL,
            buyer_name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            city VARCHAR(60) NOT NULL,
            district VARCHAR(60) NOT NULL,
            address VARCHAR(400) NOT NULL,
            note VARCHAR(500) NOT NULL DEFAULT \'\',
            ip VARCHAR(45) NOT NULL,
            payment_id VARCHAR(40) NOT NULL DEFAULT \'\',
            error VARCHAR(400) NOT NULL DEFAULT \'\',
            created_at DATETIME NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY token (token(64)),
            KEY ip_time (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];

    public function __construct(public readonly PDO $db)
    {
    }

    public static function connect(array $c): self
    {
        $pdo = new PDO(
            'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4',
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        return new self($pdo);
    }

    public function install(): void
    {
        foreach (self::SCHEMA as $sql) {
            $this->db->exec($sql);
        }
    }

    /** Ayar değeri (JSON). Tablo henüz yoksa (eski kurulum) boş döner. */
    public function setting(string $name): array
    {
        try {
            $stmt = $this->db->prepare('SELECT value FROM settings WHERE name = ?');
            $stmt->execute([$name]);
            return json_decode((string) $stmt->fetchColumn(), true) ?: [];
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return [];
            }
            throw $e;
        }
    }

    public function saveSetting(string $name, array $value): void
    {
        $this->db->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute([$name, self::json($value)]);
    }

    public function isEmpty(): bool
    {
        return (int) $this->db->query('SELECT (SELECT COUNT(*) FROM projects) + (SELECT COUNT(*) FROM products) + (SELECT COUNT(*) FROM client_references)')->fetchColumn() === 0;
    }

    /** content.json'daki proje, ürün ve referansları boş tablolara aktarır. */
    public function seed(array $content): void
    {
        $this->db->beginTransaction();
        try {
            foreach (array_values($content['projects'] ?? []) as $i => $p) {
                $this->writeProject(self::cleanProject($p), $i, null);
            }
            foreach (array_values($content['products'] ?? []) as $i => $p) {
                $this->writeProduct(self::cleanProduct($p), $i, null);
            }
            $ins = $this->db->prepare('INSERT INTO client_references (position, name, logo, project, wordmark, wordmark_small) VALUES (?, ?, ?, ?, ?, ?)');
            foreach (array_values($content['references'] ?? []) as $i => $r) {
                $ins->execute([$i, (string) $r['name'], (string) ($r['logo'] ?? ''), ($r['project'] ?? '') ?: null, (string) ($r['wordmark'] ?? ''), (string) ($r['wordmarkSmall'] ?? '')]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Site ve panel için content.json biçiminde veri. */
    public function load(array $static): array
    {
        $json = fn ($v) => json_decode((string) $v, true) ?? [];
        $projects = [];
        foreach ($this->db->query('SELECT * FROM projects ORDER BY position, slug') as $r) {
            $p = [
                'slug' => $r['slug'], 'title' => $r['title'], 'category' => $r['category'], 'status' => $r['status'],
                'year' => $r['year'], 'location' => $r['location'], 'summary' => $r['summary'], 'intro' => $r['intro'],
                'scope' => $json($r['scope']), 'results' => $json($r['results']), 'facts' => $json($r['facts']),
                'image' => $r['image'], 'imageAlt' => $r['image_alt'], 'gallery' => $json($r['gallery']),
                'drawing' => $r['drawing'], 'services' => $json($r['services']),
            ];
            if ((int) $r['draft']) {
                $p['draft'] = true;
            }
            $projects[] = $p;
        }
        $products = [];
        foreach ($this->db->query('SELECT * FROM products ORDER BY position, slug') as $r) {
            $p = [
                'slug' => $r['slug'], 'title' => $r['title'], 'category' => $r['category'], 'brand' => $r['brand'],
                'sku' => $r['sku'], 'price' => $r['price'] === null ? null : (float) $r['price'], 'vat' => $r['vat'],
                'unit' => $r['unit'], 'stock' => $r['stock'], 'summary' => $r['summary'], 'description' => $r['description'],
                'specs' => $json($r['specs']), 'image' => $r['image'], 'imageAlt' => $r['image_alt'], 'gallery' => $json($r['gallery']),
            ];
            if ((int) $r['draft']) {
                $p['draft'] = true;
            }
            $products[] = $p;
        }
        $references = [];
        foreach ($this->db->query('SELECT * FROM client_references ORDER BY position, id') as $r) {
            $ref = ['name' => $r['name'], 'logo' => $r['logo'], 'project' => (string) $r['project']];
            if ($r['wordmark'] !== '') {
                $ref['wordmark'] = $r['wordmark'];
            }
            if ($r['wordmark_small'] !== '') {
                $ref['wordmarkSmall'] = $r['wordmark_small'];
            }
            $references[] = $ref;
        }
        return [
            'services' => $static['services'] ?? [],
            'projects' => $projects,
            'references' => $references,
            'products' => $products,
            'about' => $static['about'] ?? [],
            'faqs' => $static['faqs'] ?? [],
        ];
    }

    /** Proje/ürün/referansların kullandığı bütün görsel yolları. */
    public function assetPaths(): array
    {
        $paths = [];
        foreach (['projects', 'products'] as $table) {
            foreach ($this->db->query("SELECT image, gallery FROM $table") as $r) {
                $paths[] = $r['image'];
                foreach (json_decode((string) $r['gallery'], true) ?? [] as $g) {
                    $paths[] = $g['src'] ?? '';
                }
            }
        }
        foreach ($this->db->query('SELECT logo FROM client_references') as $r) {
            $paths[] = $r['logo'];
        }
        return array_values(array_unique(array_filter($paths)));
    }

    /** Panelden gelen tek işlemi (ekle/güncelle, sil, sırala) uygular. */
    public function apply(array $op): void
    {
        $kind = $op['kind'] ?? '';
        if (!in_array($kind, ['projects', 'products'], true)) {
            throw new InputError('Geçersiz içerik türü.');
        }
        $this->db->beginTransaction();
        try {
            match ($op['type'] ?? '') {
                'upsert' => $this->upsert($kind, $op),
                'delete' => $this->delete($kind, (string) ($op['slug'] ?? '')),
                'reorder' => $this->reorder($kind, array_map('strval', (array) ($op['slugs'] ?? []))),
                default => throw new InputError('Geçersiz işlem.'),
            };
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                throw new InputError('Bu sayfa adresi başka bir kayıtta kullanılıyor.');
            }
            throw $e;
        }
    }

    private function upsert(string $kind, array $op): void
    {
        $item = $kind === 'projects' ? self::cleanProject((array) ($op['item'] ?? [])) : self::cleanProduct((array) ($op['item'] ?? []));
        $original = isset($op['original']) && $op['original'] !== null ? (string) $op['original'] : null;
        if ($original !== null && $original !== $item['slug']) {
            throw new InputError('Yayınlanmış sayfanın adresi değiştirilemez.');
        }
        $exists = $this->db->prepare("SELECT position FROM $kind WHERE slug = ?");
        $exists->execute([$item['slug']]);
        $position = $exists->fetchColumn();
        if ($original !== null && $position === false) {
            throw new InputError('Bu kayıt siz düzenlerken silinmiş. “Yenile” ile listeyi güncelleyin.');
        }
        if ($original === null && $position !== false) {
            throw new InputError('Bu sayfa adresi başka bir kayıtta kullanılıyor.');
        }
        if ($position === false) {
            // Yeni kayıtlar listenin başına eklenir.
            $position = (int) $this->db->query("SELECT COALESCE(MIN(position), 0) - 1 FROM $kind")->fetchColumn();
        }
        $kind === 'projects'
            ? $this->writeProject($item, (int) $position, $original)
            : $this->writeProduct($item, (int) $position, $original);
        if ($kind === 'projects') {
            $this->writeReference($item['slug'], $op['reference'] ?? null);
        }
    }

    private function writeProject(array $p, int $position, ?string $original): void
    {
        $values = [
            $p['title'], $p['category'], $p['status'], $p['year'], $p['location'], $p['summary'], $p['intro'],
            self::json($p['scope']), self::json($p['results']), self::json($p['facts']), $p['image'], $p['imageAlt'],
            self::json($p['gallery']), $p['drawing'], self::json($p['services']), $p['draft'] ? 1 : 0, $position, $p['slug'],
        ];
        $sql = $original === null
            ? 'INSERT INTO projects (title, category, status, year, location, summary, intro, scope, results, facts, image, image_alt, gallery, drawing, services, draft, position, slug) VALUES (' . implode(',', array_fill(0, 18, '?')) . ')'
            : 'UPDATE projects SET title=?, category=?, status=?, year=?, location=?, summary=?, intro=?, scope=?, results=?, facts=?, image=?, image_alt=?, gallery=?, drawing=?, services=?, draft=?, position=? WHERE slug=?';
        $this->db->prepare($sql)->execute($values);
    }

    private function writeProduct(array $p, int $position, ?string $original): void
    {
        $values = [
            $p['title'], $p['category'], $p['brand'], $p['sku'], $p['price'], $p['vat'], $p['unit'], $p['stock'],
            $p['summary'], $p['description'], self::json($p['specs']), $p['image'], $p['imageAlt'], self::json($p['gallery']),
            $p['draft'] ? 1 : 0, $position, $p['slug'],
        ];
        $sql = $original === null
            ? 'INSERT INTO products (title, category, brand, sku, price, vat, unit, stock, summary, description, specs, image, image_alt, gallery, draft, position, slug) VALUES (' . implode(',', array_fill(0, 17, '?')) . ')'
            : 'UPDATE products SET title=?, category=?, brand=?, sku=?, price=?, vat=?, unit=?, stock=?, summary=?, description=?, specs=?, image=?, image_alt=?, gallery=?, draft=?, position=? WHERE slug=?';
        $this->db->prepare($sql)->execute($values);
    }

    private function writeReference(string $project, mixed $ref): void
    {
        $find = $this->db->prepare('SELECT id FROM client_references WHERE project = ? ORDER BY position LIMIT 1');
        $find->execute([$project]);
        $id = $find->fetchColumn();
        if (!is_array($ref)) {
            if ($id !== false) {
                $this->db->prepare('DELETE FROM client_references WHERE id = ?')->execute([$id]);
            }
            return;
        }
        $name = self::text($ref, 'name', 160, true, 'Müşteri adı');
        $logo = self::path($ref['logo'] ?? '');
        if ($id === false) {
            $position = (int) $this->db->query('SELECT COALESCE(MAX(position), -1) + 1 FROM client_references')->fetchColumn();
            $this->db->prepare('INSERT INTO client_references (position, name, logo, project) VALUES (?, ?, ?, ?)')->execute([$position, $name, $logo, $project]);
        } else {
            $this->db->prepare('UPDATE client_references SET name = ?, logo = ? WHERE id = ?')->execute([$name, $logo, $id]);
        }
    }

    private function delete(string $kind, string $slug): void
    {
        $stmt = $this->db->prepare("DELETE FROM $kind WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($kind === 'projects') {
            $this->db->prepare('DELETE FROM client_references WHERE project = ?')->execute([$slug]);
        }
    }

    private function reorder(string $kind, array $slugs): void
    {
        $rank = array_flip($slugs);
        $all = $this->db->query("SELECT slug FROM $kind ORDER BY position, slug")->fetchAll(PDO::FETCH_COLUMN);
        // Bu arada eklenmiş kayıtlar, yeni kayıtlar gibi en üstte kalır.
        usort($all, fn ($a, $b) => ($rank[$a] ?? -1) <=> ($rank[$b] ?? -1));
        $update = $this->db->prepare("UPDATE $kind SET position = ? WHERE slug = ?");
        foreach ($all as $i => $slug) {
            $update->execute([$i, $slug]);
        }
    }

    // ------------------------------------------------------------ validation
    private static function json(array $v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function text(array $a, string $key, int $max, bool $required = false, string $label = ''): string
    {
        $value = $a[$key] ?? '';
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InputError(($label ?: $key) . ' geçersiz.');
        }
        $value = trim((string) $value);
        if ($required && $value === '') {
            throw new InputError(($label ?: $key) . ' boş bırakılamaz.');
        }
        if (mb_strlen($value) > $max) {
            throw new InputError(($label ?: $key) . " en fazla $max karakter olabilir.");
        }
        return $value;
    }

    private static function lines(array $a, string $key, int $maxItems = 40): array
    {
        $out = [];
        foreach ((array) ($a[$key] ?? []) as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $out[] = mb_substr($line, 0, 400);
            }
        }
        if (count($out) > $maxItems) {
            throw new InputError("En fazla $maxItems satır girilebilir.");
        }
        return $out;
    }

    private static function pairs(array $a, string $key, int $maxItems): array
    {
        $out = [];
        foreach ((array) ($a[$key] ?? []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label !== '' && $value !== '') {
                $out[] = ['label' => mb_substr($label, 0, 60), 'value' => mb_substr($value, 0, 120)];
            }
        }
        return array_slice($out, 0, $maxItems);
    }

    private static function path(mixed $p): string
    {
        $p = trim((string) $p);
        if ($p !== '' && (!preg_match(self::ASSET, $p) || str_contains($p, '..'))) {
            throw new InputError('Geçersiz görsel yolu.');
        }
        return $p;
    }

    private static function gallery(array $a): array
    {
        $out = [];
        foreach (array_slice((array) ($a['gallery'] ?? []), 0, 12) as $g) {
            $src = self::path($g['src'] ?? '');
            if ($src === '') {
                continue;
            }
            $out[] = [
                'src' => $src,
                'alt' => mb_substr(trim((string) ($g['alt'] ?? '')), 0, 200),
                'width' => max(1, min(5000, (int) ($g['width'] ?? 1600))),
                'height' => max(1, min(5000, (int) ($g['height'] ?? 1200))),
            ];
        }
        return $out;
    }

    private static function slug(array $a): string
    {
        $slug = (string) ($a['slug'] ?? '');
        if (strlen($slug) > 80 || !preg_match(self::SLUG, $slug)) {
            throw new InputError('Sayfa adresi yalnız küçük harf, rakam ve tire içerebilir.');
        }
        return $slug;
    }

    public static function cleanProject(array $p): array
    {
        $image = self::path($p['image'] ?? '');
        return [
            'slug' => self::slug($p),
            'title' => self::text($p, 'title', 160, true, 'Ad'),
            'category' => self::text($p, 'category', 80, true, 'Kategori'),
            'status' => self::text($p, 'status', 120, true, 'Durum'),
            'year' => self::text($p, 'year', 40),
            'location' => self::text($p, 'location', 160),
            'summary' => self::text($p, 'summary', 300, true, 'Kısa özet'),
            'intro' => self::text($p, 'intro', 4000, true, 'Proje anlatımı'),
            'scope' => self::lines($p, 'scope'),
            'results' => self::lines($p, 'results'),
            'facts' => self::pairs($p, 'facts', 12),
            'image' => $image,
            'imageAlt' => $image === '' ? '' : self::text($p, 'imageAlt', 200),
            'gallery' => self::gallery($p),
            'drawing' => in_array($p['drawing'] ?? '', self::DRAWINGS, true) ? $p['drawing'] : 'building',
            'services' => array_values(array_filter(array_map('strval', (array) ($p['services'] ?? [])), fn ($s) => preg_match(self::SLUG, $s) === 1)),
            'draft' => !empty($p['draft']),
        ];
    }

    public static function cleanProduct(array $p): array
    {
        $price = $p['price'] ?? null;
        if ($price !== null && (!is_numeric($price) || $price < 0 || $price > 1e9)) {
            throw new InputError('Fiyat geçersiz.');
        }
        $image = self::path($p['image'] ?? '');
        return [
            'slug' => self::slug($p),
            'title' => self::text($p, 'title', 160, true, 'Ad'),
            'category' => self::text($p, 'category', 80, true, 'Kategori'),
            'brand' => self::text($p, 'brand', 120),
            'sku' => self::text($p, 'sku', 80),
            'price' => $price === null ? null : round((float) $price, 2),
            'vat' => ($p['vat'] ?? '') === 'hariç' ? 'hariç' : 'dahil',
            'unit' => self::text($p, 'unit', 40) ?: 'adet',
            'stock' => in_array($p['stock'] ?? '', self::STOCK, true) ? $p['stock'] : 'Stokta',
            'summary' => self::text($p, 'summary', 300, true, 'Kısa açıklama'),
            'description' => self::text($p, 'description', 8000),
            'specs' => self::pairs($p, 'specs', 24),
            'image' => $image,
            'imageAlt' => $image === '' ? '' : self::text($p, 'imageAlt', 200),
            'gallery' => self::gallery($p),
            'draft' => !empty($p['draft']),
        ];
    }
}
