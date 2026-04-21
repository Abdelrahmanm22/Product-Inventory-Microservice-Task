# Product Inventory Microservice

A RESTful API microservice for managing products and stock levels, built with **Laravel 11**, **PostgreSQL**, and **Redis** — fully containerized with Docker.

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Laravel 11 |
| Database | PostgreSQL 16 |
| Cache | Redis 7 |
| Web Server | Nginx (Alpine) |
| Containerization | Docker + Docker Compose |
| API Docs | Scramble (OpenAPI / Swagger) |
| Testing | PHPUnit (Laravel Feature Tests) |

---

## Prerequisites

Make sure the following are installed on your machine before proceeding:
- [Docker](https://docs.docker.com/get-docker/) (v24+)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2+)
- Git
---

## Quick Start

Follow these steps exactly to get the project running from scratch.

### 1. Clone the repository

```bash
git clone <repository-url>
cd product-inventory
```

### 2. Copy the environment file

```bash
cp .env.example .env
```

> The `.env` file is already pre-configured for Docker. No changes are needed for a local run.

### 3. Build and start all containers

```bash
docker compose up -d --build
```

This starts four containers:

| Container | Role | Exposed Port |
|---|---|---|
| `product-inventory-app` | PHP 8.4-FPM (Laravel) | — |
| `product-inventory-nginx` | Web server | `8000` |
| `product-inventory-db` | PostgreSQL 16 | `5433` |
| `product-inventory-redis` | Redis 7 | `6379` |

### 4. Install PHP dependencies

```bash
docker exec -it product-inventory-app composer install
```

### 5. Generate application key

```bash
docker exec -it product-inventory-app php artisan key:generate
```

### 6. Run database migrations

```bash
docker exec -it product-inventory-app php artisan migrate
```

### 7. Verify the application is running

Open your browser or use curl:

```bash
curl http://localhost:8000/api/products
```

You should receive a successful JSON response.

---

## Environment Variables

The key variables in `.env` (already configured for Docker):

```dotenv
# Application
APP_URL=http://localhost
APP_PORT=8000

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=product_inventory
DB_USERNAME=laravel
DB_PASSWORD=secret

# Redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379

# Cache (uses Redis)
CACHE_STORE=redis

# Queue
QUEUE_CONNECTION=database
```

> **Note:** `DB_PORT` inside the container is `5432`. The host machine connects on `5433` (mapped in `docker-compose.yml`) to avoid conflicts with any local PostgreSQL instance.

---

## API Documentation (Swagger)

I used in this project **[Scramble](https://scramble.dedoc.co/)** to auto-generate interactive OpenAPI / Swagger documentation directly from the Laravel routes and request classes — no manual YAML needed.

### Accessing the docs

Once the containers are running, open your browser and navigate to:

```
http://localhost:8000/docs/api
```

You will find:

- All available endpoints listed with their HTTP methods
- Request body schemas with validation rules
- Response shape examples
- The ability to send live requests directly from the browser UI

---

## Running Tests

### 1. Create the test database

```bash
docker exec -it product-inventory-db psql -U laravel -c "CREATE DATABASE product_inventory_test;"
```

### 2. Run migrations on the test database

```bash
docker exec -it product-inventory-app php artisan migrate --env=testing --force
```

### 3. Run  the product tests

```bash
docker exec -it product-inventory-app php artisan test tests/Feature/ProductApiTest.php
```

> Tests use `CACHE_STORE=array` and `QUEUE_CONNECTION=sync` (set in `phpunit.xml`) so no live Redis connection is required during testing.

---

## API Endpoints Reference

Base URL: `http://localhost:8000/api`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/products` | List all products (paginated, excludes discontinued) |
| `GET` | `/products/low-stock` | List active products below their stock threshold |
| `GET` | `/products/{id}` | Get a single product by UUID |
| `POST` | `/products` | Create a new product |
| `PUT` | `/products/{id}` | Update an existing product |
| `DELETE` | `/products/{id}` | Soft-delete a product |
| `POST` | `/products/{id}/stock` | Adjust stock (positive = add, negative = remove) |

### Query parameters for `GET /products`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `search` | string | — | Filter by name or SKU |
| `per_page` | integer | 15 | Items per page |
| `page` | integer | 1 | Page number |

### Standard response envelope

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { },
  "meta": {
    "pagination": {
      "total": 50,
      "per_page": 15,
      "current_page": 1,
      "last_page": 4,
      "from": 1,
      "to": 15
    }
  }
}
```

### Stock adjustment request body

```json
{ "quantity": -5 }
```

A positive value increments stock; a negative value decrements it. Zero is rejected. The operation is atomic (wrapped in a DB transaction with a row-level lock).

---

## Architectural Decisions

### 1. Repository Pattern

All database interactions are abstracted behind a `ProductRepositoryInterface`. The controller depends only on the interface, never on Eloquent directly. This makes it trivial to swap the underlying data source (e.g. switching to a different DB engine) without touching business logic, and makes unit testing controllers straightforward by injecting a mock repository.

### 2. Action Class for Stock Adjustment

Stock adjustment is isolated in a dedicated `AdjustStockAction` class rather than living inside the controller or repository. The action wraps the operation in a `DB::transaction()` with a `lockForUpdate()` on the product row, which prevents race conditions when concurrent requests try to adjust the same product's stock simultaneously. The action also fires the `StockThresholdReached` event after a successful adjustment.

### 3. Event / Listener for Low Stock Alerts

When stock falls below the `low_stock_threshold`, a `StockThresholdReached` event is dispatched. The `SendLowStockAlert` listener handles it asynchronously via the `alerts` Redis queue (with 3 retry attempts). This decouples the notification concern from the stock operation — adding email, SMS, or webhook notifications later only requires a new listener, not touching the core stock logic.

### 4. Redis Caching with Tag-Based Invalidation

The `GET /products` listing is cached in Redis with a 5-minute TTL using cache tags (`['products']`). On any mutation (create, update, delete, stock adjustment), `Cache::tags(['products'])->flush()` is called to invalidate the entire product listing cache at once. This is more precise than a full `Cache::flush()` because it only evicts product-related keys and leaves other cached data intact.

### 5. UUID Primary Keys

Products use UUIDs (`HasUuids`) instead of auto-incrementing integers. This prevents ID enumeration attacks (a user cannot guess `id=1`, `id=2`…), makes the schema safer to expose publicly, and allows records to be created on the client side or across distributed systems without coordination.

### 6. Soft Deletes

Products are never hard-deleted from the database. `SoftDeletes` adds a `deleted_at` timestamp so that historical records (orders, stock logs) that reference a product UUID remain intact and auditable. Deleted products are invisible to all standard queries automatically through Eloquent's global scope.

### 7. Consistent API Response Envelope via `ApiResponseTrait`

All controllers use a shared `ApiResponseTrait` that enforces a consistent JSON structure across every endpoint (`success`, `message`, `data`, optional `meta` and `errors`). This makes the API predictable for frontend consumers and eliminates duplicated response-shaping code scattered across controllers.

### 8. Form Request Validation with Custom Error Messages

Validation logic lives in dedicated `StoreProductRequest` and `UpdateProductRequest` classes, keeping controllers thin. Each request overrides `failedValidation()` to throw an `HttpResponseException` using the same `ApiResponseTrait` envelope, so validation errors are formatted identically to all other error responses (HTTP 422 with a structured `errors` object).

---

## Thank you for reviewing my submission, for considering my efforts, and I look forward to discussing it further!


If you have any questions or would like to connect:

- **Name:** Abdelrahman Mohamed
- **Phone:** 01015496488
- **Email:** abdelrahmanmohamed2293@gmail.com

---

Best regards,  
**Abdelrahman Mohamed**


