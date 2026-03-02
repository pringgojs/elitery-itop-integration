# 📘 README - API Itop Integrations

Aplikasi ini berfungsi sebagai _bridge_ antara dua instance iTop:

- **External** – sistem yang mengirim notifikasi ke luar.
- **Internal (Elitery)** – sistem yang menerima dan memproses data.

Semua komunikasi berlangsung melalui webhook API (`/api/v1/*`).

---

## 🔧 SOP Utama

1. **Sinkronisasi data**
    - ✨ _Itop external → internal_: attachment, tiket, private log.
    - 🔁 _Itop internal → external_: hanya tiket yang berasal dari external; atribut yang disinkronkan adalah status, attachment, dan private log.
2. **Private log** selalu tersimpan sebagai attachment.
3. **Agent & org** ditentukan di masing‑masing sistem dan tidak saling bertukar.
4. **Perubahan status** di external disinkronkan ke internal; sebaliknya tidak.
5. **Agents tidak tersinkron antar kedua iTop.**

---

## ✅ Persiapan Sistem

Sebelum menjalankan integrasi:

- Pastikan kedua instance iTop (internal & external) memiliki:
    - Akun dengan profil **REST Services User**
    - Konfigurasi default `agent_id` dan `org_id`
- PHP **8.2** terpasang di lingkungan server.

---

## 📡 Trigger & Webhook

Proses notifikasi bergantung pada trigger iTop dan endpoint API berikut:

### 🔁 Itop External

#### Notifikasi yang tersedia

1. Ticket Update (on object update)
2. Ticket Created (on object creation)
3. Attachment Update (on object update)
4. Attachment Delete (on object deletion)

#### Webhook & payload

| Trigger                  | Endpoint                    | Payload (JSON)                                                                                                                                      | Notes                 |
| ------------------------ | --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------- |
| Ticket Created           | `/api/v1/create-ticket`     | `{ "event":"ticket.create", ... }`                                                                                                                  | lihat contoh di bawah |
| Ticket Update            | `/api/v1/update-ticket`     | `{ "class":"$this->finalclass$", "id":"$this->id$"}`                                                                                                |                       |
| Attachment Update/Delete | `/api/v1/update-attachment` | `{ "event":"attachment.update", "source":"external", "attachment_id":"$this->id$", "item_class":"$this->item_class$", "item_id":"$this->item_id$"}` |                       |

```json
// Contoh payload ticket created
{
    "event": "ticket.create",
    "class": "$this->finalclass$",
    "id": "$this->id$",
    "ref": "$this->ref$",
    "title": "$this->title$",
    "description": "$this->description$",
    "status": "$this->status$",
    "caller_id": "$this->caller_id->name$",
    "org_id": "$this->org_id->name$",
    "team_id": "$this->team_id->name$",
    "operator_id": "$this->agent_id->name$",
    "priority": "$this->priority$"
}
```

---

### 🔁 Itop Internal (Elitery)

#### Notifikasi yang tersedia

1. Ticket Approved (state change)
2. Ticket Assigned
3. Ticket Closed
4. Ticket Pending
5. Ticket Escalated TTO
6. Ticket Escalated TTR
7. Ticket Not Approved
8. Pending
9. Ticket Rejected
10. Ticket Resolved
11. Ticket Waiting for Approval
12. Ticket Validated
13. Attachment Delete
14. Attachment Update
15. Private Log Update

#### Webhook & payload

| Trigger                  | Endpoint                            | Payload (JSON)                                                                                                                                     | Notes                                                                                                                                                    |
| ------------------------ | ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Attachment Update/Delete | `/api/v1/update-attachment`         | `{ "event":"attachment.update", "source":"elitery", "attachment_id":"$this->id$", "item_class":"$this->item_class$", "item_id":"$this->item_id$"}` |                                                                                                                                                          |
| Private Log Update       | `/api/v1/ticket-update-private-log` | `{ "event":"update private log", "id":"$this->id$"}`                                                                                               |                                                                                                                                                          |
| State Change             | `/api/v1/ticket-state-change`       | `{ "event":"ticket assigned", "id":"$this->id$"}`                                                                                                  | berlaku untuk berbagai status: Assigned, Pending, Escalated TTO/TTR, Resolved, Closed, Waiting For Approval, Approved, Rejected, Validated, Not Approved |

```json
// contoh payload internal state change
{
    "event": "ticket assigned",
    "id": "$this->id$"
}
```

---

## 📝 Catatan Lain

- Semua webhook dikirim ke prefix `/api/v1/*` yang dilindungi oleh API key.
- Pastikan setiap request mencantumkan header `X-API-Key`.
- Detail payload disesuaikan dengan kebutuhan trigger masing‑masing iTop.

---

_Dokumen ini terakhir diperbarui pada 2 Maret 2026._
