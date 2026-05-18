# Optimize Rent-to-Rent List Performance

The Rent-to-Rent transaction list page (`RentToRentListView.vue`) is currently experiencing severe loading delays because the backend API (`RentToRentService::listDebts()`) fetches all open transactions into memory to calculate totals (total amount, paid amount, remaining amount) and determine payment statuses. This results in heavy memory consumption and slow response times, especially as the number of transactions grows, similar to the previous Receivable performance bottleneck. 

## Proposed Changes

We will introduce cached, denormalized columns on the `rent_to_rent_debts` table so that we can perform pagination, filtering, and summary calculations directly in SQL instead of doing it in PHP memory.

### 1. Database Migration
Create a migration to add cached columns to `rent_to_rent_debts`:
- `cached_total_amount` (unsigned big integer, default 0)
- `cached_paid_amount` (unsigned big integer, default 0)
- `cached_payment_status` (string, default 'open')
Add indexes for `cached_payment_status` to speed up filtering.

### 2. RentToRentService Optimization
Modify `RentToRentService.php` to leverage these new columns:
- Refactor `listDebts()` to use `paginate()` directly on the query builder.
- Calculate `summary` using SQL `SUM()` aggregate functions (`SUM(cached_total_amount)`, `SUM(cached_paid_amount)`).
- Filter using `where('cached_payment_status', ...)` instead of `filter()` collections in memory.
- Implement search filtering using `whereHas` or join-based query scopes for booking code, customer, unit, etc.

### 3. Maintain Cached Data
Update all mutation points in `RentToRentService` to keep these cached columns perfectly in sync:
- `syncDetail()`: Calculate initial total amount based on `unit` base price & duration.
- `updateDebtAmount()`: Update total amount when manual override happens.
- `createBill()`: Update total amount to match the bill item and set status to 'billed'.
- `storePayment()`, `approveVoid()`, `rejectVoid()`: Recalculate `cached_paid_amount` and `cached_payment_status` when payments happen or void requests are approved.

### 4. Controller & Resource Adjustments
- `RentToRentController`: Update filters to pass proper pagination parameters to SQL queries.
- `RentToRentDebtResource`: Use the cached columns where applicable to avoid redundant eager loading triggers per item.

## User Review Required
> [!IMPORTANT]
> This optimization introduces a new migration file. You will need to run `php artisan migrate` after this implementation is complete.

## Open Questions
- Is there any legacy data that needs a one-time command to seed the new `cached_*` columns, or can we just create an Artisan command (`php artisan rent-to-rent:sync-cache`) for you to run once?

## Verification Plan

### Automated Tests
- N/A

### Manual Verification
- Open the Rent to Rent page, observe the loading speed.
- Verify that totals, paid amounts, and statuses reflect accurately.
- Perform search and filter, ensuring pagination works smoothly at the database level.
- Add an override amount and verify the summary and cached columns update in real-time.
