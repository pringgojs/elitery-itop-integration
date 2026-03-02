# ✅ API Key Authentication System - Setup Summary

## 📋 Files yang Telah Dibuat/Dimodifikasi

### 1. **Database Migration**

📂 `database/migrations/2026_03_02_000000_create_api_keys_table.php`

- Membuat tabel `api_keys` dengan struktur lengkap
- Kolom: id, key (unique), name, description, last_used_at, is_active, timestamps

### 2. **Model**

📂 `app/Models/ApiKey.php`

- Model untuk tabel api_keys
- Method `updateLastUsed()` untuk track penggunaan API Key
- Auto-casting untuk boolean dan datetime

### 3. **Middleware**

📂 `app/Http/Middleware/ValidateApiKey.php`

- Validasi API Key dari header `X-API-Key`
- Cek status aktif/tidak aktif
- Update timestamp last_used_at
- Return error 401 jika API Key tidak valid

### 4. **Middleware Registration**

📂 `app/Http/Kernel.php`

- Registrasi middleware dengan alias `api_key`
- Dapat digunakan di route dengan `middleware('api_key')`

### 5. **Routes Protection**

📂 `routes/api.php`

- Semua route di prefix `v1` sudah dilindungi dengan `middleware('api_key')`
- Setiap request harus menyertakan header `X-API-Key`

### 6. **Artisan Command**

📂 `app/Console/Commands/GenerateApiKey.php`

- Command untuk generate API Key baru
- Usage: `php artisan apikey:generate`
- Support dengan parameter: `--name` dan `--description`

### 7. **Documentation**

📂 `API_KEY_DOCUMENTATION.md`

- Dokumentasi lengkap tentang penggunaan API Key
- Contoh cURL, JavaScript, Python
- Manajemen API Key via Tinker dan Database

---

## 🚀 Langkah-Langkah Selanjutnya

### Step 1: Jalankan Migration

```bash
php artisan migrate
```

Perintah ini akan membuat tabel `api_keys` di database.

### Step 2: Generate API Key Pertama

```bash
# Cara 1: Interaktif
php artisan apikey:generate

# Cara 2: Dengan parameter
php artisan apikey:generate --name="Integration Server" --description="API Key untuk integration server"
```

### Step 3: Test API dengan API Key

Gunakan salah satu dari contoh berikut:

**cURL:**

```bash
curl -X POST http://localhost:8000/api/v1/create-ticket \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","description":"Test ticket"}'
```

**Postman/Insomnia:**

- Method: POST
- URL: `http://localhost:8000/api/v1/create-ticket`
- Header: `X-API-Key: YOUR_API_KEY_HERE`
- Body: JSON sesuai kebutuhan

---

## 🏗️ Arsitektur

```
Request dengan header X-API-Key
        ↓
ValidateApiKey Middleware
        ↓
Validasi (cek ada/tidak, cek status)
        ↓
Update last_used_at timestamp
        ↓
Lanjut ke Controller
```

---

## 📊 Database Schema

| Column       | Type      | Nullable | Default | Keterangan                        |
| ------------ | --------- | -------- | ------- | --------------------------------- |
| id           | bigint    | NO       | -       | Primary Key                       |
| key          | varchar   | NO       | -       | Unique API Key (format: sk_xxxxx) |
| name         | varchar   | YES      | -       | Nama/Deskripsi API Key            |
| description  | text      | YES      | -       | Keterangan lengkap                |
| last_used_at | timestamp | YES      | NULL    | Terakhir digunakan                |
| is_active    | boolean   | NO       | true    | Status aktif                      |
| created_at   | timestamp | YES      | -       | Waktu dibuat                      |
| updated_at   | timestamp | YES      | -       | Waktu diupdate                    |

---

## 🔒 Security Features

✅ API Key disimpan di database  
✅ API Key harus dikirim di header `X-API-Key`  
✅ Middleware validasi untuk setiap request  
✅ Status aktif/tidak aktif dapat di-control  
✅ Tracking penggunaan via `last_used_at`  
✅ Response error yang clear (missing vs invalid)

---

## 📝 Response Examples

### ✅ Request Sukses

```json
{
  "success": true,
  "data": { ... }
}
```

**HTTP Status:** 200 OK

### ❌ Missing API Key

```json
{
    "message": "API Key is required",
    "error": "missing_api_key"
}
```

**HTTP Status:** 401 Unauthorized

### ❌ Invalid API Key

```json
{
    "message": "Invalid API Key",
    "error": "invalid_api_key"
}
```

**HTTP Status:** 401 Unauthorized

---

## 🛠️ Useful Commands

```bash
# Generate API Key baru
php artisan apikey:generate

# Akses Tinker untuk manage API Key
php artisan tinker

# Di dalam Tinker:
# Lihat semua API Key
>>> \App\Models\ApiKey::all();

# Generate API Key langsung
>>> $key = \App\Models\ApiKey::create([
    'key' => 'sk_' . \Illuminate\Support\Str::random(32),
    'name' => 'Test Key',
    'is_active' => true
]);

# Nonaktifkan API Key
>>> \App\Models\ApiKey::find(1)->update(['is_active' => false]);

# Hapus API Key
>>> \App\Models\ApiKey::find(1)->delete();
```

---

## ✨ Fitur yang Dapat Ditambahkan di Masa Depan

- [ ] Rate limiting per API Key
- [ ] Allowed IP address whitelist
- [ ] Allowed endpoints restriction
- [ ] Webhook untuk monitoring penggunaan
- [ ] API Key expiration
- [ ] Admin dashboard untuk manage API Keys
- [ ] Audit log untuk setiap request

---

**Status:** ✅ Production Ready  
**Version:** 1.0.0  
**Created:** 2 Maret 2026
