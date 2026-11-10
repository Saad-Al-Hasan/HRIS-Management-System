<?php

// Use the separate test database.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'hris_management_system_test');
define('DB_USER', 'root');
define('DB_PASS', '');

// Load the application database and model classes.
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Attendance.php';
