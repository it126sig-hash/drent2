# Rent-to-Rent Performance Optimization Tasks

- [x] Create database migration for cached columns (`cached_total_amount`, `cached_paid_amount`, `cached_payment_status`) on `rent_to_rent_debts`.
- [x] Implement `rent-to-rent:sync-cache` Artisan command to sync legacy data.
- [x] Refactor `RentToRentService::syncDetail` and `updateDebtAmount` to populate cached columns.
- [x] Refactor `RentToRentService::createBill` to update cached columns.
- [x] Refactor `RentToRentService::storePayment`, `approveVoid`, `rejectVoid` to recalculate cached columns.
- [x] Refactor `RentToRentService::listDebts` to use database-level pagination, aggregation, and filtering.
- [x] Update `RentToRentController` and `RentToRentDebtResource` as needed.
- [x] Run the migration and the sync-cache command.
