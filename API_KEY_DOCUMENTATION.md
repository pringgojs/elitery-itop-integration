# API Key Authentication System

## 📋 Daftar Isi

- [Instalasi](#instalasi)
- [Cara Menggunakan](#cara-menggunakan)
- [Generate API Key](#generate-api-key)
- [Menggunakan API Key](#menggunakan-api-key)
- [Manajemen API Key](#manajemen-api-key)
- [Format Response](#format-response)

## 📦 Instalasi

### 1. Jalankan Migration

Middleware dan model telah dibuat. Jalankan perintah berikut untuk membuat tabel API keys:

```bash
php artisan migrate
```

Tabel `api_keys` akan dibuat dengan struktur:

- `id` - Primary Key
- `key` - Unique API Key (format: sk\_[random])
- `name` - Nama/deskripsi API Key
- `description` - Keterangan lengkap
- `last_used_at` - Timestamp penggunaan terakhir
- `is_active` - Status aktif/tidak aktif
- `created_at` - Waktu dibuat
- `updated_at` - Waktu diupdate

## 🔑 Generate API Key

### Via Command Line

Gunakan perintah artisan untuk generate API Key baru:

```bash
# Dengan parameter
php artisan apikey:generate --name="Mobile App" --description="API Key untuk mobile app"

# Interaktif (akan diminta input)
php artisan apikey:generate
```

**Contoh Output:**

```
✓ API Key generated successfully!

┌────┬──────────────┬──────────────────────────────────┬─────────────────────┐
│ ID │ Name         │ Key                              │ Created At          │
├────┼──────────────┼──────────────────────────────────┼─────────────────────┤
│ 1  │ Mobile App   │ sk_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5 │ 2026-03-02 10:30:00 │
└────┴──────────────┴──────────────────────────────────┴─────────────────────┘

Usage in request header:
X-API-Key: sk_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5
```

## 🚀 Menggunakan API Key

### Request Header

Setiap request ke API harus menyertakan header `X-API-Key` dengan nilai API Key:

```
X-API-Key: sk_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5
```

### Contoh dengan cURL

```bash
curl -X POST http://localhost:8000/api/v1/create-ticket \
  -H "X-API-Key: sk_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5" \
  -H "Content-Type: application/json" \
  -d '{"title":"New Ticket","description":"Ticket description"}'
```

### Contoh dengan JavaScript/Fetch

```javascript
fetch("http://localhost:8000/api/v1/create-ticket", {
    method: "POST",
    headers: {
        "X-API-Key": "sk_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5",
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        title: "New Ticket",
        description: "Ticket description",
    }),
})
    .then((response) => response.json())
    .then((data) => console.log(data));
```

### Contoh dengan Python/Requests

```python
import requests

headers = {
    'X-API-Key': 'sk_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5',
    'Content-Type': 'application/json'
}

response = requests.post(
    'http://localhost:8000/api/v1/create-ticket',
    json={'title': 'New Ticket', 'description': 'Ticket description'},
    headers=headers
)

print(response.json())
```

## 📊 Manajemen API Key

### Via Tinker (CLI Interaktif)

```bash
php artisan tinker

# Generate API Key langsung
>>> $apiKey = \App\Models\ApiKey::create([
    'key' => 'sk_' . \Illuminate\Support\Str::random(32),
    'name' => 'Integration Server',
    'description' => 'API Key for server integration',
]);

# Lihat semua API Key
>>> \App\Models\ApiKey::all();

# Cari API Key berdasarkan nama
>>> \App\Models\ApiKey::where('name', 'Mobile App')->first();

# Nonaktifkan API Key
>>> $apiKey = \App\Models\ApiKey::find(1);
>>> $apiKey->update(['is_active' => false]);

# Aktifkan kembali API Key
>>> $apiKey->update(['is_active' => true]);

# Lihat last used timestamp
>>> \App\Models\ApiKey::find(1)->last_used_at;
```

### Via Database Query

```sql
-- Lihat semua API Key
SELECT id, name, key, is_active, last_used_at, created_at FROM api_keys;

-- Lihat API Key yang sudah digunakan
SELECT * FROM api_keys WHERE last_used_at IS NOT NULL ORDER BY last_used_at DESC;

-- Nonaktifkan API Key tertentu
UPDATE api_keys SET is_active = FALSE WHERE id = 1;

-- Hapus API Key (opsional)
DELETE FROM api_keys WHERE id = 1;
```

## 📨 Format Response

### ✅ Request Berhasil

Jika API Key valid, request akan diproses normal:

```json
{
  "status": "success",
  "data": { ... }
}
```

### ❌ Missing API Key

Response jika header `X-API-Key` tidak dikirim:

```json
{
    "message": "API Key is required",
    "error": "missing_api_key"
}
```

**HTTP Status:** 401 Unauthorized

### ❌ Invalid API Key

Response jika API Key tidak ditemukan atau tidak aktif:

```json
{
    "message": "Invalid API Key",
    "error": "invalid_api_key"
}
```

**HTTP Status:** 401 Unauthorized

## 🔐 Security Best Practices

1. **Jangan share API Key** - Simpan API Key di tempat yang aman
2. **Gunakan environment variables** - Jangan hard-code API Key di code
3. **Rotate regularly** - Buat API Key baru dan nonaktifkan yang lama secara berkala
4. **Monitor usage** - Cek `last_used_at` untuk deteksi penggunaan mencurigakan
5. **Limit scope** - Pertimbangkan untuk menambah kolom `allowed_endpoints` jika diperlukan
6. **Use HTTPS** - Selalu gunakan HTTPS untuk mengirim API Key

## 🛠️ Troubleshooting

### Issue: "API Key is required"

**Solusi:** Pastikan header `X-API-Key` sudah dikirim dalam request

### Issue: "Invalid API Key"

**Solusi:**

- Pastikan API Key sudah di-generate
- Pastikan API Key berstatus aktif (`is_active = true`)
- Pastikan tidak ada typo di API Key

### Issue: API Key tidak ter-track penggunaannya

**Solusi:** Pastikan database sudah di-migrate dengan benar

---

**Dibuat:** 2 Maret 2026  
**Version:** 1.0.0
