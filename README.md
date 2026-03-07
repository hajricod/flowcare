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
cd flow-care
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

## API Endpoints

### Public (no auth required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/branches` | List active branches |
| GET | `/api/branches/{id}/services` | List services for branch |
| GET | `/api/branches/{id}/services/{svcId}/slots` | List available slots |
| GET | `/api/branches/{id}/queue` | Live queue count |
| POST | `/api/auth/register` | Register new customer |

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
| GET | `/api/manage/customers/{id}/id-image` | Download customer ID image |

### Admin Only Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/audit-logs` | Full audit log view |
| GET | `/api/admin/audit-logs/export` | Export audit logs as CSV |
| PUT | `/api/admin/settings/retention` | Set soft-delete retention days |
| POST | `/api/admin/slots/cleanup` | Hard-delete expired soft-deleted slots |

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
