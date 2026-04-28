# FlowForge

A real-time, multi-tenant workflow orchestration engine. Define, execute, and monitor
automated workflows as DAGs — with live step-by-step visibility.

---

## Tech Stack

| Layer            | Technology                               |
| ---------------- | ---------------------------------------- |
| Backend          | Laravel 11, PHP 8.3                      |
| Queue            | Redis + BullMQ (via Laravel Horizon)     |
| Database         | PostgreSQL 16                            |
| Frontend         | React 18, Vite, Tailwind CSS, React Flow |
| Real-Time        | Server-Sent Events (SSE)                 |
| Auth             | JWT (tymon/jwt-auth)                     |
| Containerization | Docker, docker-compose                   |
| CI/CD            | GitHub Actions                           |

---

## Prerequisites

- Docker & docker-compose
- PHP 8.3 + Composer (for local dev without Docker)
- Node.js 20 + npm (for frontend dev)

---

## Quick Start (Docker)

```bash
# 1. Clone repo
git clone https://github.com/your-org/flowforge.git
cd flowforge

# 2. Copy env files
cp apps/api/.env.example apps/api/.env.docker
cp apps/web/.env.example apps/web/.env

# 3. Spin up seluruh stack
docker-compose up --build -d

# 4. Jalankan migrations + seeder
docker-compose run --rm migrate

# 5. Akses aplikasi
# Frontend : http://localhost:3000
# API      : http://localhost:8000/api
```

Default credentials setelah seeder:

```
Email    : admin@flowforge.dev
Password : password
```

---

## Local Development (tanpa Docker)

### Backend

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Sesuaikan DB_* dan REDIS_* di .env
php artisan migrate --seed
php artisan serve          # API di http://localhost:8000
php artisan queue:work     # Worker terpisah
```

### Frontend

```bash
cd apps/web
npm install
cp .env.example .env       # Set VITE_API_URL=http://localhost:8000
npm run dev                # http://localhost:5173
```

---

## Running Tests

```bash
cd apps/api

# Semua tests
php artisan test

# Per suite
php artisan test tests/Unit/DagParserTest.php
php artisan test tests/Feature/WorkflowApiTest.php
php artisan test tests/Feature/FullWorkflowRunTest.php

# Dengan coverage
php artisan test --coverage --min=60
```

---

## Project Structure

```
flowforge/
├── apps/
│   ├── api/                    # Laravel backend
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/Api/
│   │   │   │   └── Middleware/
│   │   │   ├── Jobs/           # ExecuteWorkflowJob
│   │   │   ├── Models/
│   │   │   └── Services/       # DagParser
│   │   ├── database/migrations/
│   │   ├── tests/
│   │   │   ├── Unit/           # DagParserTest
│   │   │   └── Feature/        # WorkflowApiTest, FullWorkflowRunTest
│   │   └── docker/
│   └── web/                    # React frontend
│       ├── src/
│       │   ├── api/            # Axios client
│       │   ├── components/     # DagViewer, HealthPanel, StepNode
│       │   ├── hooks/          # useWorkflowSocket (SSE)
│       │   ├── pages/          # DashboardPage, WorkflowDetailPage
│       │   └── stores/         # Zustand auth store
│       └── docker/
├── .github/workflows/ci.yml
├── docker-compose.yml
├── README.md
└── REVIEW.md
```

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                     Client Browser                  │
│  React + Vite │ React Flow │ React Query │ Zustand  │
└───────────────────────┬─────────────────────────────┘
                        │ HTTP/SSE
┌───────────────────────▼─────────────────────────────┐
│              Laravel API (nginx + php-fpm)           │
│  JWT Auth │ Tenant Middleware │ Rate Limiting        │
│  REST API │ SSE Stream │ DAG Parser │ DagValidator   │
└──────┬────────────────┬────────────────────────────┘
       │                │
┌──────▼──────┐  ┌──────▼──────┐
│ PostgreSQL  │  │    Redis     │
│  - tenants  │  │  - queues    │
│  - users    │  │  - cache     │
│  - workflows│  │  - rate limit│
│  - runs     │  └─────────────┘
│  - step_logs│         │
└─────────────┘  ┌──────▼──────┐
                 │Queue Worker  │
                 │ExecuteWorkflow│
                 │Job (2 procs) │
                 └─────────────┘
```

### Request Flow

1. **Trigger** — `POST /api/workflows/:id/trigger` → buat `WorkflowRun` → dispatch `ExecuteWorkflowJob` ke Redis queue
2. **Execute** — Job melakukan topological sort DAG, eksekusi step per step (parallel jika tidak ada dependency), catat setiap status ke `step_logs`
3. **Stream** — Frontend subscribe ke `GET /api/runs/:id/stream` (SSE), menerima event real-time setiap step berubah status
4. **Visualize** — React Flow render DAG, node berubah warna sesuai status (pending → running → success/failed)

---

## Database Schema

```
tenants          users            workflow_versions
─────────        ─────────        ─────────────────
id (uuid) ──┐   id (uuid)        id (uuid)
name        │   tenant_id ──┐    workflow_id ──┐
slug        │   email       │    version         │
            │   role        │    dag_definition  │
            │   password    │    is_active       │
            └───────────────┘                    │
                                workflows        │
                                ─────────        │
                                id (uuid) ───────┘
                                tenant_id
                                name
                                current_version

workflow_runs     step_logs
─────────────     ─────────
id (uuid)         id (uuid)
workflow_id       run_id ──────┐
tenant_id                      │
version           workflow_runs│
status            id (uuid) ───┘
started_at        step_id
finished_at       status
duration_ms       started_at
                  finished_at
                  duration_ms
                  output (jsonb)
                  error_message
```

### High-Volume Log Strategy

`step_logs` disimpan di PostgreSQL dengan append-only pattern (tidak ada UPDATE, hanya INSERT). Justifikasi:

- **PostgreSQL JSONB** cukup untuk MVP dengan volume ribuan run/hari
- Index komposit `(run_id, step_id)` dan `(created_at DESC)` menjaga query tetap cepat
- Untuk produksi skala besar, bisa migrasi ke **TimescaleDB** (hypertable) atau **ClickHouse** tanpa mengubah API

---

## API Endpoints

| Method | Endpoint                               | Auth            | Description                |
| ------ | -------------------------------------- | --------------- | -------------------------- |
| POST   | `/api/auth/login`                      | —               | Login, dapat JWT           |
| GET    | `/api/workflows`                       | ✓               | List workflows (paginated) |
| POST   | `/api/workflows`                       | Admin           | Create workflow            |
| GET    | `/api/workflows/:id`                   | ✓               | Get workflow detail        |
| PUT    | `/api/workflows/:id`                   | Admin/Editor    | Update workflow            |
| DELETE | `/api/workflows/:id`                   | Admin           | Delete workflow            |
| POST   | `/api/workflows/:id/trigger`           | Admin/Editor    | Trigger manual run         |
| POST   | `/api/workflows/:id/rollback/:version` | Admin           | Rollback ke versi lama     |
| GET    | `/api/workflows/:id/runs`              | ✓               | Run history                |
| GET    | `/api/runs/:id/stream`                 | ✓ (query param) | SSE real-time stream       |
| GET    | `/api/health`                          | ✓               | System health metrics      |

---

## Trade-offs & What I'd Improve

### Trade-offs yang Dibuat

**1. SSE vs WebSocket**
SSE dipilih karena lebih simpel untuk unidirectional streaming (server → client) tanpa
memerlukan infrastruktur tambahan seperti Laravel Reverb atau Pusher. Trade-off: tidak bisa
kirim event dari client ke server melalui SSE. Untuk produksi, WebSocket dengan Reverb
lebih scalable.

**2. PostgreSQL untuk Step Logs**
Step logs disimpan di PostgreSQL dengan append-only strategy alih-alih dedicated log store
seperti Elasticsearch. Lebih mudah disetup dan cukup untuk MVP. Trade-off: pada volume
sangat tinggi (jutaan log/hari), perlu partitioning atau migrasi ke TimescaleDB.

**3. Supervisor untuk Queue Worker**
Queue worker dijalankan via Supervisor di container yang sama dengan API. Lebih mudah
untuk local dev dan deployment sederhana. Trade-off: tidak bisa scale worker secara
independen. Untuk produksi, worker sebaiknya container terpisah dengan auto-scaling.

**4. Single-Region Architecture**
Arsitektur dirancang untuk single-region deployment. Trade-off: tidak ada multi-region
failover. Untuk produksi global, perlu read replicas PostgreSQL di region lain.

### Yang Akan Diperbaiki dengan Lebih Banyak Waktu

- [ ] **GraphQL endpoint** di samping REST (bonus requirement B)
- [ ] **AI Feature** — Natural language workflow builder dengan OpenAI GPT-4o
- [ ] **Webhook trigger** — verifikasi HMAC signature untuk keamanan
- [ ] **Cron scheduler** — gunakan distributed lock (Redis) untuk mencegah double-trigger
- [ ] **Audit log** — catat semua perubahan workflow per user
- [ ] **Frontend tests** — Vitest + React Testing Library
- [ ] **E2E tests** — Playwright untuk full browser automation
- [ ] **Metrics** — Prometheus + Grafana untuk observability produksi
- [ ] **Rate limiting** berbasis Redis sliding window yang lebih granular per tenant
- [ ] **Step output chaining** — output step sebelumnya bisa jadi input step berikutnya
