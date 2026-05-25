# BeyTor Stats

BeyTor Stats, PHP + MySQL ile çalışan hafif ve bağımsız bir web sitesi istatistik sistemidir.

Ziyaretçi sayısı, anlık aktif kullanıcı, sayfa görüntülemeleri, trafik kaynakları, cihaz/tarayıcı/işletim sistemi bilgileri ve sayfa bazlı istatistikleri kendi sunucunuzda tutar.

---

## Özellikler

- Günlük, aylık, yıllık ve tüm zamanlar ziyaretçi sayımı
- Son 60 saniyeye göre anlık aktif kullanıcı takibi
- Sayfa görüntüleme kaydı
- En çok ziyaret edilen sayfalar
- Trafik kaynakları analizi
- Cihaz, tarayıcı ve işletim sistemi istatistikleri
- Saatlik/günlük/aylık/yıllık detay grafikleri
- HTML sayfalarına script eklemek/kaldırmak için enjektör
- IP adreslerini hashleyerek saklama
- Kurulum sihirbazı ile otomatik tablo ve admin oluşturma
- Basic Auth + dashboard girişi ile çift katmanlı koruma

---

## Gereksinimler

- PHP 8.0+
- MySQL 5.7+ veya MariaDB 10.3+
- PDO PHP eklentisi
- Apache `.htaccess` desteği önerilir

---

## Dosya Yapısı

```text
beytorstats/
├── analytics.js     # Ziyaretçi takibi ve footer widget
├── dashboard.php    # Admin dashboard
├── db.php           # Kurulum sırasında otomatik oluşturulur
├── install.php      # Kurulum sihirbazı
├── stats.php        # Ana API
├── inject.php       # Kurulum sonrası kök dizine taşınır
├── setup.sql        # Tablo şeması
└── README.md
```

Kurulumdan sonra yapı şu hale gelir:

```text
public_html/
├── inject.php
└── beytorstats/
    ├── analytics.js
    ├── dashboard.php
    ├── db.php
    ├── stats.php
    ├── setup.sql
    └── README.md
```

`install.php` kurulum sonunda güvenlik için silinir.

---

## Kurulum

### 1. Dosyaları yükleyin

`beytorstats/` klasörünü web sitenizin kök dizinine yükleyin.

Örnek:

```text
/public_html/beytorstats/
```

veya Plesk ortamında:

```text
/httpdocs/beytorstats/
```

---

### 2. Kurulum sihirbazını açın

Tarayıcıdan şu adresi açın:

```text
https://siteadresiniz.com/beytorstats/install.php
```

Kurulum ekranında:

1. Veritabanı bilgilerinizi girin.
2. Bağlantıyı test edin.
3. Dashboard kullanıcı adını ve şifresini belirleyin.
4. Basic Auth kullanıcı adını ve şifresini belirleyin.
5. Kurulumu tamamlayın.

Kurulum sonunda:

- Veritabanı tabloları oluşturulur.
- `db.php` otomatik yazılır.
- Admin kullanıcısı oluşturulur.
- `.htaccess` ve `.htpasswd` oluşturulur.
- `inject.php` ana dizine taşınır.
- `install.php` silinir.

---

## Dashboard Erişimi

Kurulumdan sonra dashboard adresi:

```text
https://siteadresiniz.com/beytorstats/dashboard.php
```

Önce Basic Auth ekranı, ardından dashboard giriş ekranı gelir.

---

## Script Enjektörü

Kurulumdan sonra `inject.php` web sitesinin kök dizinine taşınır.

Adres:

```text
https://siteadresiniz.com/inject.php
```

Dashboard içinden de şu sekmeden açılabilir:

```text
Enjektör
```

Enjektör ile:

- HTML dosyaları taranır.
- Eski `/sitestats/analytics.js` scriptleri tespit edilip kaldırılabilir.
- Yeni `/beytorstats/analytics.js` scripti seçilen sayfalara eklenebilir.
- Script eklemeden önce dosyaların yedeği `_ss_backup/` klasörüne alınır.

Eklenen script:

```html
<script src="/beytorstats/analytics.js"></script>
```

---

## Analytics Ayarları

`analytics.js` içinde API yolu şu şekilde olmalıdır:

```js
const STATS_URL = "/beytorstats/stats.php";
```

Bu yol, ziyaret ve sayfa görüntüleme verilerini `stats.php` dosyasına gönderir.

---

## API Uçları

### Ziyaret Kaydı

```text
GET /beytorstats/stats.php?action=visit
```

Örnek cevap:

```json
{
  "success": true,
  "daily_visits": 1,
  "monthly_visits": 2,
  "yearly_visits": 2,
  "live_visitors": 1
}
```

### Sayfa Görüntüleme Kaydı

```text
POST /beytorstats/stats.php
```

Form verisi:

```text
action=pageview
page=/index.html
title=Ana Sayfa
referrer=direct
```

### Dashboard Verisi

```text
GET /beytorstats/stats.php?action=dashboard
```

Bu endpoint sadece admin oturumu varsa veri döndürür.

---

## Veritabanı Tabloları

Kurulum sırasında şu tablolar oluşturulur:

```text
visitors
pageviews
link_clicks
admin_users
```

Kısa açıklama:

- `visitors`: ziyaretçi tokenı, IP hash, user agent ve son aktiflik bilgisi
- `pageviews`: sayfa görüntüleme kayıtları
- `link_clicks`: dış bağlantı tıklamaları
- `admin_users`: dashboard kullanıcıları

---

## Güvenlik Notları

- `install.php` kurulumdan sonra otomatik silinir.
- Admin şifresi bcrypt ile hashlenir.
- IP adresleri ham olarak saklanmaz; hashlenerek kaydedilir.
- `db.php`, `.htpasswd` ve hassas dosyalar `.htaccess` ile korunur.
- Public GitHub reposuna gerçek şifre içeren `db.php` yüklemeyin.
- GitHub için örnek bağlantı dosyası kullanmak isterseniz `db.example.php` oluşturabilirsiniz.

---

## GitHub İçin Önerilen `.gitignore`

```gitignore
# Local config
db.php
.env

# Generated security files
.htaccess
.htpasswd

# Backups
_ss_backup/
backup/
backups/

# Logs
*.log

# OS / editor
.DS_Store
Thumbs.db
.vscode/
.idea/
```

---

## Yeni Bir Siteye Kurulum

1. `beytorstats/` klasörünü yeni sitenin kök dizinine yükleyin.
2. `https://siteadresiniz.com/beytorstats/install.php` adresini açın.
3. Veritabanı ve admin bilgilerini girin.
4. Kurulumu tamamlayın.
5. Dashboard içinden veya `/inject.php` adresinden script enjektörünü açın.
6. İstediğiniz HTML sayfalarına analytics scriptini ekleyin.

Her site için ayrı veritabanı kullanılması önerilir.

---

## Sorun Giderme

### Dashboard sayaçları geliyor ama sayfa istatistikleri boş

HTML sayfalarında şu scriptin bulunduğundan emin olun:

```html
<script src="/beytorstats/analytics.js"></script>
```

Tarayıcıda F12 → Network bölümünde şu istekleri kontrol edin:

```text
/beytorstats/stats.php?action=visit
/beytorstats/stats.php
```

### Eski script hâlâ görünüyor

Eski script:

```html
<script src="/sitestats/analytics.js"></script>
```

`inject.php` içindeki eski script kaldırma butonu ile seçili sayfalardan temizlenebilir.

### Dashboard verileri güncellenmiyor

Dashboard sayfasında `Ctrl + F5` yapın. Tarayıcı önbelleği eski `dashboard.php` veya `analytics.js` dosyasını gösterebilir.

---

## Lisans

Bu proje için lisans seçmediyseniz varsayılan olarak tüm hakları saklıdır.

Açık kaynak paylaşmak istiyorsanız GitHub reposuna bir `LICENSE` dosyası ekleyebilirsiniz.
