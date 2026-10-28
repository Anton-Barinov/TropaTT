# Mercado Pago Checkout Pro for TropaTT CRM

This module creates Checkout Pro preferences through Mercado Pago's official API (`POST /checkout/preferences`) and reconciles payment status through the provider's payment search and payment-detail APIs. It supports BRL only. The CRM does not collect or store card numbers or security codes; the customer completes checkout on Mercado Pago.

## Setup

1. Install the module and apply its migration.
2. Open the module page in the CRM, choose test mode, and save the Mercado Pago access token (and optional public key). The token is encrypted using the CRM `APP_KEY`; if the key or OpenSSL support is unavailable, saving and payment operations fail closed.
3. Run **Проверить подключение** before creating payment links. Saving a token confirms only that it was stored; the check makes a real authenticated request to Mercado Pago and is required to enable checkout creation in the current page session.
4. Use a test access token with test mode. The module returns the provider's `sandbox_init_point` in test mode and `init_point` in live mode.
5. Keep live credentials out of source control, URLs, logs, and support tickets.

The provider request uses HTTPS with certificate verification, a 15-second timeout, redirects disabled, a fixed API host, a 1 MiB response cap, and generic error messages that do not expose authorization headers or provider response bodies. A payment record is written only after Mercado Pago returns a valid preference ID and a checkout URL on an HTTPS Mercado Pago domain.

## Payment status verification

The initial payment-link response contains the CRM payment public ID and Mercado Pago preference ID. Use the **Обновить статус** action or `mercadopago_check_payment_status` with the CRM payment public ID. When no provider payment ID is stored yet, the module searches Mercado Pago by the saved external reference, then requires the preference ID, amount, and currency to match before fetching and applying the payment status. A provider payment ID may also be supplied explicitly. If no matching payment exists yet, the current status remains unchanged and unverified. A webhook receiver is not included in this release; status reconciliation is an explicit user/MCP action.

## MCP tools

- `mercadopago_create_payment_link`: create a provider preference and return the checkout URL.
- `mercadopago_check_payment_status`: verify a provider payment against its CRM transaction and reconcile its status.

## Focused service tests

Run `php -d zend.assertions=1 upload/api/tests/unit/mercadopago_module_unit.php` from the repository root. The test uses an injected HTTP transport and SQLite memory database; it does not contact Mercado Pago or require real credentials.

## Official provider references

- [Create a Checkout Pro preference](https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro-preferences/create-preference/post)
- [Search payments by external reference](https://www.mercadopago.com.br/developers/en/reference/online-payments/subscriptions/search-payments/get)
- [Mercado Pago Webhooks](https://www.mercadopago.com.br/developers/en/docs/links-and-debts/additional-content/your-integrations/notifications/webhooks)
