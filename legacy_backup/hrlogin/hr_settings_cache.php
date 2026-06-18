<?php
require_once __DIR__ . '/capacity_config.php';

const HR_SETTINGS_APCU_KEY = 'hr_settings_all_v1';

/**
 * @return array<string, string|null>
 */
function hr_settings_defaults(): array
{
    return [
        'office_start_time' => '09:00:00',
        'grace_period_minutes' => '15',
        'break_time_minutes' => '0',
        'office_latitude' => null,
        'office_longitude' => null,
        'office_radius' => '100',
    ];
}

/**
 * Load all HR settings with request-level and optional APCu cache.
 *
 * @return array<string, string|null>
 */
function hr_settings_load_all(mysqli $con): array
{
    static $requestCache = null;
    if ($requestCache !== null) {
        return $requestCache;
    }

    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch(HR_SETTINGS_APCU_KEY, $success);
        if ($success && is_array($cached)) {
            $requestCache = $cached;
            return $requestCache;
        }
    }

    $settings = hr_settings_defaults();
    $result = $con->query('SELECT setting_key, setting_value FROM hr_settings');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $result->free();
    }

    if (function_exists('apcu_store')) {
        apcu_store(HR_SETTINGS_APCU_KEY, $settings, HR_SETTINGS_CACHE_TTL);
    }

    $requestCache = $settings;
    return $requestCache;
}

function hr_settings_get(mysqli $con, string $key, $default = null)
{
    $settings = hr_settings_load_all($con);
    return array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== ''
        ? $settings[$key]
        : $default;
}

function hr_settings_invalidate_cache(): void
{
    if (function_exists('apcu_delete')) {
        apcu_delete(HR_SETTINGS_APCU_KEY);
    }
}
