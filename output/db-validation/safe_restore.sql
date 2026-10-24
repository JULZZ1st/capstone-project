-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: barangay_donation_system
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bank_ledger_entries`
--

DROP TABLE IF EXISTS `bank_ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_ledger_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `statement_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  UNIQUE KEY `bank_ledger_entries_statement_id_unique` (`statement_id`),
  UNIQUE KEY `bank_ledger_entries_matched_contribution_id_unique` (`matched_contribution_id`),
  KEY `bank_ledger_entries_matched_by_foreign` (`matched_by`),
  KEY `bank_ledger_entries_status_transaction_date_index` (`status`,`transaction_date`),
  KEY `bank_ledger_entries_reference_number_index` (`reference_number`),
  KEY `bank_ledger_entries_payment_method_index` (`payment_method`),
  KEY `bank_ledger_entries_transaction_type_index` (`transaction_type`),
  KEY `bank_ledger_entries_type_status_date_index` (`transaction_type`,`status`,`transaction_date`),
  KEY `bank_ledger_entries_reconciled_by_foreign` (`reconciled_by`),
  CONSTRAINT `bank_ledger_entries_matched_by_foreign` FOREIGN KEY (`matched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_matched_contribution_id_foreign` FOREIGN KEY (`matched_contribution_id`) REFERENCES `contributions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_ledger_entries_reconciled_by_foreign` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_ledger_entries`
--

LOCK TABLES `bank_ledger_entries` WRITE;
/*!40000 ALTER TABLE `bank_ledger_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_ledger_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_reconciliations`
--

DROP TABLE IF EXISTS `bank_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `opening_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cash_in` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cash_out` decimal(10,2) NOT NULL DEFAULT '0.00',
  `closing_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_reconciliations`
--

LOCK TABLES `bank_reconciliations` WRITE;
/*!40000 ALTER TABLE `bank_reconciliations` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_reconciliations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_withdrawal_disbursement_matches`
--

DROP TABLE IF EXISTS `bank_withdrawal_disbursement_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_withdrawal_disbursement_matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bank_ledger_entry_id` bigint unsigned NOT NULL,
  `cash_disbursement_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_withdrawal_disbursement_unique` (`bank_ledger_entry_id`,`cash_disbursement_id`),
  UNIQUE KEY `cash_disbursement_single_withdrawal_unique` (`cash_disbursement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_withdrawal_disbursement_matches`
--

LOCK TABLES `bank_withdrawal_disbursement_matches` WRITE;
/*!40000 ALTER TABLE `bank_withdrawal_disbursement_matches` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_withdrawal_disbursement_matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('dashboard.summary.in_kind_batches','i:0;',1792220602),('dashboard.summary.registered_donors','i:2;',1792220603);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_slug_unique` (`slug`),
  KEY `campaigns_status_index` (`status`),
  KEY `campaigns_starts_at_index` (`starts_at`),
  KEY `campaigns_ends_at_index` (`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_disbursements`
--

DROP TABLE IF EXISTS `cash_disbursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_disbursements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `cash_disbursements_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_disbursements_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cash_disbursements_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_disbursements`
--

LOCK TABLES `cash_disbursements` WRITE;
/*!40000 ALTER TABLE `cash_disbursements` DISABLE KEYS */;
/*!40000 ALTER TABLE `cash_disbursements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_transactions`
--

DROP TABLE IF EXISTS `cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_transactions`
--

LOCK TABLES `cash_transactions` WRITE;
/*!40000 ALTER TABLE `cash_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `cash_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contribution_items`
--

DROP TABLE IF EXISTS `contribution_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contribution_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `contribution_items_contribution_id_foreign` FOREIGN KEY (`contribution_id`) REFERENCES `contributions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contribution_items`
--

LOCK TABLES `contribution_items` WRITE;
/*!40000 ALTER TABLE `contribution_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `contribution_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contributions`
--

DROP TABLE IF EXISTS `contributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contributions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_id` bigint unsigned NOT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  UNIQUE KEY `contributions_payment_method_reference_number_unique` (`payment_method`,`reference_number`),
  UNIQUE KEY `contributions_receipt_number_unique` (`receipt_number`),
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
  CONSTRAINT `contributions_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_donor_id_foreign` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `contributions_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributions_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contributions`
--

LOCK TABLES `contributions` WRITE;
/*!40000 ALTER TABLE `contributions` DISABLE KEYS */;
/*!40000 ALTER TABLE `contributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donations`
--

DROP TABLE IF EXISTS `donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  CONSTRAINT `donations_donor_id_foreign` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donations`
--

LOCK TABLES `donations` WRITE;
/*!40000 ALTER TABLE `donations` DISABLE KEYS */;
/*!40000 ALTER TABLE `donations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donors`
--

DROP TABLE IF EXISTS `donors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donors` (
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donors`
--

LOCK TABLES `donors` WRITE;
/*!40000 ALTER TABLE `donors` DISABLE KEYS */;
INSERT INTO `donors` VALUES (1,'individual','Shared','Central Donor','Shared Central Donor',NULL,'1 Shared Street','Central','Manila','Metro Manila',NULL,'active','09171234567',NULL,'shared.central.donor@example.test',NULL,'2026-10-09 23:22:49','2026-10-09 23:22:49'),(2,'individual','Test','Test','Test Test',NULL,'Lower Lahug','Manatalongon','Dalaguete','Cebu',NULL,'active','0951623907','phone','pnph@gmail.com','None','2026-10-09 23:37:20','2026-10-09 23:37:20');
/*!40000 ALTER TABLE `donors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_categories`
--

DROP TABLE IF EXISTS `inventory_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_categories_key_unique` (`key`),
  KEY `inventory_categories_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_categories`
--

LOCK TABLES `inventory_categories` WRITE;
/*!40000 ALTER TABLE `inventory_categories` DISABLE KEYS */;
INSERT INTO `inventory_categories` VALUES (1,'food','Food',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(2,'medical_supplies','Medical Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(3,'clothing','Clothing',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(4,'hygiene_supplies','Hygiene Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(5,'baby_supplies','Baby Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(6,'shelter_materials','Shelter Materials',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(7,'school_supplies','School Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(8,'kitchen_supplies','Kitchen Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(9,'water_beverages','Water & Beverages',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(10,'disaster_relief_supplies','Disaster Relief Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(11,'electronics','Electronics',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(12,'furniture','Furniture',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(13,'bedding_linens','Bedding & Linens',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(14,'cleaning_supplies','Cleaning Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(15,'tools_hardware','Tools & Hardware',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(16,'ppe','Personal Protective Equipment',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(17,'senior_care_supplies','Senior Care Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(18,'mobility_assistive_devices','Mobility & Assistive Devices',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(19,'animal_feed','Animal Feed',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(20,'livelihood_supplies','Livelihood Supplies',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(21,'toys_recreational_items','Toys & Recreational Items',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(22,'other','Other',1,'2026-10-07 06:12:03','2026-10-07 06:12:03');
/*!40000 ALTER TABLE `inventory_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_items` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_stock_movements`
--

DROP TABLE IF EXISTS `inventory_stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `inventory_stock_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_stock_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_stock_movements`
--

LOCK TABLES `inventory_stock_movements` WRITE;
/*!40000 ALTER TABLE `inventory_stock_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_subcategories`
--

DROP TABLE IF EXISTS `inventory_subcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_subcategories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `inventory_subcategories_inventory_category_id_foreign` FOREIGN KEY (`inventory_category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_subcategories`
--

LOCK TABLES `inventory_subcategories` WRITE;
/*!40000 ALTER TABLE `inventory_subcategories` DISABLE KEYS */;
INSERT INTO `inventory_subcategories` VALUES (1,8,'cookware','Cookware',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(2,8,'utensils','Utensils',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(3,8,'dinnerware','Dinnerware',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(4,8,'food_storage','Food Storage',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(5,8,'small_appliances','Small Appliances',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(6,1,'rice_grains','Rice & Grains',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(7,1,'canned_goods','Canned Goods',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(8,1,'noodles','Noodles',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(9,1,'snacks','Snacks',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(10,1,'condiments','Condiments',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(11,2,'first_aid','First Aid',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(12,2,'medicines','Medicines',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(13,2,'ppe','PPE',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(14,2,'medical_devices','Medical Devices',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(15,3,'shirts','Shirts',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(16,3,'pants','Pants',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(17,3,'underwear','Underwear',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(18,3,'footwear','Footwear',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(19,3,'jackets','Jackets',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(20,4,'soap','Soap',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(21,4,'shampoo','Shampoo',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(22,4,'toothcare','Toothcare',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(23,4,'sanitary_products','Sanitary Products',1,'2026-10-07 06:12:54','2026-10-07 06:12:54'),(24,4,'cleaning_hygiene','Cleaning Hygiene',1,'2026-10-07 06:12:54','2026-10-07 06:12:54');
/*!40000 ALTER TABLE `inventory_subcategories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_units`
--

DROP TABLE IF EXISTS `inventory_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_units_key_unique` (`key`),
  KEY `inventory_units_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_units`
--

LOCK TABLES `inventory_units` WRITE;
/*!40000 ALTER TABLE `inventory_units` DISABLE KEYS */;
INSERT INTO `inventory_units` VALUES (1,'piece','Piece',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(2,'pack','Pack',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(3,'box','Box',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(4,'sack','Sack',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(5,'bottle','Bottle',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(6,'can','Can',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(7,'kilogram','Kilogram',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(8,'gram','Gram',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(9,'liter','Liter',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(10,'meter','Meter',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(11,'set','Set',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(12,'pair','Pair',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(13,'bundle','Bundle',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(14,'carton','Carton',1,'2026-10-07 06:12:03','2026-10-07 06:12:03'),(15,'other','Other',1,'2026-10-07 06:12:03','2026-10-07 06:12:03');
/*!40000 ALTER TABLE `inventory_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000002_create_jobs_table',1),(2,'2026_06_29_000001_create_donors_table',1),(3,'0001_01_01_000000_create_users_table',1),(4,'2026_06_29_000002_create_donations_table',1),(5,'2026_06_29_000003_add_role_to_users_table',1),(6,'2026_06_29_000004_create_inventory_items_table',1),(7,'2026_06_29_000005_create_cash_transactions_table',1),(8,'2026_06_29_000006_create_bank_reconciliations_table',1),(9,'2026_07_11_000001_create_roles_table_and_normalize_users',1),(10,'2026_07_11_000002_add_profile_fields_to_donors_table',1),(11,'2026_07_12_000001_drop_legacy_role_from_users_table',1),(12,'2026_07_12_000002_create_campaigns_table',1),(13,'2026_07_12_000003_create_contributions_table',1),(14,'2026_07_12_000004_create_contribution_items_table',1),(15,'2026_07_15_000001_add_inventory_tracking_fields_to_inventory_items_table',1),(16,'2026_07_15_000002_create_inventory_stock_movements_table',1),(17,'2026_07_25_000001_create_bank_ledger_entries_table',1),(18,'2026_07_25_000002_create_system_security_administration_tables',1),(19,'2026_08_02_000001_create_inventory_categories_and_units_tables',1),(20,'2026_08_23_000001_add_void_fields_to_contributions_table',1),(21,'2026_08_23_000002_create_cash_disbursements_table',1),(22,'2026_09_02_000001_add_first_and_last_name_to_donors_table',1),(23,'2026_09_05_000001_create_inventory_subcategories_and_add_to_contribution_items',1),(24,'2026_09_05_000002_add_status_to_users_table_if_missing',1),(25,'2026_09_05_000003_add_last_login_at_to_users_table_if_missing',1),(26,'2026_09_05_000004_add_receipt_number_to_contributions_table',1),(27,'2026_09_05_000005_add_inventory_subcategory_id_to_inventory_items_table',1),(28,'2026_09_12_000001_add_request_details_to_cash_disbursements_table',1),(29,'2026_09_12_000002_add_transaction_type_to_bank_ledger_entries_table',1),(30,'2026_09_12_000003_create_bank_withdrawal_disbursement_matches_table',1),(31,'2026_09_19_000001_add_discrepancy_fields_to_bank_ledger_entries_table',1),(32,'2026_09_19_000002_add_outgoing_reconciliation_fields_to_bank_ledger_entries_table',1),(33,'0001_01_01_000001_create_cache_table',1),(34,'2026_10_08_000001_add_must_change_password_to_users_table',2),(35,'2026_10_08_000002_add_structured_address_fields_to_donors_table',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrator','Full system access and user management.','2026-10-07 06:09:35','2026-10-07 06:09:35'),(2,'secretary','Secretary','Donor records and in-kind inventory workflows.','2026-10-07 06:09:35','2026-10-07 06:09:35'),(3,'treasurer','Treasurer','Cash transactions and bank reconciliation workflows.','2026-10-07 06:09:35','2026-10-07 06:09:35');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_activity_events`
--

DROP TABLE IF EXISTS `security_activity_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_activity_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `security_activity_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_activity_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_activity_events`
--

LOCK TABLES `security_activity_events` WRITE;
/*!40000 ALTER TABLE `security_activity_events` DISABLE KEYS */;
INSERT INTO `security_activity_events` VALUES (4,11,11,'admin_authenticated','Authorized admin user Gina Gamboa successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-07 06:17:48','2026-10-07 06:17:48','2026-10-07 06:17:48'),(5,12,11,'staff_account_created','Gina Gamboa created staff account playwright.secretary@example.com','{\"role\": \"secretary\"}','127.0.0.1','2026-10-07 06:22:26','2026-10-07 06:22:26','2026-10-07 06:22:26'),(6,13,11,'staff_account_created','Gina Gamboa created staff account playwright.treasurer@example.com','{\"role\": \"treasurer\"}','127.0.0.1','2026-10-07 06:24:09','2026-10-07 06:24:09','2026-10-07 06:24:09'),(7,14,11,'staff_account_created','Gina Gamboa created staff account playwright.admin.created@example.test','{\"role\": \"admin\"}','127.0.0.1','2026-10-07 06:55:14','2026-10-07 06:55:14','2026-10-07 06:55:14'),(8,15,11,'staff_account_created','Gina Gamboa created staff account playwright.secretary.created@example.test','{\"role\": \"secretary\"}','127.0.0.1','2026-10-07 07:03:23','2026-10-07 07:03:23','2026-10-07 07:03:23'),(9,16,11,'staff_account_created','Gina Gamboa created staff account playwright.treasurer.created@example.test','{\"role\": \"treasurer\"}','127.0.0.1','2026-10-07 07:03:27','2026-10-07 07:03:27','2026-10-07 07:03:27'),(10,17,11,'staff_account_created','Gina Gamboa created staff account playwright.secretary.new@example.test','{\"role\": \"secretary\"}','127.0.0.1','2026-10-07 07:03:31','2026-10-07 07:03:31','2026-10-07 07:03:31'),(11,18,11,'staff_account_created','Gina Gamboa created staff account playwright.treasurer.new@example.test','{\"role\": \"treasurer\"}','127.0.0.1','2026-10-07 07:03:34','2026-10-07 07:03:34','2026-10-07 07:03:34'),(12,14,14,'admin_authenticated','Authorized admin user Browser Admin Created successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-07 07:19:53','2026-10-07 07:19:53','2026-10-07 07:19:53'),(13,19,11,'staff_account_created','Gina Gamboa created staff account pw.admin.verified@example.test','{\"role\": \"admin\"}','127.0.0.1','2026-10-09 23:00:16','2026-10-09 23:00:16','2026-10-09 23:00:16'),(14,20,11,'staff_account_created','Gina Gamboa created staff account pw.secretary.verified@example.test','{\"role\": \"secretary\"}','127.0.0.1','2026-10-09 23:01:13','2026-10-09 23:01:13','2026-10-09 23:01:13'),(15,21,11,'staff_account_created','Gina Gamboa created staff account mcp.admin.20261010@example.test','{\"role\": \"admin\"}','127.0.0.1','2026-10-09 23:03:35','2026-10-09 23:03:35','2026-10-09 23:03:35'),(16,22,11,'staff_account_created','Gina Gamboa created staff account pw.treasurer.final@example.test','{\"role\": \"treasurer\"}','127.0.0.1','2026-10-09 23:06:54','2026-10-09 23:06:54','2026-10-09 23:06:54'),(17,19,19,'admin_authenticated','Authorized admin user PW Admin Verified successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-16 22:57:15','2026-10-16 22:57:15','2026-10-16 22:57:15'),(18,21,21,'admin_authenticated','Authorized admin user MCP Admin successfully unlocked access with correct credentials and active 2FA.','{\"source\": \"system_security_seed\"}','127.0.0.1','2026-10-16 22:57:15','2026-10-16 22:57:15','2026-10-16 22:57:15');
/*!40000 ALTER TABLE `security_activity_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_permission_user`
--

DROP TABLE IF EXISTS `security_permission_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_permission_user`
--

LOCK TABLES `security_permission_user` WRITE;
/*!40000 ALTER TABLE `security_permission_user` DISABLE KEYS */;
INSERT INTO `security_permission_user` VALUES (1,11,1,NULL,'2026-10-07 06:17:47','2026-10-16 22:57:04'),(2,11,2,NULL,'2026-10-07 06:17:47','2026-10-16 22:57:05'),(3,12,3,NULL,'2026-10-07 06:33:05','2026-10-16 22:57:06'),(4,12,4,NULL,'2026-10-07 06:33:05','2026-10-16 22:57:07'),(5,13,2,NULL,'2026-10-07 06:33:05','2026-10-16 22:57:07'),(6,13,5,NULL,'2026-10-07 06:33:05','2026-10-16 22:57:08'),(7,14,1,NULL,'2026-10-07 07:19:51','2026-10-16 22:57:08'),(8,14,2,NULL,'2026-10-07 07:19:51','2026-10-16 22:57:09'),(9,15,3,NULL,'2026-10-07 07:19:51','2026-10-16 22:57:09'),(10,15,4,NULL,'2026-10-07 07:19:51','2026-10-16 22:57:09'),(11,16,2,NULL,'2026-10-07 07:19:52','2026-10-16 22:57:10'),(12,16,5,NULL,'2026-10-07 07:19:52','2026-10-16 22:57:10'),(13,17,3,NULL,'2026-10-07 07:19:52','2026-10-16 22:57:10'),(14,17,4,NULL,'2026-10-07 07:19:52','2026-10-16 22:57:11'),(15,18,2,NULL,'2026-10-07 07:19:52','2026-10-16 22:57:11'),(16,18,5,NULL,'2026-10-07 07:19:53','2026-10-16 22:57:11'),(17,19,1,NULL,'2026-10-16 22:57:12','2026-10-16 22:57:12'),(18,19,2,NULL,'2026-10-16 22:57:12','2026-10-16 22:57:12'),(19,20,3,NULL,'2026-10-16 22:57:13','2026-10-16 22:57:13'),(20,20,4,NULL,'2026-10-16 22:57:13','2026-10-16 22:57:13'),(21,21,1,NULL,'2026-10-16 22:57:13','2026-10-16 22:57:13'),(22,21,2,NULL,'2026-10-16 22:57:14','2026-10-16 22:57:14'),(23,22,2,NULL,'2026-10-16 22:57:14','2026-10-16 22:57:14'),(24,22,5,NULL,'2026-10-16 22:57:14','2026-10-16 22:57:14');
/*!40000 ALTER TABLE `security_permission_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_permissions`
--

DROP TABLE IF EXISTS `security_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_permissions`
--

LOCK TABLES `security_permissions` WRITE;
/*!40000 ALTER TABLE `security_permissions` DISABLE KEYS */;
INSERT INTO `security_permissions` VALUES (1,'manage_accounts_permissions','Manage Accounts & Key Permissions','Create users, update role access, and maintain authorization keys.',10,'2026-10-07 06:17:46','2026-10-07 06:17:46'),(2,'access_audit_logs','Access System Audit Logs & Security Tickets','Review administrative audit trails and security incident tickets.',20,'2026-10-07 06:17:46','2026-10-07 06:17:46'),(3,'register_in_kind_supplies','Register & Allocate In-Kind Supplies','Manage incoming goods and inventory allocation workflows.',30,'2026-10-07 06:17:46','2026-10-07 06:17:46'),(4,'adjust_stock_levels','Adjust Warehouse Stock Levels','Update inventory counts and warehouse stock movement records.',40,'2026-10-07 06:17:46','2026-10-07 06:17:46'),(5,'record_cash_credits','Record Cash / GCash Ledger Credits','Record cash and electronic donation ledger credits.',50,'2026-10-07 06:17:46','2026-10-07 06:17:46');
/*!40000 ALTER TABLE `security_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_security_profiles`
--

DROP TABLE IF EXISTS `user_security_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_security_profiles`
--

LOCK TABLES `user_security_profiles` WRITE;
/*!40000 ALTER TABLE `user_security_profiles` DISABLE KEYS */;
INSERT INTO `user_security_profiles` VALUES (1,11,1,1,'2026-10-07 06:17:47','912043','2026-10-07 06:17:47','2026-10-07 06:17:47'),(2,12,1,1,'2026-10-07 06:33:04','994956','2026-10-07 06:33:04','2026-10-07 06:33:04'),(3,13,1,1,'2026-10-07 06:33:05','077869','2026-10-07 06:33:05','2026-10-07 06:33:05'),(4,14,1,1,'2026-10-07 07:19:51','160782','2026-10-07 07:19:51','2026-10-07 07:19:51'),(5,15,1,1,'2026-10-07 07:19:51','243695','2026-10-07 07:19:51','2026-10-07 07:19:51'),(6,16,1,1,'2026-10-07 07:19:51','326608','2026-10-07 07:19:51','2026-10-07 07:19:51'),(7,17,1,1,'2026-10-07 07:19:52','409521','2026-10-07 07:19:52','2026-10-07 07:19:52'),(8,18,1,1,'2026-10-07 07:19:52','492434','2026-10-07 07:19:52','2026-10-07 07:19:52'),(9,19,1,1,'2026-10-16 22:57:11','575347','2026-10-16 22:57:12','2026-10-16 22:57:12'),(10,20,1,1,'2026-10-16 22:57:13','658260','2026-10-16 22:57:13','2026-10-16 22:57:13'),(11,21,1,1,'2026-10-16 22:57:13','741173','2026-10-16 22:57:13','2026-10-16 22:57:13'),(12,22,1,1,'2026-10-16 22:57:14','824086','2026-10-16 22:57:14','2026-10-16 22:57:14');
/*!40000 ALTER TABLE `user_security_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (11,1,'Gina','Gamboa','Gina Gamboa','gina@gmail.com',NULL,'$2y$12$toAj.HE/zZXhNqC3Xc9XbuSohwdww4CRHX80sUPx8/RIScRzTf8f.',0,'active','2026-10-16 22:58:18',NULL,'2026-10-07 06:17:46','2026-10-16 22:58:18'),(12,2,'Playwright','Secretary','Playwright Secretary','playwright.secretary@example.com',NULL,'$2y$12$94hLYFPCYz18m7rXemC.DO0DViDW5aiznkFAERMmdBx.pg9NDmQga',0,'active','2026-10-07 06:35:10',NULL,'2026-10-07 06:22:26','2026-10-07 06:35:10'),(13,3,'Playwright','Treasurer','Playwright Treasurer','playwright.treasurer@example.com',NULL,'$2y$12$RLHknRw/iflZf0PorSxKuu8PjSGm0uh0J1m0nS/ll7s7RSdxEnGFG',0,'active','2026-10-07 06:35:16',NULL,'2026-10-07 06:24:09','2026-10-07 06:35:16'),(14,1,'Browser','Admin Created','Browser Admin Created','playwright.admin.created@example.test',NULL,'$2y$12$IGubYJ0vCSCyD.m3P.AKue2/LbTVoGzIBxqxr5SXLHuzGO0Grq8ZC',0,'active',NULL,NULL,'2026-10-07 06:55:14','2026-10-07 06:55:14'),(15,2,'Browser','Secretary Two','Browser Secretary Two','playwright.secretary.created@example.test',NULL,'$2y$12$fhSR8esLqeqVKoMjQTO2Ru.WZpKa7NmkGMNiUqzb8G8a4vRb/INtS',0,'active',NULL,NULL,'2026-10-07 07:03:23','2026-10-07 07:03:23'),(16,3,'Browser','Treasurer Two','Browser Treasurer Two','playwright.treasurer.created@example.test',NULL,'$2y$12$2/TsCKZe.6C7q0Gee5Bo9uVyVYiSb2JNaV2D0cxvWxiiZmw5.MbvG',0,'active',NULL,NULL,'2026-10-07 07:03:27','2026-10-07 07:03:27'),(17,2,'Browser','Secretary New','Browser Secretary New','playwright.secretary.new@example.test',NULL,'$2y$12$fKujxPWUdM4uMA7ZnnDY6eVqwPyWPIn7oXl6DHqBpNgcr3x282Iyq',0,'active',NULL,NULL,'2026-10-07 07:03:31','2026-10-07 07:03:31'),(18,3,'Browser','Treasurer New','Browser Treasurer New','playwright.treasurer.new@example.test',NULL,'$2y$12$eI6Ltb/CQl/W3CD9vbESXeJ3sLCmAwT2bONTlkHNh/aZmd2IAOjoq',0,'active',NULL,NULL,'2026-10-07 07:03:34','2026-10-07 07:03:34'),(19,1,'PW Admin','Verified','PW Admin Verified','pw.admin.verified@example.test',NULL,'$2y$12$SyL2ys9CdoV2M2Hag2NXhOJqFQXNWbAj/lk6uZ9s9i2xch6WUANY6',0,'active','2026-10-09 23:00:22',NULL,'2026-10-09 23:00:16','2026-10-09 23:01:02'),(20,2,'PW secretary','Verified','PW secretary Verified','pw.secretary.verified@example.test',NULL,'$2y$12$YgIXsFG.6XpC3vuVuSAuhOxKfmSojFFAnbBNbtXocOLsuyqo3WxUO',0,'active','2026-10-09 23:23:52',NULL,'2026-10-09 23:01:13','2026-10-09 23:23:52'),(21,1,'MCP','Admin','MCP Admin','mcp.admin.20261010@example.test',NULL,'$2y$12$XH3k3K8a0ZowhrbAKE3BXuC5jMu4t6JuwdnQvX8OfmvL3HQXaO4/2',1,'active',NULL,NULL,'2026-10-09 23:03:35','2026-10-09 23:03:35'),(22,3,'PW Treasurer','Final','PW Treasurer Final','pw.treasurer.final@example.test',NULL,'$2y$12$T84ldMaXyLQuTM7WDEvTg.pSj.ho4ornUssYVmm1RGCLIlYPzbyce',0,'active','2026-10-09 23:25:19',NULL,'2026-10-09 23:06:54','2026-10-09 23:29:39');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'barangay_donation_system'
--

--
-- Dumping routines for database 'barangay_donation_system'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-21  9:28:11
