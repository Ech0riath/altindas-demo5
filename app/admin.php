<?php
// Yönetim paneli sunucu tarafı: oturum, giriş denemesi sınırı, CSRF,
// JSON API ve ilk kurulum sihirbazı.
declare(strict_types=1);

namespace Altindas;

use PDO;

final class Admin
{
    private const MAX_FAILURES = 5;
    private const LOCK_MINUTES = 15;
    private const IDLE_SECONDS = 8 * 3600;
    private const UPLOAD = '#^uploads/(?:projects|products|references)/[a-z0-9-]+\.(webp|jpg|png)$#';
    private const MIME = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    public function __construct(private App $app)
    {
    }

    public static function startSession(string $base): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('altindas_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $base . 'admin/',
            'secure' => App::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > self::IDLE_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['seen'] = time();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    private static function json(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function requireCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
            self::json(403, ['error' => 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.']);
        }
    }

    private function requireUser(): void
    {
        if (empty($_SESSION['admin'])) {
            // Giriş isteği için gereken CSRF anahtarı bu yanıtla verilir.
            self::json(401, ['error' => 'Oturum açık değil. Lütfen giriş yapın.', 'csrf' => $_SESSION['csrf']]);
        }
    }

    private function state(): array
    {
        $data = $this->app->store()->load($this->app->staticContent());
        return [
            'user' => ['login' => $_SESSION['admin']],
            'csrf' => $_SESSION['csrf'],
            'data' => [
                'services' => array_map(fn ($s) => ['slug' => $s['slug'], 'title' => $s['title'], 'short' => $s['short']], $data['services']),
                'projects' => $data['projects'],
                'products' => $data['products'],
                'references' => $data['references'],
            ],
            'payments' => $this->app->payments()->adminState(),
        ];
    }

    // ------------------------------------------------------------ API
    public function api(string $action): never
    {
        if (!$this->app->configured()) {
            self::json(503, ['error' => 'Kurulum tamamlanmamış.']);
        }
        self::startSession($this->app->base);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        try {
            if ($action === 'state' && $method === 'GET') {
                $this->requireUser();
                self::json(200, $this->state());
            }
            if ($method !== 'POST') {
                self::json(405, ['error' => 'Geçersiz istek.']);
            }
            $this->requireCsrf();
            match ($action) {
                'login' => $this->login(),
                'logout' => $this->logout(),
                'save' => $this->save(),
                'payments' => $this->savePayments(),
                'order-check' => $this->checkOrder(),
                default => self::json(404, ['error' => 'Bulunamadı.']),
            };
        } catch (InputError $e) {
            self::json(422, ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[altindas admin] ' . $e);
            self::json(500, ['error' => 'Sunucuda bir hata oluştu. Lütfen tekrar deneyin.']);
        }
    }

    private function body(): array
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }

    private function login(): never
    {
        $db = $this->app->store()->db;
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $db->prepare('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY')->execute();
        $count = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL ' . self::LOCK_MINUTES . ' MINUTE');
        $count->execute([$ip]);
        if ((int) $count->fetchColumn() >= self::MAX_FAILURES) {
            self::json(429, ['error' => 'Çok fazla hatalı deneme. ' . self::LOCK_MINUTES . ' dakika sonra tekrar deneyin.']);
        }
        $body = $this->body();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $find = $db->prepare('SELECT username, password_hash FROM admins WHERE username = ?');
        $find->execute([$username]);
        $row = $find->fetch();
        // Kullanıcı yoksa da benzer sürede yanıt vermek için şifre özeti hesaplanır.
        $hash = $row['password_hash'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT);
        if (!$row | !password_verify($password, $hash)) {
            $db->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())')->execute([$ip]);
            self::json(401, ['error' => 'Kullanıcı adı veya şifre hatalı.']);
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $db->prepare('UPDATE admins SET password_hash = ? WHERE username = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['username']]);
        }
        $db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        // Sonradan eklenen tablolar (ör. siparişler) eski kurulumlarda girişte oluşturulur.
        $this->app->store()->install();
        session_regenerate_id(true);
        $_SESSION['admin'] = $row['username'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::json(200, $this->state());
    }

    private function logout(): never
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::json(200, ['ok' => true]);
    }

    private function save(): never
    {
        $this->requireUser();
        $op = json_decode((string) ($_POST['op'] ?? ''), true);
        if (!is_array($op)) {
            throw new InputError('Geçersiz istek.');
        }
        $paths = json_decode((string) ($_POST['paths'] ?? '[]'), true);
        $files = self::uploadedFiles();
        if (!is_array($paths) || count($paths) !== count($files)) {
            throw new InputError('Fotoğraf bilgileri eksik.');
        }
        $store = $this->app->store();
        $before = $store->assetPaths();
        $written = [];
        try {
            foreach ($files as $i => $file) {
                $written[] = $this->storeUpload($file, (string) $paths[$i]);
            }
            foreach (self::referencedUploads($op) as $path) {
                if (!is_file($this->app->root . '/' . $path)) {
                    throw new InputError('Fotoğraf yüklenemedi; lütfen tekrar deneyin.');
                }
            }
            $store->apply($op);
        } catch (\Throwable $e) {
            foreach ($written as $file) {
                @unlink($file);
            }
            throw $e;
        }
        // Artık hiçbir kaydın kullanmadığı panel yüklemeleri silinir.
        foreach (array_diff($before, $store->assetPaths()) as $path) {
            if (preg_match(self::UPLOAD, $path) && is_file($this->app->root . '/' . $path)) {
                @unlink($this->app->root . '/' . $path);
            }
        }
        self::json(200, $this->state());
    }

    private function savePayments(): never
    {
        $this->requireUser();
        $this->app->payments()->saveSettings($this->body());
        self::json(200, $this->state());
    }

    private function checkOrder(): never
    {
        $this->requireUser();
        $this->app->payments()->recheck((string) ($this->body()['reference'] ?? ''));
        self::json(200, $this->state());
    }

    private static function referencedUploads(array $op): array
    {
        $item = (array) ($op['item'] ?? []);
        $paths = [(string) ($item['image'] ?? ''), (string) (($op['reference'] ?? [])['logo'] ?? '')];
        foreach ((array) ($item['gallery'] ?? []) as $g) {
            $paths[] = (string) ($g['src'] ?? '');
        }
        return array_filter($paths, fn ($p) => str_starts_with($p, 'uploads/'));
    }

    private static function uploadedFiles(): array
    {
        $f = $_FILES['files'] ?? null;
        if (!$f) {
            return [];
        }
        $out = [];
        foreach ((array) $f['tmp_name'] as $i => $tmp) {
            $out[] = ['tmp' => $tmp, 'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $f['size'][$i] ?? 0];
        }
        return $out;
    }

    private function storeUpload(array $file, string $path): string
    {
        if (!preg_match(self::UPLOAD, $path, $m)) {
            throw new InputError('Geçersiz fotoğraf adı.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp'])) {
            throw new InputError($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Fotoğraf sunucunun izin verdiği boyuttan büyük.'
                : 'Fotoğraf yüklenemedi; lütfen tekrar deneyin.');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new InputError('Fotoğraf 10 MB’tan büyük olamaz.');
        }
        $info = @getimagesize($file['tmp']);
        if (!$info || ($info['mime'] ?? '') !== self::MIME[$m[1]] || $info[0] > 5000 || $info[1] > 5000) {
            throw new InputError('Yalnız WebP, JPG veya PNG fotoğraf yüklenebilir.');
        }
        $target = $this->app->root . '/' . $path;
        if (file_exists($target)) {
            throw new InputError('Aynı adda bir fotoğraf zaten var; tekrar deneyin.');
        }
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
            throw new \RuntimeException('Yükleme klasörü oluşturulamadı: ' . dirname($target));
        }
        if (!move_uploaded_file($file['tmp'], $target)) {
            throw new \RuntimeException('Fotoğraf taşınamadı: ' . $target);
        }
        @chmod($target, 0644);
        return $target;
    }

    // ------------------------------------------------------------ setup
    public function setup(): never
    {
        self::startSession($this->app->base);
        header('Cache-Control: no-store');
        $configured = $this->app->configured();
        $hasAdmin = $configured && $this->adminCount() > 0;
        $errors = [];
        $done = false;
        $manualConfig = null;
        if ($hasAdmin) {
            $this->setupPage('<p>Kurulum tamamlanmış. Panele <a href="' . esc($this->app->url('admin/')) . '">giriş sayfasından</a> ulaşabilirsiniz.</p>');
        }
        $input = [
            'db_name' => trim((string) ($_POST['db_name'] ?? '')),
            'db_user' => trim((string) ($_POST['db_user'] ?? '')),
            'db_pass' => (string) ($_POST['db_pass'] ?? ''),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'password2' => (string) ($_POST['password2'] ?? ''),
        ];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
                $errors[] = 'Form süresi doldu; sayfayı yenileyip tekrar deneyin.';
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $input['username'])) {
                $errors[] = 'Kullanıcı adı 3–60 karakter olmalı; harf, rakam, nokta, tire ve alt çizgi kullanılabilir.';
            }
            if (mb_strlen($input['password']) < 10) {
                $errors[] = 'Şifre en az 10 karakter olmalı.';
            } elseif ($input['password'] !== $input['password2']) {
                $errors[] = 'Şifreler aynı değil.';
            }
            $db = $configured ? $this->app->config['db'] : ['host' => 'localhost', 'name' => $input['db_name'], 'user' => $input['db_user'], 'pass' => $input['db_pass']];
            if ($configured && !hash_equals((string) $db['pass'], $input['db_pass'])) {
                $errors[] = 'Veritabanı şifresi kayıtlı ayarla uyuşmuyor.';
            }
            if (!$configured && ($db['name'] === '' || $db['user'] === '')) {
                $errors[] = 'Veritabanı adı ve kullanıcı adı gerekli.';
            }
            if (!$errors) {
                try {
                    $store = Store::connect($db);
                    $store->install();
                    if ($store->isEmpty()) {
                        $store->seed($this->app->staticContent());
                    }
                    $store->db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
                        ->execute([$input['username'], password_hash($input['password'], PASSWORD_DEFAULT)]);
                    if (!$configured) {
                        $config = [
                            'db' => $db,
                            'origin' => App::detectOrigin(),
                            'indexable' => false,
                        ];
                        $php = "<?php\n// Kurulum sihirbazı tarafından oluşturuldu. Bu dosyayı paylaşmayın.\nreturn " . var_export($config, true) . ";\n";
                        if (@file_put_contents($this->app->root . '/app/config.php', $php, LOCK_EX) === false) {
                            $manualConfig = $php;
                        } else {
                            @chmod($this->app->root . '/app/config.php', 0600);
                        }
                    }
                    $done = true;
                } catch (\PDOException $e) {
                    error_log('[altindas setup] ' . $e->getMessage());
                    $errors[] = 'Veritabanına bağlanılamadı veya tablolar oluşturulamadı. Veritabanı adı, kullanıcı adı ve şifreyi hPanel’deki bilgilerle karşılaştırın.';
                }
            }
        }
        if ($done) {
            $note = $manualConfig === null
                ? '<p>Veritabanı tabloları oluşturuldu, mevcut projeler aktarıldı ve yönetici hesabı açıldı.</p><p><a class="btn btn-primary" href="' . esc($this->app->url('admin/')) . '">Panele giriş yapın</a></p>'
                : '<p>Tablolar ve yönetici hesabı oluşturuldu, ancak ayar dosyası yazılamadı. hPanel Dosya Yöneticisi’nde <code>app/config.php</code> adlı bir dosya oluşturup aşağıdaki içeriği yapıştırın:</p><pre class="setup-config">' . esc($manualConfig) . '</pre>';
            $this->setupPage($note);
        }
        $err = $errors ? '<div class="admin-errors" role="alert"><h3>Kurulum tamamlanamadı</h3><ul>' . implode('', array_map(fn ($e) => '<li>' . esc($e) . '</li>', $errors)) . '</ul></div>' : '';
        $field = fn ($id, $label, $type = 'text', $help = '', $value = '') => '<div class="field"><label for="' . $id . '">' . $label . '</label><input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . esc($value) . '" autocomplete="off" required' . ($help ? ' aria-describedby="' . $id . '-help"' : '') . '>' . ($help ? '<small id="' . $id . '-help">' . $help . '</small>' : '') . '</div>';
        $dbFields = $configured
            ? $field('db_pass', 'Veritabanı şifresi', 'password', 'Ayar dosyası zaten var; doğrulama için veritabanı şifresini yazın.')
            : '<div class="form-row">' . $field('db_name', 'Veritabanı adı', 'text', 'hPanel → Veritabanları’ndaki tam ad (ör. u123456789_altindas).', $input['db_name']) . $field('db_user', 'Veritabanı kullanıcısı', 'text', 'Aynı sayfadaki kullanıcı adı.', $input['db_user']) . '</div>' . $field('db_pass', 'Veritabanı şifresi', 'password');
        $form = $err . '<form method="post" class="discovery-form" action="' . esc($this->app->url('admin/kurulum/')) . '"><input type="hidden" name="csrf" value="' . esc($_SESSION['csrf']) . '"><div class="form-heading"><h2>Veritabanı</h2><p>Sunucu adresi <code>localhost</code> olarak kullanılır.</p></div>' . $dbFields . '<div class="form-heading"><h2>Yönetici hesabı</h2><p>Panele bu bilgilerle giriş yapacaksınız.</p></div>' . $field('username', 'Kullanıcı adı', 'text', '', $input['username']) . '<div class="form-row">' . $field('password', 'Şifre', 'password', 'En az 10 karakter.') . $field('password2', 'Şifre (tekrar)', 'password') . '</div><button class="btn btn-primary" type="submit">Kurulumu tamamla</button></form>';
        $this->setupPage($form);
    }

    private function adminCount(): int
    {
        try {
            return (int) $this->app->store()->db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    private function setupPage(string $content): never
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->app->site()->standalone(
            'admin/kurulum/',
            'Kurulum',
            'Altındaş Mühendislik sitesinin veritabanı ve yönetici hesabı kurulumu.',
            '<div class="wrap admin-shell setup-shell">' . $content . '</div>',
        );
        exit;
    }
}
