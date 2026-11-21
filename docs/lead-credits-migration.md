# Integer lead credits and reconciliation

Lead credits are whole-number entitlements. The default is **1 credit per successfully delivered lead**. Existing whole-number project, team, organization and global pricing overrides remain configured as **credits per delivered lead**, in the existing priority order. Review overrides when reconciling a client; a configured cost of 10 still consumes 10 credits per successful lead.

## Existing balances and the dummy allocation

There is deliberately no automatic money-to-credit exchange rate. Migration `2026_11_21_100000_introduce_integer_lead_credits.php` preserves exact historical decimal values in `legacy_*` columns and retains original transaction IDs, dates, actions, relationships and descriptions. Historical ledger rows are marked `is_legacy` and are excluded from active credit totals. Historical monetary lead costs are preserved separately and are not counted as consumed lead credits.

Existing credit accounts initially have **0 spendable lead credits and require reconciliation**. They cannot fund new deliveries until Super Admin confirms an allocation. Previously delivered leads remain delivered and visible; their original financial transactions are not charged again. Pending old reservations do not exempt a newly completed delivery from its new lead-credit cost.

For the dummy 20,000 entry, open **Lead Credits → Reconcile Lead Credits & Manage Disputes**, select the client, enter the verified remaining quantity (including 0 if appropriate), provide a reason, tick confirmation and confirm. The same section is available in **Client Details → Lead Credits**. The preserved legacy reference is visible to Super Admin for review. Reconciliation writes a separate correction and audit event; it never deletes the dummy ledger or treats it as a real credit entitlement. Stale balance corrections are rejected if credits changed during review.

## Credit management and disputes

Super Admin can add or deduct integer credits with remarks. Requests exceeding available credits are rejected rather than silently clamped. Client requests are quantities, not payments; approval snapshots the approved quantity and can correct a legacy request without altering its original reference. Approval is locked and applied once.

A disputed ledger entry can be flagged with a reason. Resolution can either record a decision without changing credits, or add/deduct a specified quantity through a linked corrective transaction. The original entry remains unchanged. Duplicate resolution cannot apply a second correction. Reconcile an old account before applying a credit correction.

## Delivery and ledger guarantees

The existing delivery transaction commits delivery status, credit consumption and downstream assignment together. Failure rolls them all back. Pending, rejected, duplicate, undelivered and unreconciled records consume nothing. Consumption locks the lead and credit account, and a unique `delivery_charge_lead_id` prevents duplicate deductions. Credit returns use actual active-ledger consumption and do not manufacture refunds from legacy monetary charges or pricing defaults.

Available, remaining, used and total credits are calculated from the current integer balance and actual ledger movements, including credit returns. A pricing change does not recalculate historical usage. Low-credit notifications use the saved client threshold and skip accounts awaiting initial reconciliation.

## UI, API and compatibility

Credit screens, dashboard statistics, client overview, delivery health, requests, transaction history and credit exports use integer quantities. Credit exports have no currency formatting. Old database/API field names such as `balance`, `credit_used`, `lead_cost` and request `amount` remain for compatibility; their current values represent integer credits, not currency. Additional serialized fields include wallet `available_lead_credits`, `remaining_credits`, `used_credits`, `total_credits`, ledger `quantity`, `action`, `remarks`, and request `credits_requested` / `credits_approved`.

Legacy values are hidden from ordinary model serialization. Original immutable audit records remain intact. Monetary property budgets, booking/payment amounts, revenue reports and Meta ad-spend management are restricted to Super Admin presentation. Client/team lead serialization and exports redact monetary fields while retaining operational lead information and lead-credit quantities. Financial notification text is also redacted for other recipients.

## Deployment

1. Back up the database and deploy the complete source and rebuilt assets together. Pause the scheduler/queue during the schema change so old workers cannot write into renamed legacy fields.
2. Run `php artisan migrate --force`, then `php artisan optimize:clear` and `php artisan queue:restart`.
3. Super Admin must reconcile each existing client and review its credits-per-delivered-lead overrides. Existing accounts remain held for new delivery until this review is complete.
4. Resume the existing scheduler/queue. No extra cron or manual queue enrollment is required.

This is a forward migration. Automatic rollback is intentionally disabled to prevent losing financial history or silently interpreting credits as money. A rollback requires a reviewed restore from the deployment backup. Local automated checks use isolated SQLite databases; no live balances are changed by implementation or tests.

## Reviewing legacy delivery consumption

The reconciliation panel opens by default and selects the organization’s billing client, including legacy wallet owners without current billing roles. The duplicate Client Credit Wallets card has been removed from Client Details.

Reconcile the verified allocation first. To count an old successful delivery against it, select its legacy consumption entry, enter a whole credit quantity and reason, confirm, and choose **Mark as Consumption**. This subtracts credits from the reconciled allocation, increases used credits, and links a new consumption entry to the delivered lead. It does not resend the lead or alter the original monetary history. Already-accounted or undelivered leads are rejected, and repeated submissions cannot charge twice. Do not include the same consumption in a net remaining allocation and then deduct it again.
