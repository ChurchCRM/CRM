<?php

/**
 * Safely loads Config.php with graceful handling for missing configuration.
 * Redirects to setup if Config.php doesn't exist.
 * This file should be used by all Slim application entry points.
 */

if (file_exists(__DIR__ . '/Config.php')) {
    require_once __DIR__ . '/Config.php';
} else {
    // Calculate relative path to setup from the calling script
    // All Slim apps are in subdirectories one level down from src/
    header('Location: ../setup');
    exit;
}
