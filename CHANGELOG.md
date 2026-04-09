# Changelog

---

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog,
and this project adheres to Semantic Versioning.

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