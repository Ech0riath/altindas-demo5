<?php
// iyzico Ortak Ödeme Formu (Checkout Form) ile kartlı ödeme: sipariş kaydı,
// ödemeyi başlatma ve iyzico dönüşünü sunucudan doğrulama. Kart bilgisi bu
// siteye hiç gelmez; ziyaretçi kartını iyzico'nun sayfasında girer.
// Bu sürüm yalnız iyzico test (sandbox) ortamıyla çalışır.
declare(strict_types=1);

namespace Altindas;

use PDO;
use PDOException;

final class PaymentError extends \RuntimeException
{
}

/** iyzico REST istemcisi (resmî iyzipay-php SDK'sının IYZWSv2 imzasıyla aynı). */
final class Iyzico
{
    public const SANDBOX = 'https://sandbox-api.iyzipay.com';
    private const INITIALIZE = '/payment/iyzipos/checkoutform/initialize/auth/ecom';
    private const RETRIEVE = '/payment/iyzipos/checkoutform/auth/ecom/detail/';

    public function __construct(private string $apiKey, private string $secretKey, private string $base = self::SANDBOX)
    {
    }

    /** SDK'daki RequestFormatter::formatPrice: 10 → "10.0", 10.50 → "10.5". */
    public static function price(float|string $value): string
    {
        $s = is_float($value) ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') : $value;
        if (!str_contains($s, '.')) {
            return $s . '.0';
        }
        $s = rtrim($s, '0');
        return str_ends_with($s, '.') ? $s . '0' : $s;
    }

    public function initialize(array $request): array
    {
        $r = $this->post(self::INITIALIZE, $request);
        if (($r['status'] ?? '') === 'success') {
            $expected = $this->sign([(string) ($r['conversationId'] ?? ''), (string) ($r['token'] ?? '')]);
            if (isset($r['signature']) && !hash_equals($expected, (string) $r['signature'])) {
                throw new PaymentError('iyzico yanıtının imzası doğrulanamadı.');
            }
        }
        return $r;
    }

    public function retrieve(string $conversationId, string $token): array
    {
        return $this->post(self::RETRIEVE, ['locale' => 'tr', 'conversationId' => $conversationId, 'token' => $token]);
    }

    /**
     * Ödeme sorgusu yanıtının imzası: paymentStatus, paymentId, currency,
     * basketId, conversationId, paidPrice, price, token. Tutarlar SDK örneğindeki
     * gibi ya ham sayı ya da formatPrice biçiminde imzalanmış olabilir; ikisi de
     * aynı gizli anahtarla hesaplandığından ikisini de kabul etmek güvenliği azaltmaz.
     * İmza yoksa null döner.
     */
    public function retrieveSignatureValid(array $r): ?bool
    {
        if (!isset($r['signature'])) {
            return null;
        }
        $fields = fn (callable $money) => [
            (string) ($r['paymentStatus'] ?? ''), (string) ($r['paymentId'] ?? ''), (string) ($r['currency'] ?? ''),
            (string) ($r['basketId'] ?? ''), (string) ($r['conversationId'] ?? ''),
            $money($r['paidPrice'] ?? ''), $money($r['price'] ?? ''), (string) ($r['token'] ?? ''),
        ];
        foreach ([fn ($v) => (string) $v, fn ($v) => self::price((string) $v)] as $money) {
            if (hash_equals($this->sign($fields($money)), (string) $r['signature'])) {
                return true;
            }
        }
        return false;
    }

    private function sign(array $parts): string
    {
        return hash_hmac('sha256', implode(':', $parts), $this->secretKey);
    }

    private function post(string $uri, array $body): array
    {
        if (!function_exists('curl_init')) {
            throw new PaymentError('Sunucuda PHP cURL eklentisi yok.');
        }
        // SDK ile aynı JSON biçimi; imza gönderilen gövdenin kendisi üzerinden hesaplanır.
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $rnd = uniqid('', true);
        $auth = base64_encode('apiKey:' . $this->apiKey . '&randomKey:' . $rnd . '&signature:' . hash_hmac('sha256', $rnd . $uri . $json, $this->secretKey));
        $ch = curl_init($this->base . $uri);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: IYZWSv2 ' . $auth,
            ],
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            throw new PaymentError('iyzico’ya ulaşılamadı' . ($error !== '' ? ': ' . $error : '.'));
        }
        return $data;
    }
}

final class Payments
{
    public const STATUSES = [
        'bekliyor' => 'Ödeme bekleniyor',
        'odendi' => 'Ödendi',
        'basarisiz' => 'Ödeme başarısız',
        'kontrol' => 'Kontrol gerekiyor',
    ];
    private const MAX_QTY = 999;
    private const MAX_TOTAL = 1_000_000;
    private const HOURLY_LIMIT = 10;

    public function __construct(private App $app)
    {
    }

    // ------------------------------------------------------------ settings
    public function settings(): array
    {
        $s = $this->app->store()->setting('iyzico');
        return [
            'enabled' => !empty($s['enabled']),
            'apiKey' => (string) ($s['apiKey'] ?? ''),
            'secretKey' => (string) ($s['secretKey'] ?? ''),
        ];
    }

    public function ready(): bool
    {
        $s = $this->settings();
        return $s['enabled'] && $s['apiKey'] !== '' && $s['secretKey'] !== '' && function_exists('curl_init');
    }

    /** Panele giden ayarlar; anahtarların kendisi tarayıcıya gönderilmez. */
    public function adminState(): array
    {
        $s = $this->settings();
        return [
            'enabled' => $s['enabled'],
            'mode' => 'sandbox',
            'apiKeyHint' => $s['apiKey'] === '' ? '' : '…' . substr($s['apiKey'], -4),
            'secretKeySet' => $s['secretKey'] !== '',
            'curl' => function_exists('curl_init'),
            'callbackUrl' => $this->app->origin . $this->app->url('odeme/sonuc/'),
            'orders' => $this->orders(),
        ];
    }

    public function saveSettings(array $body): void
    {
        $this->app->store()->install();
        $s = $this->settings();
        foreach (['apiKey', 'secretKey'] as $key) {
            $value = trim((string) ($body[$key] ?? ''));
            if ($value === '') {
                continue; // Boş alan, kayıtlı anahtarı korur.
            }
            if (strlen($value) > 120 || !preg_match('/^[A-Za-z0-9-]+$/', $value)) {
                throw new InputError('Anahtar geçersiz görünüyor; iyzico panelinden kopyalayıp tekrar yapıştırın.');
            }
            // Canlı ödeme bu sürümde kapalı: yalnız test ortamı anahtarları kabul edilir.
            if (!str_starts_with($value, 'sandbox-')) {
                throw new InputError('Yalnız iyzico test (sandbox) anahtarları kabul edilir; “sandbox-” ile başlamalı. Canlı ödeme bu sürümde kapalı.');
            }
            $s[$key] = $value;
        }
        if (!empty($body['clear'])) {
            $s['apiKey'] = $s['secretKey'] = '';
        }
        $s['enabled'] = !empty($body['enabled']);
        if ($s['enabled'] && ($s['apiKey'] === '' || $s['secretKey'] === '')) {
            throw new InputError('Kartla ödemeyi açmak için API anahtarı ve gizli anahtar gerekli.');
        }
        if ($s['enabled'] && !function_exists('curl_init')) {
            throw new InputError('Sunucuda PHP cURL eklentisi yok; hPanel → PHP Yapılandırması’ndan açın.');
        }
        $this->app->store()->saveSetting('iyzico', $s);
    }

    private function client(): Iyzico
    {
        $s = $this->settings();
        // Yerel denemede sahte bir iyzico sunucusu kullanılabilir; yayında hep sandbox.
        $base = PHP_SAPI === 'cli-server' && getenv('ALTINDAS_IYZICO_BASE') ? (string) getenv('ALTINDAS_IYZICO_BASE') : Iyzico::SANDBOX;
        return new Iyzico($s['apiKey'], $s['secretKey'], $base);
    }

    // ------------------------------------------------------------ orders
    public function orders(int $limit = 200): array
    {
        try {
            $rows = $this->app->store()->db->query('SELECT * FROM orders ORDER BY id DESC LIMIT ' . $limit)->fetchAll();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                return [];
            }
            throw $e;
        }
        return array_map(fn ($o) => [
            'reference' => $o['reference'], 'status' => $o['status'], 'statusText' => self::STATUSES[$o['status']] ?? $o['status'],
            'mode' => $o['mode'], 'product' => $o['product_title'], 'slug' => $o['product_slug'], 'sku' => $o['sku'],
            'unit' => $o['unit'], 'quantity' => (int) $o['quantity'], 'total' => (float) $o['total'],
            'buyer' => $o['buyer_name'], 'email' => $o['email'], 'phone' => $o['phone'],
            'address' => $o['address'] . ', ' . $o['district'] . ' / ' . $o['city'], 'note' => $o['note'],
            'paymentId' => $o['payment_id'], 'error' => $o['error'], 'createdAt' => $o['created_at'],
        ], $rows);
    }

    private function find(string $column, string $value): ?array
    {
        $stmt = $this->app->store()->db->prepare("SELECT * FROM orders WHERE $column = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    /** Panelden: bekleyen bir siparişin durumunu iyzico'dan yeniden sorgular. */
    public function recheck(string $reference): void
    {
        $order = $this->find('reference', $reference);
        if (!$order) {
            throw new InputError('Sipariş bulunamadı.');
        }
        if ($order['status'] !== 'bekliyor' || !$order['token']) {
            throw new InputError('Yalnız ödeme bekleyen siparişler sorgulanabilir.');
        }
        try {
            $this->verify($order);
        } catch (PaymentError $e) {
            throw new InputError($e->getMessage());
        }
    }

    // ------------------------------------------------------------ checkout
    /** odeme/ — GET: alıcı bilgisi formu, POST: sipariş + iyzico ödeme sayfasına yönlendirme. */
    public function checkout(Site $site): void
    {
        if (!$this->ready()) {
            send(404, 'text/html; charset=utf-8', $site->notFound());
            return;
        }
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $src = $post ? $_POST : $_GET;
        $product = $site->product((string) ($src['urun'] ?? ''));
        if (!$product || !Site::payable($product)) {
            send(404, 'text/html; charset=utf-8', $site->notFound());
            return;
        }
        $qty = max(1, min(self::MAX_QTY, (int) ($src['qty'] ?? 1)));
        $input = array_map(fn ($v) => is_string($v) ? trim($v) : '', array_intersect_key($src, array_flip(['ad', 'soyad', 'eposta', 'telefon', 'tckn', 'il', 'ilce', 'adres', 'not'])));
        $errors = [];
        if ($post) {
            $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
            if ($origin !== '' && $origin !== $this->app->origin) {
                send(403, 'text/plain; charset=utf-8', 'Geçersiz istek.');
                return;
            }
            [$buyer, $errors] = self::validate($input);
            if (!$errors) {
                try {
                    $url = $this->start($product, $qty, $buyer);
                    header('Location: ' . $url, true, 303);
                    return;
                } catch (InputError $e) {
                    $errors[] = $e->getMessage();
                } catch (PaymentError $e) {
                    error_log('[altindas iyzico] ' . $e->getMessage());
                    $errors[] = 'Ödeme sayfası şu an açılamadı. Lütfen biraz sonra tekrar deneyin veya siparişinizi WhatsApp’tan iletin.';
                }
            }
        }
        send($errors ? 422 : 200, 'text/html; charset=utf-8', $site->checkoutPage($product, $qty, $input, $errors), ['Cache-Control: no-store']);
    }

    /** @return array{0: array, 1: list<string>} */
    private static function validate(array $in): array
    {
        $errors = [];
        $get = fn ($k) => (string) ($in[$k] ?? '');
        foreach (['ad' => 'Ad', 'soyad' => 'Soyad', 'il' => 'İl', 'ilce' => 'İlçe'] as $k => $label) {
            if ($get($k) === '' || mb_strlen($get($k)) > 60) {
                $errors[] = "$label boş bırakılamaz (en fazla 60 karakter).";
            }
        }
        $email = $get('eposta');
        if (mb_strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi yazın.';
        }
        $digits = preg_replace('/\D/', '', $get('telefon'));
        $phone = preg_match('/^(?:90|0)?(5\d{9})$/', $digits, $m) ? '+90' . $m[1] : '';
        if ($phone === '') {
            $errors[] = 'Cep telefonunu 05xx xxx xx xx biçiminde yazın.';
        }
        if (!self::validTckn($get('tckn'))) {
            $errors[] = 'T.C. kimlik numarası geçersiz.';
        }
        $address = $get('adres');
        if (mb_strlen($address) < 10 || mb_strlen($address) > 300) {
            $errors[] = 'Açık adres 10–300 karakter olmalı.';
        }
        if (mb_strlen($get('not')) > 500) {
            $errors[] = 'Sipariş notu en fazla 500 karakter olabilir.';
        }
        return [[
            'name' => $get('ad'), 'surname' => $get('soyad'), 'email' => $email, 'phone' => $phone, 'tckn' => $get('tckn'),
            'city' => $get('il'), 'district' => $get('ilce'), 'address' => $address, 'note' => $get('not'),
        ], $errors];
    }

    public static function validTckn(string $n): bool
    {
        if (!preg_match('/^[1-9]\d{10}$/', $n)) {
            return false;
        }
        $d = array_map('intval', str_split($n));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        return (($odd * 7 - $even) % 10 + 10) % 10 === $d[9] && array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
    }

    private static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /** Siparişi kaydeder, iyzico ödeme formunu başlatır ve ödeme sayfası adresini döner. */
    private function start(array $product, int $qty, array $buyer): string
    {
        $db = $this->app->store()->db;
        $ip = self::clientIp();
        $recent = $db->prepare('SELECT COUNT(*) FROM orders WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
        $recent->execute([$ip]);
        if ((int) $recent->fetchColumn() >= self::HOURLY_LIMIT) {
            throw new InputError('Kısa sürede çok fazla sipariş denemesi yapıldı. Lütfen daha sonra tekrar deneyin veya WhatsApp’tan yazın.');
        }
        $unitPrice = round((float) $product['price'], 2);
        $total = round($unitPrice * $qty, 2);
        if ($total <= 0 || $total > self::MAX_TOTAL) {
            throw new InputError('Bu tutar için siparişinizi WhatsApp’tan iletin.');
        }
        $reference = strtoupper(bin2hex(random_bytes(6)));
        $unit = ($product['unit'] ?? '') ?: 'adet';
        $db->prepare('INSERT INTO orders (reference, status, mode, product_slug, product_title, sku, unit, quantity, unit_price, total, buyer_name, email, phone, city, district, address, note, ip, created_at) VALUES (?, \'bekliyor\', \'sandbox\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$reference, $product['slug'], $product['title'], (string) ($product['sku'] ?? ''), $unit, $qty, $unitPrice, $total,
                $buyer['name'] . ' ' . $buyer['surname'], $buyer['email'], $buyer['phone'], $buyer['city'], $buyer['district'], $buyer['address'], $buyer['note'], $ip]);
        $address = [
            'address' => $buyer['address'] . ', ' . $buyer['district'],
            'contactName' => $buyer['name'] . ' ' . $buyer['surname'],
            'city' => $buyer['city'],
            'country' => 'Turkey',
        ];
        $price = Iyzico::price($total);
        $r = $this->client()->initialize([
            'locale' => 'tr',
            'conversationId' => $reference,
            'price' => $price,
            'basketId' => $reference,
            'paymentGroup' => 'PRODUCT',
            'buyer' => [
                'id' => $reference,
                'name' => $buyer['name'],
                'surname' => $buyer['surname'],
                'identityNumber' => $buyer['tckn'],
                'email' => $buyer['email'],
                'gsmNumber' => $buyer['phone'],
                'registrationAddress' => $address['address'],
                'city' => $buyer['city'],
                'country' => 'Turkey',
                'ip' => $ip,
            ],
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'basketItems' => [[
                'id' => $product['slug'],
                'price' => $price,
                'name' => mb_substr($product['title'], 0, 100),
                'category1' => mb_substr($product['category'], 0, 60),
                'itemType' => 'PHYSICAL',
            ]],
            'callbackUrl' => $this->app->origin . $this->app->url('odeme/sonuc/'),
            'currency' => 'TRY',
            'paidPrice' => $price,
        ]);
        $page = (string) ($r['paymentPageUrl'] ?? '');
        $host = (string) parse_url($page, PHP_URL_HOST);
        if (($r['status'] ?? '') !== 'success' || empty($r['token']) || !str_starts_with($page, 'https://') || !preg_match('/(?:^|\.)iyzipay\.com$/', $host)) {
            $message = mb_substr((string) ($r['errorMessage'] ?? 'Ödeme başlatılamadı.'), 0, 300);
            $db->prepare('UPDATE orders SET status = \'basarisiz\', error = ? WHERE reference = ?')->execute([$message, $reference]);
            throw new PaymentError('Ödeme başlatılamadı: ' . ($r['errorCode'] ?? '') . ' ' . $message);
        }
        $db->prepare('UPDATE orders SET token = ? WHERE reference = ?')->execute([(string) $r['token'], $reference]);
        return $page;
    }

    // ------------------------------------------------------------ callback
    /** odeme/sonuc/ — POST: iyzico dönüşü (token), GET ?siparis=: sonuç sayfası. */
    public function result(Site $site): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $token = (string) ($_POST['token'] ?? '');
            $order = $token !== '' && strlen($token) <= 255 ? $this->find('token', $token) : null;
            if (!$order) {
                send(404, 'text/html; charset=utf-8', $site->notFound());
                return;
            }
            if ($order['status'] === 'bekliyor') {
                try {
                    $this->verify($order);
                } catch (PaymentError $e) {
                    // Sipariş "bekliyor" kalır; panelden yeniden sorgulanabilir.
                    error_log('[altindas iyzico] ' . $e->getMessage());
                }
            }
            header('Location: ' . $this->app->url('odeme/sonuc/') . '?siparis=' . rawurlencode($order['reference']), true, 303);
            return;
        }
        $reference = (string) ($_GET['siparis'] ?? '');
        $order = preg_match('/^[0-9A-F]{12}$/', $reference) ? $this->find('reference', $reference) : null;
        if (!$order) {
            send(404, 'text/html; charset=utf-8', $site->notFound());
            return;
        }
        send(200, 'text/html; charset=utf-8', $site->orderResultPage([
            'reference' => $order['reference'], 'status' => $order['status'], 'product' => $order['product_title'],
            'slug' => $order['product_slug'], 'unit' => $order['unit'], 'quantity' => (int) $order['quantity'],
            'total' => (float) $order['total'], 'mode' => $order['mode'], 'error' => $order['error'],
        ]), ['Cache-Control: no-store', 'Referrer-Policy: no-referrer']);
    }

    /** Ödemeyi iyzico'dan sorgular ve siparişin durumunu günceller. */
    private function verify(array $order): void
    {
        $client = $this->client();
        $r = $client->retrieve($order['reference'], (string) $order['token']);
        $paymentStatus = (string) ($r['paymentStatus'] ?? '');
        $error = mb_substr((string) ($r['errorMessage'] ?? ''), 0, 400);
        if (($r['status'] ?? '') !== 'success' || $paymentStatus === '') {
            // Sorgu başarısız (ör. süresi dolmuş form): ödeme yapılmadı sayılmaz, sipariş bekler.
            $this->update($order, 'bekliyor', '', $error ?: 'Ödeme sonucu alınamadı.');
            return;
        }
        if ($paymentStatus !== 'SUCCESS') {
            $this->update($order, $paymentStatus === 'FAILURE' ? 'basarisiz' : 'bekliyor', (string) ($r['paymentId'] ?? ''), $error ?: 'Ödeme tamamlanmadı (' . $paymentStatus . ').');
            return;
        }
        $problems = [];
        if ($client->retrieveSignatureValid($r) === false) {
            $problems[] = 'iyzico imzası doğrulanamadı';
        }
        if (($r['token'] ?? '') !== $order['token'] || ($r['basketId'] ?? '') !== $order['reference'] || ($r['conversationId'] ?? '') !== $order['reference']) {
            $problems[] = 'sipariş numarası uyuşmuyor';
        }
        if (($r['currency'] ?? '') !== 'TRY' || abs((float) ($r['paidPrice'] ?? 0) - (float) $order['total']) > 0.004) {
            $problems[] = 'ödenen tutar sipariş tutarıyla uyuşmuyor';
        }
        if (isset($r['fraudStatus']) && (int) $r['fraudStatus'] !== 1) {
            $problems[] = 'iyzico ödemeyi inceliyor (fraudStatus ' . (int) $r['fraudStatus'] . ')';
        }
        $this->update($order, $problems ? 'kontrol' : 'odendi', (string) ($r['paymentId'] ?? ''), implode('; ', $problems));
    }

    private function update(array $order, string $status, string $paymentId, string $error): void
    {
        // Yalnız bekleyen sipariş güncellenir; aynı dönüşün iki kez işlenmesi sonucu değiştirmez.
        $this->app->store()->db->prepare('UPDATE orders SET status = ?, payment_id = ?, error = ? WHERE id = ? AND status = \'bekliyor\'')
            ->execute([$status, mb_substr($paymentId, 0, 40), mb_substr($error, 0, 400), $order['id']]);
    }
}
