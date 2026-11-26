# Changelog

---

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog,
and this project adheres to Semantic Versioning.

## 1.1.0
- Add "Connect with SimPay" (OAuth) - the API key, IPN URL and IPN signature key are configured automatically after logging in to SimPay and selecting a payment service.
- Add "Connection method" setting (Connect with SimPay / manual credentials); existing installations with credentials keep the manual method.
- Require `simpay/ecommerce` SDK (installed automatically by Composer).
- Use the SDK for API calls (transactions, refunds), IPN validation and IP allowlist instead of own implementations.

---

## 1.0.3
- Add online refund improvements: support partial refunds from Credit Memo.
- Send optional `amount` in refund request body to SimPay API (full refund when amount is omitted).
- Restrict module usage to PLN currency only.
- Improve refund conflict/error handling and refund status updates through IPN.

## 1.0.2
- Add translations.
- Add Twisto data to payment request for future integration.

---

## 1.0.1
- Update README and composer requirements.

---

## 1.0.0
- Initial release.

---