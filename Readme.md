# Digidog

A simple "virtual pet" game. The backend is PHP, frontend in React/Typescript.

## Requirements
- Docker

## Getting started

To run everything, simply run `make up`. This builds and starts the server and postgres containers, then runs the database migrations.

When run on docker, the server is available on localhost:8000. To check it's running, open http://localhost:8000/health.

Run `make help` to list the other commands (logs, shell, migrate, rollback, and creating a migration).

## Backend

index.php is the only public entrypoint. The routes are registered with the router there, then passed to the App for handling.

### Dependencies
I am avoiding any heavyweight frameworks, but am using a few helpful dependencies:

- DB Migrations: [Phinx](https://github.com/cakephp/phinx)
- Routing: [bramus/router](https://github.com/bramus/router)
- JWT: [firebase/php-jwt](https://github.com/googleapis/php-jwt)

### API

JSON over HTTP. Request and response fields are camelCase. Every endpoint except `/health` and login needs an `Authorization: Bearer <token>` header, and the player always comes from the token, never from the URL or body.

| Method | Path | Body | Returns |
|---|---|---|---|
| GET | `/health` | | `HealthResponse` |
| POST | `/v1/auth/login` | `AuthRequest` | `AuthResponse` |
| GET | `/v1/me` | | `MeResponse` |
| GET | `/v1/items` | | `ItemsResponse` |
| POST | `/v1/purchases` | `PurchaseRequest` | `PurchaseResponse` |

Each request and response is a class in `server/src/Api/`.

#### POST /v1/auth/login

Logs in with a device ID. An unknown device creates a new player, starting with 100 money. The token lasts one day.

```json
// AuthRequest
{ "provider": 1, "externalId": "device-uuid", "playerName": "Mark" }

// AuthResponse
{ "token": "eyJ..." }
```

- `provider`: `1` = device. Firebase (`2`) is planned.
- `playerName`: optional, 1-30 characters, only used for a new player. Defaults to "New Player".

#### GET /v1/me

The player and everything they hold. Money is an inventory item like any other (`slug: "money"`). Items with a quantity of zero are left out.

```json
// MeResponse
{
  "player": { "playerId": 9, "name": "Mark" },
  "inventory": [
    { "itemId": 1, "slug": "money", "name": "Money", "category": "currency", "quantity": 80 },
    { "itemId": 2, "slug": "food", "name": "Food", "category": "food", "quantity": 4 }
  ]
}
```

#### GET /v1/items

The shop catalogue, cheapest first. Money isn't listed because it isn't for sale.

```json
// ItemsResponse
{
  "items": [
    { "itemId": 2, "slug": "food", "name": "Food", "category": "food", "price": 5, "effects": { "fullness": 25 } },
    { "itemId": 5, "slug": "toy_ball", "name": "Ball", "category": "toy", "price": 15,
      "effects": { "fun": 20, "energy": -15, "break_chance": 0.15 } }
  ]
}
```

`category` is one of `currency`, `food`, `treat`, `toy`, `supply`. `effects` holds the numbers the game rules use, and its keys vary by item.

#### POST /v1/purchases

Buys an item. Money is taken and the item added together, or not at all.

```json
// PurchaseRequest
{ "itemId": 2, "quantity": 4 }

// PurchaseResponse: the inventory after the purchase, same shape as in MeResponse
{ "inventory": [ ... ] }
```

`quantity` is optional (default 1, max 99).

#### Errors

Every error has the same shape, with a message that is safe to show:

```json
{ "error": "not enough money" }
```

| Status | When |
|---|---|
| 400 | Invalid JSON or a bad field |
| 401 | Missing, invalid or expired token |
| 404 | Unknown route, or the item isn't for sale |
| 409 | Not enough money |
| 500 | Anything unexpected (details go to the server log only) |

### Security notes

This is a demo, and a few shortcuts are deliberate:

- **The device ID is the only credential.** Anyone who knows a player's device ID can log in as them. Real accounts (Firebase) are planned.
- **The `JWT_SECRET` in `docker-compose.yaml` is for local development only.** Any real deployment must set its own secret.
- **Clearing browser storage loses the player**, since the device ID lives there and there is no recovery yet.
