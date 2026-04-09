# SimPay Payment Gateway for Magento 2

Moduł płatności SimPay dla Magento 2 (wersje 2.4.x).

Integracja realizowana jest w modelu przekierowania (redirect) z obsługą notyfikacji IPN (webhook).

---

## Kompatybilność

- Magento 2.4.x
- PHP zgodne z wymaganiami używanej wersji Magento
- Wymagane rozszerzenia PHP:
    - `curl`
    - `hash`

---

## Funkcjonalności

Moduł umożliwia:

- Tworzenie transakcji w systemie SimPay
- Przekierowanie klienta do bramki płatności
- Obsługę notyfikacji IPN (webhook)
- Automatyczną zmianę statusów zamówień
- Zwroty online z poziomu Credit Memo (refund API)
- Opcjonalną walidację adresu IP notyfikacji
---

## Przepływ płatności

1. Klient składa zamówienie.
2. Zamówienie otrzymuje status `pending_payment`.
3. Klient zostaje przekierowany do SimPay.
4. SimPay wysyła notyfikację IPN.
5. Moduł:
    - weryfikuje podpis,
    - sprawdza `service_id`,
    - porównuje kwotę,
    - aktualizuje status zamówienia.

## Zwroty online

- Zwrot uruchamiany jest z panelu Magento przez **Credit Memo -> Refund Online**.
- Moduł wywołuje endpoint SimPay: `POST /payment/{serviceId}/transactions/{transactionId}/refunds`.
- `transactionId` pobierane jest z `payment.additional_information[simpay_transaction_id]` (fallback: `last_trans_id`).
- Pole `amount` w request body jest opcjonalne: brak `amount` oznacza zwrot pełny, podanie `amount` oznacza zwrot częściowy.
- `refund_id` i statusy zwrotów są zapisywane w `additional_information` płatności.
- Finalny status zwrotu jest aktualizowany przez IPN.

---

## Instalacja

```bash
composer require simpay/magento24-payment
php bin/magento module:enable SimPay_Magento
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

## Konfiguracja

1. Przejdź do:

   **Admin → Stores → Configuration → Sales → Payment Methods**

2. Odszukaj sekcję **SimPay**

3. Uzupełnij poniższe pola:

| Parametr | Opis |
|-----------|------|
| Enabled | Włączenie metody płatności |
| Title | Nazwa wyświetlana klientowi |
| Service ID | ID usługi z panelu SimPay |
| API password | Token Bearer z panelu SimPay |
| IPN signature key | Klucz do weryfikacji webhook |
| IPN check IP | Włączenie walidacji adresu IP dla notyfikacji |
| Webhook URL | Adres webhook do ustawienia w panelu SimPay |

> **Uwaga:** Adres webhook jest wymagany do poprawnego działania modułu. Skopiuj go i ustaw w panelu SimPay w konfiguracji usługi.
---
