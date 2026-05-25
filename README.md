# SiteStats v2.0 — Kurulum Kılavuzu

Tamamen bağımsız, dışa bağımlılığı olmayan ziyaretçi istatistik sistemi.
PHP + MySQL ile çalışır. Her web sitesine 3 adımda kurulur.

---

## 📁 Dosya Yapısı

```
sitestats/
├── db.php          ← Veritabanı bağlantısı (sadece bunu düzenleyin)
├── install.php     ← Tarayıcıdan açılır, tabloları kurar, sonra silinir
├── stats.php       ← Ana API — ziyaret kaydı + istatistik
├── analytics.js    ← Her sayfaya eklenen script (widget + takip)
├── dashboard.php   ← Admin paneli
├── setup.sql       ← Veritabanı tabloları (install.php otomatik çalıştırır)
└── README.md       ← Bu dosya
```

---

## 🚀 Kurulum (3 Adım)

### Adım 1 — Dosyaları sunucuya yükleyin

`sitestats/` klasörünü web sitenizin kök dizinine yükleyin.

```
/var/www/html/sitestats/   (Linux)
/public_html/sitestats/    (cPanel)
```

### Adım 2 — db.php dosyasını düzenleyin

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani_adiniz');
define('DB_USER', 'kullanici_adiniz');
define('DB_PASS', 'sifreniz');
```

> ⚠️ Veritabanını önceden oluşturmanız gerekir (phpMyAdmin veya cPanel'den).

### Adım 3 — Kurulum sihirbazını çalıştırın

Tarayıcıdan açın:
```
https://siteniz.com/sitestats/install.php
```

Admin kullanıcı adı ve şifrenizi belirleyin, "Kurulumu Başlat" butonuna tıklayın.
`install.php` otomatik olarak silinir.

---

## 📌 Her Sayfaya Entegrasyon

`</body>` kapanış etiketinden önce şu satırı ekleyin:

```html
<script src="/sitestats/analytics.js"></script>
```

Bu kadar! Widget otomatik olarak footer'a eklenir.

---

## 📊 Dashboard Erişimi

```
https://siteniz.com/sitestats/dashboard.php
```

Install sırasında belirlediğiniz kullanıcı adı ve şifreyle giriş yapın.

---

## ⚙️ Özelleştirme

### analytics.js içinde:

```js
const STATS_URL     = "/sitestats/stats.php"; // stats.php yolu
const HEARTBEAT_SEC = 30;                      // Anlık güncelleme sıklığı (saniye)
```

### stats.php içinde:

```php
const SECRET_SALT    = 'degistirin_bunu'; // IP hash güvenlik tuzu
const ACTIVE_SECONDS = 60;               // "Anlık aktif" eşiği (saniye)
```

---

## 🔄 Yeni Bir Siteye Kurulum

1. `sitestats/` klasörünü kopyalayın
2. `db.php`'deki veritabanı bilgilerini güncelleyin
3. Yeni veritabanı oluşturun
4. `install.php`'yi tarayıcıdan çalıştırın
5. `analytics.js`'yi sayfalara ekleyin

Her site için ayrı veritabanı kullanılır — veriler birbirinden tamamen bağımsızdır.

---

## 🔒 Güvenlik Notları

- `install.php` kurulum sonrası otomatik silinir
- IP adresleri hash'lenerek saklanır (ham IP saklanmaz)
- Admin şifresi bcrypt ile hash'lenir
- `SECRET_SALT` değerini mutlaka değiştirin

---

## 📋 Gereksinimler

- PHP 8.0+
- MySQL 5.7+ veya MariaDB 10.3+
- PDO PHP eklentisi (çoğu hostingde varsayılan açık)
