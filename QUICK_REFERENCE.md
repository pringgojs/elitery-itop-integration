# 🚀 API Key Quick Reference

## Generate API Key

```bash
php artisan apikey:generate --name="Your App Name"
```

## Using API Key in Request

### Header Format

```
X-API-Key: sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### cURL

```bash
curl -H "X-API-Key: YOUR_KEY" http://localhost:8000/api/v1/endpoint
```

### JavaScript Fetch

```javascript
fetch("http://localhost:8000/api/v1/endpoint", {
    headers: { "X-API-Key": "YOUR_KEY" },
});
```

### Python Requests

```python
requests.get('http://localhost:8000/api/v1/endpoint',
  headers={'X-API-Key': 'YOUR_KEY'}
)
```

## Manage via Tinker

```bash
php artisan tinker

# List all
>>> \App\Models\ApiKey::all();

# Create
>>> \App\Models\ApiKey::create(['key' => 'sk_xxx', 'name' => 'Test', 'is_active' => true]);

# Update
>>> \App\Models\ApiKey::find(1)->update(['is_active' => false]);

# Delete
>>> \App\Models\ApiKey::find(1)->delete();
```

## Manage via Database

```sql
SELECT * FROM api_keys;
UPDATE api_keys SET is_active = FALSE WHERE id = 1;
DELETE FROM api_keys WHERE id = 1;
```

---

**Protected Routes:** All `/api/v1/*` endpoints  
**Check Docs:** See `API_KEY_DOCUMENTATION.md` for detailed guide
