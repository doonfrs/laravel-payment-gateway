# Nomod Payment Gateway — API Reference

> Source: extracted from the rendered Nomod docs (https://nomod.com/docs/api-reference/) on 2026-05-06.

This document is the single source of truth for the upcoming `NomodPaymentPlugin` integration. All field names, types, and response shapes below come from the live OpenAPI-rendered docs.

## 1. Base URL

```
https://api.nomod.com
```

Nomod uses **a single base URL** for both test and live. The environment is determined entirely by which API key you send (test vs live), so the plugin does not need a separate `base_url_test` / `base_url_production` field — only separate API keys.

## 2. Authentication

- **Scheme**: API key in header
- **Header**: `X-API-KEY`
- **Where to get it**: Nomod app → Settings → Connect and Manage Integrations → activate API key
- **Format**: `sk_test_…` (test) or `sk_live_…` (production)
- **Required on every endpoint** — sending no key returns `401 not_authenticated`

```
X-API-KEY: sk_test_LKhVdQrc.EqkGuzuCQ0qQvgwdrnu43TtMDwTlppxU
```

## 3. Endpoints used by the plugin

| Method | Path | Purpose in the plugin |
|--------|------|---------------------|
| `POST` | `/v1/checkout` | Create a Hosted Checkout session, get the redirect URL |
| `GET` | `/v1/checkout/{id}` | Verify payment status server-side after callback |
| `POST` | `/v1/charges/{id}/refund` | Process a refund (uses charge id, not checkout id) |
| `GET` | `/v1/charges` | List charges (used to find the charge id for a paid checkout) |

## 4. `POST /v1/checkout` — Create Checkout

### Request

Headers:
```
Content-Type: application/json
Accept: application/json
X-API-KEY: sk_test_…
```

Body (`*/*`, JSON accepted):

| Field | Type | Required | Notes |
|-------|------|:---:|-------|
| `reference_id` | string (≤ 100 chars) | YES | Merchant-supplied unique id — use `PaymentOrder.order_code`. We use this to reconcile the callback. |
| `amount` | string\<decimal\> | YES | Total payable amount in **main currency unit** (e.g. `"123.45"`). Pattern: `^-?\d{0,8}(?:\.\d{0,2})?$` |
| `currency` | string (≤ 3 chars) | YES | ISO 4217 — `USD`, `AED`, `INR`, etc. |
| `items` | object[] | YES | At least one line item — see schema below |
| `success_url` | string\<uri\> | YES | Where Nomod redirects after **successful** payment |
| `failure_url` | string\<uri\> | YES | Where Nomod redirects after **failed** payment |
| `cancelled_url` | string\<uri\> | YES | Where Nomod redirects if the customer **cancels** |
| `discount` | string\<decimal\> | no | Total discount in main currency unit. Default `"0.00"`. Pattern same as `amount` |
| `customer` | object | no | See schema below |
| `metadata` | object | no | Free-form key/value blob |

#### `items[]` schema

| Field | Type | Notes |
|-------|------|-------|
| `item_id` | string | Merchant SKU/id |
| `name` | string | Display name |
| `quantity` | integer | ≥ 1 |
| `unit_amount` | string\<decimal\> | Price per unit |
| `discount_type` | string | `flat` (other values not yet documented) |
| `discount_amount` | string\<decimal\> | Default `"0.00"` |
| `total_amount` | string\<decimal\> | `unit_amount × quantity` |
| `net_amount` | string\<decimal\> | `total_amount − discount_amount` |

#### `customer` schema

| Field | Type |
|-------|------|
| `first_name` | string |
| `last_name` | string |
| `business_name` | string |
| `email` | string |
| `phone_number` | string |

### Response 200

```json
{
  "id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "url": "https://checkout.nomod.com/...",
  "status": "created",
  "amount": 123.45,
  "discount": 0,
  "currency": "AED",
  "reference_id": "PO-ABCD1234",
  "created_at": "2026-05-06T15:51:28.071Z",
  "items": [
    {
      "item_id": "sku-1",
      "name": "Item 1",
      "quantity": 1,
      "unit_amount": "100.00",
      "discount_type": "flat",
      "discount_amount": "0.00",
      "total_amount": "100.00",
      "net_amount": "100.00"
    }
  ],
  "customer": { "first_name": "", "last_name": "", "business_name": "", "email": "", "phone_number": "" },
  "metadata": {},
  "charges": []
}
```

Key fields the plugin must use:

- **`url`** — the URL we redirect the customer to (`return redirect()->away($response['url'])`)
- **`id`** — the checkout id; store on `PaymentOrder->remote_transaction_id` for status verification
- **`status`** — initially `created`; becomes `paid` / `cancelled` / `expired` over the session lifecycle
- **`charges`** — populated once payment is captured; each charge has its own id (used for refunds)

### Response 400

```json
{ "error": { "message": "string", "code": "string" } }
```

Common codes: `field_required`, `invalid_amount`, `invalid_currency`, `not_authenticated`.

## 5. `GET /v1/checkout/{id}` — Get Checkout

Used by `handleCallback()` to **verify payment status server-side** instead of trusting the redirect query string.

### Request

```
GET https://api.nomod.com/v1/checkout/{id}
X-API-KEY: sk_test_…
Accept: application/json
```

### Response 200

Identical schema to `POST /v1/checkout` response above. Check `status`:

| `status` | Meaning | Plugin maps to |
|----------|---------|----------------|
| `paid` | Payment captured | `CallbackResponse::success()` |
| `cancelled` | Customer cancelled | `CallbackResponse::cancelled()` |
| `expired` | Session timed out | `CallbackResponse::failure()` |
| `created` | Pending — not yet paid | `CallbackResponse::failure()` (treat as not paid) |

### Response 404

Checkout id not found.

## 6. `POST /v1/charges/{id}/refund` — Refund Charge

> **Important**: refunds operate on the **charge id**, not the checkout id. To refund a paid checkout, first fetch `GET /v1/checkout/{id}` and use `response.charges[0].id`.

### Request

```
POST https://api.nomod.com/v1/charges/{id}/refund
Content-Type: application/json
X-API-KEY: sk_test_…
```

```json
{ "amount": "123.45" }
```

| Field | Type | Required | Notes |
|-------|------|:---:|-------|
| `amount` | string\<decimal\> | YES | Pattern `^-?\d{0,7}(?:\.\d{0,3})?$` — supports up to 3 decimal places. For full refund, send the original charge amount. |

### Response 200

```json
{ "message": "Successfully refunded" }
```

### Response 400

```json
{ "error": { "message": "string", "code": "string" } }
```

## 7. `GET /v1/charges` — List Charges (utility)

Used as a fallback to discover the charge id when only the checkout/link id is known.

Query parameters (all optional):

| Param | Type |
|-------|------|
| `id` | string |
| `link_id` | string |
| `customer_id` | uuid |
| `currency` | string |
| `status` | string |
| `type` | string — one of `charge`, `link`, `invoice`, `checkout`, `collectquickpay`, `collectcampaign` |
| `search` | string |
| `page` | integer |
| `page_size` | integer |

### Response 200 (paginated envelope)

```json
{
  "count": 123,
  "next": "https://api.nomod.com/v1/charges/?page=4",
  "previous": "https://api.nomod.com/v1/charges/?page=2",
  "results": [ /* charge objects */ ]
}
```

## 8. Webhooks

**Nomod's API reference does not document a webhook endpoint, signature scheme, or event list** as of this writing (`/docs/integrations/webhooks/` returns 404).

The plugin therefore relies on the **redirect-back + server-side status check** flow, which is fully secure:

1. Customer is redirected by Nomod to one of `success_url` / `failure_url` / `cancelled_url`
2. The plugin's `handleCallback()` runs at `/payment-gateway/callback/nomod`
3. Plugin **does not trust the redirect query string** — it calls `GET /v1/checkout/{id}` using the stored `remote_transaction_id` and reads `status` from the authenticated API response
4. The result is mapped to `CallbackResponse::success/failure/cancelled` as in §5

If Nomod adds webhooks later, we can add an `inboundRequest` handler — the plumbing is already there in `PaymentPluginInterface::handleInboundRequest()` (see [src/Contracts/PaymentPluginInterface.php](../src/Contracts/PaymentPluginInterface.php)).

## 9. Errors & status codes

| HTTP | Meaning |
|------|---------|
| 401 | `not_authenticated` — invalid or missing `X-API-KEY` |
| 403 | Forbidden — insufficient rights |
| 404 | Resource not found (e.g. unknown checkout id) |
| 429 | `throttled` — rate limit exceeded |
| 500 | Internal server error |
| 503 | Service unavailable |

Error envelope: `{ "error": { "message": "...", "code": "..." } }` plus the dedicated codes `not_authenticated`, `field_required`, `invalid_amount`, `invalid_currency`, `invalid_business_id`, `invalid_email`, `throttled`.

## 10. Plugin configuration mapping

The plugin will expose these admin settings (mirrors the convention in [HyperPayPaymentPlugin](../src/Plugins/HyperPay/HyperPayPaymentPlugin.php)):

| Setting | Type | Required | Notes |
|---------|------|:---:|-------|
| `api_key_test` | text (encrypted) | YES (when test_mode on) | `sk_test_…` |
| `api_key_production` | text (encrypted) | YES (when test_mode off) | `sk_live_…` |
| `test_mode` | checkbox | — | Default `true` |

**Why no `webhook_secret_*` field**: Nomod doesn't document webhook signing yet (§8). If/when they add it, we extend the plugin with `webhook_secret_test` / `webhook_secret_production`.

## 11. Sandbox / test cards

The Nomod docs do not publish sandbox card numbers in the public API reference. Test mode is activated solely by using a `sk_test_…` API key — when an `sk_test_…` key is used, `https://api.nomod.com` runs in sandbox mode, and Nomod's hosted checkout page presents the relevant test instructions to the customer.

## 12. Country and currency support

- Confirmed countries (from Nomod marketing pages): UAE (`AE`); the plugin sets `getSupportedCountries() = ['AE']` — extend if Nomod adds markets
- Currencies: any ISO 4217 code accepted by the API (`AED`, `USD`, `INR`, etc.); validation is server-side via the `invalid_currency` error
