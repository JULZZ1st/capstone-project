-- MySQL 8.0 final schema derived from all 73 database/migrations/*.php files.
-- Forward migrations were processed in filename order, including changes and removals.
-- CREATE TABLE statements are ordered by foreign key dependencies.
-- 35 tables; 424 columns; 35 primary keys; 22 unique constraints;
-- 83 explicit nonunique indexes; 67 foreign key constraints.
-- Schema definitions only. No SQL or migrations were executed to generate this file.
--
-- Translation limits:
-- users.status uses DEFAULT 'active', the repository fallback for
-- config('auth_defaults.user_status') / AUTH_DEFAULT_USER_STATUS.
-- donors.donor_type uses DEFAULT 'individual', the repository fallback for
-- config('donors.defaults.type') / DONOR_DEFAULT_TYPE.
-- donors.status uses DEFAULT 'active', the repository fallback for
-- config('donors.defaults.status') / DONOR_DEFAULT_STATUS.
-- Deployment environment overrides of these defaults cannot be established from migrations.
-- inventory_items.unit resolves config('inventory.defaults.unit') to the literal 'piece'.
-- Conditional legacy renames/imports depend on pre-existing schemas not defined by
-- these migrations. Undefined legacy tables and their columns are not inferred.
-- Framework-managed migration repository and package-only schemas are outside this folder.
-- InnoDB is used as requested; utf8mb4 / utf8mb4_unicode_ci follow repository defaults.
-- MySQL automatically creates any supporting foreign key indexes not already covered
-- by a declared index. All explicitly declared indexes are included below.

-- Migration sources: 0001_01_01_000000_create_users_table.php
CREATE TABLE `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 0001_01_01_000000_create_users_table.php
CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 0001_01_01_000001_create_cache_table.php
CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 0001_01_01_000001_create_cache_table.php
CREATE TABLE `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 0001_01_01_000002_create_jobs_table.php
CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 0001_01_01_000002_create_jobs_table.php
CREATE TABLE `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 0001_01_01_000002_create_jobs_table.php
CREATE TABLE `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_06_29_000005_create_cash_transactions_table.php
CREATE TABLE `cash_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `donor_name` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `purpose` VARCHAR(255) NOT NULL,
  `received_at` TIMESTAMP NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'verified',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_11_000001_create_roles_table_and_normalize_users.php
CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_25_000002_create_system_security_administration_tables.php
CREATE TABLE `security_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `security_permissions_key_unique` (`key`),
  KEY `security_permissions_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_17_000001_create_workspaces_and_memberships.php
CREATE TABLE `workspaces` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspaces_slug_unique` (`slug`),
  KEY `workspaces_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_17_000004_create_workspace_backfill_issues_table.php
CREATE TABLE `workspace_backfill_issues` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(100) NOT NULL,
  `record_id` BIGINT UNSIGNED NOT NULL,
  `issue_type` VARCHAR(100) NOT NULL,
  `details` TEXT NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_backfill_issue_unique` (`table_name`, `record_id`, `issue_type`),
  KEY `workspace_backfill_issues_table_name_issue_type_index` (`table_name`, `issue_type`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 0001_01_01_000000_create_users_table.php, 2026_06_29_000003_add_role_to_users_table.php, 2026_07_11_000001_create_roles_table_and_normalize_users.php, 2026_07_12_000001_drop_legacy_role_from_users_table.php, 2026_10_08_000001_add_must_change_password_to_users_table.php, 2026_10_17_000002_add_platform_super_admin_to_users_table.php, 2026_10_24_000001_remove_super_admin_role_and_flag.php
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL,
  `password` VARCHAR(255) NOT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `last_login_at` TIMESTAMP NULL,
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_status_index` (`role_id`, `status`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_06_29_000001_create_donors_table.php, 2026_07_11_000002_add_profile_fields_to_donors_table.php, 2026_09_02_000001_add_first_and_last_name_to_donors_table.php, 2026_10_08_000002_add_structured_address_fields_to_donors_table.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php, 2026_10_29_000006_add_donor_identity_fields.php, 2026_10_29_000006_add_region_to_donors_table.php
CREATE TABLE `donors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `donor_type` VARCHAR(50) NOT NULL DEFAULT 'individual',
  `first_name` VARCHAR(255) NULL,
  `middle_name` VARCHAR(255) NULL,
  `suffix` VARCHAR(255) NULL,
  `last_name` VARCHAR(255) NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255) NULL,
  `address` VARCHAR(255) NOT NULL,
  `barangay` VARCHAR(255) NULL,
  `city_municipality` VARCHAR(255) NULL,
  `province` VARCHAR(255) NULL,
  `region` VARCHAR(255) NULL,
  `barangay_area` VARCHAR(255) NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `contact_number` VARCHAR(255) NOT NULL,
  `preferred_contact_method` VARCHAR(50) NULL,
  `email` VARCHAR(255) NULL,
  `tin` VARCHAR(30) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `donors_donor_type_status_index` (`donor_type`, `status`),
  KEY `donors_full_name_index` (`full_name`),
  KEY `donors_contact_number_index` (`contact_number`),
  KEY `donors_first_name_last_name_index` (`first_name`, `last_name`),
  KEY `donors_workspace_id_index` (`workspace_id`),
  CONSTRAINT `donors_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_08_02_000001_create_inventory_categories_and_units_tables.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `inventory_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `key` VARCHAR(255) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_categories_workspace_key_unique` (`workspace_id`, `key`),
  KEY `inventory_categories_is_active_index` (`is_active`),
  KEY `inventory_categories_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_categories_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_08_02_000001_create_inventory_categories_and_units_tables.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `inventory_units` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `key` VARCHAR(255) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_units_workspace_key_unique` (`workspace_id`, `key`),
  KEY `inventory_units_is_active_index` (`is_active`),
  KEY `inventory_units_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_units_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_30_000006_create_dv_number_sequences_table.php
CREATE TABLE `dv_number_sequences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `period` VARCHAR(7) NOT NULL,
  `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dv_number_sequences_workspace_id_period_unique` (`workspace_id`, `period`),
  CONSTRAINT `dv_number_sequences_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_06_29_000002_create_donations_table.php
CREATE TABLE `donations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `donor_id` BIGINT UNSIGNED NOT NULL,
  `donation_type` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` TEXT NULL,
  `received_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `donations_donor_id_foreign` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_06_29_000006_create_bank_reconciliations_table.php, 2026_10_12_000001_add_reconciliation_period_integrity_fields.php, 2026_10_12_000004_add_period_workflow_fields.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `bank_reconciliations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `date` DATE NOT NULL,
  `period_start` DATE NULL,
  `period_end` DATE NULL,
  `opening_balance` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `bank_ending_balance` DECIMAL(12,2) NULL,
  `cash_in` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `calculated_bank_cash_in` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `cash_out` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `calculated_bank_cash_out` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `financial_ledger_cash_in` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `financial_ledger_cash_out` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `closing_balance` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `financial_ledger_ending_balance` DECIMAL(12,2) NULL,
  `reconciliation_difference` DECIMAL(12,2) NULL,
  `accounting_adjustment` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `adjusted_bank_balance` DECIMAL(12,2) NULL,
  `unresolved_discrepancy_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `prepared_by` BIGINT UNSIGNED NULL,
  `submitted_at` TIMESTAMP NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `correction_requested_by` BIGINT UNSIGNED NULL,
  `correction_requested_at` TIMESTAMP NULL,
  `correction_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_by` BIGINT UNSIGNED NULL,
  `locked_at` TIMESTAMP NULL,
  `calculated_at` TIMESTAMP NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `bank_reconciliations_period_start_period_end_index` (`period_start`, `period_end`),
  KEY `bank_reconciliations_status_period_end_index` (`status`, `period_end`),
  KEY `bank_reconciliations_status_period_start_period_end_index` (`status`, `period_start`, `period_end`),
  KEY `bank_reconciliations_workspace_id_index` (`workspace_id`),
  CONSTRAINT `bank_reconciliations_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_prepared_by_foreign` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_correction_requested_by_foreign` FOREIGN KEY (`correction_requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_12_000003_create_contributions_table.php, 2026_08_23_000001_add_void_fields_to_contributions_table.php, 2026_09_05_000004_add_receipt_number_to_contributions_table.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php, 2026_10_27_000001_add_payment_method_name_to_contributions_table.php, 2026_10_27_000002_repair_contributions_donor_foreign_key.php, 2026_10_28_000001_remove_campaigns_and_supporting_references.php, 2026_10_28_000002_add_photo_path_to_contributions.php, 2026_10_29_000001_add_donation_receipt_details_to_contributions_table.php, 2026_10_29_000002_add_cash_document_tracking_to_contributions.php, 2026_10_29_000003_add_cash_purpose_and_bank_details_to_contributions.php, 2026_10_29_000005_add_cash_deed_review_fields_to_contributions.php
CREATE TABLE `contributions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `receipt_number` VARCHAR(32) NULL,
  `donor_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `verified_by` BIGINT UNSIGNED NULL,
  `type` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(12,2) NULL,
  `purpose_type` VARCHAR(30) NULL,
  `purpose_name` VARCHAR(255) NULL,
  `payment_method` VARCHAR(255) NULL,
  `payment_method_name` VARCHAR(255) NULL,
  `reference_number` VARCHAR(100) NULL,
  `check_number` VARCHAR(100) NULL,
  `bank_name` VARCHAR(150) NULL,
  `registered_bank_account` VARCHAR(150) NULL,
  `official_receipt_number` VARCHAR(100) NULL,
  `certificate_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `certificate_status` VARCHAR(30) NOT NULL DEFAULT 'not_requested',
  `certificate_issued_by` BIGINT UNSIGNED NULL,
  `certificate_issued_at` TIMESTAMP NULL,
  `deed_declined` TINYINT(1) NOT NULL DEFAULT 0,
  `deed_decline_reason` TEXT NULL,
  `document_review_status` VARCHAR(40) NOT NULL DEFAULT 'not_required',
  `document_request_reason` TEXT NULL,
  `documents_requested_by` BIGINT UNSIGNED NULL,
  `documents_requested_at` TIMESTAMP NULL,
  `donor_tin` VARCHAR(30) NULL,
  `notes` TEXT NULL,
  `photo_path` VARCHAR(255) NULL,
  `received_at` TIMESTAMP NULL,
  `verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `voided_at` TIMESTAMP NULL,
  `voided_by` BIGINT UNSIGNED NULL,
  `void_reason` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contributions_receipt_number_unique` (`receipt_number`),
  UNIQUE KEY `contributions_workspace_payment_reference_unique` (`workspace_id`, `payment_method`, `reference_number`),
  KEY `contributions_type_index` (`type`),
  KEY `contributions_status_index` (`status`),
  KEY `contributions_payment_method_index` (`payment_method`),
  KEY `contributions_received_at_index` (`received_at`),
  KEY `contributions_donor_id_index` (`donor_id`),
  KEY `contributions_created_by_index` (`created_by`),
  KEY `contributions_verified_by_index` (`verified_by`),
  KEY `contributions_type_status_received_at_index` (`type`, `status`, `received_at`),
  KEY `contributions_voided_at_index` (`voided_at`),
  KEY `contributions_workspace_id_index` (`workspace_id`),
  KEY `contributions_payment_method_check_number_index` (`payment_method`, `check_number`),
  KEY `contributions_certificate_requested_certificate_status_index` (`certificate_requested`, `certificate_status`),
  KEY `contributions_type_purpose_type_index` (`type`, `purpose_type`),
  KEY `contrib_doc_review_req_at_idx` (`document_review_status`, `documents_requested_at`),
  CONSTRAINT `contributions_donor_id_foreign` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `contributions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_certificate_issued_by_foreign` FOREIGN KEY (`certificate_issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_documents_requested_by_foreign` FOREIGN KEY (`documents_requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_25_000002_create_system_security_administration_tables.php
CREATE TABLE `user_security_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_enforced` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_verified_at` TIMESTAMP NULL,
  `security_key_suffix` VARCHAR(16) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_security_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `user_security_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_25_000002_create_system_security_administration_tables.php
CREATE TABLE `security_permission_user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `security_permission_id` BIGINT UNSIGNED NOT NULL,
  `granted_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `security_permission_user_unique` (`user_id`, `security_permission_id`),
  CONSTRAINT `security_permission_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_permission_user_security_permission_id_foreign` FOREIGN KEY (`security_permission_id`) REFERENCES `security_permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_permission_user_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_25_000002_create_system_security_administration_tables.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `security_activity_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(255) NOT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `metadata` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `occurred_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `security_activity_events_action_index` (`action`),
  KEY `security_activity_events_occurred_at_index` (`occurred_at`),
  KEY `security_activity_events_workspace_id_index` (`workspace_id`),
  CONSTRAINT `security_activity_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_activity_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_activity_events_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_08_23_000002_create_cash_disbursements_table.php, 2026_09_12_000001_add_request_details_to_cash_disbursements_table.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php, 2026_10_28_000001_remove_campaigns_and_supporting_references.php, 2026_10_30_000002_add_signed_voucher_review_to_cash_disbursements.php, 2026_10_30_000003_add_payment_status_to_cash_disbursements.php, 2026_10_30_000005_add_annex12_fields_to_cash_disbursements.php, 2026_10_30_000007_add_location_fields_to_cash_disbursements_table.php
CREATE TABLE `cash_disbursements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `purpose` TEXT NOT NULL,
  `recipient` VARCHAR(255) NULL,
  `payee_address` VARCHAR(255) NULL,
  `province` VARCHAR(255) NULL,
  `city_municipality` VARCHAR(255) NULL,
  `barangay` VARCHAR(255) NULL,
  `payee_tin` VARCHAR(30) NULL,
  `fund_source` VARCHAR(255) NULL,
  `obr_number` VARCHAR(255) NULL,
  `dv_number` VARCHAR(30) NULL,
  `treasurer_certified_by` BIGINT UNSIGNED NULL,
  `treasurer_certified_at` TIMESTAMP NULL,
  `treasurer_certification_notes` TEXT NULL,
  `requested_date` DATE NULL,
  `notes` TEXT NULL,
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `signed_voucher_status` VARCHAR(30) NOT NULL DEFAULT 'missing',
  `signed_voucher_request_reason` TEXT NULL,
  `signed_voucher_requested_by` BIGINT UNSIGNED NULL,
  `signed_voucher_requested_at` TIMESTAMP NULL,
  `payment_status` VARCHAR(30) NOT NULL DEFAULT 'not_disbursed',
  `disbursed_at` TIMESTAMP NULL,
  `approved_at` TIMESTAMP NULL,
  `rejected_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `voided_at` TIMESTAMP NULL,
  `voided_by` BIGINT UNSIGNED NULL,
  `void_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_disbursements_workspace_id_dv_number_unique` (`workspace_id`, `dv_number`),
  KEY `cash_disbursements_status_index` (`status`),
  KEY `cash_disbursements_voided_at_index` (`voided_at`),
  KEY `cash_disbursements_workspace_id_index` (`workspace_id`),
  CONSTRAINT `cash_disbursements_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cash_disbursements_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_disbursements_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_disbursements_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_disbursements_signed_voucher_requested_by_foreign` FOREIGN KEY (`signed_voucher_requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_disbursements_treasurer_certified_by_foreign` FOREIGN KEY (`treasurer_certified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_09_05_000001_create_inventory_subcategories_and_add_to_contribution_items.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `inventory_subcategories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `inventory_category_id` BIGINT UNSIGNED NOT NULL,
  `key` VARCHAR(255) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_subcategories_inventory_category_id_key_unique` (`inventory_category_id`, `key`),
  KEY `inventory_subcategories_is_active_index` (`is_active`),
  KEY `inventory_subcategories_inventory_category_id_is_active_index` (`inventory_category_id`, `is_active`),
  KEY `inventory_subcategories_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_subcategories_inventory_category_id_foreign` FOREIGN KEY (`inventory_category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_subcategories_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_17_000001_create_workspaces_and_memberships.php
CREATE TABLE `workspace_memberships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_memberships_workspace_id_user_id_unique` (`workspace_id`, `user_id`),
  KEY `workspace_memberships_status_index` (`status`),
  KEY `workspace_memberships_user_id_status_index` (`user_id`, `status`),
  CONSTRAINT `workspace_memberships_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workspace_memberships_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workspace_memberships_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `workspace_memberships_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_06_29_000004_create_inventory_items_table.php, 2026_07_15_000001_add_inventory_tracking_fields_to_inventory_items_table.php, 2026_09_05_000005_add_inventory_subcategory_id_to_inventory_items_table.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `inventory_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `inventory_subcategory_id` BIGINT UNSIGNED NULL,
  `unit` VARCHAR(255) NOT NULL DEFAULT 'piece',
  `quantity` INT NOT NULL DEFAULT 0,
  `current_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `minimum_reserve_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `estimated_unit_value` DECIMAL(12,2) NULL,
  `expires_at` DATE NULL,
  `batch_number` VARCHAR(100) NULL,
  `storage_location` VARCHAR(255) NOT NULL DEFAULT 'Main Storage',
  `notes` TEXT NULL,
  `description` TEXT NULL,
  `received_at` TIMESTAMP NULL,
  `last_audited_at` TIMESTAMP NULL,
  `last_movement_at` TIMESTAMP NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'received',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_items_category_index` (`category`),
  KEY `inventory_items_status_index` (`status`),
  KEY `inventory_items_storage_location_index` (`storage_location`),
  KEY `inventory_items_expires_at_index` (`expires_at`),
  KEY `inventory_items_last_audited_at_index` (`last_audited_at`),
  KEY `inventory_items_last_movement_at_index` (`last_movement_at`),
  KEY `inventory_items_category_status_index` (`category`, `status`),
  KEY `inventory_items_status_expires_at_index` (`status`, `expires_at`),
  KEY `inventory_items_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_inventory_subcategory_id_foreign` FOREIGN KEY (`inventory_subcategory_id`) REFERENCES `inventory_subcategories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_12_000004_create_contribution_items_table.php, 2026_09_05_000001_create_inventory_subcategories_and_add_to_contribution_items.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php, 2026_10_29_000004_add_item_detail_fields_to_contribution_items.php
CREATE TABLE `contribution_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `contribution_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `category_description` VARCHAR(255) NULL,
  `subcategory` VARCHAR(255) NULL,
  `quantity` DECIMAL(12,2) NOT NULL,
  `unit` VARCHAR(255) NOT NULL,
  `condition` VARCHAR(255) NOT NULL,
  `expiration_date` DATE NULL,
  `brand` VARCHAR(150) NULL,
  `model` VARCHAR(150) NULL,
  `serial_number` VARCHAR(150) NULL,
  `estimated_value` DECIMAL(12,2) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `contribution_items_category_index` (`category`),
  KEY `contribution_items_condition_index` (`condition`),
  KEY `contribution_items_contribution_id_index` (`contribution_id`),
  KEY `contribution_items_subcategory_index` (`subcategory`),
  KEY `contribution_items_workspace_id_index` (`workspace_id`),
  CONSTRAINT `contribution_items_contribution_id_foreign` FOREIGN KEY (`contribution_id`) REFERENCES `contributions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `contribution_items_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_25_000001_create_bank_ledger_entries_table.php, 2026_09_12_000002_add_transaction_type_to_bank_ledger_entries_table.php, 2026_09_19_000001_add_discrepancy_fields_to_bank_ledger_entries_table.php, 2026_09_19_000002_add_outgoing_reconciliation_fields_to_bank_ledger_entries_table.php, 2026_10_12_000002_add_reconciliation_matching_fields.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `bank_ledger_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `statement_id` VARCHAR(255) NOT NULL,
  `duplicate_fingerprint` VARCHAR(64) NULL,
  `description` VARCHAR(255) NOT NULL,
  `reference_number` VARCHAR(100) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` VARCHAR(255) NOT NULL DEFAULT 'deposit',
  `payment_method` VARCHAR(255) NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'unmatched',
  `matched_contribution_id` BIGINT UNSIGNED NULL,
  `matched_by` BIGINT UNSIGNED NULL,
  `matched_at` TIMESTAMP NULL,
  `reconciled_by` BIGINT UNSIGNED NULL,
  `reconciled_at` TIMESTAMP NULL,
  `notes` TEXT NULL,
  `discrepancy_amount` DECIMAL(12,2) NULL,
  `discrepancy_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_ledger_entries_matched_contribution_id_unique` (`matched_contribution_id`),
  UNIQUE KEY `bank_ledger_entries_workspace_statement_unique` (`workspace_id`, `statement_id`),
  KEY `bank_ledger_entries_status_transaction_date_index` (`status`, `transaction_date`),
  KEY `bank_ledger_entries_reference_number_index` (`reference_number`),
  KEY `bank_ledger_entries_payment_method_index` (`payment_method`),
  KEY `bank_ledger_entries_transaction_type_index` (`transaction_type`),
  KEY `bank_ledger_entries_type_status_date_index` (`transaction_type`, `status`, `transaction_date`),
  KEY `bank_ledger_entries_duplicate_fingerprint_index` (`duplicate_fingerprint`),
  KEY `bank_ledger_entries_workspace_id_index` (`workspace_id`),
  CONSTRAINT `bank_ledger_entries_matched_contribution_id_foreign` FOREIGN KEY (`matched_contribution_id`) REFERENCES `contributions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_matched_by_foreign` FOREIGN KEY (`matched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_reconciled_by_foreign` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_28_000001_create_contribution_evidence_table.php, 2026_10_29_000002_add_cash_document_tracking_to_contributions.php
CREATE TABLE `contribution_evidence` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `contribution_id` BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(50) NOT NULL DEFAULT 'supporting_evidence',
  `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
  `path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `size` BIGINT UNSIGNED NOT NULL,
  `uploaded_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `contribution_evidence_workspace_id_contribution_id_index` (`workspace_id`, `contribution_id`),
  KEY `contribution_evidence_contribution_id_document_type_index` (`contribution_id`, `document_type`),
  CONSTRAINT `contribution_evidence_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contribution_evidence_contribution_id_foreign` FOREIGN KEY (`contribution_id`) REFERENCES `contributions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contribution_evidence_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_30_000001_create_disbursement_vouchers_table.php
CREATE TABLE `disbursement_vouchers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `cash_disbursement_id` BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(50) NOT NULL DEFAULT 'signed_voucher',
  `review_status` VARCHAR(30) NOT NULL DEFAULT 'pending_review',
  `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
  `path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `size` BIGINT UNSIGNED NOT NULL,
  `uploaded_by` BIGINT UNSIGNED NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` TIMESTAMP NULL,
  `review_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `disbursement_vouchers_review_status_index` (`review_status`),
  KEY `disbursement_vouchers_workspace_id_cash_disbursement_id_index` (`workspace_id`, `cash_disbursement_id`),
  CONSTRAINT `disbursement_vouchers_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disbursement_vouchers_cash_disbursement_id_foreign` FOREIGN KEY (`cash_disbursement_id`) REFERENCES `cash_disbursements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disbursement_vouchers_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disbursement_vouchers_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_30_000004_create_cash_disbursement_payments_table.php
CREATE TABLE `cash_disbursement_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `cash_disbursement_id` BIGINT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(30) NOT NULL,
  `reference_number` VARCHAR(255) NULL,
  `released_by` BIGINT UNSIGNED NOT NULL,
  `released_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_disbursement_payments_cash_disbursement_id_unique` (`cash_disbursement_id`),
  KEY `cash_disbursement_payments_workspace_id_payment_method_index` (`workspace_id`, `payment_method`),
  CONSTRAINT `cash_disbursement_payments_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_disbursement_payments_cash_disbursement_id_foreign` FOREIGN KEY (`cash_disbursement_id`) REFERENCES `cash_disbursements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_disbursement_payments_released_by_foreign` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_07_15_000002_create_inventory_stock_movements_table.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `inventory_stock_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `inventory_item_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `movement_type` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL,
  `quantity_before` DECIMAL(12,2) NOT NULL,
  `quantity_after` DECIMAL(12,2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `reference_number` VARCHAR(100) NULL,
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_stock_movements_movement_type_index` (`movement_type`),
  KEY `inventory_stock_movements_reason_index` (`reason`),
  KEY `inventory_stock_movements_reference_number_index` (`reference_number`),
  KEY `inventory_stock_movements_inventory_item_id_index` (`inventory_item_id`),
  KEY `inventory_stock_movements_created_by_index` (`created_by`),
  KEY `inventory_stock_movements_created_at_index` (`created_at`),
  KEY `inventory_stock_movements_inventory_item_id_created_at_index` (`inventory_item_id`, `created_at`),
  KEY `inventory_stock_movements_movement_type_created_at_index` (`movement_type`, `created_at`),
  KEY `inventory_stock_movements_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_stock_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_stock_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_stock_movements_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_09_12_000003_create_bank_withdrawal_disbursement_matches_table.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `bank_withdrawal_disbursement_matches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `bank_ledger_entry_id` BIGINT UNSIGNED NOT NULL,
  `cash_disbursement_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_withdrawal_disbursement_unique` (`bank_ledger_entry_id`, `cash_disbursement_id`),
  UNIQUE KEY `cash_disbursement_single_withdrawal_unique` (`cash_disbursement_id`),
  KEY `bank_withdrawal_disbursement_matches_workspace_id_index` (`workspace_id`),
  CONSTRAINT `bank_ledger_match_fk` FOREIGN KEY (`bank_ledger_entry_id`) REFERENCES `bank_ledger_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_disbursement_match_fk` FOREIGN KEY (`cash_disbursement_id`) REFERENCES `cash_disbursements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_withdrawal_disbursement_matches_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration sources: 2026_10_12_000003_create_reconciliation_discrepancies_table.php, 2026_10_12_000005_add_timing_clearance_fields.php, 2026_10_17_000003_add_workspace_ids_to_business_tables.php
CREATE TABLE `reconciliation_discrepancies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NULL,
  `bank_reconciliation_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `classification` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `explanation` TEXT NOT NULL,
  `evidence_reference` VARCHAR(255) NULL,
  `resolution_status` VARCHAR(255) NOT NULL DEFAULT 'unresolved',
  `adjustment_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `carry_forward` TINYINT(1) NOT NULL DEFAULT 0,
  `resolved_by` BIGINT UNSIGNED NULL,
  `resolved_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `cleared_bank_ledger_entry_id` BIGINT UNSIGNED NULL,
  `cleared_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reconciliation_discrepancies_cleared_bank_ledger_entry_id_unique` (`cleared_bank_ledger_entry_id`),
  KEY `recon_discrepancies_period_status_idx` (`bank_reconciliation_id`, `resolution_status`),
  KEY `reconciliation_discrepancies_workspace_id_index` (`workspace_id`),
  CONSTRAINT `reconciliation_discrepancies_bank_reconciliation_id_foreign` FOREIGN KEY (`bank_reconciliation_id`) REFERENCES `bank_reconciliations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reconciliation_discrepancies_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recon_discrepancies_cleared_entry_fk` FOREIGN KEY (`cleared_bank_ledger_entry_id`) REFERENCES `bank_ledger_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliation_discrepancies_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
