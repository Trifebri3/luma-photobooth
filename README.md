# Luma Photobooth
> Digital Multi-Event Photobooth SaaS & Self-Service Kiosk Platform  
> **Developed by YOTA INOVASI NUSANTARA**

---

## 🌟 Tentang Produk
**Luma Photobooth** adalah platform *Multi-Event Photobooth Management Software-as-a-Service (SaaS)* modern berbasis web. Memungkinkan penyelenggara acara (organizer) membuat dan mengelola banyak event, merancang template frame melalui visual editor, mengunci perangkat booth di lokasi acara (kiosk mode), dan membiarkan pengunjung mengambil foto secara mandiri (*self-service*) tanpa operator.

Foto final dikomposisikan secara otomatis dan didistribusikan secara privat ke nomor WhatsApp pengunjung dengan sistem keamanan anti-bocor (*unique cryptographic token*).

---

## ✨ Fitur Utama
1. **Self-Service Kiosk Mode (`/booth/{slug}`)**:
   - Pairing perangkat booth dengan kode aktivasi 6-karakter.
   - Live camera preview WebRTC (dukungan webcam USB PC & kamera depan/belakang tablet/HP).
   - Countdown visual besar dengan efek flash layar dan suara beep.
   - Pilihan template frame (Strip, Polaroid, Duo landscape).
   - Fitur retake per slot foto.
   - Client-side Canvas rendering resolusi tinggi.
   - QR code instan di layar untuk scan langsung di lokasi + timer auto-reset 15 detik.

2. **Sistem Distribusi Foto Anti-Bocor (`/p/{token}`)**:
   - Penyimpanan terisolasi di private storage (`storage/app/private/`).
   - Akses foto hanya melalui token acak terenkripsi (tidak ada enumerasi ID).
   - Pengiriman instan ke WhatsApp melalui Fonnte API Gateway.
   - Halaman galeri privat mobile-first dengan tombol unduh foto HD dan share media sosial.

3. **Visual Frame Studio (`/organizer/events/{id}/studio`)**:
   - Upload overlay frame PNG transparan.
   - Pengaturan koordinat slot foto visual ($X, Y, W, H$).
   - Pratinjau proporsional langsung di browser.

4. **Dashboard Organizer & Multi-Event Management**:
   - Kelola banyak acara tanpa saling tercampur.
   - Atur batas kuota sesi per pengunjung, timer countdown, dan retake.
   - Riwayat pengunjung dan pengiriman WhatsApp.

5. **Portal Super Admin (`/admin/login`)**:
   - Manajemen seluruh organizer dan monitoring event platform.
   - Konfigurasi WhatsApp API Gateway terpusat.

---

## 🛠️ Tech Stack
- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: Vue 3 + Inertia.js
- **Styling**: Tailwind CSS (Nuansa Putih Bersih dengan Aksen Teal)
- **Database**: MySQL 8 / MariaDB
- **Camera Engine**: WebRTC `MediaDevices API`
- **Frame Compositor**: HTML5 Canvas API
- **WhatsApp Gateway**: Fonnte API Gateway
- **Auth**: Laravel Breeze + Google OAuth (Laravel Socialite)

---

## 🚀 Panduan Instalasi Lokal

### 1. Clone & Dependencies
```bash
git clone https://github.com/Trifebri3/luma-photobooth.git
cd luma-photobooth

composer install
npm install --legacy-peer-deps
```

### 2. Konfigurasi Environment
Salin file `.env.example` ke `.env` dan sesuaikan koneksi database MySQL:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=luma
DB_USERNAME=root
DB_PASSWORD=

FONNTE_TOKEN=Eeo4Pf43HnDcaeSQmSua
FONNTE_SENDER=6285862319524
```

### 3. Migrasi Database & Seeder Demo
```bash
php artisan key:generate
php artisan migrate --seed
```

### 4. Build Frontend Assets & Run
```bash
npm run build
php artisan serve
```

---

## 🔐 Kredensial Demo Awal
- **Super Admin**: `admin@luma.test` / `password` ([`/admin/login`](http://localhost:8000/admin/login))
- **Organizer**: `organizer@luma.test` / `password` ([`/login`](http://localhost:8000/login))
- **Kode Aktivasi Kiosk**: `LUMA26`
- **Demo Booth Event**: `http://localhost:8000/booth/festival-kreativitas-2026`

---

## 🏢 Lisensi & Pengembang
Dikembangkan oleh **YOTA INOVASI NUSANTARA**. Seluruh hak cipta dilindungi.
