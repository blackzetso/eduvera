
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` enum('enable','disable') NOT NULL DEFAULT 'enable',
  `parent_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_subject` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_category_subject` (`category_id`,`subject_id`),
  KEY `category_subject_subject_id_foreign` (`subject_id`),
  CONSTRAINT `category_subject_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_subject_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exchange_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(3) NOT NULL DEFAULT 'USD',
  `to_currency` varchar(3) NOT NULL DEFAULT 'EGP',
  `rate` decimal(10,4) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `fetched_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exchange_rates_from_currency_to_currency_is_active_unique` (`from_currency`,`to_currency`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `video_id` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `access_type` varchar(255) NOT NULL DEFAULT 'free',
  `embed_code` text DEFAULT NULL,
  `lecture_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `files_lecture_id_foreign` (`lecture_id`),
  CONSTRAINT `files_lecture_id_foreign` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_inputs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `form_inputs_form_id_foreign` (`form_id`),
  CONSTRAINT `form_inputs_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` enum('enable','disable') NOT NULL DEFAULT 'enable',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guardian_student` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guardian_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `guardian_student_guardian_id_student_id_unique` (`guardian_id`,`student_id`),
  KEY `guardian_student_student_id_foreign` (`student_id`),
  CONSTRAINT `guardian_student_guardian_id_foreign` FOREIGN KEY (`guardian_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guardian_student_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `language_phrases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` bigint(20) unsigned NOT NULL,
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `key` varchar(255) NOT NULL,
  `word` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `language_phrases_language_id_group_key_unique` (`language_id`,`group`,`key`),
  CONSTRAINT `language_phrases_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` enum('enabled','disabled') NOT NULL DEFAULT 'enabled',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lectures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lectures_lesson_id_foreign` (`lesson_id`),
  CONSTRAINT `lectures_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_timetable_period` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `timetable_period_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lesson_period` (`lesson_id`,`timetable_period_id`),
  KEY `lesson_timetable_period_timetable_period_id_foreign` (`timetable_period_id`),
  CONSTRAINT `lesson_timetable_period_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_timetable_period_timetable_period_id_foreign` FOREIGN KEY (`timetable_period_id`) REFERENCES `timetable_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lessons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `short_description` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `semesters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`semesters`)),
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('enable','disable') NOT NULL DEFAULT 'enable',
  `expiry_period` enum('lifetime','limited') NOT NULL DEFAULT 'lifetime',
  `expire_date` date DEFAULT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `faqs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`faqs`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_stream_attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `live_stream_id` bigint(20) unsigned NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_email` varchar(255) DEFAULT NULL,
  `join_time` datetime DEFAULT NULL,
  `leave_time` datetime DEFAULT NULL,
  `duration_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_stream_attendances_live_stream_id_foreign` (`live_stream_id`),
  CONSTRAINT `live_stream_attendances_live_stream_id_foreign` FOREIGN KEY (`live_stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_stream_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `live_stream_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `author_name` varchar(100) NOT NULL,
  `author_email` varchar(150) DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_stream_comments_live_stream_id_foreign` (`live_stream_id`),
  KEY `live_stream_comments_parent_id_foreign` (`parent_id`),
  CONSTRAINT `live_stream_comments_live_stream_id_foreign` FOREIGN KEY (`live_stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_stream_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `live_stream_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_stream_exam_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `live_stream_id` bigint(20) unsigned NOT NULL,
  `time_limit` smallint(5) unsigned NOT NULL DEFAULT 600,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `activated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_stream_exam_sessions_live_stream_id_foreign` (`live_stream_id`),
  CONSTRAINT `live_stream_exam_sessions_live_stream_id_foreign` FOREIGN KEY (`live_stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_stream_quiz_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `live_stream_quiz_id` bigint(20) unsigned NOT NULL,
  `live_stream_id` bigint(20) unsigned NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_identifier` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `correction` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quiz_student_unique` (`live_stream_quiz_id`,`student_identifier`),
  KEY `live_stream_quiz_answers_live_stream_id_foreign` (`live_stream_id`),
  CONSTRAINT `live_stream_quiz_answers_live_stream_id_foreign` FOREIGN KEY (`live_stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_stream_quiz_answers_live_stream_quiz_id_foreign` FOREIGN KEY (`live_stream_quiz_id`) REFERENCES `live_stream_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_stream_quizzes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `live_stream_id` bigint(20) unsigned NOT NULL,
  `exam_session_id` bigint(20) unsigned DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('true_false','true_false_correction','fill_blank','multiple_choice','essay','pdf_exam') NOT NULL DEFAULT 'true_false',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` varchar(255) DEFAULT NULL,
  `allow_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `time_limit` smallint(6) NOT NULL DEFAULT 60,
  `status` enum('draft','active','closed') NOT NULL DEFAULT 'draft',
  `activated_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_stream_quizzes_live_stream_id_foreign` (`live_stream_id`),
  KEY `live_stream_quizzes_exam_session_id_foreign` (`exam_session_id`),
  CONSTRAINT `live_stream_quizzes_exam_session_id_foreign` FOREIGN KEY (`exam_session_id`) REFERENCES `live_stream_exam_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `live_stream_quizzes_live_stream_id_foreign` FOREIGN KEY (`live_stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_stream_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `live_stream_id` bigint(20) unsigned NOT NULL,
  `reviewer_name` varchar(255) NOT NULL,
  `reviewer_email` varchar(255) DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_stream_reviews_live_stream_id_foreign` (`live_stream_id`),
  CONSTRAINT `live_stream_reviews_live_stream_id_foreign` FOREIGN KEY (`live_stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_streams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `learning_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`learning_points`)),
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `teacher_name` varchar(255) NOT NULL,
  `teacher_email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `teams_meeting_id` varchar(255) DEFAULT NULL,
  `zoom_meeting_id` varchar(255) DEFAULT NULL,
  `livekit_room_name` varchar(255) DEFAULT NULL,
  `google_meet_space_name` varchar(255) DEFAULT NULL,
  `hms_room_id` varchar(255) DEFAULT NULL,
  `join_url` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `status` enum('scheduled','live','ended') NOT NULL DEFAULT 'scheduled',
  `recording_type` enum('none','server','local') NOT NULL DEFAULT 'none',
  `recording_status` enum('none','recording','uploading','ready','failed') NOT NULL DEFAULT 'none',
  `recording_path` varchar(255) DEFAULT NULL,
  `recording_size_mb` double unsigned DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `extra_session_status` enum('pending','approved','rejected') DEFAULT NULL,
  `pending_extension_minutes` int(10) unsigned DEFAULT NULL,
  `provider` enum('none','teams','zoom','livekit','google_meet','hms') NOT NULL DEFAULT 'none',
  `classroom_dashboard` enum('jitsi','livekit','hms') NOT NULL DEFAULT 'jitsi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_streams_category_id_foreign` (`category_id`),
  CONSTRAINT `live_streams_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storage_consumption` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` bigint(20) unsigned NOT NULL,
  `storage_gb` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `bandwidth_gb` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `bunny_cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `platform_cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `storage_consumption_file_id_foreign` (`file_id`),
  CONSTRAINT `storage_consumption_file_id_foreign` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storage_wallets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `balance` decimal(12,6) NOT NULL,
  `total_credited` decimal(12,6) NOT NULL,
  `total_debited` decimal(12,6) NOT NULL,
  `last_synced_storage_gb` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `last_synced_bandwidth_gb` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `is_activated` tinyint(1) NOT NULL DEFAULT 0,
  `initial_credit_granted` tinyint(1) NOT NULL DEFAULT 0,
  `activated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subject_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject_user_user_id_foreign` (`user_id`),
  KEY `subject_user_subject_id_foreign` (`subject_id`),
  CONSTRAINT `subject_user_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subject_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_name` varchar(255) NOT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `run_interval` int(11) NOT NULL DEFAULT 86400,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_result` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_tasks_task_name_unique` (`task_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetable_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timetable_period_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved') NOT NULL DEFAULT 'approved',
  `type` enum('main','backup') NOT NULL DEFAULT 'main',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`timetable_period_id`,`teacher_id`),
  KEY `timetable_assignments_teacher_id_foreign` (`teacher_id`),
  KEY `timetable_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `timetable_assignments_subject_id_foreign` (`subject_id`),
  CONSTRAINT `timetable_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `timetable_assignments_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_assignments_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_assignments_timetable_period_id_foreign` FOREIGN KEY (`timetable_period_id`) REFERENCES `timetable_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetable_days` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timetable_id` bigint(20) unsigned NOT NULL,
  `day_name` varchar(255) NOT NULL,
  `day_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_day_name` (`timetable_id`,`day_name`),
  CONSTRAINT `timetable_days_timetable_id_foreign` FOREIGN KEY (`timetable_id`) REFERENCES `timetables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetable_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timetable_id` bigint(20) unsigned NOT NULL,
  `timetable_day_id` bigint(20) unsigned DEFAULT NULL,
  `day` enum('sunday','monday','tuesday','wednesday','thursday','friday','saturday') NOT NULL,
  `period_number` int(11) NOT NULL,
  `time_from` time NOT NULL,
  `time_to` time NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_period_time` (`timetable_id`,`timetable_day_id`,`time_from`,`time_to`,`category_id`),
  KEY `timetable_periods_category_id_foreign` (`category_id`),
  KEY `timetable_periods_timetable_day_id_foreign` (`timetable_day_id`),
  CONSTRAINT `timetable_periods_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_periods_timetable_day_id_foreign` FOREIGN KEY (`timetable_day_id`) REFERENCES `timetable_days` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_periods_timetable_id_foreign` FOREIGN KEY (`timetable_id`) REFERENCES `timetables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `academic_year` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `national_id` varchar(255) DEFAULT NULL,
  `guardian_phone` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','student','teacher','guardian') NOT NULL DEFAULT 'student',
  `role` varchar(255) DEFAULT NULL,
  `is_owner` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_team_id` bigint(20) unsigned DEFAULT NULL,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_recharge_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,6) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_gateway` varchar(255) NOT NULL DEFAULT 'fawaterak',
  `payment_method_id` tinyint(3) unsigned DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_recharge_requests_wallet_id_foreign` (`wallet_id`),
  KEY `wallet_recharge_requests_status_index` (`status`),
  KEY `wallet_recharge_requests_transaction_id_index` (`transaction_id`),
  CONSTRAINT `wallet_recharge_requests_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `storage_wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint(20) unsigned NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(12,6) NOT NULL,
  `description` varchar(255) NOT NULL,
  `related_type` varchar(255) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_transactions_wallet_id_foreign` (`wallet_id`),
  KEY `wallet_transactions_related_type_related_id_index` (`related_type`,`related_id`),
  CONSTRAINT `wallet_transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `storage_wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

