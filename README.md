# Bumpa Senior Backend Engineer Assessment

This is a backend assessment built with Laravel v13 to demonstrate the implementation of an event-driven customer rewards system for a local ecommerce store. 

Purchases unlock achievements, achievement milestones unlock badges, and every earned badge creates a ₦300 cashback transfer through Paystack.

## Requirements

- Docker Desktop with Docker Compose
- Composer
- A Paystack test secret key to exercise the real transfer integration

The Docker environment provides PHP 8.5, MySQL 8.4, Redis, and a dedicated queue worker.

## Setup

1. Install PHP dependencies and create the environment file:

   ```bash
   composer install
   cp .env.example .env
   ```

2. Add a Paystack test secret key to `.env`:

   ```dotenv
   PAYSTACK_SECRET_KEY=sk_test_your_key
   ```

3. Start the application, database, Redis, and queue worker:

   ```bash
   ./vendor/bin/sail up -d
   ```

4. Generate the application key and prepare sample data:

   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate --seed
   ```

The application is available at `http://localhost` by default. To use port 8000, set `APP_PORT=8000` and `APP_URL=http://localhost:8000` in `.env` before starting Docker.

The seeders create two users, ten active products, all achievement and badge definitions, and Paystack test payout accounts for the users.

## API

### Record a purchase

```http
POST /api/users/{user}/purchase
Content-Type: application/json
```

Example:

```bash
curl --request POST http://localhost/api/users/1/purchase \
    --header 'Accept: application/json' \
    --header 'Content-Type: application/json' \
    --data '{
        "reference": "01J6M9YQF6Q7QXW4V4YP7Q9Q5H",
        "product_id": 1,
        "quantity": 1
    }'
```

`reference` must be a ULID and acts as the idempotency key. Replaying the same reference with the same user, product, and quantity returns the original purchase. Reusing it with different details returns `409 Conflict`. Prices are always read from the product record rather than trusted from the request.

### View reward progress

```http
GET /users/{user}/achievements
Accept: application/json
```

Example:

```bash
curl --header 'Accept: application/json' \
    http://localhost/users/1/achievements
```

Example response:

```json
{
    "unlocked_achievements": [
        "First Purchase",
        "2 Purchases",
        "3 Purchases",
        "4 Purchases"
    ],
    "next_available_achievements": [
        "5 Purchases"
    ],
    "current_badge": "Intermediate",
    "next_badge": "Advanced",
    "remaining_to_unlock_next_badge": 4
}
```

## Reward Rules

Purchase achievements currently uses these thresholds:

| Achievement | Purchase count |
| --- | ---: |
| First Purchase | 1 |
| 2 Purchases | 2 |
| 3 Purchases | 3 |
| 4 Purchases | 4 |
| 5 Purchases | 5 |
| 10 Purchases | 10 |
| 15 Purchases | 15 |
| 20 Purchases | 20 |
| 25 Purchases | 25 |
| 50 Purchases | 50 |

Every purchase counts, including repeated purchases of the same product.

Badge thresholds are deliberately reachable by the ten available achievements:

| Badge | Required achievements | Cashback |
| --- | ---: | ---: |
| Beginner | 0 | - |
| Intermediate | 4 | ₦300 |
| Advanced | 8 | ₦300 |
| Master | 10 | ₦300 |

Beginner is the default display tier and does not create a persisted badge unlock or cashback.

## Design

The main event chain is:

```text
Purchase API
  -> PurchaseCreatedEvent
  -> queued achievement evaluation
  -> AchievementUnlockedEvent
  -> queued badge evaluation
  -> BadgeUnlockedEvent
  -> cashback ledger creation
  -> queued Paystack transfer
```

Key design choices:

- Events and queued listeners isolate each reward stage and keep purchase requests fast.
- Events and listeners are dispatched after database commits so workers never read uncommitted records.
- Database uniqueness constraints and `firstOrCreate` operations make achievement, badge, and cashback processing idempotent.
- Delayed event processing unlocks every crossed achievement or badge rather than only the latest one.
- A `PaymentProvider` contract keeps the domain workflow independent of Paystack.
- Cashback is stored before calling Paystack and uses a stable reference derived from the badge unlock.
- The cashback job is unique per ledger entry and treats successful or submitted transfers as terminal, preventing duplicate payouts.
- Transient Paystack and connection failures are retried with backoff; definitive provider rejections are recorded for investigation.
- Payout account numbers are encrypted at rest and hidden from serialization.

## Paystack Test Transfers

The seeded payout account uses Paystack's documented test details:

- Bank: Zenith Bank
- Bank code: `057`
- Account number: `0000000000`


## Testing

Run all tests using the command:

```bash
./vendor/bin/sail artisan test --compact
```

The test suite covers:

- Purchase validation, pricing, idempotency, and conflict handling
- Achievement thresholds, repeated products, delayed processing, events, and replay safety
- Badge thresholds, crossed milestones, events, and replay safety
- Progress endpoint responses and missing users
- Cashback ledger uniqueness, recipient creation, transfers, encryption, provider failures, retries, and duplicate prevention
- The complete purchase-to-cashback event chain

Format PHP changes with:

```bash
./vendor/bin/sail pint --format agent
```

## Stopping the Environment

```bash
./vendor/bin/sail down
```
