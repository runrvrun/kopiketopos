-- Step 2 of docs/deployment.md: free the table names v2 needs by renaming every v1 table to v1_*.
-- Run in phpMyAdmin › k3346760_kopiketopos › SQL. Nothing is deleted.
RENAME TABLE
  failed_jobs TO v1_failed_jobs,
  migrations TO v1_migrations,
  oauth_access_tokens TO v1_oauth_access_tokens,
  oauth_auth_codes TO v1_oauth_auth_codes,
  oauth_clients TO v1_oauth_clients,
  oauth_personal_access_clients TO v1_oauth_personal_access_clients,
  oauth_refresh_tokens TO v1_oauth_refresh_tokens,
  password_resets TO v1_password_resets,
  product_transaction TO v1_product_transaction,
  products TO v1_products,
  stores TO v1_stores,
  transaction_items TO v1_transaction_items,
  transactions TO v1_transactions,
  users TO v1_users;
