-- ROLLBACK ONLY: remove the v2 tables and give the v1 tables their names back.
-- Run in phpMyAdmin › k3346760_kopiketopos › SQL, then restore the v1 folder (docs/deployment.md).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS cache, cache_locks, category_product, customers, failed_jobs, job_batches, jobs,
  migrations, password_reset_tokens, payment_accounts, product_categories, product_transaction,
  products, sessions, store_user, stores, transactions, users;
SET FOREIGN_KEY_CHECKS = 1;
RENAME TABLE
  v1_failed_jobs TO failed_jobs,
  v1_migrations TO migrations,
  v1_oauth_access_tokens TO oauth_access_tokens,
  v1_oauth_auth_codes TO oauth_auth_codes,
  v1_oauth_clients TO oauth_clients,
  v1_oauth_personal_access_clients TO oauth_personal_access_clients,
  v1_oauth_refresh_tokens TO oauth_refresh_tokens,
  v1_password_resets TO password_resets,
  v1_product_transaction TO product_transaction,
  v1_products TO products,
  v1_stores TO stores,
  v1_transaction_items TO transaction_items,
  v1_transactions TO transactions,
  v1_users TO users;
