# Lead archive management

## Deployment

Deploy the code and built assets, then run:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

Do not run delivery or assignment workers on the new code before applying the migration. No existing lead state, timestamp format or assignment is rewritten by this migration.

## Operation

Only an active Super Admin can archive, inspect, restore or permanently delete archived leads. In the lead table or detail drawer, open Lead Actions → Archive. Confirm the action and optionally enter a reason. Super Admin → Archived Leads provides search, stage/user/date filters, sorting, Excel export, details, restore and permanent deletion confirmations.

Archiving changes only archive metadata. Assignments, stages, follow-ups and activity remain in the database. Archived leads and their dependent sales records are excluded from normal model queries, including tenant-scope bypasses used by background jobs. Direct lead API binding, public proposals and namespaced document downloads cannot reveal archived leads. Transfer items already queued for a batch return to pending; a pending archived lead cannot enter a delivery cycle.

Restoration removes archive metadata and preserves the current saved assignment and sales state. It sets an assignment hold by default, preventing automatic assignment of restored unassigned leads. Super Admin can explicitly enable automatic assignment in the restore confirmation. Existing delivery verification, consent, credit and scheduling rules continue to apply; restoration does not itself push a lead to another client.

Permanent deletion requires an archived lead and confirmation. Leads with credit transactions, payments or delivery history are retained. Other related rows use the existing foreign-key cascade/null/restrict rules, inside a transaction. Archive, restore and permanent-delete audit entries remain append-only even after the lead is deleted. Other database restrictions roll back the operation rather than deleting evidence.

## Verification

`php vendor/phpunit/phpunit/phpunit tests/Feature/Leads/LeadArchiveTest.php`

The feature tests cover role restrictions through HTTP and Livewire, archived model/API invisibility, restoration with original history, automatic-assignment holds, archive export and financial retention. Production cron and delivery still require verification in the deployed environment.
