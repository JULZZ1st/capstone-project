-- Verified local backup of barangay_donation_system
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `bank_ledger_entries`;
CREATE TABLE `bank_ledger_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `statement_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `duplicate_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'deposit',
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unmatched',
  `matched_contribution_id` bigint unsigned DEFAULT NULL,
  `matched_by` bigint unsigned DEFAULT NULL,
  `matched_at` timestamp NULL DEFAULT NULL,
  `reconciled_by` bigint unsigned DEFAULT NULL,
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `discrepancy_amount` decimal(12,2) DEFAULT NULL,
  `discrepancy_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_ledger_entries_matched_contribution_id_unique` (`matched_contribution_id`),
  UNIQUE KEY `bank_ledger_entries_workspace_statement_unique` (`workspace_id`,`statement_id`),
  KEY `bank_ledger_entries_matched_by_foreign` (`matched_by`),
  KEY `bank_ledger_entries_status_transaction_date_index` (`status`,`transaction_date`),
  KEY `bank_ledger_entries_reference_number_index` (`reference_number`),
  KEY `bank_ledger_entries_payment_method_index` (`payment_method`),
  KEY `bank_ledger_entries_transaction_type_index` (`transaction_type`),
  KEY `bank_ledger_entries_type_status_date_index` (`transaction_type`,`status`,`transaction_date`),
  KEY `bank_ledger_entries_reconciled_by_foreign` (`reconciled_by`),
  KEY `bank_ledger_entries_duplicate_fingerprint_index` (`duplicate_fingerprint`),
  KEY `bank_ledger_entries_workspace_id_index` (`workspace_id`),
  CONSTRAINT `bank_ledger_entries_matched_by_foreign` FOREIGN KEY (`matched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_matched_contribution_id_foreign` FOREIGN KEY (`matched_contribution_id`) REFERENCES `contributions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_reconciled_by_foreign` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `bank_ledger_entries` VALUES ('4','1','DEP-26001',NULL,'LANDBANK CASH DEPOSIT - BARANGAY DONATION DRIVE','DEP-26001','125000.00','2026-10-01','deposit','cash deposit','unmatched',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('5','1','CHK-26001',NULL,'CHECK PAYMENT - RELIEF GOODS SUPPLIER','CHK-26001','18500.00','2026-10-02','withdrawal','check','unreconciled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('6','1','DEP-26002',NULL,'LANDBANK CASH DEPOSIT - COMMUNITY DONATION','DEP-26002','42000.00','2026-10-03','deposit','cash deposit','unmatched',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('7','1','CHG-26001',NULL,'SERVICE CHARGE - ACCOUNT MAINTENANCE','CHG-26001','250.00','2026-10-04','withdrawal','bank charge','unreconciled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('8','1','TRF-26001',NULL,'INCOMING FUND TRANSFER - MUNICIPAL SUPPORT','TRF-26001','75000.00','2026-10-05','deposit','bank transfer','unmatched',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('9','1','CHK-26002',NULL,'CHECK PAYMENT - EMERGENCY MEDICAL SUPPLIES','CHK-26002','32650.00','2026-10-06','withdrawal','check','unreconciled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('10','1','DEP-26003',NULL,'LANDBANK CASH DEPOSIT - BARANGAY FUNDRAISER','DEP-26003','18500.00','2026-10-07','deposit','cash deposit','unmatched',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('11','1','TRF-26002',NULL,'ONLINE TRANSFER - COMMUNITY PROGRAM EXPENSE','TRF-26002','12000.00','2026-10-08','withdrawal','online transfer','unreconciled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('12','1','CHG-26002',NULL,'SERVICE CHARGE - ELECTRONIC TRANSFER','CHG-26002','50.00','2026-10-09','withdrawal','bank charge','unreconciled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `bank_ledger_entries` VALUES ('13','1','DEP-26004',NULL,'LANDBANK CASH DEPOSIT - YEAR-END DONATION','DEP-26004','90000.00','2026-10-10','deposit','cash deposit','unmatched',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28');
DROP TABLE IF EXISTS `bank_reconciliations`;
CREATE TABLE `bank_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `opening_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bank_ending_balance` decimal(12,2) DEFAULT NULL,
  `cash_in` decimal(10,2) NOT NULL DEFAULT '0.00',
  `calculated_bank_cash_in` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cash_out` decimal(10,2) NOT NULL DEFAULT '0.00',
  `calculated_bank_cash_out` decimal(12,2) NOT NULL DEFAULT '0.00',
  `financial_ledger_cash_in` decimal(12,2) NOT NULL DEFAULT '0.00',
  `financial_ledger_cash_out` decimal(12,2) NOT NULL DEFAULT '0.00',
  `closing_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `financial_ledger_ending_balance` decimal(12,2) DEFAULT NULL,
  `reconciliation_difference` decimal(12,2) DEFAULT NULL,
  `accounting_adjustment` decimal(12,2) NOT NULL DEFAULT '0.00',
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `unresolved_discrepancy_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `prepared_by` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `correction_requested_by` bigint unsigned DEFAULT NULL,
  `correction_requested_at` timestamp NULL DEFAULT NULL,
  `correction_count` int unsigned NOT NULL DEFAULT '0',
  `locked_by` bigint unsigned DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_reconciliations_locked_by_foreign` (`locked_by`),
  KEY `bank_reconciliations_period_start_period_end_index` (`period_start`,`period_end`),
  KEY `bank_reconciliations_status_period_end_index` (`status`,`period_end`),
  KEY `bank_reconciliations_prepared_by_foreign` (`prepared_by`),
  KEY `bank_reconciliations_approved_by_foreign` (`approved_by`),
  KEY `bank_reconciliations_correction_requested_by_foreign` (`correction_requested_by`),
  KEY `bank_reconciliations_status_period_start_period_end_index` (`status`,`period_start`,`period_end`),
  KEY `bank_reconciliations_workspace_id_index` (`workspace_id`),
  CONSTRAINT `bank_reconciliations_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_correction_requested_by_foreign` FOREIGN KEY (`correction_requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_prepared_by_foreign` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `bank_withdrawal_disbursement_matches`;
CREATE TABLE `bank_withdrawal_disbursement_matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `bank_ledger_entry_id` bigint unsigned NOT NULL,
  `cash_disbursement_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_withdrawal_disbursement_unique` (`bank_ledger_entry_id`,`cash_disbursement_id`),
  UNIQUE KEY `cash_disbursement_single_withdrawal_unique` (`cash_disbursement_id`),
  KEY `bank_withdrawal_disbursement_matches_workspace_id_index` (`workspace_id`),
  CONSTRAINT `bank_withdrawal_disbursement_matches_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `bank_withdrawal_disbursement_matches` VALUES ('1',NULL,'3','5','2026-10-09 07:43:32','2026-10-09 07:43:32');
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `cache` VALUES ('dashboard.summary.in_kind_batches','i:0;','1793076789');
INSERT INTO `cache` VALUES ('dashboard.summary.registered_donors','i:1;','1793076789');
INSERT INTO `cache` VALUES ('gina.gamboa24@gmail.com|127.0.0.1','i:1;','1793075158');
INSERT INTO `cache` VALUES ('gina.gamboa24@gmail.com|127.0.0.1:timer','i:1793075158;','1793075158');
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `campaigns`;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_workspace_slug_unique` (`workspace_id`,`slug`),
  KEY `campaigns_status_index` (`status`),
  KEY `campaigns_starts_at_index` (`starts_at`),
  KEY `campaigns_ends_at_index` (`ends_at`),
  KEY `campaigns_workspace_id_index` (`workspace_id`),
  CONSTRAINT `campaigns_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `cash_disbursements`;
CREATE TABLE `cash_disbursements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `supporting_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_disbursements_requested_by_foreign` (`requested_by`),
  KEY `cash_disbursements_approved_by_foreign` (`approved_by`),
  KEY `cash_disbursements_voided_by_foreign` (`voided_by`),
  KEY `cash_disbursements_status_index` (`status`),
  KEY `cash_disbursements_voided_at_index` (`voided_at`),
  KEY `cash_disbursements_workspace_id_index` (`workspace_id`),
  CONSTRAINT `cash_disbursements_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_disbursements_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cash_disbursements_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_disbursements_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `cash_transactions`;
CREATE TABLE `cash_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `donor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verified',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `contribution_items`;
CREATE TABLE `contribution_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `contribution_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subcategory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estimated_value` decimal(12,2) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contribution_items_contribution_id_index` (`contribution_id`),
  KEY `contribution_items_category_index` (`category`),
  KEY `contribution_items_condition_index` (`condition`),
  KEY `contribution_items_subcategory_index` (`subcategory`),
  KEY `contribution_items_workspace_id_index` (`workspace_id`),
  CONSTRAINT `contribution_items_contribution_id_foreign` FOREIGN KEY (`contribution_id`) REFERENCES `contributions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `contribution_items_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `contribution_items` VALUES ('1','1','5','Notebook','school_supplies',NULL,'10.00','piece','new',NULL,NULL,'2026-10-08 08:03:00','2026-10-19 01:04:08');
DROP TABLE IF EXISTS `contributions`;
CREATE TABLE `contributions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `receipt_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_id` bigint unsigned NOT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `received_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contributions_receipt_number_unique` (`receipt_number`),
  UNIQUE KEY `contributions_workspace_payment_reference_unique` (`workspace_id`,`payment_method`,`reference_number`),
  KEY `contributions_donor_id_index` (`donor_id`),
  KEY `contributions_campaign_id_index` (`campaign_id`),
  KEY `contributions_created_by_index` (`created_by`),
  KEY `contributions_verified_by_index` (`verified_by`),
  KEY `contributions_type_status_received_at_index` (`type`,`status`,`received_at`),
  KEY `contributions_campaign_id_status_index` (`campaign_id`,`status`),
  KEY `contributions_type_index` (`type`),
  KEY `contributions_status_index` (`status`),
  KEY `contributions_payment_method_index` (`payment_method`),
  KEY `contributions_received_at_index` (`received_at`),
  KEY `contributions_voided_by_foreign` (`voided_by`),
  KEY `contributions_voided_at_index` (`voided_at`),
  KEY `contributions_workspace_id_index` (`workspace_id`),
  CONSTRAINT `contributions_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_donor_id_foreign` FOREIGN KEY (`donor_id`) REFERENCES `legacy_donors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `contributions_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `contributions` VALUES ('1','1','DON-20261008-000001','1',NULL,'12',NULL,'cash','pending','50.00','cash',NULL,NULL,'This is a test','2026-10-08 06:50:20',NULL,'2026-10-08 06:50:20','2026-10-19 01:04:08',NULL,NULL,NULL);
INSERT INTO `contributions` VALUES ('2','1','DON-20261008-000002','1',NULL,'12',NULL,'cash','pending','123.45','cash',NULL,NULL,NULL,'2026-10-08 00:00:00',NULL,'2026-10-08 08:01:36','2026-10-19 01:04:08',NULL,NULL,NULL);
INSERT INTO `contributions` VALUES ('3','1','DON-20261008-000003','1',NULL,'12',NULL,'cash','pending','234.56','gcash',NULL,'GCASH-PLAYWRIGHT-001',NULL,'2026-10-08 00:00:00',NULL,'2026-10-08 08:01:57','2026-10-19 01:04:08',NULL,NULL,NULL);
INSERT INTO `contributions` VALUES ('5','1','DON-20261008-000005','1',NULL,'12',NULL,'in_kind','received',NULL,NULL,NULL,NULL,NULL,'2026-10-08 00:00:00',NULL,'2026-10-08 08:03:00','2026-10-19 01:04:08',NULL,NULL,NULL);
DROP TABLE IF EXISTS `donations`;
CREATE TABLE `donations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `donor_id` bigint unsigned NOT NULL,
  `donation_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `received_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `donations_donor_id_foreign` (`donor_id`),
  CONSTRAINT `donations_donor_id_foreign` FOREIGN KEY (`donor_id`) REFERENCES `legacy_donors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `donors`;
CREATE TABLE `donors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `donor_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_municipality` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay_area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `contact_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferred_contact_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `donors_donor_type_status_index` (`donor_type`,`status`),
  KEY `donors_full_name_index` (`full_name`),
  KEY `donors_contact_number_index` (`contact_number`),
  KEY `donors_first_name_last_name_index` (`first_name`,`last_name`),
  KEY `donors_workspace_id_index` (`workspace_id`),
  CONSTRAINT `donors_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `donors` VALUES ('1','1','individual','Gina','Gamboa','Gina Gamboa',NULL,'Mantalongon Dalaguete Cebu',NULL,NULL,NULL,NULL,'active','09651623907',NULL,NULL,NULL,'2026-10-08 06:48:16','2026-10-19 01:04:08');
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE `inventory_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_categories_workspace_key_unique` (`workspace_id`,`key`),
  KEY `inventory_categories_is_active_index` (`is_active`),
  KEY `inventory_categories_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_categories_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `inventory_categories` VALUES ('1','1','food','Food','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('2','1','medical_supplies','Medical Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('3','1','clothing','Clothing','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('4','1','hygiene_supplies','Hygiene Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('5','1','baby_supplies','Baby Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('6','1','shelter_materials','Shelter Materials','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('7','1','school_supplies','School Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('8','1','kitchen_supplies','Kitchen Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('9','1','water_beverages','Water & Beverages','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('10','1','disaster_relief_supplies','Disaster Relief Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('11','1','electronics','Electronics','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('12','1','furniture','Furniture','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('13','1','bedding_linens','Bedding & Linens','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('14','1','cleaning_supplies','Cleaning Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('15','1','tools_hardware','Tools & Hardware','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('16','1','ppe','Personal Protective Equipment','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('17','1','senior_care_supplies','Senior Care Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('18','1','mobility_assistive_devices','Mobility & Assistive Devices','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('19','1','animal_feed','Animal Feed','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('20','1','livelihood_supplies','Livelihood Supplies','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('21','1','toys_recreational_items','Toys & Recreational Items','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_categories` VALUES ('22','1','other','Other','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_subcategory_id` bigint unsigned DEFAULT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'piece',
  `quantity` int NOT NULL DEFAULT '0',
  `current_quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `minimum_reserve_quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estimated_unit_value` decimal(12,2) DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `batch_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Storage',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `received_at` timestamp NULL DEFAULT NULL,
  `last_audited_at` timestamp NULL DEFAULT NULL,
  `last_movement_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `repair_inventory_items_subcategory_fk` (`inventory_subcategory_id`),
  KEY `repair_inventory_items_created_by_fk` (`created_by`),
  KEY `repair_inventory_items_updated_by_fk` (`updated_by`),
  KEY `inventory_items_category_index` (`category`),
  KEY `inventory_items_status_index` (`status`),
  KEY `inventory_items_storage_location_index` (`storage_location`),
  KEY `inventory_items_expires_at_index` (`expires_at`),
  KEY `inventory_items_last_audited_at_index` (`last_audited_at`),
  KEY `inventory_items_last_movement_at_index` (`last_movement_at`),
  KEY `inventory_items_category_status_index` (`category`,`status`),
  KEY `inventory_items_status_expires_at_index` (`status`,`expires_at`),
  KEY `inventory_items_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_items_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_inventory_items_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_inventory_items_subcategory_fk` FOREIGN KEY (`inventory_subcategory_id`) REFERENCES `inventory_subcategories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_inventory_items_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `inventory_stock_movements`;
CREATE TABLE `inventory_stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `movement_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `quantity_before` decimal(12,2) NOT NULL,
  `quantity_after` decimal(12,2) NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_stock_movements_inventory_item_id_index` (`inventory_item_id`),
  KEY `inventory_stock_movements_created_by_index` (`created_by`),
  KEY `inventory_stock_movements_created_at_index` (`created_at`),
  KEY `inventory_stock_movements_inventory_item_id_created_at_index` (`inventory_item_id`,`created_at`),
  KEY `inventory_stock_movements_movement_type_created_at_index` (`movement_type`,`created_at`),
  KEY `inventory_stock_movements_movement_type_index` (`movement_type`),
  KEY `inventory_stock_movements_reason_index` (`reason`),
  KEY `inventory_stock_movements_reference_number_index` (`reference_number`),
  KEY `inventory_stock_movements_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_stock_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_stock_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `legacy_inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_stock_movements_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `inventory_subcategories`;
CREATE TABLE `inventory_subcategories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `inventory_category_id` bigint unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_subcategories_inventory_category_id_key_unique` (`inventory_category_id`,`key`),
  KEY `inventory_subcategories_inventory_category_id_is_active_index` (`inventory_category_id`,`is_active`),
  KEY `inventory_subcategories_is_active_index` (`is_active`),
  KEY `inventory_subcategories_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_subcategories_inventory_category_id_foreign` FOREIGN KEY (`inventory_category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_subcategories_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `inventory_subcategories` VALUES ('1','1','8','cookware','Cookware','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('2','1','8','utensils','Utensils','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('3','1','8','dinnerware','Dinnerware','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('4','1','8','food_storage','Food Storage','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('5','1','8','small_appliances','Small Appliances','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('6','1','1','rice_grains','Rice & Grains','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('7','1','1','canned_goods','Canned Goods','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('8','1','1','noodles','Noodles','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('9','1','1','snacks','Snacks','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('10','1','1','condiments','Condiments','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('11','1','2','first_aid','First Aid','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('12','1','2','medicines','Medicines','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('13','1','2','ppe','PPE','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('14','1','2','medical_devices','Medical Devices','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('15','1','3','shirts','Shirts','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('16','1','3','pants','Pants','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('17','1','3','underwear','Underwear','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('18','1','3','footwear','Footwear','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('19','1','3','jackets','Jackets','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('20','1','4','soap','Soap','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('21','1','4','shampoo','Shampoo','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('22','1','4','toothcare','Toothcare','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('23','1','4','sanitary_products','Sanitary Products','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_subcategories` VALUES ('24','1','4','cleaning_hygiene','Cleaning Hygiene','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
DROP TABLE IF EXISTS `inventory_units`;
CREATE TABLE `inventory_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_units_workspace_key_unique` (`workspace_id`,`key`),
  KEY `inventory_units_is_active_index` (`is_active`),
  KEY `inventory_units_workspace_id_index` (`workspace_id`),
  CONSTRAINT `inventory_units_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `inventory_units` VALUES ('1','1','piece','Piece','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('2','1','pack','Pack','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('3','1','box','Box','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('4','1','sack','Sack','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('5','1','bottle','Bottle','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('6','1','can','Can','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('7','1','kilogram','Kilogram','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('8','1','gram','Gram','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('9','1','liter','Liter','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('10','1','meter','Meter','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('11','1','set','Set','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('12','1','pair','Pair','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('13','1','bundle','Bundle','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('14','1','carton','Carton','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
INSERT INTO `inventory_units` VALUES ('15','1','other','Other','1','2026-10-08 01:57:13','2026-10-19 01:04:08');
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `legacy_donors`;
CREATE TABLE `legacy_donors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `donor_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_municipality` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay_area` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `contact_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferred_contact_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `donors_donor_type_status_index` (`donor_type`,`status`),
  KEY `donors_full_name_index` (`full_name`),
  KEY `donors_contact_number_index` (`contact_number`),
  KEY `donors_first_name_last_name_index` (`first_name`,`last_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `legacy_donors` VALUES ('1','individual','Gina','Gamboa','Gina Gamboa',NULL,'Mantalongon Dalaguete Cebu','Test',NULL,NULL,'Test','active','09651623907','phone','gina@gmail.com','This is a test','2026-10-08 06:48:16','2026-10-08 06:48:16');
DROP TABLE IF EXISTS `legacy_inventory_items`;
CREATE TABLE `legacy_inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_subcategory_id` bigint unsigned DEFAULT NULL,
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'piece',
  `quantity` int NOT NULL DEFAULT '0',
  `current_quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `minimum_reserve_quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estimated_unit_value` decimal(12,2) DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `batch_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Main Storage',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `received_at` timestamp NULL DEFAULT NULL,
  `last_audited_at` timestamp NULL DEFAULT NULL,
  `last_movement_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_items_created_by_foreign` (`created_by`),
  KEY `inventory_items_updated_by_foreign` (`updated_by`),
  KEY `inventory_items_category_index` (`category`),
  KEY `inventory_items_status_index` (`status`),
  KEY `inventory_items_storage_location_index` (`storage_location`),
  KEY `inventory_items_expires_at_index` (`expires_at`),
  KEY `inventory_items_last_audited_at_index` (`last_audited_at`),
  KEY `inventory_items_last_movement_at_index` (`last_movement_at`),
  KEY `inventory_items_category_status_index` (`category`,`status`),
  KEY `inventory_items_status_expires_at_index` (`status`,`expires_at`),
  KEY `inventory_items_inventory_subcategory_id_foreign` (`inventory_subcategory_id`),
  CONSTRAINT `inventory_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_inventory_subcategory_id_foreign` FOREIGN KEY (`inventory_subcategory_id`) REFERENCES `inventory_subcategories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `migrations` VALUES ('1','0001_01_01_000000_create_users_table','1');
INSERT INTO `migrations` VALUES ('2','0001_01_01_000001_create_cache_table','1');
INSERT INTO `migrations` VALUES ('3','0001_01_01_000002_create_jobs_table','1');
INSERT INTO `migrations` VALUES ('4','2026_06_29_000001_create_donors_table','1');
INSERT INTO `migrations` VALUES ('5','2026_06_29_000002_create_donations_table','1');
INSERT INTO `migrations` VALUES ('6','2026_06_29_000003_add_role_to_users_table','1');
INSERT INTO `migrations` VALUES ('7','2026_06_29_000004_create_inventory_items_table','1');
INSERT INTO `migrations` VALUES ('8','2026_06_29_000005_create_cash_transactions_table','1');
INSERT INTO `migrations` VALUES ('9','2026_06_29_000006_create_bank_reconciliations_table','1');
INSERT INTO `migrations` VALUES ('10','2026_07_11_000001_create_roles_table_and_normalize_users','1');
INSERT INTO `migrations` VALUES ('11','2026_07_11_000002_add_profile_fields_to_donors_table','1');
INSERT INTO `migrations` VALUES ('12','2026_07_12_000001_drop_legacy_role_from_users_table','1');
INSERT INTO `migrations` VALUES ('13','2026_07_12_000002_create_campaigns_table','1');
INSERT INTO `migrations` VALUES ('14','2026_07_12_000003_create_contributions_table','1');
INSERT INTO `migrations` VALUES ('15','2026_07_12_000004_create_contribution_items_table','1');
INSERT INTO `migrations` VALUES ('16','2026_07_15_000001_add_inventory_tracking_fields_to_inventory_items_table','1');
INSERT INTO `migrations` VALUES ('17','2026_07_15_000002_create_inventory_stock_movements_table','1');
INSERT INTO `migrations` VALUES ('18','2026_07_25_000001_create_bank_ledger_entries_table','1');
INSERT INTO `migrations` VALUES ('19','2026_07_25_000002_create_system_security_administration_tables','1');
INSERT INTO `migrations` VALUES ('20','2026_08_02_000001_create_inventory_categories_and_units_tables','1');
INSERT INTO `migrations` VALUES ('21','2026_08_23_000001_add_void_fields_to_contributions_table','1');
INSERT INTO `migrations` VALUES ('22','2026_08_23_000002_create_cash_disbursements_table','1');
INSERT INTO `migrations` VALUES ('23','2026_09_02_000001_add_first_and_last_name_to_donors_table','1');
INSERT INTO `migrations` VALUES ('24','2026_09_05_000001_create_inventory_subcategories_and_add_to_contribution_items','1');
INSERT INTO `migrations` VALUES ('25','2026_09_05_000002_add_status_to_users_table_if_missing','1');
INSERT INTO `migrations` VALUES ('26','2026_09_05_000003_add_last_login_at_to_users_table_if_missing','1');
INSERT INTO `migrations` VALUES ('27','2026_09_05_000004_add_receipt_number_to_contributions_table','1');
INSERT INTO `migrations` VALUES ('28','2026_09_05_000005_add_inventory_subcategory_id_to_inventory_items_table','1');
INSERT INTO `migrations` VALUES ('29','2026_09_12_000001_add_request_details_to_cash_disbursements_table','1');
INSERT INTO `migrations` VALUES ('30','2026_09_12_000002_add_transaction_type_to_bank_ledger_entries_table','1');
INSERT INTO `migrations` VALUES ('31','2026_09_12_000003_create_bank_withdrawal_disbursement_matches_table','1');
INSERT INTO `migrations` VALUES ('32','2026_09_19_000001_add_discrepancy_fields_to_bank_ledger_entries_table','1');
INSERT INTO `migrations` VALUES ('33','2026_09_19_000002_add_outgoing_reconciliation_fields_to_bank_ledger_entries_table','1');
INSERT INTO `migrations` VALUES ('34','2026_10_08_000001_add_must_change_password_to_users_table','2');
INSERT INTO `migrations` VALUES ('35','2026_10_08_000002_add_structured_address_fields_to_donors_table','3');
INSERT INTO `migrations` VALUES ('36','2026_06_28_000001_preserve_legacy_tables','4');
INSERT INTO `migrations` VALUES ('37','2026_06_29_000007_import_legacy_donors','5');
INSERT INTO `migrations` VALUES ('38','2026_07_12_000005_import_legacy_donations','5');
INSERT INTO `migrations` VALUES ('39','2026_07_15_000003_import_legacy_inventory_data','5');
INSERT INTO `migrations` VALUES ('40','2026_09_02_000002_backfill_legacy_donor_profiles','5');
INSERT INTO `migrations` VALUES ('41','2026_10_12_000001_add_reconciliation_period_integrity_fields','5');
INSERT INTO `migrations` VALUES ('42','2026_10_12_000002_add_reconciliation_matching_fields','5');
INSERT INTO `migrations` VALUES ('43','2026_10_12_000003_create_reconciliation_discrepancies_table','5');
INSERT INTO `migrations` VALUES ('44','2026_10_12_000004_add_period_workflow_fields','5');
INSERT INTO `migrations` VALUES ('45','2026_10_12_000005_add_timing_clearance_fields','5');
INSERT INTO `migrations` VALUES ('46','2026_10_17_000001_create_workspaces_and_memberships','5');
INSERT INTO `migrations` VALUES ('47','2026_10_17_000002_add_platform_super_admin_to_users_table','5');
INSERT INTO `migrations` VALUES ('48','2026_10_17_000003_add_workspace_ids_to_business_tables','5');
INSERT INTO `migrations` VALUES ('49','2026_10_17_000004_create_workspace_backfill_issues_table','5');
INSERT INTO `migrations` VALUES ('50','2026_10_17_000005_backfill_workspace_ownership','5');
INSERT INTO `migrations` VALUES ('51','2026_10_17_000006_add_workspace_scoped_unique_constraints','5');
INSERT INTO `migrations` VALUES ('52','2026_10_21_000001_create_super_admin_role_and_remove_gina_workspace_memberships','6');
INSERT INTO `migrations` VALUES ('53','2026_10_24_000001_remove_super_admin_role_and_flag','6');
INSERT INTO `migrations` VALUES ('54','2026_10_24_000002_combine_secretary_and_treasurer_as_staff','6');
INSERT INTO `migrations` VALUES ('55','2026_10_27_000001_add_payment_method_name_to_contributions_table','6');
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `reconciliation_discrepancies`;
CREATE TABLE `reconciliation_discrepancies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `bank_reconciliation_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classification` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unresolved',
  `adjustment_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `carry_forward` tinyint(1) NOT NULL DEFAULT '0',
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cleared_bank_ledger_entry_id` bigint unsigned DEFAULT NULL,
  `cleared_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reconciliation_discrepancies_cleared_bank_ledger_entry_id_unique` (`cleared_bank_ledger_entry_id`),
  KEY `reconciliation_discrepancies_resolved_by_foreign` (`resolved_by`),
  KEY `recon_discrepancies_period_status_idx` (`bank_reconciliation_id`,`resolution_status`),
  KEY `reconciliation_discrepancies_workspace_id_index` (`workspace_id`),
  CONSTRAINT `recon_discrepancies_cleared_entry_fk` FOREIGN KEY (`cleared_bank_ledger_entry_id`) REFERENCES `bank_ledger_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliation_discrepancies_bank_reconciliation_id_foreign` FOREIGN KEY (`bank_reconciliation_id`) REFERENCES `bank_reconciliations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reconciliation_discrepancies_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliation_discrepancies_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `roles` VALUES ('1','admin','Admin','Manages all workspaces, staff accounts, and operational workflows.','2026-10-27 01:30:17','2026-10-27 01:30:17');
INSERT INTO `roles` VALUES ('4','staff','Staff','Manages donors, donations, inventory, cash verification, and bank reconciliation; cannot manage workspaces, staff, or approve restricted transactions.','2026-10-27 01:30:18','2026-10-27 01:30:18');
DROP TABLE IF EXISTS `security_activity_events`;
CREATE TABLE `security_activity_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `security_activity_events_user_id_foreign` (`user_id`),
  KEY `security_activity_events_actor_id_foreign` (`actor_id`),
  KEY `security_activity_events_action_index` (`action`),
  KEY `security_activity_events_occurred_at_index` (`occurred_at`),
  KEY `security_activity_events_workspace_id_index` (`workspace_id`),
  CONSTRAINT `security_activity_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_activity_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_activity_events_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `security_activity_events` VALUES ('1','1','1','1','admin_authenticated','Authorized admin user Gina Gamboa successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-08 03:22:12','2026-10-08 03:22:12','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('2','1','2','1','staff_account_created','Gina Gamboa created staff account single-1791430689516@example.com','{\"role\": \"admin\"}','127.0.0.1','2026-10-08 03:38:12','2026-10-08 03:38:12','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('3','1','3','1','staff_account_created','Gina Gamboa created staff account final-admin-1791430718957@example.com','{\"role\": \"admin\"}','127.0.0.1','2026-10-08 03:38:41','2026-10-08 03:38:41','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('4','1','4','1','staff_account_created','Gina Gamboa created staff account final-secretary-1791430718957@example.com','{\"role\": \"secretary\"}','127.0.0.1','2026-10-08 03:38:42','2026-10-08 03:38:42','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('5','1','5','1','staff_account_created','Gina Gamboa created staff account final-treasurer-1791430718957@example.com','{\"role\": \"treasurer\"}','127.0.0.1','2026-10-08 03:38:43','2026-10-08 03:38:43','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('6','1','6','1','staff_account_created','Gina Gamboa created staff account e2e-admin-1791430779453@example.com','{\"role\": \"admin\"}','127.0.0.1','2026-10-08 03:39:43','2026-10-08 03:39:43','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('7','1','7','1','staff_account_created','Gina Gamboa created staff account e2e-secretary-1791430779453@example.com','{\"role\": \"secretary\"}','127.0.0.1','2026-10-08 03:39:44','2026-10-08 03:39:44','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('8','1','8','1','staff_account_created','Gina Gamboa created staff account e2e-treasurer-1791430779453@example.com','{\"role\": \"treasurer\"}','127.0.0.1','2026-10-08 03:39:45','2026-10-08 03:39:45','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('9','1','9','1','staff_account_created','Gina Gamboa created staff account pw-admin-1791431332591@example.com','{\"role\": \"admin\"}','127.0.0.1','2026-10-08 03:48:56','2026-10-08 03:48:56','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('10','1','10','1','staff_account_created','Gina Gamboa created staff account pw-secretary-1791431332591@example.com','{\"role\": \"secretary\"}','127.0.0.1','2026-10-08 03:48:57','2026-10-08 03:48:57','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('11','1','11','1','staff_account_created','Gina Gamboa created staff account pw-treasurer-1791431332591@example.com','{\"role\": \"treasurer\"}','127.0.0.1','2026-10-08 03:48:58','2026-10-08 03:48:58','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('12','1','12','1','staff_account_created','Gina Gamboa created staff account neziel@gmail.com','{\"role\": \"secretary\"}','127.0.0.1','2026-10-08 04:01:27','2026-10-08 04:01:27','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('14','1','12','12','disbursement_requested','Neziel Rellita requested a cash disbursement of 3210.50.','{\"purpose\": \"Playwright disbursement verification\", \"recipient\": \"Playwright Test Payee\", \"requested_date\": \"2026-10-09\", \"disbursement_id\": 1, \"supporting_reference\": \"PW-REF-001\"}',NULL,'2026-10-09 00:31:16','2026-10-09 00:31:16','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('15','1','12','1','disbursement_approved','Gina Gamboa approved cash disbursement #1.','{\"amount\": \"3210.50\", \"disbursement_id\": 1}',NULL,'2026-10-09 00:32:28','2026-10-09 00:32:28','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('17','1',NULL,'1','disbursement_rejected','Gina Gamboa rejected cash disbursement #2.','{\"reason\": \"Missing supporting documents\", \"disbursement_id\": 2}',NULL,'2026-10-09 00:35:18','2026-10-09 00:35:18','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('18','1','1','1','disbursement_requested','Gina Gamboa created a fund request for 1250.00.','{\"purpose\": \"Playwright Fund Request Flow Verification\", \"recipient\": \"Playwright Test Payee\", \"requested_date\": \"2026-10-09\", \"disbursement_id\": 3, \"supporting_reference\": \"PW-20261009\"}',NULL,'2026-10-09 06:44:19','2026-10-09 06:44:19','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('20','1',NULL,'1','disbursement_approved','Gina Gamboa approved fund request #4.','{\"amount\": \"500.00\", \"disbursement_id\": 4}',NULL,'2026-10-09 07:11:09','2026-10-09 07:11:09','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('21','1',NULL,'1','disbursement_voided','Gina Gamboa cancelled fund request #4.','{\"amount\": \"500.00\", \"reason\": \"Playwright cancellation verification\", \"disbursement_id\": 4}',NULL,'2026-10-09 07:11:38','2026-10-09 07:11:38','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('24','1','2','2','admin_authenticated','Authorized admin user Single Flow successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-12 02:31:13','2026-10-12 02:31:13','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('25','1','3','3','admin_authenticated','Authorized admin user Final Admin successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-12 02:31:13','2026-10-12 02:31:13','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('26','1','6','6','admin_authenticated','Authorized admin user E2E Admin successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-12 02:31:13','2026-10-12 02:31:13','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('27','1','9','9','admin_authenticated','Authorized admin user PW Admin successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-12 02:31:13','2026-10-12 02:31:13','2026-10-19 01:04:08');
INSERT INTO `security_activity_events` VALUES ('28','2','17','1','workspace_created','Gina Gamboa created workspace Test Workspace and assigned goles@gmail.com','{\"admin_id\": 17, \"workspace_id\": 2}','127.0.0.1','2026-10-19 09:29:52','2026-10-19 09:29:52','2026-10-19 09:29:52');
INSERT INTO `security_activity_events` VALUES ('29','3','18','1','workspace_created','Gina Gamboa created workspace Gina Test Admin and assigned gina.gamboa@gmail.com','{\"admin_id\": 18, \"workspace_id\": 3}','127.0.0.1','2026-10-19 09:56:32','2026-10-19 09:56:32','2026-10-19 09:56:32');
INSERT INTO `security_activity_events` VALUES ('30',NULL,'17','17','admin_authenticated','Authorized admin user Julius Goles successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-26 23:38:29','2026-10-26 23:38:29','2026-10-26 23:38:29');
INSERT INTO `security_activity_events` VALUES ('31',NULL,'18','18','admin_authenticated','Authorized admin user Gina Gamboa successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-26 23:38:29','2026-10-26 23:38:29','2026-10-26 23:38:29');
INSERT INTO `security_activity_events` VALUES ('32','1',NULL,'1','bank_statement_imported','Gina Gamboa imported 10 bank transaction(s) for reconciliation.','{\"imported_count\": 10}',NULL,'2026-10-27 00:28:28','2026-10-27 00:28:28','2026-10-27 00:28:28');
INSERT INTO `security_activity_events` VALUES ('33','1','19','1','staff_account_created','Gina Gamboa created staff account gina.gamboa.24@gmail.com','{\"role\": \"staff\"}','127.0.0.1','2026-10-27 01:55:03','2026-10-27 01:55:03','2026-10-27 01:55:03');
DROP TABLE IF EXISTS `security_permission_user`;
CREATE TABLE `security_permission_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `security_permission_id` bigint unsigned NOT NULL,
  `granted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `security_permission_user_unique` (`user_id`,`security_permission_id`),
  KEY `security_permission_user_security_permission_id_foreign` (`security_permission_id`),
  KEY `security_permission_user_granted_by_foreign` (`granted_by`),
  CONSTRAINT `security_permission_user_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_permission_user_security_permission_id_foreign` FOREIGN KEY (`security_permission_id`) REFERENCES `security_permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_permission_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `security_permission_user` VALUES ('1','1','1',NULL,'2026-10-08 03:22:12','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('2','1','2',NULL,'2026-10-08 03:22:12','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('3','2','1',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('4','2','2',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('5','3','1',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('6','3','2',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('7','4','3',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('8','4','4',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('9','5','2',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('10','5','5',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('11','6','1',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('12','6','2',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('13','7','3',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('14','7','4',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('15','8','2',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('16','8','5',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:23');
INSERT INTO `security_permission_user` VALUES ('17','9','1',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('18','9','2',NULL,'2026-10-12 02:31:13','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('19','10','3',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:24');
INSERT INTO `security_permission_user` VALUES ('20','10','4',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:24');
INSERT INTO `security_permission_user` VALUES ('21','11','2',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:24');
INSERT INTO `security_permission_user` VALUES ('22','11','5',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:24');
INSERT INTO `security_permission_user` VALUES ('23','12','3',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:24');
INSERT INTO `security_permission_user` VALUES ('24','12','4',NULL,'2026-10-12 02:31:13','2026-10-18 23:40:24');
INSERT INTO `security_permission_user` VALUES ('25','17','1',NULL,'2026-10-26 23:38:29','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('26','17','2',NULL,'2026-10-26 23:38:29','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('27','18','1',NULL,'2026-10-26 23:38:29','2026-10-26 23:38:29');
INSERT INTO `security_permission_user` VALUES ('28','18','2',NULL,'2026-10-26 23:38:29','2026-10-26 23:38:29');
DROP TABLE IF EXISTS `security_permissions`;
CREATE TABLE `security_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `security_permissions_key_unique` (`key`),
  KEY `security_permissions_sort_order_index` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `security_permissions` VALUES ('1','manage_accounts_permissions','Manage Accounts & Key Permissions','Create users, update role access, and maintain authorization keys.','10','2026-10-08 03:22:12','2026-10-08 03:22:12');
INSERT INTO `security_permissions` VALUES ('2','access_audit_logs','Access System Audit Logs & Security Tickets','Review administrative audit trails and security incident tickets.','20','2026-10-08 03:22:12','2026-10-08 03:22:12');
INSERT INTO `security_permissions` VALUES ('3','register_in_kind_supplies','Register & Allocate In-Kind Supplies','Manage incoming goods and inventory allocation workflows.','30','2026-10-08 03:22:12','2026-10-08 03:22:12');
INSERT INTO `security_permissions` VALUES ('4','adjust_stock_levels','Adjust Warehouse Stock Levels','Update inventory counts and warehouse stock movement records.','40','2026-10-08 03:22:12','2026-10-08 03:22:12');
INSERT INTO `security_permissions` VALUES ('5','record_cash_credits','Record Cash / GCash Ledger Credits','Record cash and electronic donation ledger credits.','50','2026-10-08 03:22:12','2026-10-08 03:22:12');
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `user_security_profiles`;
CREATE TABLE `user_security_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_enforced` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_verified_at` timestamp NULL DEFAULT NULL,
  `security_key_suffix` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_security_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `user_security_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `user_security_profiles` VALUES ('1','1','1','1','2026-10-08 03:22:12','082913','2026-10-08 03:22:12','2026-10-08 03:22:12');
INSERT INTO `user_security_profiles` VALUES ('2','2','1','1','2026-10-12 02:31:13','165826','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('3','3','1','1','2026-10-12 02:31:13','248739','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('4','4','1','1','2026-10-12 02:31:13','331652','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('5','5','1','1','2026-10-12 02:31:13','414565','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('6','6','1','1','2026-10-12 02:31:13','497478','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('7','7','1','1','2026-10-12 02:31:13','580391','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('8','8','1','1','2026-10-12 02:31:13','663304','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('9','9','1','1','2026-10-12 02:31:13','746217','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('10','10','1','1','2026-10-12 02:31:13','829130','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('11','11','1','1','2026-10-12 02:31:13','912043','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('12','12','1','1','2026-10-12 02:31:13','994956','2026-10-12 02:31:13','2026-10-12 02:31:13');
INSERT INTO `user_security_profiles` VALUES ('13','17','1','1','2026-10-26 23:38:29','409521','2026-10-26 23:38:29','2026-10-26 23:38:29');
INSERT INTO `user_security_profiles` VALUES ('14','18','1','1','2026-10-26 23:38:29','492434','2026-10-26 23:38:29','2026-10-26 23:38:29');
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_status_index` (`role_id`,`status`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `users` VALUES ('1','1','Gina','Gamboa','Gina Gamboa','gina@gmail.com',NULL,'$2y$12$o14KD8xmGKwcVyZS72njbeCbVK3eGirVc.Hw3WBmC6mH3uTiia0m6','0','active','2026-10-27 00:20:19','NmYMHj5HqNRBVAes9Q7wyIKPEKMsZgmnPwNMNdDEIGZQ2vgq0R4eWSwaO0VZ','2026-10-08 03:22:12','2026-10-27 01:30:17');
INSERT INTO `users` VALUES ('2','1','Single','Flow','Single Flow','single-1791430689516@example.com',NULL,'$2y$12$ohaZQgKdvCuPlR0aH2C6gem5DuaIyhfh0LJ1LONDg3J1X4B7WREIa','0','active','2026-10-08 03:38:14',NULL,'2026-10-08 03:38:12','2026-10-08 03:38:24');
INSERT INTO `users` VALUES ('3','1','Final','Admin','Final Admin','final-admin-1791430718957@example.com',NULL,'$2y$12$BAAh6YsYgiWwT68tf9p6iu/bDazwvrvBZMmrMyMklnf5X3/QhTWN2','0','active','2026-10-08 03:38:49',NULL,'2026-10-08 03:38:41','2026-10-08 03:38:49');
INSERT INTO `users` VALUES ('4','4','Final','Secretary','Final Secretary','final-secretary-1791430718957@example.com',NULL,'$2y$12$/e.R0DWleD1YBPtQM611vOmH8ZrxXnJk4kiNP9cHahhgtkId5mNDW','0','active','2026-10-08 03:38:55',NULL,'2026-10-08 03:38:42','2026-10-27 01:30:18');
INSERT INTO `users` VALUES ('5','4','Final','Treasurer','Final Treasurer','final-treasurer-1791430718957@example.com',NULL,'$2y$12$h4deKp4PSWyYreBG.pS/Luqg/QT81HIrmy/xzPcm0YeeTMNoQZxnW','1','active',NULL,NULL,'2026-10-08 03:38:43','2026-10-27 01:30:18');
INSERT INTO `users` VALUES ('6','1','E2E','Admin','E2E Admin','e2e-admin-1791430779453@example.com',NULL,'$2y$12$5p9Ma3vYtXMTYfWpKyPfQuTbwB3eCzzP9Uz7CkwLLf/B4OXsrzju.','0','active','2026-10-08 03:39:51',NULL,'2026-10-08 03:39:43','2026-10-08 03:39:51');
INSERT INTO `users` VALUES ('7','4','E2E','Secretary','E2E Secretary','e2e-secretary-1791430779453@example.com',NULL,'$2y$12$8SmKO.9sos/xDOeIBjWbsOw0gYaHUK2c1Er/uwBmvnKMHwL5CYU2G','0','active','2026-10-08 03:39:56',NULL,'2026-10-08 03:39:44','2026-10-27 01:30:18');
INSERT INTO `users` VALUES ('8','4','E2E','Treasurer','E2E Treasurer','e2e-treasurer-1791430779453@example.com',NULL,'$2y$12$ZxsvfcTUbKl6lP9dikPSo.7lVPV1U0yDZOU2oo9j4g8AhiRu5ERzq','0','active','2026-10-08 03:40:03',NULL,'2026-10-08 03:39:45','2026-10-27 01:30:18');
INSERT INTO `users` VALUES ('9','1','PW','Admin','PW Admin','pw-admin-1791431332591@example.com',NULL,'$2y$12$INC2E7HIuQXU5YxJYQeOqO9b5y87t8/yidKBf/nBR5KbjkrFdCbve','0','active','2026-10-08 03:49:07',NULL,'2026-10-08 03:48:56','2026-10-08 03:49:07');
INSERT INTO `users` VALUES ('10','4','PW','Secretary','PW Secretary','pw-secretary-1791431332591@example.com',NULL,'$2y$12$oT7d164YntEUx2IV6qX6zueg2Bodjn6dFwIzSMkeZ9.caX5h24K42','0','active','2026-10-08 03:49:23',NULL,'2026-10-08 03:48:57','2026-10-27 01:30:18');
INSERT INTO `users` VALUES ('11','4','PW','Treasurer','PW Treasurer','pw-treasurer-1791431332591@example.com',NULL,'$2y$12$WpOVSJ.9M44eK9Mk8bpXA.um8mAFaKS0HCjGyaHNSe7fWLjhPYFqa','0','active','2026-10-08 03:49:26',NULL,'2026-10-08 03:48:58','2026-10-27 01:30:18');
INSERT INTO `users` VALUES ('12','4','Neziel','Rellita','Neziel Rellita','neziel@gmail.com',NULL,'$2y$12$S3v71uZFfh9pB.YfOPyGTO6j8bPEut8ki.slZ8BMOh4r3w1moBO4C','0','active','2026-10-09 03:56:46','LzKAHizRYT0XZme85cHCeTOucFhiCXF4B517BuPzKtHTZZWgXDSL9fsiwSUI','2026-10-08 04:01:27','2026-10-27 01:30:18');
INSERT INTO `users` VALUES ('17','1','Julius','Goles','Julius Goles','goles@gmail.com',NULL,'$2y$12$xvSAeuhSQkCVVHPHv1W3NOoI6U.Wkr0vP3lP9K3aeohaRwHKtiuYS','1','active','2026-10-19 09:45:20',NULL,'2026-10-19 09:29:52','2026-10-19 09:45:20');
INSERT INTO `users` VALUES ('18','1','Gina','Gamboa','Gina Gamboa','gina.gamboa@gmail.com',NULL,'$2y$12$bfpYjd8TMD5hIhvWOV8yoeKUCLLHpFq1yYdMmWRKQGpiWG99H4b2q','0','active','2026-10-19 09:57:18','3BG5abuDW8tHojmLWnanVnCPaZWEaGRtyOmWg6yYPSNlPiLmrw7mAycZxrjI','2026-10-19 09:56:32','2026-10-19 09:57:48');
INSERT INTO `users` VALUES ('19','4','Gina','Gamboa','Gina Gamboa','gina.gamboa.24@gmail.com',NULL,'$2y$12$tYxpZhsD0qVn3juB5.oi5ep3ZR092ogBGeufk9GAcWQiGnyaYFlN.','0','active','2026-10-27 04:25:25',NULL,'2026-10-27 01:55:03','2026-10-27 04:25:25');
DROP TABLE IF EXISTS `workspace_backfill_issues`;
CREATE TABLE `workspace_backfill_issues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` bigint unsigned NOT NULL,
  `issue_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_backfill_issue_unique` (`table_name`,`record_id`,`issue_type`),
  KEY `workspace_backfill_issues_table_name_issue_type_index` (`table_name`,`issue_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workspace_backfill_issues` VALUES ('1','bank_withdrawal_disbursement_matches','1','parent_workspace_missing','Match parent records do not both have workspace ownership.','2026-10-19 01:04:08','2026-10-19 01:04:08');
DROP TABLE IF EXISTS `workspace_memberships`;
CREATE TABLE `workspace_memberships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_memberships_workspace_id_user_id_unique` (`workspace_id`,`user_id`),
  KEY `workspace_memberships_role_id_foreign` (`role_id`),
  KEY `workspace_memberships_created_by_foreign` (`created_by`),
  KEY `workspace_memberships_user_id_status_index` (`user_id`,`status`),
  KEY `workspace_memberships_status_index` (`status`),
  CONSTRAINT `workspace_memberships_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workspace_memberships_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `workspace_memberships_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workspace_memberships_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workspace_memberships` VALUES ('2','1','2','1','active',NULL,'2026-10-19 01:04:05','2026-10-19 01:04:05');
INSERT INTO `workspace_memberships` VALUES ('3','1','3','1','active',NULL,'2026-10-19 01:04:05','2026-10-19 01:04:05');
INSERT INTO `workspace_memberships` VALUES ('4','1','6','1','active',NULL,'2026-10-19 01:04:05','2026-10-19 01:04:05');
INSERT INTO `workspace_memberships` VALUES ('5','1','9','1','active',NULL,'2026-10-19 01:04:05','2026-10-19 01:04:05');
INSERT INTO `workspace_memberships` VALUES ('6','1','4','4','active',NULL,'2026-10-19 01:04:05','2026-10-27 01:30:18');
INSERT INTO `workspace_memberships` VALUES ('7','1','7','4','active',NULL,'2026-10-19 01:04:05','2026-10-27 01:30:18');
INSERT INTO `workspace_memberships` VALUES ('8','1','10','4','active',NULL,'2026-10-19 01:04:05','2026-10-27 01:30:18');
INSERT INTO `workspace_memberships` VALUES ('9','1','12','4','active',NULL,'2026-10-19 01:04:05','2026-10-27 01:30:18');
INSERT INTO `workspace_memberships` VALUES ('10','1','5','4','active',NULL,'2026-10-19 01:04:05','2026-10-27 01:30:18');
INSERT INTO `workspace_memberships` VALUES ('11','1','8','4','active',NULL,'2026-10-19 01:04:05','2026-10-27 01:30:18');
INSERT INTO `workspace_memberships` VALUES ('12','1','11','4','active',NULL,'2026-10-19 01:04:05','2026-10-27 01:30:18');
INSERT INTO `workspace_memberships` VALUES ('13','2','17','1','active','1','2026-10-19 09:29:52','2026-10-19 09:29:52');
INSERT INTO `workspace_memberships` VALUES ('14','3','18','1','active','1','2026-10-19 09:56:32','2026-10-19 09:56:32');
INSERT INTO `workspace_memberships` VALUES ('15','1','19','4','active','1','2026-10-27 01:55:03','2026-10-27 01:55:03');
DROP TABLE IF EXISTS `workspaces`;
CREATE TABLE `workspaces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspaces_slug_unique` (`slug`),
  KEY `workspaces_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workspaces` VALUES ('1','Legacy Workspace','legacy','active','2026-10-19 01:04:05','2026-10-19 01:04:05');
INSERT INTO `workspaces` VALUES ('2','Test Workspace','test-workspace','active','2026-10-19 09:29:52','2026-10-19 09:29:52');
INSERT INTO `workspaces` VALUES ('3','Gina Test Admin','gina-test-admin','active','2026-10-19 09:56:32','2026-10-19 09:56:32');
SET FOREIGN_KEY_CHECKS=1;
