<?php
/**
 * Capacity tuning for high-volume punch-in windows.
 * Set PUNCH_STAGGER_MAX_SECONDS to 0 to disable client-side load spreading.
 */
define('PUNCH_STAGGER_MAX_SECONDS', 0);
define('HR_SETTINGS_CACHE_TTL', 300);
