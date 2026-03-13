# FlowCare – Queue & Appointment Booking System

A RESTful API backend built with **Laravel 12** (PHP 8.3) and **PostgreSQL** for managing services, queues and appointments.

## Features

- **HTTP Basic Authentication** (custom middleware, no tokens)
- **Role-based access control**: ADMIN, BRANCH_MANAGER, STAFF, CUSTOMER
- **Branch management** with service types and time slots
- **Appointment booking** with queue number assignment
- **File uploads**: patient ID images and appointment attachments
- **Soft-deleted slots** with configurable retention cleanup
- **Audit logging** with CSV export
- **Live queue count** per branch

## Tech Stack

| Component | Version |
|-----------|---------|
| PHP | 8.3 |
| Laravel | 12.x |
| PostgreSQL | 16 |
| Docker | 24+ |

## Quick Start

### Prerequisites
- Docker & Docker Compose
- PHP 8.3+ and Composer (for local development)

### 1. Clone and configure

```bash
git clone <repo-url>
cd flowcare
cp .env.example .env
php artisan key:generate
```

### 2. Run with Docker

```bash
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

### 3. Run locally (with PostgreSQL running)

```bash
composer install
php artisan migrate --seed
php artisan serve
```

The API will be available at `http://localhost:8000`.

## Authentication

All protected endpoints use **HTTP Basic Authentication**.

```
Authorization: Basic base64(username:password)
```

Default seeded credentials:

| Role | Username | Password |
|------|----------|----------|
| ADMIN | `admin` | `admin123` |
| BRANCH_MANAGER | `manager_muscat` | `manager123` |
| STAFF | `staff_muscat_1` | `staff123` |
| CUSTOMER | `customer_1` | `customer123` |

## API Documentation (Scramble)

This project uses [`dedoc/scramble`](https://github.com/dedoc/scramble) for OpenAPI documentation and interactive endpoint testing.

### Open docs UI

- URL: `/docs/api`
- OpenAPI JSON: `/docs/api.json`

When running locally via Sail, open:

- `http://localhost/docs/api`
- `http://localhost/docs/api.json`

### Test endpoints with Try It

1. Open `/docs/api`.
2. Expand any endpoint and click `Try It`.
3. For protected endpoints, add header:
	`Authorization: Basic base64(username:password)`

Example:

```bash
echo -n 'admin:admin123' | base64
```

Then use:

```text
Authorization: Basic <encoded-value>
```

### Export OpenAPI file

```bash
./vendor/bin/sail artisan scramble:export
```

Optional custom output path:

```bash
./vendor/bin/sail artisan scramble:export --path=storage/app/public/openapi.json
```

### Analyze docs generation

```bash
./vendor/bin/sail artisan scramble:analyze
```

## API Endpoints

### Public (no auth required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/branches` | List active branches |
| GET | `/api/branches/{id}/services` | List services for branch |
| GET | `/api/branches/{id}/services/{svcId}/slots` | List available slots |
| GET | `/api/branches/{id}/queue` | Live queue count |
| POST | `/api/auth/register` | Register new customer (ID image required) |

### Auth Required

| Method | Endpoint | Roles | Description |
|--------|----------|-------|-------------|
| POST | `/api/auth/login` | All | Login (returns user info) |
| GET | `/api/auth/me` | All | Get current user |

### Customer Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/appointments` | Book appointment |
| GET | `/api/appointments` | List my appointments |
| GET | `/api/appointments/{id}` | Get appointment details |
| DELETE | `/api/appointments/{id}` | Cancel appointment |
| PUT | `/api/appointments/{id}/reschedule` | Reschedule appointment |
| GET | `/api/appointments/{id}/attachment` | Download attachment |

### Staff / Manager / Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/manage/appointments` | List appointments (scoped by role) |
| PUT | `/api/manage/appointments/{id}/status` | Update appointment status |
| GET | `/api/manage/audit-logs` | View audit logs (scoped by role) |

### Manager / Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/manage/slots` | Create slot(s) |
| PUT | `/api/manage/slots/{id}` | Update slot |
| DELETE | `/api/manage/slots/{id}` | Soft-delete slot |
| GET | `/api/manage/staff` | List staff |
| PUT | `/api/manage/staff/{id}/assign` | Assign staff to branch/services |
| GET | `/api/manage/customers` | List customers |
| GET | `/api/manage/customers/{id}` | Get customer detail |

### Admin File Access

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/customers/{id}/id-image` | Download customer ID image |

### Admin Only Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/audit-logs` | Full audit log view |
| GET | `/api/admin/audit-logs/export` | Export audit logs as CSV |
| GET | `/api/admin/customers/{id}/id-image` | Download customer ID image |
| GET | `/api/admin/slots/trashed` | List soft-deleted slots |
| PUT | `/api/admin/settings/retention` | Set soft-delete retention days |
| POST | `/api/admin/slots/cleanup` | Hard-delete expired soft-deleted slots |

## Example API Usage (curl)

Use these shell variables once, then run any command below:

```bash
BASE_URL="http://localhost/api"

ADMIN_AUTH="$(printf 'admin:admin123' | base64)"
MANAGER_AUTH="$(printf 'manager_muscat:manager123' | base64)"
STAFF_AUTH="$(printf 'staff_muscat_1:staff123' | base64)"
CUSTOMER_AUTH="$(printf 'customer_1:customer123' | base64)"
```

### Public Endpoints

```bash
# GET /api/branches
curl "$BASE_URL/branches"

# GET /api/branches/{branch}/services
curl "$BASE_URL/branches/br_muscat_001/services"

# GET /api/branches/{branch}/services/{service}/slots
curl "$BASE_URL/branches/br_muscat_001/services/svc_mus_001/slots?date=2026-03-20&page=1&size=10"

# GET /api/branches/{branch}/queue
curl "$BASE_URL/branches/br_muscat_001/queue"

# POST /api/auth/register
curl -X POST "$BASE_URL/auth/register" \
	-F "username=new_customer" \
	-F "email=new_customer@example.com" \
	-F "password=customer123" \
	-F "full_name=New Customer" \
	-F "phone=+96890000000" \
	-F "id_image=@/path/to/id-image.jpg"
```

### Auth Endpoints

```bash
# POST /api/auth/login
curl -X POST "$BASE_URL/auth/login" \
	-H "Authorization: Basic $CUSTOMER_AUTH"

# GET /api/auth/me
curl "$BASE_URL/auth/me" \
	-H "Authorization: Basic $CUSTOMER_AUTH"
```

### Customer Endpoints

```bash
# POST /api/appointments
curl -X POST "$BASE_URL/appointments" \
	-H "Authorization: Basic $CUSTOMER_AUTH" \
	-F "slot_id=slot_mus_001" \
	-F "notes=Please call me on arrival" \
	-F "attachment=@/path/to/referral.pdf"

# GET /api/appointments
curl "$BASE_URL/appointments?page=1&size=10" \
	-H "Authorization: Basic $CUSTOMER_AUTH"

# GET /api/appointments/{id}
curl "$BASE_URL/appointments/appt_001" \
	-H "Authorization: Basic $CUSTOMER_AUTH"

# DELETE /api/appointments/{id}
curl -X DELETE "$BASE_URL/appointments/appt_001" \
	-H "Authorization: Basic $CUSTOMER_AUTH"

# PUT /api/appointments/{id}/reschedule
curl -X PUT "$BASE_URL/appointments/appt_001/reschedule" \
	-H "Authorization: Basic $CUSTOMER_AUTH" \
	-H "Content-Type: application/json" \
	-d '{"new_slot_id":"slot_mus_005"}'

# GET /api/appointments/{id}/attachment
curl "$BASE_URL/appointments/appt_001/attachment" \
	-H "Authorization: Basic $CUSTOMER_AUTH" \
	-o appointment-attachment.bin
```

### Staff / Manager / Admin Endpoints

```bash
# GET /api/manage/appointments
curl "$BASE_URL/manage/appointments?page=1&size=10&status=BOOKED" \
	-H "Authorization: Basic $STAFF_AUTH"

# PUT /api/manage/appointments/{id}/status
curl -X PUT "$BASE_URL/manage/appointments/appt_001/status" \
	-H "Authorization: Basic $STAFF_AUTH" \
	-H "Content-Type: application/json" \
	-d '{"status":"CHECKED_IN","notes":"Arrived on time"}'

# GET /api/manage/audit-logs
curl "$BASE_URL/manage/audit-logs?page=1&size=20" \
	-H "Authorization: Basic $MANAGER_AUTH"
```

### Manager / Admin Endpoints

```bash
# POST /api/manage/slots (single)
curl -X POST "$BASE_URL/manage/slots" \
	-H "Authorization: Basic $MANAGER_AUTH" \
	-H "Content-Type: application/json" \
	-d '{
		"branch_id":"br_muscat_001",
		"service_type_id":"svc_mus_001",
		"staff_id":"usr_staff_001",
		"start_at":"2026-03-20T08:00:00+04:00",
		"end_at":"2026-03-20T08:30:00+04:00"
	}'

# POST /api/manage/slots (bulk)
curl -X POST "$BASE_URL/manage/slots" \
	-H "Authorization: Basic $MANAGER_AUTH" \
	-H "Content-Type: application/json" \
	-d '{
		"slots":[
			{
				"branch_id":"br_muscat_001",
				"service_type_id":"svc_mus_001",
				"staff_id":"usr_staff_001",
				"start_at":"2026-03-21T08:00:00+04:00",
				"end_at":"2026-03-21T08:30:00+04:00"
			},
			{
				"branch_id":"br_muscat_001",
				"service_type_id":"svc_mus_001",
				"staff_id":"usr_staff_001",
				"start_at":"2026-03-21T09:00:00+04:00",
				"end_at":"2026-03-21T09:30:00+04:00"
			}
		]
	}'

# PUT /api/manage/slots/{id}
curl -X PUT "$BASE_URL/manage/slots/slot_mus_001" \
	-H "Authorization: Basic $MANAGER_AUTH" \
	-H "Content-Type: application/json" \
	-d '{"is_active":true,"end_at":"2026-03-20T08:45:00+04:00"}'

# DELETE /api/manage/slots/{id}
curl -X DELETE "$BASE_URL/manage/slots/slot_mus_001" \
	-H "Authorization: Basic $MANAGER_AUTH"

# GET /api/manage/staff
curl "$BASE_URL/manage/staff?page=1&size=10&term=muscat" \
	-H "Authorization: Basic $MANAGER_AUTH"

# PUT /api/manage/staff/{id}/assign
curl -X PUT "$BASE_URL/manage/staff/usr_staff_001/assign" \
	-H "Authorization: Basic $MANAGER_AUTH" \
	-H "Content-Type: application/json" \
	-d '{
		"branch_id":"br_muscat_001",
		"service_type_ids":["svc_mus_001","svc_mus_002"]
	}'

# GET /api/manage/customers
curl "$BASE_URL/manage/customers?page=1&size=10&term=customer" \
	-H "Authorization: Basic $MANAGER_AUTH"

# GET /api/manage/customers/{id}
curl "$BASE_URL/manage/customers/usr_cust_001" \
	-H "Authorization: Basic $MANAGER_AUTH"
```

### Admin-only Endpoints

```bash
# GET /api/admin/audit-logs
curl "$BASE_URL/admin/audit-logs?page=1&size=20" \
	-H "Authorization: Basic $ADMIN_AUTH"

# GET /api/admin/audit-logs/export
curl "$BASE_URL/admin/audit-logs/export" \
	-H "Authorization: Basic $ADMIN_AUTH" \
	-o audit-logs.csv

# GET /api/admin/customers/{id}/id-image
curl "$BASE_URL/admin/customers/usr_cust_001/id-image" \
	-H "Authorization: Basic $ADMIN_AUTH" \
	-o customer-id-image.bin

# GET /api/admin/slots/trashed
curl "$BASE_URL/admin/slots/trashed?page=1&size=20" \
	-H "Authorization: Basic $ADMIN_AUTH"

# PUT /api/admin/settings/retention
curl -X PUT "$BASE_URL/admin/settings/retention" \
	-H "Authorization: Basic $ADMIN_AUTH" \
	-H "Content-Type: application/json" \
	-d '{"days":30}'

# POST /api/admin/slots/cleanup
curl -X POST "$BASE_URL/admin/slots/cleanup" \
	-H "Authorization: Basic $ADMIN_AUTH"
```

## Database Schema

```
users           – id (uuid), username, email, password, full_name, phone, role, branch_id, id_image_path, is_active
branches        – id (uuid), name, city, address, timezone, is_active
service_types   – id (uuid), branch_id, name, description, duration_minutes, is_active
slots           – id (uuid), branch_id, service_type_id, staff_id, start_at, end_at, capacity, is_active, deleted_at
appointments    – id (uuid), customer_id, branch_id, service_type_id, slot_id, staff_id, status, notes, attachment_path, queue_number
audit_logs      – id (uuid), actor_id, actor_role, action_type, entity_type, entity_id, metadata, branch_id, created_at
settings        – id, key, value
staff_service_types – staff_id, service_type_id
```

## Roles & Permissions

| Action | CUSTOMER | STAFF | BRANCH_MANAGER | ADMIN |
|--------|----------|-------|----------------|-------|
| Book appointment | ✅ | ❌ | ❌ | ❌ |
| View own appointments | ✅ | ❌ | ❌ | ❌ |
| Update appointment status | ❌ | ✅ | ✅ | ✅ |
| Manage slots | ❌ | ❌ | ✅ | ✅ |
| Manage staff | ❌ | ❌ | ✅ | ✅ |
| View audit logs | ❌ | ✅ | ✅ | ✅ |
| Export audit logs | ❌ | ❌ | ❌ | ✅ |
| System settings | ❌ | ❌ | ❌ | ✅ |

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_KEY` | Laravel app key | Generate with `php artisan key:generate` |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name | `flowcare` |
| `DB_USERNAME` | Database user | `flowcare` |
| `DB_PASSWORD` | Database password | `secret` |

## File Storage

Files are stored on the local disk under `storage/app/`:

- Customer ID images: `uploads/customers/{uuid}.{ext}`
- Appointment attachments: `uploads/appointments/{uuid}.{ext}`

## License

MIT
