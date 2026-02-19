# Surf VCard — Nextcloud Contacts API

A Nextcloud app that exposes an **admin-only OCS API** for creating, reading, updating, and deleting VCard contacts in any user's address book.

## Use Case

When users accept federated sharing invitations between Nextcloud instances, this app can automatically add the remote user as a contact in the invitee's address book — including their Federated Cloud ID for easy sharing.

## Features

- **CRUD API** for contacts via the Nextcloud OCS endpoint
- Creates standards-compliant VCard 3.0 entries with `CLOUD` / `X-NEXTCLOUD-CLOUD-ID` fields
- Operates on any user's default address book (creates one if it doesn't exist)
- Admin-only access — all endpoints require Nextcloud admin credentials
- Supports pagination, per-user filtering, and JSON/XML responses

## Requirements

| Requirement | Version |
|---|---|
| Nextcloud | 28 – 32 |
| PHP | 8.0+ |

## Installation

```bash
# Copy the app into your Nextcloud apps directory
cp -r surf_vcard /var/www/nextcloud/apps/

# Set correct ownership
chown -R www-data:www-data /var/www/nextcloud/apps/surf_vcard

# Enable the app
sudo -u www-data php occ app:enable surf_vcard
```

## API Reference

**Base URL:** `https://{nextcloud-domain}/ocs/v2.php/apps/surf_vcard`

**Authentication:** HTTP Basic Auth with Nextcloud admin credentials

**Required Header:** `OCS-APIRequest: true`

> Add `?format=json` to any endpoint for JSON output instead of XML.

### Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/contacts` | List all contacts (supports `user_id`, `limit`, `offset` query params) |
| `POST` | `/api/v1/contacts` | Create a contact |
| `GET` | `/api/v1/contacts/{userId}` | List contacts for a specific user |
| `GET` | `/api/v1/contacts/{userId}/{uid}` | Get a single contact |
| `PUT` | `/api/v1/contacts/{userId}/{uid}` | Update a contact |
| `DELETE` | `/api/v1/contacts/{userId}/{uid}` | Delete a contact |

### Create a contact

```bash
curl -X POST -u 'admin:password' \
  -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' \
  -d '{
    "user_id": "alice",
    "displayname": "Bob Smith",
    "email": "bob@example.com",
    "cloud_id": "bob@remote.cloud",
    "organization": "Partner Org"
  }' \
  'https://nextcloud.example.com/ocs/v2.php/apps/surf_vcard/api/v1/contacts'
```

**Required fields:** `user_id`, `displayname`, `email`, `cloud_id`
**Optional fields:** `organization`

### List contacts

```bash
# All contacts
curl -u 'admin:password' \
  -H 'OCS-APIRequest: true' \
  'https://nextcloud.example.com/ocs/v2.php/apps/surf_vcard/api/v1/contacts'

# Filtered by user
curl -u 'admin:password' \
  -H 'OCS-APIRequest: true' \
  'https://nextcloud.example.com/ocs/v2.php/apps/surf_vcard/api/v1/contacts?user_id=alice'
```

### Update a contact

```bash
curl -X PUT -u 'admin:password' \
  -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' \
  -d '{
    "displayname": "Bob Smith Jr.",
    "email": "bob.jr@example.com",
    "cloud_id": "bob.jr@remote.cloud"
  }' \
  'https://nextcloud.example.com/ocs/v2.php/apps/surf_vcard/api/v1/contacts/alice/{uid}'
```

### Delete a contact

```bash
curl -X DELETE -u 'admin:password' \
  -H 'OCS-APIRequest: true' \
  'https://nextcloud.example.com/ocs/v2.php/apps/surf_vcard/api/v1/contacts/alice/{uid}'
```

### VCard Fields

Each created contact contains:

| Field | VCard Property | Description |
|---|---|---|
| Display name | `FN` | Full display name |
| Email | `EMAIL` | Email address |
| Cloud ID | `CLOUD`, `X-NEXTCLOUD-CLOUD-ID` | Federated Cloud ID for sharing |
| Organization | `ORG` | Organization name (optional) |

### Error Codes

| Code | Meaning |
|---|---|
| `200` | Success |
| `201` | Created |
| `400` | Bad request (missing required fields) |
| `401` | Not authenticated |
| `403` | Not an admin |
| `404` | Not found |
| `500` | Server error |

## Project Structure

```
surf_vcard/
├── appinfo/
│   ├── info.xml              # App metadata (id, version, dependencies)
│   └── routes.php            # OCS API route definitions
└── lib/
    ├── AppInfo/
    │   └── Application.php   # App bootstrap
    ├── Controller/
    │   └── OCSController.php # API controller (all endpoints)
    └── Service/
        └── VCardService.php  # VCard CRUD logic via CardDAV backend
```

## License

AGPL-3.0 — see [LICENSE](LICENSE) for details.
