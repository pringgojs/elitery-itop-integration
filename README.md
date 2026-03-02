## Tentang Aplikasi

Jembatan penghubung antara dua instance Itop: External dan Internal Elitery.

## SOP

- Itop external hanya sync ke internal: attachment, form ticket, private log.
- Itop internal sync ke external hanya tiket yg sudah dikirim oleh external. Adapaun attribute nya: state, attachment, private log.
- Private log berupa gambar dilampirkan dalam bentuk attachment di tiket.
- Ketika sync antar Itop sudah ditentukan masing-masing agent_id dan org_id.
- Status yg dirubah di external, di sync ke internal. Tapi sebaliknya.
- Agent tidak sync antar itop.

## Persiapan

- Itop Internal dan Itop External:
    - username, password dan dengan profile: REST Services User
    - setting agent_id dan org_id sbg default masing-masing.
- php 8.2

## Trigger Itop external

### Notifikasi

1. Ticket Update
2. Ticket Created
3. Attachment Update
4. Attachment Delete

### Webhook External

1. Webhook Ticket Created
    - payload: Payload
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
    - path: /api/v1/create-ticket
    - Trigger: ticket created
2. Ticket Update
    - payload:
      {
      "class": "$this->finalclass$",
      "id": "$this->id$"
      }
    - path: /api/v1/update-ticket
    - trigger: ticket update
3. Attachment Update / Delete
    - payload:
      {
      "event": "attachment.update",
      "source": "external",
      "attachment_id": "$this->id$",
      "item_class":"$this->item_class$",
      "item_id": "$this->item_id$"
      }
    - path: /api/v1/update-attachment
    - trigger: attachment update, attachment delete

## Trigger Itop Internal

### Notifikasi

1. Ticket Approved
2. Ticket Assigned
3. Ticket Closed
4. Ticket Pending
5. Ticket Escalated TTO
6. Ticket Escataled TTR
7. Ticket Not Approved
8. Pending
9. Ticket Rejected
10. Ticket Resolved
11. Ticket waiting for approval
12. Ticket Validated
13. Attachment Delete
14. Attachment Update
15. Private Log Update

### Webhook Internal

1. Attachment Update

- payload: {
  "event": "attachment.update",
  "source": "elitery",
  "attachment_id": "$this->id$",
  "item_class":"$this->item_class$",
  "item_id": "$this->item_id$"
  }
- path: /api/v1/update-attachment
- trigger: Attachment Update, Attachment Delete

2. Update Private Log

- payload: {
  "event": "update private log",
  "id": "$this->id$"
  }
- path: /api/v1/ticket-update-private-log
- trigger: Private Log Update

3. Update State

- payload: {
  "event": "ticket assigned ",
  "id": "$this->id$"
  }
- path: /api/v1/ticket-state-change
- trigger: Assigned
  Pending
  Escalated TTO
  Escalated TTR
  Resolved
  Closed
  Waiting For Approval
  Approved
  Rejected
  Validated
  Not Approved
