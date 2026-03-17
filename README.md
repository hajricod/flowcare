# FlowCare – Queue & Appointment Booking System

A RESTful API backend built with **Laravel 12** (PHP 8.3) and **PostgreSQL** for managing services, queues and appointments.

## Features

- **HTTP Basic Authentication** (custom middleware, no tokens)
- **Role-based access control**: ADMIN, BRANCH_MANAGER, STAFF, CUSTOMER
- **Branch management** with service types and time slots
- **Appointment booking** with queue number assignment
- **Rate limiting for customers**: daily booking cap and daily reschedule cap
- **File uploads**: customer ID images and appointment attachments
- **Soft-deleted slots** with configurable retention cleanup
- **Background scheduling service** for automatic retention cleanup
- **Audit logging** with CSV export
- **Live queue count** per branch

## Tech Stack

| Component | Version |
|-----------|---------|
| PHP | 8.3 |
| Laravel | 12.x |
| PostgreSQL | 18 (Docker Compose) |
| Docker | 24+ |

## Quick Start

### Prerequisites
- Docker & Docker Compose
- PHP 8.3+ and Composer (for local development)
- Redis 7+ (for non-Docker local run with default settings)

### 1. Clone and configure

```bash
git clone https://github.com/hajricod/flowcare.git
cd flowcare
cp .env.example .env
```

## Database Schema

Schema is managed through Laravel migrations in `database/migrations`.

Core tables:

- `users` (roles: ADMIN, BRANCH_MANAGER, STAFF, CUSTOMER)
- `branches`
- `service_types`
- `staff_service_types` (pivot between staff users and service types)
- `slots` (bookable times linked to branch/service/staff)
- `appointments` (linked to customer, branch, slot, service type)
- `audit_logs` (action trail)
- `settings` (runtime app limits and retention settings)

Supporting Laravel infrastructure tables:

- `jobs`
- `cache`
- `personal_access_tokens`

Key relationships:

- One branch has many staff users and many slots.
- One service type belongs to one branch and has many slots.
- Staff users can serve many service types via `staff_service_types`.
- One slot can be used by at most one active appointment.
- One customer can have many appointments.

### 2. Run with Docker

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

docker compose up -d --build
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed
```

When using Docker Compose, the app, PostgreSQL, Redis, and scheduler services
start together.

### 3. Run locally (with PostgreSQL and Redis running)

For non-Docker local runs, update `.env` hosts before starting:

- `DB_HOST=127.0.0.1`
- `REDIS_HOST=127.0.0.1`

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The API will be available at `http://localhost/api`.

## Authentication

All protected endpoints use **HTTP Basic Authentication**.

```
Authorization: Basic base64(username:password)
```

Default seeded credentials:

| Role | Username | Password |
|------|----------|----------|
| ADMIN | `admin` | `Admin@123` |
| BRANCH_MANAGER | `mgr_muscat` | `Manager@123` |
| STAFF | `staff_muscat_1` | `Staff@123` |
| CUSTOMER | `cust_ahmed` | `Customer@123` |

## API Documentation (Scramble)

This project uses [`dedoc/scramble`](https://github.com/dedoc/scramble) for OpenAPI documentation and interactive endpoint testing.

### Open docs UI

- URL: `/docs/api`
- OpenAPI JSON: `/docs/api.json`

When running locally via Sail or Docker Compose, open:

- `http://localhost/docs/api`
- `http://localhost/docs/api.json`

## Deployment

### Deploy on Windows (WSL2)

Use this flow if you develop or deploy from Windows with WSL2.

1. Install WSL2 and Ubuntu (PowerShell as Administrator):

```powershell
wsl --install -d Ubuntu
```

2. Install Docker Desktop on Windows and enable:

- Use the WSL2 based engine.
- WSL integration for your Ubuntu distro.

3. Open Ubuntu (WSL) terminal and run:

```bash
git clone https://github.com/hajricod/flowcare.git
cd flowcare
cp .env.example .env

docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

docker compose up -d --build
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed --force
```

4. Verify services:

```bash
docker compose ps
curl -I http://localhost/up
```

Notes:

- Run the project from the Linux filesystem in WSL (for example `/home/<user>/flowcare`) for better performance.
- If port `80` is already used on Windows, set `APP_PORT` in `.env` (for example `APP_PORT=8080`) and reopen `http://localhost:8080`.

### Docker image

This repository now includes a production Docker image definition in `Dockerfile`.
You can build it locally with:

```bash
docker build -t flowcare:latest .
```

Run it with environment variables and a reachable PostgreSQL database:

```bash

# start container (if not running)
docker run --rm -p 8080:8080 \
	-e APP_KEY="base64:replace-with-real-key" \
	-e APP_URL="http://localhost:8080" \
	-e DB_CONNECTION=pgsql \
	-e DB_HOST=REPLACE_WITH_YOUR_MACHINE_IP \
	-e DB_PORT=5432 \
	-e DB_DATABASE=flowcare \
	-e DB_USERNAME=sail \
	-e DB_PASSWORD=password \
	flowcare:latest

# build container
docker run -d --name flowcare-api -p 8080:8080 \
  -e APP_KEY="base64:replace-with-real-key" \
  -e APP_URL="http://localhost:8080" \
  -e DB_CONNECTION=pgsql \
  -e DB_HOST=REPLACE_WITH_YOUR_MACHINE_IP \
  -e DB_PORT=5432 \
  -e DB_DATABASE=flowcare \
  -e DB_USERNAME=sail \
  -e DB_PASSWORD=password \
  flowcare:latest

# run migrations
docker exec -it flowcare-api php artisan migrate --force

```

### Find Docker image (Docker Hub)

Tag and push the image so others can pull it:

Run the published image with:

```bash
docker run --rm -p 8080:8080 hajricod/flowcare:latest
```

### Docker Compose deployment

For VM-based deployment, copy the project to your server, create `.env`, then run:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
	
docker compose up -d --build
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed --force
```

### Cloud deployment on Render

This repository includes a Render blueprint in `render.yaml` with:

- `flowcare-api` web service
- `flowcare-scheduler` background worker for Laravel scheduler
- `flowcare-db` PostgreSQL database

Production runtime also uses Redis for cache, queue, and session drivers.
Provide a Redis instance (external or Render-managed) and set `REDIS_URL`
for both the web and worker services.
Note: the current `render.yaml` blueprint provisions PostgreSQL only; it does
not provision Redis directly.

Deployment steps:

1. Push this branch to GitHub.
2. In Render, create a new Blueprint and point it to the repository.
3. Review the generated services from `render.yaml`.
4. Set a valid Laravel `APP_KEY` in both the web and worker services.
5. Set `REDIS_URL` in both the web and worker services.
	Example format: `redis://:<password>@<host>:<port>`
6. Ensure the Redis instance is reachable from both services.
7. After first deploy, open a Render shell and run:
```bash
php artisan migrate --seed --force
```

8. Use the generated Render URL as `APP_URL` if it was not auto-filled.

Files used for cloud deployment:

- `Dockerfile`
- `render.yaml`

### Live API URL

Working API Server
- [API Server](https://16.171.18.251)
- docs and testing  [FlowCare API Docs](https://16.171.18.251/docs/api#/)
- Live queue demo  [FlowCare Queue SSE Demo](https://16.171.18.251/queue-sse-demo)

### Test endpoints with Try It

1. Open `/docs/api`.
2. Expand any endpoint and click `Try It`.
3. For protected endpoints, add header:
	`Authorization: Basic base64(username:password)`

Example:

```bash
echo -n 'admin:Admin@123' | base64
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

### Listing Response Format

All paginated listing endpoints return the same shape:

```json
{
	"results": [...],
	"total": 125
}
```

Supported listing query parameters:

- `page` (default: `1`)
- `size` (default: `15`)
- `term` (optional case-insensitive search where supported)

### Public (no auth required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/branches` | List active branches |
| GET | `/api/branches/{id}/services` | List services for branch |
| GET | `/api/branches/{id}/services/{svcId}/slots` | List available slots |
| GET | `/api/branches/{id}/queue` | Live queue count |
| GET | `/api/branches/{id}/queue/stream` | Live queue stream (SSE) |
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

## Example API Usage (curl)

Use these shell variables once, then run any command below:

```bash
BASE_URL="http://localhost/api"

ADMIN_AUTH="$(printf 'admin:Admin@123' | base64)"
MANAGER_AUTH="$(printf 'mgr_muscat:Manager@123' | base64)"
STAFF_AUTH="$(printf 'staff_muscat_1:Staff@123' | base64)"
CUSTOMER_AUTH="$(printf 'cust_ahmed:Customer@123' | base64)"
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

# GET /api/branches/{branch}/queue/stream (SSE)
curl -N "$BASE_URL/branches/br_muscat_001/queue/stream?interval=2&duration=60"

# POST /api/auth/register
curl -X POST "$BASE_URL/auth/register" \
	-F "username=new_customer" \
	-F "email=new_customer@example.com" \
	-F "password=Customer@123" \
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

```

## SSE Client Example (Browser)

Use Server-Sent Events for live queue updates without polling.

```html
<div>
	<strong>Branch:</strong> <span id="branch">br_muscat_001</span><br>
	<strong>Live Queue Number:</strong> <span id="queue">-</span><br>
	<strong>Updated At:</strong> <span id="updated">-</span>
</div>

<script>
	const branchId = "br_muscat_001";
	const streamUrl = `/api/branches/${branchId}/queue/stream?interval=2&duration=300`;

	const queueEl = document.getElementById("queue");
	const updatedEl = document.getElementById("updated");

	const source = new EventSource(streamUrl);

	source.addEventListener("queue.update", (event) => {
		const payload = JSON.parse(event.data);
		queueEl.textContent = String(payload.live_queue_number);
		updatedEl.textContent = payload.timestamp;
	});

	source.addEventListener("queue.end", () => {
		source.close();
		// Reconnect after server-enforced duration ends.
		setTimeout(() => window.location.reload(), 1000);
	});

	source.onerror = () => {
		// Browser auto-reconnects for transient network/server interruptions.
		console.warn("SSE connection issue. Waiting for automatic reconnect...");
	};
</script>
```

Notes:

- `queue.update` carries `branch_id`, `live_queue_number`, `active_queue_count`, and `timestamp`.
- `queue.end` is sent when the configured stream duration is reached.
- For long-lived dashboards, reconnect automatically when `queue.end` arrives.

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
| `DB_HOST` | Database host | `pgsql` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name | `flowcare` |
| `DB_USERNAME` | Database user | `sail` |
| `DB_PASSWORD` | Database password | `password` |
| `SESSION_DRIVER` | Session backend | `redis` |
| `CACHE_STORE` | Cache backend | `redis` |
| `QUEUE_CONNECTION` | Queue backend | `redis` |
| `REDIS_CLIENT` | Redis client | `phpredis` |
| `REDIS_HOST` | Redis host | `127.0.0.1` |
| `REDIS_PORT` | Redis port | `6379` |

## File Storage

Files are stored on the local disk under `storage/app/`:

- Customer ID images: `uploads/customers/{uuid}.{ext}`
- Appointment attachments: `uploads/appointments/{uuid}.{ext}`

## Rate Limits

Customer booking and rescheduling limits are enforced server-side:

- `max_bookings_per_customer_per_day` (default: `3`)
- `max_reschedules_per_appointment_per_day` (default: `2`)

These values are stored in the `settings` table and are seeded by default.

## Background Scheduling

Expired soft-deleted slots are hard-deleted automatically by a scheduled artisan
command:

- Command: `php artisan slots:cleanup-expired`
- Schedule: daily at `01:00`
- Runtime service in Docker Compose: `scheduler`

When using Docker Compose, start the application normally and the scheduler
service will run in the background alongside the API container.

If you run the app without Docker, add a system cron entry like this:

```cron
* * * * * cd /path/to/flowcare && php artisan schedule:run >> /dev/null 2>&1
```

## License

MIT
