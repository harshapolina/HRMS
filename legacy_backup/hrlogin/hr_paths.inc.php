<?php
/**
 * Resolves …/hrlogin web base + parent (…/userlogin1) from SCRIPT_NAME.
 * Sets $HR_WEB_BASE, $HR_APP_BASE, and CSS hrefs. Falls back to mnts.in layout if needed.
 */

$HR_WEB_BASE = '';
$HR_APP_BASE = '';
$hr_superadmin_css_href = '../superadmin/assets/css/style_dashboard.css';
$hr_overrides_css_href = 'assets/css/hr_soft.css';

$sn = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';

if ($sn !== '') {
    // First /hrlogin segment in the path (non-greedy prefix)
    if (preg_match('#^(.+?)/hrlogin(?:/|$)#', $sn, $m)) {
        $HR_WEB_BASE = $m[1] . '/hrlogin';
    } elseif (preg_match('#^(/hrlogin)(?:/|$)#', $sn, $m2)) {
        $HR_WEB_BASE = '/hrlogin';
    } else {
        $d = dirname($sn);
        $d = ($d === '/' || $d === '\\' || $d === '.') ? '' : rtrim($d, '/');
        $HR_WEB_BASE = $d;
    }

    if ($HR_WEB_BASE !== '' && substr($HR_WEB_BASE, 0, 1) !== '/') {
        $HR_WEB_BASE = '/' . ltrim($HR_WEB_BASE, '/');
    }

    if ($HR_WEB_BASE !== '' && $HR_WEB_BASE !== '.') {
        $pv = dirname($HR_WEB_BASE);
        $pv = str_replace('\\', '/', $pv);
        if ($pv !== '/' && $pv !== '\\' && $pv !== '.' && $pv !== '') {
            $HR_APP_BASE = rtrim($pv, '/');
        }
    }

    if ($HR_APP_BASE !== '' && $HR_WEB_BASE !== '') {
        $hr_superadmin_css_href = $HR_APP_BASE . '/superadmin/assets/css/style_dashboard.css';
                $hr_overrides_css_href = $HR_WEB_BASE . '/assets/css/hr_soft.css';
    } elseif ($HR_WEB_BASE !== '' && ($HR_APP_BASE === '' || $HR_APP_BASE === '/')) {
        // e.g. /hrlogin at domain root — superadmin is sibling via relative URL
        $hr_superadmin_css_href = '../superadmin/assets/css/style_dashboard.css';
                $hr_overrides_css_href = $HR_WEB_BASE . '/assets/css/hr_soft.css';
    }
}

// Production default (mnts.in) if SCRIPT_NAME did not contain /hrlogin/
if ($HR_WEB_BASE === '') {
    $HR_WEB_BASE = '/incentiveapp_integration/userlogin1/hrlogin';
    $HR_APP_BASE = '/incentiveapp_integration/userlogin1';
    $hr_superadmin_css_href = $HR_APP_BASE . '/superadmin/assets/css/style_dashboard.css';
            $hr_overrides_css_href = $HR_WEB_BASE . '/assets/css/hr_soft.css';
}
