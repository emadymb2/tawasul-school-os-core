<?php
/**
 * TawasulOS Configuration File
 *
 * Database credentials, paths, and system-wide settings.
 * For tos.fiksutiliratkaisut.fi deployment.
 */

// Prevent direct access
if (!defined('TAWASUL_ROOT')) {
    die('Direct access not permitted.');
}

// ---------------------------------------------------------------------
// Database Configuration
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'tos');
define('DB_USER', 'tawasul');
define('DB_PASS', 'tawasul123');

// ---------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------
define('TAWASUL_URL', 'https://tos.fiksutiliratkaisut.fi');
define('TAWASUL_ASSETS_URL', TAWASUL_URL . '/tawasul-os/public/assets');
define('TAWASUL_UPLOAD_PATH', TAWASUL_ROOT . '/public/uploads');
define('TAWASUL_UPLOAD_URL', TAWASUL_URL . '/tawasul-os/public/uploads');

// ---------------------------------------------------------------------
# Security
# ---------------------------------------------------------------------
define('TAWASUL_SALT', 'tos_fiksutiliratkaisut_2026_secret_key_change_in_prod');
define('TAWASUL_HASH_ALGORITHM', 'sha256');
define('TAWASUL_PASSWORD_PEPPER', 'tos_pepper_2026_change_in_prod');

// ---------------------------------------------------------------------
# Session
# ---------------------------------------------------------------------
define('TAWASUL_SESSION_NAME', 'TAWASULSESSION');
define('TAWASUL_SESSION_LIFETIME', 3600); // 1 hour

// ---------------------------------------------------------------------
# API
# ---------------------------------------------------------------------
define('TAWASUL_API_VERSION', 'v2');
define('TAWASUL_API_RATE_LIMIT', 600); // requests per minute per API key

// ---------------------------------------------------------------------
# System
# ---------------------------------------------------------------------
define('TAWASUL_SYSTEM_NAME', 'TawasulOS');
define('TAWASUL_ORGANIZATION_NAME', 'Fiksu Utiliratkaisut');
define('TAWASUL_ORGANIZATION_SHORT', 'Fiksu');
define('TAWASUL_DEFAULT_TIMEZONE', 'Europe/Helsinki');
define('TAWASUL_DEFAULT_LOCALE', 'en');
define('TAWASUL_DEFAULT_CURRENCY', 'EUR');