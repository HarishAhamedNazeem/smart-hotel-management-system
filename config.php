<?php
/**
 * System Configuration File
 * Smart Hotel Management System
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kaizen');

// Application Configuration
define('APP_NAME', 'Smart Hotel Management System');
define('APP_URL', 'http://localhost/HotelMS-PHP');
define('APP_TIMEZONE', 'Asia/Kolkata'); // Change to your timezone

// Security Configuration
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('ACCOUNT_LOCKOUT_DURATION', 1800); // 30 minutes in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'kaizenhotelmanagementsystem@gmail.com'); // Your email
define('SMTP_PASS', 'upzj dfmx poiw dczk'); // Your email password
define('SMTP_FROM_EMAIL', 'kaizenhotelmanagementsystem@gmail.com');
define('SMTP_FROM_NAME', 'Kaizen Hotel Management System');

// Payment Gateway Configuration (Stripe)
define('STRIPE_PUBLIC_KEY', '');
define('STRIPE_SECRET_KEY', '');
define('STRIPE_WEBHOOK_SECRET', '');

// File Upload Configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Notification Configuration
define('ENABLE_EMAIL_NOTIFICATIONS', true);
define('ENABLE_PUSH_NOTIFICATIONS', false); // For mobile app

// Booking Configuration
define('CHECK_IN_TIME', '14:00:00'); // 2:00 PM
define('CHECK_OUT_TIME', '11:00:00'); // 11:00 AM
define('MIN_BOOKING_ADVANCE_HOURS', 0); // Allow same-day bookings
define('MAX_BOOKING_ADVANCE_DAYS', 365); // 1 year in advance
define('CANCELLATION_FREE_HOURS', 24); // Free cancellation within 24 hours

// Currency Configuration
define('CURRENCY_SYMBOL', 'LKR');
define('CURRENCY_CODE', 'LKR');
define('TAX_RATE', 0.18); // 18% GST

// Pagination Configuration
define('ITEMS_PER_PAGE', 20);

// Error Reporting (Set to 0 in production)
define('DISPLAY_ERRORS', 1);
define('ERROR_REPORTING', E_ALL);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting
if (DISPLAY_ERRORS) {
    error_reporting(ERROR_REPORTING);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session configuration (only set if session hasn't started yet)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 7200);
}

