# Fund Transfer API

A secure REST API for transferring funds between accounts, built with Symfony 7 and PHP 8.3.

## Tech Stack

- **PHP 8.3** with **Symfony 7.2**
- **MySQL 8.0** — persistent storage for accounts and transfers
- **Redis 7** — rate limiting and caching
- **Docker** — containerized development environment
- **Nginx** — reverse proxy

## Quick Start

```bash
# Clone and enter the project
git clone <repository-url>
cd paysera_home_assignment

# Build and start everything (containers, dependencies, migrations, fixtures)
make setup

# Or step by step:
make build
make up
make install
make migrate
make fixtures
```

The API is available at **http://localhost:8080**.

## API Endpoints

### Create Transfer

```
POST /api/v1/transfers
Content-Type: application/json
```

**Request body:**
```json
{
  "from_account_id": 1,
  "to_account_id": 2,
  "amount": 2500,
  "currency": "EUR",
  "idempotency_key": "unique-transfer-key-001"
}
```

- `amount` is in **minor units** (cents). `2500` = €25.00.
- `idempotency_key` prevents duplicate transfers on retries.

**Success response (201):**
```json
{
  "id": 1,
  "idempotencyKey": "unique-transfer-key-001",
  "fromAccountId": 1,
  "toAccountId": 2,
  "amount": 2500,
  "currency": "EUR",
  "status": "completed",
  "failureReason": null,
  "createdAt": "2026-03-18T12:00:00+00:00"
}
```

### Get Account

```
GET /api/v1/accounts/{id}
```

**Response (200):**
```json
{
  "id": 1,
  "currency": "EUR",
  "balance": 7500,
  "balanceFormatted": "75.00",
  "createdAt": "2026-03-18T12:00:00+00:00",
  "updatedAt": "2026-03-18T12:00:00+00:00"
}
```

### Error Responses

| Status | Error Code | Description |
|--------|-----------|-------------|
| 400 | `validation_failed` | Invalid request payload |
| 400 | `invalid_json` | Malformed JSON body |
| 404 | `account_not_found` | Account does not exist |
| 409 | `transfer_conflict` | Concurrent modification — retry the request |
| 422 | `insufficient_funds` | Source account lacks sufficient balance |
| 422 | `currency_mismatch` | Account currencies don't match the transfer |
| 429 | `rate_limit_exceeded` | Too many requests — slow down |

## Running Tests

```bash
make test
```

This creates the test database, runs migrations, and executes all integration tests.

## Architecture Decisions

### Balance as Integer (Cents)
All monetary values are stored as integers in minor currency units (cents). This avoids floating-point precision issues entirely. `10050` = `100.50` in the account's currency.

### Optimistic Locking
The `Account` entity uses Doctrine's `@Version` column. If two concurrent transfers modify the same account, the second flush throws an `OptimisticLockException`, which is caught and returned as a 409 response. The client is expected to retry.

**Why not pessimistic locking?** Optimistic locking avoids holding database row locks during the request lifecycle, which is better for throughput under moderate contention. For extremely high contention scenarios, pessimistic locking (`SELECT ... FOR UPDATE`) would be more appropriate.

### Idempotency Keys
Every transfer requires a unique `idempotency_key`. If a request is retried with the same key, the original transfer is returned without executing again. This prevents duplicate transfers from network retries. The unique database constraint is the final safety net.

### Rate Limiting
The transfer endpoint is rate-limited using Symfony's `RateLimiter` component backed by Redis. Default: 30 requests per minute per IP address (sliding window).

### Redis Usage
- **Rate limiting storage** — sliding window counters per IP
- **Cache layer** — configured as Symfony's default cache adapter for production

## Project Structure

```
src/
├── Controller/Api/V1/    # REST endpoints
│   ├── AccountController.php
│   └── TransferController.php
├── DTO/                  # Request/response objects
│   ├── TransferRequest.php
│   ├── TransferResponse.php
│   └── AccountResponse.php
├── Entity/               # Doctrine entities
│   ├── Account.php       # With @Version for optimistic locking
│   └── Transfer.php      # With unique idempotency_key
├── EventListener/        # Kernel event listeners
│   ├── ExceptionListener.php
│   └── RateLimitListener.php
├── Exception/            # Structured API exceptions
├── Repository/           # Doctrine repositories
├── Service/
│   └── TransferService.php   # Core transfer logic
└── DataFixtures/         # Test data seeders
```

## Future Improvements

If this were a production system, I would additionally implement:

- **Pessimistic locking option** — for high-contention accounts (e.g., merchant accounts), `SELECT ... FOR UPDATE` with ordered locking by account ID to prevent deadlocks
- **Async processing** — for high throughput, queue transfers via Symfony Messenger and process asynchronously with workers
- **Audit log** — immutable event log of all balance changes for compliance and debugging
- **Multi-currency support** — exchange rate service integration for cross-currency transfers
- **Account creation/management endpoints** — full CRUD for accounts with proper authorization
- **Authentication & authorization** — JWT/OAuth2 tokens, account ownership verification
- **Pagination** — for transfer history endpoints (not yet implemented)
- **Health check endpoint** — `/health` checking DB and Redis connectivity
- **Metrics** — Prometheus counters for transfer volume, latency, error rates
- **Database read replicas** — separate read/write connections for account balance queries vs. transfers

## Time Spent

~2 hours

## AI Tools Used

- **Claude Code (Anthropic)** — Used for scaffolding the project structure, generating boilerplate code (entities, DTOs, exceptions), and writing integration tests. All generated code was reviewed and modified to ensure correctness and alignment with the architectural decisions documented above.
