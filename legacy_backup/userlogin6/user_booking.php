<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$pageTitle = "Bookings";
$isBookingPage = true;


// Session is already started in htmlopen.php, so we can safely access session variables
// Get session variables
$nameuser = $_SESSION['username'] ?? '';
$userid = $_SESSION['tablename'] ?? '';
$Project_type = $_SESSION['project_type'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';
$id = $_SESSION['id'] ?? '';

// ── Mirror of APPROVAL_MANAGER in action.php — change BOTH if you switch users ──
$approvalManager = 'rahul00761';

$normalizedUserTypeRaw = strtolower(trim((string) $user_type));
$normalizedUserType = preg_replace('/[\s_-]+/', ' ', $normalizedUserTypeRaw);
$normalizedUserType = trim($normalizedUserType);

$isBusinessHead = (
    $normalizedUserType === 'business head' ||
    $normalizedUserType === 'businesshead' ||
    (str_contains($normalizedUserType, 'business') && str_contains($normalizedUserType, 'head'))
);

$isPromoter = (
    $normalizedUserType === 'promoter' ||
    str_contains($normalizedUserType, 'promoter')
);

$canSeeActualRevenue = $isBusinessHead || $isPromoter;

// Check for dashboard filter parameters
$filterUser = $_GET['filterUser'] ?? '';
$managerView = $_GET['managerView'] ?? '';

// Debug: Log the filter parameters
error_log("BOOKINGS DEBUG - filterUser: " . $filterUser . ", managerView: " . $managerView . ", user_type: " . $user_type);

// Override userid if manager view with specific user filter is requested
if (!empty($filterUser) && $managerView === 'true' && $user_type !== 'user') {
    $originalUserId = $userid;
    $userid = $filterUser;
    error_log("BOOKINGS DEBUG - User filter applied. Original userid: " . $originalUserId . ", New userid: " . $userid);
} else {
    error_log("BOOKINGS DEBUG - No user filter applied. Current userid: " . $userid);
}


// Include database connection and utility classes
require_once 'db.php';
require_once 'util.php';

$db = new Database;
$util = new Util;

// DB fallback: re-verify $isPromoter from accounts table (session user_type can be stale)
if (!$isPromoter && !empty($userid)) {
    try {
        $conn = $db->getConnection();
        $stmtRole = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tn LIMIT 1");
        $stmtRole->execute(['tn' => $userid]);
        $dbUserType = strtolower(trim((string)($stmtRole->fetchColumn() ?? '')));
        if (str_contains($dbUserType, 'promoter')) {
            $isPromoter = true;
            $canSeeActualRevenue = true;
        }
    } catch (Exception $e) {}
}

// Get commission structure from session 
$salary = $_SESSION['salary'] ?? 0;
$frist = $_SESSION['one_amt'] ?? 0;
$secound = $_SESSION['two_amt'] ?? 0;
$third = $_SESSION['thrid_amt'] ?? 0;
$forth = $_SESSION['forth_amt'] ?? 0;
$fifth = $_SESSION['fifth_amt'] ?? 0;
$sixth = $_SESSION['sixth_amt'] ?? 0;

// Fetch bookings data from database
$allBookings = $db->read();

// Apply same ownership logic as dashboard total agreement:
// - Always include own bookings (source_table / created_by)
// - For managers/leads/promoters/business heads, also include bookings where they are in assign_user
$includeAssigned = strtolower(trim((string) $user_type)) !== 'user';

if ($user_type === 'business head') {
    $bookings = $allBookings; // Business Head sees everything
} else {
    $bookings = array_filter($allBookings, function ($booking) use ($userid, $includeAssigned) {
        $source = $booking['source_table'] ?? '';
        $created_by = $booking['created_by'] ?? '';
        $assignUserRaw = $booking['assign_user'] ?? '';
        $assignedList = array_filter(array_map('trim', explode(',', (string) $assignUserRaw)));
        $isAssigned = $includeAssigned && in_array($userid, $assignedList, true);
        return $source == $userid || $created_by == $userid || $isAssigned;
    });
}

// Determine financial years available and filter by the selected year (April 1 - March 31)
$today = new DateTime();
$currentYear = (int) $today->format('Y');
$currentMonth = (int) $today->format('n');
$currentFyStartYear = $currentMonth >= 4 ? $currentYear : $currentYear - 1;
$currentFinancialYear = $currentFyStartYear . '-' . ($currentFyStartYear + 1);

// Build available financial year options from booking data (fallback to current FY)
// Parse a booking date that may come in multiple formats (Y-m-d, d-m-Y, d/m/Y, etc.)
function parseBookingDate(?string $dateStr): ?DateTime
{
    if (empty($dateStr)) {
        error_log("BOOKINGS DEBUG - parseBookingDate: Empty date string");
        return null;
    }

    // Common formats we have seen in the DB/ui
    $formats = [
        'Y-m-d',
        'Y/m/d',
        'd-m-Y',
        'd/m/Y',
        'm-d-Y',
        'm/d/Y',
        'Y-m',    // for booking_month like 2024-04
        'Y/m',    // for booking_month like 2024/04
        'Y',      // fallback if only year is given
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $dateStr);
        if ($dt instanceof DateTime) {
            error_log("BOOKINGS DEBUG - parseBookingDate: Parsed '$dateStr' as format '$format' => " . $dt->format('Y-m-d'));
            return $dt;
        }
    }

    // Fallback to DateTime's parser for anything else
    try {
        $dt = new DateTime($dateStr);
        error_log("BOOKINGS DEBUG - parseBookingDate: Parsed '$dateStr' using DateTime parser => " . $dt->format('Y-m-d'));
        return $dt;
    } catch (Exception $e) {
        error_log("BOOKINGS DEBUG - parseBookingDate: Failed to parse '$dateStr' - " . $e->getMessage());
        return null;
    }
}

// Derive a usable DateTime for a booking: prefer booking_date, otherwise fall back to booking_month (assume 1st of month)
function getBookingDate(array $booking): ?DateTime
{
    // Prefer booking_month if present and valid
    $monthStr = $booking['booking_month'] ?? '';
    if (!empty($monthStr)) {
        $normalizedMonth = str_replace('/', '-', trim($monthStr));
        if (preg_match('/^\d{4}-\d{2}$/', $normalizedMonth)) {
            $normalizedMonth .= '-01';
        } elseif (preg_match('/^\d{4}$/', $normalizedMonth)) {
            $normalizedMonth .= '-04-01';
        }
        $fromMonth = parseBookingDate($normalizedMonth);
        if ($fromMonth instanceof DateTime) {
            return $fromMonth;
        }
    }

    // Fallback to booking_date
    $bookingDate = parseBookingDate($booking['booking_date'] ?? '');
    if ($bookingDate instanceof DateTime) {
        return $bookingDate;
    }

    return null;
}

$financialYearOptionsSet = [];
foreach ($allBookings as $booking) {
    $bookingDate = getBookingDate($booking);
    if (!$bookingDate) {
        continue;
    }

    $bookingYear = (int) $bookingDate->format('Y');
    $bookingMonth = (int) $bookingDate->format('n');
    $fyStartYear = $bookingMonth >= 4 ? $bookingYear : $bookingYear - 1;
    $fyLabel = $fyStartYear . '-' . ($fyStartYear + 1);
    $financialYearOptionsSet[$fyLabel] = true;
}

// Ensure the current FY is always present
$financialYearOptionsSet[$currentFinancialYear] = true;
// Always include recent fiscal years even if no bookings yet
$financialYearOptionsSet['2024-2025'] = true;
$financialYearOptionsSet['2023-2024'] = true;

// Build available city options from booking data
$cityOptionsSet = [];
foreach ($allBookings as $booking) {
    $city = trim($booking['city'] ?? '');
    if (!empty($city)) {
        $cityOptionsSet[$city] = true;
    }
}

// Add default cities if none exist in bookings
if (empty($cityOptionsSet)) {
    $defaultCities = ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow'];
    foreach ($defaultCities as $city) {
        $cityOptionsSet[$city] = true;
    }
}

$cityOptions = array_keys($cityOptionsSet);
sort($cityOptions); // Sort cities alphabetically

$requestedFinancialYear = $_GET['fy'] ?? $currentFinancialYear;
if (!preg_match('/^\d{4}-\d{4}$/', $requestedFinancialYear)) {
    $requestedFinancialYear = $currentFinancialYear;
}

[$fyStartStr, $fyEndStr] = explode('-', $requestedFinancialYear);
$selectedFyStartYear = (int) $fyStartStr;
$selectedFyEndYear = (int) $fyEndStr;

if ($selectedFyEndYear !== $selectedFyStartYear + 1) {
    $selectedFyStartYear = $currentFyStartYear;
    $selectedFyEndYear = $currentFyStartYear + 1;
    $requestedFinancialYear = $currentFinancialYear;
}

// Make sure the selected year is part of the options list
$financialYearOptionsSet[$requestedFinancialYear] = true;

$financialYearOptions = array_keys($financialYearOptionsSet);
rsort($financialYearOptions);

$selectedFinancialYear = $requestedFinancialYear;
$financialYearStart = new DateTime("{$selectedFyStartYear}-04-01");
$financialYearEnd = new DateTime("{$selectedFyEndYear}-03-31");

$financialYearStartStr = $financialYearStart->format('Y-m-d');
$financialYearEndStr = $financialYearEnd->format('Y-m-d');

// Handle city filtering
$requestedCity = $_GET['city'] ?? '';
$selectedCity = '';

// Only set selectedCity if a valid city is requested and it exists in our options
if (!empty($requestedCity) && in_array($requestedCity, $cityOptions, true)) {
    $selectedCity = $requestedCity;
}

// Debug: Log city filtering
error_log("CITY FILTER DEBUG - requestedCity: " . $requestedCity . ", selectedCity: " . $selectedCity);

// Apply financial year filter
$bookings = array_filter($bookings, function ($booking) use ($financialYearStart, $financialYearEnd) {
    $bookingDate = getBookingDate($booking);
    if (!$bookingDate) {
        return false;
    }
    // Use >= and <= to include boundary dates
    return ($bookingDate >= $financialYearStart && $bookingDate <= $financialYearEnd);
});

// Apply city filter if selected (only filter when a specific city is chosen)
if (!empty($selectedCity)) {
    $bookings = array_filter($bookings, function ($booking) use ($selectedCity) {
        $bookingCity = trim($booking['city'] ?? '');
        return $bookingCity === $selectedCity;
    });
    error_log("CITY FILTER DEBUG - Filtering for city: " . $selectedCity . ", bookings after filter: " . count($bookings));
} else {
    error_log("CITY FILTER DEBUG - No city filter applied, showing all cities");
}

// Sum agreement value and count for cancelled bookings (used for dashboard card)
$cancelledAgreementValue = 0;
$cancelledBookingCount = 0;
foreach ($bookings as $row) {
    if (strtolower(trim($row['astatus'] ?? '')) === 'canceled') {
        $cancelledAgreementValue += (float) ($row['agreement_value'] ?? 0);
        $cancelledBookingCount++;
    }
}


// Group rows by month
// Group rows by booking_month (use safe key fallback)
$groupedRows = [];
foreach ($bookings as $row) {
    $month = $row['booking_month'] ?? 'Unknown';
    if (!isset($groupedRows[$month])) {
        $groupedRows[$month] = [];
    }
    $groupedRows[$month][] = $row;
}

// Initialize overall stats
$overall_stats = [
    'booking' => 0,
    'earn' => 0,
    'paid' => 0,
    'revenue' => 0,
    'invoice_raise' => 0,
    'cancelledAgreementValue' => $cancelledAgreementValue,
    'cancelledBookingCount' => $cancelledBookingCount,
    'advancePay' => 0,
    'finalRemaining' => 0,
    'agreement' => 0,
    'deduct_agreement' => 0
];

// Helper function to format numbers in compact format (K, M, B) - 2 decimal places
function formatCompactNumber($number)
{
    if ($number >= 1000000000) {
        return '₹' . round($number / 1000000000, 2) . 'B';
    } elseif ($number >= 1000000) {
        return '₹' . round($number / 1000000, 2) . 'M';
    } elseif ($number >= 1000) {
        return '₹' . round($number / 1000, 2) . 'K';
    } else {
        return '₹' . number_format((float) $number);
    }
}

// Helper function to get full rupee value without abbreviation (for tooltips) - no decimals
function getFullRupeeValue($number)
{
    return '₹' . number_format((float) $number, 0);
}

// Compact formatter for Actual Revenue with K, L, M, B, T suffixes - 2 decimal places
function formatActualRevenueCompact($number)
{
    $num = (float) $number;
    $abs = abs($num);

    $suffixes = [
        'T' => 1_000_000_000_000, // trillion
        'B' => 1_000_000_000,     // billion
        'M' => 1_000_000,         // million
        'L' => 100_000,           // lakh
        'K' => 1_000,             // thousand
    ];

    foreach ($suffixes as $suffix => $threshold) {
        if ($abs >= $threshold) {
            $value = $num / $threshold;
            $rounded = round($value, 2);
            // Drop trailing .00 for cleaner display
            $formatted = (fmod($rounded, 1.0) === 0.0) ? (int) $rounded : $rounded;
            return '₹' . $formatted . $suffix;
        }
    }

    return '₹' . number_format($num, 2);
}

// Calculate monthly stats
$monthlyData = [];
foreach ($groupedRows as $month => $rows) {
    $monthStats = [
        'month' => $month,
        'bookings' => 0,
        'earn' => 0,
        'paid' => 0,
        'remaining' => 0,
        'revenue' => 0,
        'invoice_raise' => 0
    ];

    // Always calculate stats for whatever rows are visible for this user.
    // This keeps the top cards (overall bookings / agreement / revenue)
    // in sync with the table rows, regardless of role or project type.

    // Exclude cancelled bookings so count matches the visible table rows.
    $filteredRows = array_filter($rows, function ($row) {
        return strtolower(trim($row['astatus'] ?? '')) !== 'canceled';
    });
    $rowCount = count($filteredRows);

    // Initialize accumulators
    $D2 = 0;
    $total_agreement = 0;
    $deduct_agreement = 0;
    $recent_pay = 0;
    $remaning_amt = 0;
    $actual_incentive = 0;
    $monthlyActualRevenue = 0;
    $monthlyInvoiceAmount = 0;

    foreach ($filteredRows as $row) {
        $val = $row['agreement_value'] ?? 0;
        $D2 += $val;
        $total_agreement += $val;
        $deduct_agreement += (float) ($row['deduct_agreement'] ?? 0);
        $monthlyActualRevenue += (float) ($row['crevenue'] ?? 0);
        $monthlyInvoiceAmount += (float) ($row['invoice_raise'] ?? 0);
    }

    try {
        $recent_pay = is_callable([$db, 'getTotalSendAmt']) ? (int) $db->getTotalSendAmt($month) : 0;
    } catch (Throwable $e) {
        $recent_pay = 0;
    }

    $remaning_amt = max(0, $D2 - $recent_pay);
    $actual_incentive = $total_incentive ?? 0;

    $monthStats['bookings'] = $rowCount;
    $monthStats['earn'] = $D2;
    $monthStats['paid'] = $recent_pay;
    $monthStats['remaining'] = $remaning_amt;
    $monthStats['revenue'] = $monthlyActualRevenue;
    $monthStats['invoice_raise'] = $monthlyInvoiceAmount;
    $monthStats['incentive'] = $actual_incentive;
    $monthStats['agreement'] = $total_agreement;
    $monthStats['deduct_agreement'] = $deduct_agreement;

    $db->insertOrUpdateTrackingData(
        $month,
        $D2,
        $recent_pay,
        $remaning_amt,
        $userid,
        $rowCount,
        $actual_incentive,
        $user_type
    );

    // Update overall stats
    $overall_stats['booking'] += $monthStats['bookings'];
    $overall_stats['earn'] += $monthStats['earn'];
    $overall_stats['paid'] += $monthStats['paid'];
    $overall_stats['revenue'] += $monthStats['revenue'] ?? 0;
    $overall_stats['invoice_raise'] += $monthStats['invoice_raise'] ?? 0;
    $overall_stats['agreement'] += $monthStats['agreement'] ?? 0;
    $overall_stats['deduct_agreement'] += $monthStats['deduct_agreement'] ?? 0;

    $monthlyData[] = $monthStats;
}

// Get advance payment
$advancePay = $db->getAdvancePayByUser($userid);
$overall_stats['advancePay'] = $advancePay ? $advancePay : 0;

// Calculate final remaining based on total actual revenue minus total invoice raised amounts
$overall_stats['finalRemaining'] = max(0, $overall_stats['revenue'] - $overall_stats['invoice_raise']);


include 'htmlopen.php';
include 'header.php';
?>
<style>
/* iOS Safari specific fix for Bookings background repaint in dark mode */
@supports (-webkit-touch-callout: none) {
    [data-theme="dark"] body {
        background-attachment: scroll !important;
        background-image: none !important;
        background-color: #2c2c2e !important;
    }
}
</style>
<?php
// Align visibility flags with normalized role from header
$canSeeActualRevenue = $canSeeActualRevenue || (
    isset($currentRoleNormalized) && in_array($currentRoleNormalized, ['promoter', 'business_head'], true)
);
$isBusinessHead = $isBusinessHead || (
    isset($currentRoleNormalized) && $currentRoleNormalized === 'business_head'
);
?>

<?php if ($canSeeActualRevenue): ?>
    <style>
        /* Allow promoter and business head to view Actual Revenue column */
        .table-head th.expenses-col,
        .table-row td.expenses-cell {
            display: table-cell !important;
        }

        /* Ensure compact/mobile views also show */
        .compact-table-head th.expenses-col,
        .compact-row td.expenses-cell,
        .mobile-summary-info .expenses-cell {
            display: table-cell !important;
        }
    </style>
<?php endif; ?>






<!-- Edit Booking Modal Styling -->
<style>
    /* ==================================================
       Edit User Modal - Complete Styling
       ================================================== */

    /* Modal Background and Container */
    #editUserModal {
        background: rgba(9, 9, 9, 0.41) !important;
    }

    #editUserModal .modal-content {
        background: #fff !important;
        border-radius: 18px !important;
    }

    #editUserModal .modal-header {
        background: #fff !important;
        border-bottom: 1px solid #dee2e6 !important;
        padding: 1rem 1.5rem !important;
    }

    #editUserModal .modal-title {
        font-weight: 700 !important;
        font-size: 18px !important;
        margin: 0 !important;
        color: #000 !important;
    }

    #editUserModal .modal-body {
        background: #fff !important;
        padding: 1.25rem !important;
    }

    /* Form padding override */
    #editUserModal #edit-user-form {
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Container and Row Padding */
    #editUserModal .container {
        padding: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
    }

    #editUserModal .row {
        display: flex !important;
        flex-wrap: wrap !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100% !important;
    }

    #editUserModal .col-md-6 {
        flex: 0 0 auto !important;
        width: 50% !important;
        padding-left: 8px !important;
        padding-right: 8px !important;
        box-sizing: border-box !important;
    }

    #editUserModal .col-md-12,
    #editUserModal .col-lg-12 {
        flex: 0 0 auto !important;
        width: 100% !important;
        padding-left: 8px !important;
        padding-right: 8px !important;
        box-sizing: border-box !important;
    }

    #editUserModal .mb-2 {
        margin-bottom: 0.75rem !important;
    }

    #editUserModal .mb-3 {
        margin-bottom: 1rem !important;
    }

    /* Mobile - Stack columns */
    @media (max-width: 768px) {
        #editUserModal .col-md-6 {
            width: 100% !important;
        }
    }

    /* Form Item Styling */
    #editUserModal .form-item {
        margin-bottom: 0.5rem !important;
        position: relative !important;
        width: 100% !important;
    }

    #editUserModal .form-item input,
    #editUserModal .form-item select {
        display: block !important;
        background: #fff !important;
        border: 1px solid #000 !important;
        transition: .3s !important;
        padding: 10px 15px !important;
        border-radius: 8px !important;
        width: 100% !important;
        height: 42px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #000 !important;
        box-sizing: border-box !important;
    }

    /* Ensure form-control-lg doesn't override our styles */
    #editUserModal .form-control-lg {
        padding: 10px 15px !important;
        height: 42px !important;
        font-size: 14px !important;
        line-height: 1.5 !important;
    }

    /* Readonly inputs styling */
    #editUserModal input[readonly] {
        background-color: #f5f5f5 !important;
        cursor: not-allowed !important;
        opacity: 0.7 !important;
    }

    /* Date and Month inputs specific styling */
    #editUserModal input[type="date"],
    #editUserModal input[type="month"] {
        background: #fff !important;
        color: #000 !important;
        padding: 8px 15px !important;
        height: 42px !important;
    }

    /* Calendar picker icon */
    #editUserModal input[type="date"]::-webkit-calendar-picker-indicator,
    #editUserModal input[type="month"]::-webkit-calendar-picker-indicator {
        cursor: pointer !important;
        opacity: 1 !important;
    }

    /* Assigned User Dropdown Container */
    #editUserModal .dropdown-container {
        position: relative !important;
        width: 100% !important;
        margin-bottom: 1rem !important;
    }

    #editUserModal .form-label {
        font-weight: 600 !important;
        color: #000 !important;
        font-size: 14px !important;
        margin-bottom: 8px !important;
        display: block !important;
        position: static !important;
        background: transparent !important;
    }

    #editUserModal #unique_searchInput {
        display: block !important;
        background: #fff !important;
        border: 1px solid #000 !important;
        border-radius: 8px !important;
        width: 100% !important;
        height: 42px !important;
        padding: 10px 15px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #000 !important;
        transition: .3s !important;
        box-sizing: border-box !important;
    }

    #editUserModal #unique_searchInput:focus {
        border-color: #1b6c9f !important;
        outline: none !important;
        box-shadow: none !important;
    }

    #editUserModal #unique_source_table {
        display: none;
        border: 1px solid #ddd;
        border-radius: 8px;
        list-style-type: none;
        margin: 4px 0 0 0;
        padding: 0;
        max-height: 250px;
        overflow-y: auto;
        position: fixed !important;
        background-color: #fff;
        z-index: 100005;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    #editUserModal .unique-option {
        padding: 12px 16px;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
        color: #333;
    }

    #editUserModal .unique-option:last-child {
        border-bottom: none;
    }

    #editUserModal .unique-option:hover {
        background-color: #f8f9fa;
        color: #007bff;
    }

    /* Floating labels for text inputs (not date/month) */
    #editUserModal .form-item label {
        position: absolute !important;
        cursor: text !important;
        z-index: 2000 !important;
        left: 10px !important;
        font-weight: 600 !important;
        background: #fff !important;
        padding: 0 10px !important;
        transition: .3s !important;
        font-size: 11px !important;
        top: -8px !important;
        color: #000000 !important;
    }

    #editUserModal .form-item input:focus,
    #editUserModal .form-item select:focus {
        border-color: #1b6c9f !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(27, 108, 159, 0.1) !important;
    }

    #editUserModal .form-item input::placeholder,
    #editUserModal .form-item select::placeholder {
        color: #999 !important;
        font-weight: normal !important;
    }

    /* Checkboxes Styling - Horizontal Layout */
    #editUserModal .btncheckboxs {
        display: flex !important;
        gap: 20px !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        margin-bottom: 15px !important;
        padding: 0 !important;
    }

    #editUserModal .form-check-input {
        width: 18px !important;
        height: 18px !important;
        cursor: pointer !important;
        margin: 0 !important;
        border: 1px solid #ccc !important;
    }

    #editUserModal .form-check-label {
        cursor: pointer !important;
        font-weight: 500 !important;
        margin: 0 !important;
        font-size: 14px !important;
        color: #000 !important;
        user-select: none !important;
    }

    /* Status Buttons - Horizontal Pill Style */
    #editUserModal .btnwraps {
        display: flex !important;
        flex-direction: row !important;
        gap: 12px !important;
        flex-wrap: wrap !important;
        padding: 0 !important;
        margin-bottom: 15px !important;
    }

    #editUserModal .bttm-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 !important;
        flex: 0 0 auto !important;
        border-radius: 20px !important;
        padding: 8px 24px !important;
        font-weight: 500 !important;
        font-size: 14px !important;
        border-width: 1px !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        background-color: transparent !important;
    }

    /* Specific colors for each button type */
    #editUserModal .btn-outline-primary.bttm-btn {
        border-color: #0d6efd !important;
        color: #0d6efd !important;
        background-color: transparent !important;
    }

    #editUserModal .btn-outline-success.bttm-btn {
        border-color: #198754 !important;
        color: #198754 !important;
        background-color: transparent !important;
    }

    #editUserModal .btn-outline-danger.bttm-btn {
        border-color: #dc3545 !important;
        color: #dc3545 !important;
        background-color: transparent !important;
    }

    /* Thicker border when button is checked/selected */
    #editUserModal .btn-check:checked+.bttm-btn {
        border-width: 2px !important;
    }

    /* Keep filled colors when selected (both :checked and .active class) */
    #editUserModal .btn-check:checked+.btn-outline-primary.bttm-btn,
    #editUserModal label.btn-outline-primary.bttm-btn.active {
        background-color: #0d6efd !important;
        color: white !important;
        border-color: #0d6efd !important;
        border-width: 2px !important;
    }

    #editUserModal .btn-check:checked+.btn-outline-success.bttm-btn,
    #editUserModal label.btn-outline-success.bttm-btn.active {
        background-color: #198754 !important;
        color: white !important;
        border-color: #198754 !important;
        border-width: 2px !important;
    }

    #editUserModal .btn-check:checked+.btn-outline-danger.bttm-btn,
    #editUserModal label.btn-outline-danger.bttm-btn.active {
        background-color: #dc3545 !important;
        color: white !important;
        border-color: #dc3545 !important;
        border-width: 2px !important;
    }

    /* Submit Button Styling */
    #editUserModal .Ubsubmitbtn {
        display: flex !important;
        justify-content: flex-end !important;
        padding: 0 !important;
        margin-top: 10px !important;
    }

    #editUserModal .Ubsubmitbtn input[type="submit"],
    #editUserModal .Ubsubmitbtn .btn-success {
        width: 100% !important;
        height: 45px !important;
        border-radius: 8px !important;
        padding: 12px 20px !important;
        font-weight: 600 !important;
        font-size: 15px !important;
        background-color: #198754 !important;
        border: none !important;
        color: white !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }

    #editUserModal .Ubsubmitbtn input[type="submit"]:hover,
    #editUserModal .Ubsubmitbtn .btn-success:hover {
        background-color: #146c43 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3) !important;
    }

    /* Mobile Responsiveness */
    @media (max-width: 480px) {
        #editUserModal .modal-dialog {
            width: 95% !important;
            max-width: 95% !important;
        }

        #editUserModal .modal-body {
            padding: 1rem !important;
        }

        #editUserModal .btnwraps {
            display: flex !important;
            flex-direction: row !important;
            gap: 8px !important;
            flex-wrap: wrap !important;
            margin-bottom: 10px !important;
        }

        #editUserModal .bttm-btn {
            padding: 6px 16px !important;
            font-size: 13px !important;
        }

        #editUserModal .btncheckboxs {
            gap: 12px !important;
            flex-direction: column !important;
            align-items: flex-start !important;
        }

        #editUserModal .form-item input {
            height: 40px !important;
            font-size: 13px !important;
        }
    }

    /* Dark Mode Styles */
    [data-theme="dark"] #editUserModal .modal-content {
        background: rgba(30, 30, 30, 0.95) !important;
    }

    [data-theme="dark"] #editUserModal .modal-body {
        background: rgba(30, 30, 30, 0.95) !important;
    }

    [data-theme="dark"] #editUserModal .modal-header {
        background: rgba(30, 30, 30, 0.95) !important;
    }

    [data-theme="dark"] #editUserModal .form-item label {
        background: rgba(30, 30, 30, 0.95) !important;
        color: white !important;
    }

    [data-theme="dark"] #editUserModal .form-item input {
        border: 1px solid #aaaaaa7d !important;
        color: white !important;
    }

    [data-theme="dark"] #editUserModal input[type="date"] {
        color-scheme: dark;
        background: transparent !important;
    }

    [data-theme="dark"] #editUserModal .form-control {
        background: transparent !important;
        color: white !important;
        border: 1px solid #ffffff7d !important;
    }

    [data-theme="dark"] #editUserModal .modal-header .btn-close {
        filter: invert(1) !important;
    }

    [data-theme="dark"] #editUserModal .modal-close-btn {
        color: white !important;
    }

    [data-theme="dark"] #editUserModal .alert-warning {
        background: transparent !important;
        color: white !important;
        border: 1px solid #ffc107 !important;
    }

    [data-theme="dark"] #editUserModal select.form-control {
        background: rgba(30, 30, 30, 0.95) !important;
        color: white !important;
    }

    [data-theme="dark"] #editUserModal .invalid-feedback {
        color: #ff6b6b !important;
    }

    [data-theme="dark"] #editUserModal .form-check-label {
        color: white !important;
    }

    [data-theme="dark"] #editUserModal .form-check-input {
        background-color: #2a2a2a !important;
        border-color: #ffffff7d !important;
        filter: brightness(0.8);
    }

    [data-theme="dark"] #editUserModal .form-check-input:checked {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        filter: none;
    }

    [data-theme="dark"] #editUserModal .form-label {
        color: white !important;
        background: transparent !important;
    }

    [data-theme="dark"] #editUserModal #selected_user_label {
        color: white !important;
    }

    [data-theme="dark"] #editUserModal #unique_searchInput {
        background: #2a2a2a !important;
        border-color: #ffffff7d !important;
        color: white !important;
    }

    [data-theme="dark"] #editUserModal #unique_searchInput::placeholder {
        color: #aaa !important;
    }

    [data-theme="dark"] #editUserModal #unique_searchInput:focus {
        border-color: #4da3ff !important;
        background: #2a2a2a !important;
    }

    [data-theme="dark"] #editUserModal #unique_source_table {
        background-color: #1e1e1e !important;
        border-color: #333 !important;
    }

    [data-theme="dark"] #editUserModal .unique-option {
        color: #e0e0e0 !important;
        border-bottom-color: #2a2a2a !important;
    }

    [data-theme="dark"] #editUserModal .unique-option:hover {
        background-color: #2a2a2a !important;
        color: #4da3ff !important;
    }

    [data-theme="dark"] #editUserModal input[type="date"],
    [data-theme="dark"] #editUserModal input[type="month"] {
        background: #2a2a2a !important;
        color: white !important;
        border-color: #ffffff7d !important;
        color-scheme: dark;
    }

    [data-theme="dark"] #editUserModal input[type="date"]::-webkit-calendar-picker-indicator,
    [data-theme="dark"] #editUserModal input[type="month"]::-webkit-calendar-picker-indicator {
        filter: invert(1);
    }

    [data-theme="dark"] #editUserModal input[readonly] {
        background-color: #1a1a1a !important;
        opacity: 0.6 !important;
        color: #aaa !important;
    }
</style>

<?php if (!empty($filterUser) && $managerView === 'true' && $user_type !== 'user'): ?>
    <!-- User Filter Indicator -->
    <div class="user-filter-indicator"
        style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px 20px; margin: 10px 20px; border-radius: 4px; font-size: 14px; color: #1976d2;">
        <i class="fas fa-user-circle"></i>
        <strong>Viewing bookings for:</strong> <?php echo htmlspecialchars($filterUser); ?>
        <span style="font-size: 12px; color: #666; margin-left: 10px;">(Manager View)</span>
    </div>
<?php endif; ?>

<!-- Mobile Bottom Navigation -->
<div class="mobile-bottom-nav">
    <button class="mobile-nav-btn filter-btn-mobile" onclick="openFilterModal()">
        <i class="fas fa-filter bottom-icon"></i>
        <span>Filter</span>
    </button>

    <img class="bottom-nav-logo" src="assets/dataimage/mecntec-icon.png" alt="">
    <button class="mobile-nav-btn add-btn-mobile" onclick="openAddBookingModal()">

        <i class="fas fa-user-plus bottom-icon"></i>
        <span class="add-lead-btn">Add Booking</span>
    </button>
</div>

<button class="clear-filters-btn" id="clearAllFiltersBtn" onclick="clearFilters()">
    <i class="fas fa-times-circle"></i>
    Clear All Filters
</button>

<div id="showAlert"></div>

<!-- Filter Modal -->
<div class="modal-overlay" id="filterModalOverlay" style="display: none;">
    <div class="modal-container-eoi">
        <div class="modal-header">
            <h3>Filter Bookings</h3>
            <button type="button" class="modal-close-btn" onclick="closeFilterModal()">&times;</button>
        </div>

        <div class="modal-body">
            <form id="filterBookingsForm">
                <!-- Booking Info Section -->
                <div class="section">

                    <div class="grid">
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Booking Date Start</legend>
                                <div class="date-overlay-wrapper" style="position: relative; display: flex; align-items: center; padding: 10px 12px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;">
        <span id="display_filterBookingDateStart" style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="color: #6b7280; pointer-events: none; z-index: 1;">
            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z"/>
            <path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z"/>
        </svg>
        <input type="date" id="filterBookingDateStart" name="bookingStart" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; z-index: 2; border: none; appearance: none; -webkit-appearance: none;">
    </div>
                           </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Booking Date End</legend>
                                <div class="date-overlay-wrapper" style="position: relative; display: flex; align-items: center; padding: 10px 12px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;">
        <span id="display_filterBookingDateEnd" style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="color: #6b7280; pointer-events: none; z-index: 1;">
            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z"/>
            <path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z"/>
        </svg>
        <input type="date" id="filterBookingDateEnd" name="bookingEnd" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; z-index: 2; border: none; appearance: none; -webkit-appearance: none;">
    </div>
                           </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Month</legend>
                                <input type="month" id="filterMonth" name="month" />
                            </fieldset>
                        </div>

                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Builder</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="builder">
                                    <input type="text" id="filterBuilder" name="builder" placeholder="Search Builder..."
                                        class="filter-dropdown-input" autocomplete="off" readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Project</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="project">
                                    <input type="text" id="filterProject" name="project" placeholder="Search Project..."
                                        class="filter-dropdown-input" autocomplete="off" readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Unit</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="unit">
                                    <input type="text" id="filterUnit" name="unit" placeholder="Search Unit..."
                                        class="filter-dropdown-input" autocomplete="off" readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        
                            <div class="field">
                                <fieldset class="fieldset-label">
                                    <legend class="field-legend">City</legend>
                                    <div class="filter-dropdown-wrapper" data-filter-field="city">
                                        <input type="text" id="filterCity" name="city" placeholder="Search City..."
                                            class="filter-dropdown-input" autocomplete="off" readonly />
                                        <div class="filter-dropdown-list" style="display: none;">
                                            <div class="filter-dropdown-search">
                                                <input type="text" class="filter-search-box"
                                                    placeholder="Type to search..." />
                                            </div>
                                            <div class="filter-dropdown-options"></div>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        

                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Customer Name</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="customer">
                                    <input type="text" id="filterCustumername" name="customer"
                                        placeholder="Search Customer..." class="filter-dropdown-input"
                                        autocomplete="off" readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Contact No.</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="contact">
                                    <input type="text" id="filterContactnumber" name="contact"
                                        placeholder="Search Contact..." class="filter-dropdown-input" autocomplete="off"
                                        readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Email</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="email">
                                    <input type="text" id="filterEmail" name="email" placeholder="Search Email..."
                                        class="filter-dropdown-input" autocomplete="off" readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>


                        <!-- CHANGED: Status filter from select to input -->
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Status</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="status">
                                    <input type="text" id="filterStatus" name="status" placeholder="Search Status..."
                                        class="filter-dropdown-input" autocomplete="off" readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Salesperson</legend>
                                <div class="filter-dropdown-wrapper" data-filter-field="salesperson">
                                    <input type="text" id="filterSalesperson" name="salesperson"
                                        placeholder="Search Salesperson..." class="filter-dropdown-input"
                                        autocomplete="off" readonly />
                                    <div class="filter-dropdown-list" style="display: none;">
                                        <div class="filter-dropdown-search">
                                            <input type="text" class="filter-search-box"
                                                placeholder="Type to search..." />
                                        </div>
                                        <div class="filter-dropdown-options"></div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Size</legend>
                                <input type="text" id="filterSize" name="size" placeholder="Project Size" />
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Type</legend>
                                <input type="text" id="filterType" name="type" placeholder="Project Type" />
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="button" class="cancel-btn btn colour" onclick="closeFilterModal()">Close</button>
                    <button type="button" class="cancel-btn btn" onclick="clearFilters()">Clear Filters</button>
                    <button type="button" class="submit-btn btn" onclick="applyFilters()">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add these custom popups to your HTML -->
<div class="modal-overlay add-name-modal" id="addOptionModalOverlay" style="display: none;">
    <div class="modal-container-eoi add-option-modal">
        <div class="modal-header">
            <h3 id="addOptionTitle">Add New Option</h3>
            <button type="button" class="modal-close-btn" onclick="closeAddOptionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addOptionForm">
                <div class="form-item">
                    <label id="addOptionLabel">Option Value</label>
                    <input type="text" id="newOptionValue" class="form-control form-control-lg" required>
                    <div class="invalid-feedback">This field is required!</div>
                </div>
                <input type="hidden" id="optionType">
            </form>
        </div>
        <div class="submit-eoi">
            <div class="form-actions">
                <button type="button" class="cancel-btn btn" onclick="closeAddOptionModal()">
                    Cancel
                </button>
                <button type="button" class="submit-btn btn" onclick="submitOption()">
                    Add Option
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay add-name-modal" id="addContactModalOverlay" style="display: none;">
    <div class="modal-container-eoi add-contact-modal">
        <div class="modal-header">
            <h3 id="addContactTitle">Add Contact Information</h3>
            <button type="button" class="modal-close-btn" onclick="closeAddContactModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addContactForm">
                <div class="form-item">
                    <label id="addContactLabel">Value</label>
                    <input type="text" id="newContactValue" class="form-control form-control-lg" required>
                    <div class="invalid-feedback">This field is required!</div>
                </div>
                <input type="hidden" id="contactField">
            </form>
        </div>
        <div class="submit-eoi">
            <div class="form-actions">
                <button type="button" class="cancel-btn btn" onclick="closeAddContactModal()">
                    Cancel
                </button>
                <button type="button" class="submit-btn btn" onclick="submitContact()">
                    Add
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Booking Modal -->
<div class="modal-overlay" id="addBookingModalOverlay" style="display: none;">
    <div class="modal-container-eoi add-booking-modal">
        <div class="modal-header">
            <h3>Add New Booking</h3>
            <button type="button" class="modal-close-btn" onclick="closeAddBookingModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="note">
                <strong>Note:</strong> Please ensure all details are accurate and complete. Incorrect entries may affect
                records and incentives.
            </div>

            <form id="add-booking-form" name="myform" novalidate>
                <!-- Booking Info Section -->
                <div class="section">
                    <h3>Booking Info</h3>
                    <div class="grid">
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Booking Date</legend>
                                <div class="date-overlay-wrapper" style="position: relative; display: flex; align-items: center; padding: 10px 12px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;">
        <span id="display_bookingDate" style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="color: #6b7280; pointer-events: none; z-index: 1;">
            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z"/>
            <path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z"/>
        </svg>
        <input type="date" id="bookingDate" name="bdate" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; z-index: 2; border: none; appearance: none; -webkit-appearance: none;">
    </div>
                           </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Booking Month</legend>
                                <div class="date-overlay-wrapper" style="position: relative; display: flex; align-items: center; padding: 10px 1px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;">
        <input id="bookingMonth" name="bmonth" placeholder="Auto-filled from date" readonly style="width: 100%; height: 100%; background: transparent; border: none; outline: none; font-size: 14px; font-family: inherit; color: inherit; appearance: none;" />
    </div>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Project Info Section -->
                <div class="section">
                    <h3>Project Info</h3>
                    <div class="grid">
                        <div class="field">
                            <button class="add-btn" data-add="builder" type="button">+ Add</button>
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Builder Name</legend>
                                <input list="builderList" id="builderName" name="developer"
                                    placeholder="Start typing..." required />
                                <datalist id="builderList">
                                    <option value="Prestige Group">
                                    <option value="Brigade Group">
                                    <option value="Sobha Limited">
                                    <option value="Godrej Properties">
                                    <option value="Puravankara Limited">
                                    <option value="Shriram Properties">
                                    <option value="Sattva Group">
                                    <option value="Salarpuria Sattva">
                                    <option value="Assetz Property Group">
                                    <option value="Embassy Group">
                                    <option value="L&T Realty">
                                    <option value="Mahaveer Group">
                                    <option value="Adarsh Developers">
                                    <option value="Mahindra Lifespaces">
                                    <option value="Neeladri Properties">
                                    <option value="Ranav Group">
                                    <option value="Amber Meadows">
                                    <option value="Ramky Group">
                                    <option value="Arvind Smart Spaces">
                                    <option value="Goyal & Co.">
                                </datalist>
                            </fieldset>
                        </div>
                        <div class="field">
                            <button class="add-btn" data-add="project" type="button">+ Add</button>
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Project Name</legend>
                                <input list="projectList" id="projectName" name="bproject" placeholder="Start typing..."
                                    required />
                                <datalist id="projectList">
                                    <option value="Prestige Lakeside Habitat">
                                    <option value="Brigade Utopia">
                                    <option value="Sobha Dream Acres">
                                    <option value="Godrej Air Nxt">
                                    <option value="Purva Zenium">
                                    <option value="Shriram Blue">
                                    <option value="Salarpuria Sattva Divinity">
                                    <option value="Assetz 63Â° East">
                                    <option value="Embassy Lake Terraces">
                                    <option value="L&T Raintree Boulevard">
                                    <option value="Mahaveer Ranches">
                                    <option value="Adarsh Palm Retreat">
                                    <option value="Mahindra Eden">
                                    <option value="Neeladri Sarovaram">
                                    <option value="Ramky One Karnival">
                                    <option value="Arvind Bel Air">
                                    <option value="Goyal Orchid Whitefield">
                                </datalist>
                            </fieldset>
                        </div>
                        <div class="field">
                            <button class="add-btn" data-add="ptype" type="button">+ Add</button>
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Project Type</legend>
                                <input list="ptypeList" id="projectType" name="tproject" placeholder="Select or type"
                                    required />
                                <datalist id="ptypeList">
                                    <option value="1-BHK">
                                    <option value="2-BHK">
                                    <option value="3-BHK">
                                    <option value="4-BHK">
                                    <option value="5-BHK">
                                    <option value="Villa">
                                    <option value="Plot">
                                </datalist>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Project Size</legend>
                                <div class="input-wrap">
                                    <input id="projectSize" name="psize" type="text" placeholder="e.g. 1200"
                                        inputmode="decimal" required />
                                    <span class="suffix">sq.ft</span>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <!-- Customer Info Section -->
                <div class="section">
                    <h3>Customer Info</h3>
                    <div class="grid">
                        <div class="field">
                            <button class="add-btn" data-add="customer" type="button">+ Add</button>
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Customer Name</legend>
                                <input id="customerName" name="cname" placeholder="Full name" required />
                            </fieldset>
                        </div>
                        <div class="field">
                            <button class="add-btn" data-add="email" type="button">+ Add</button>
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Email</legend>
                                <input id="email" name="cemail" type="text" placeholder="name@example.com" required />
                            </fieldset>
                        </div>
                        <div class="field">
                            <button class="add-btn" data-add="contact" type="button">+ Add</button>
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Contact No.</legend>
                                <input id="contactNo" name="cnumber" type="tel" placeholder="+91 XXXXX XXXXX"
                                    required />
                            </fieldset>
                        </div>
                        <?php
                        // Get first 2 characters of nameuser (capitalize first letter of each word if needed)
                        $unit_prefix = substr($nameuser, 0, 2);
                        $unit_prefix = ucfirst(strtolower($unit_prefix)) . '-';
                        ?>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Unit No.</legend>
                                <input id="unitNo" name="unitno" placeholder="e.g. A-1204" required
                                    value="<?php echo $unit_prefix ?? 'Un-'; ?>" oninput="prefixCheck(this)" />
                            </fieldset>
                        </div>
                            <div class="field">
                                <fieldset class="fieldset-label">
                                    <legend class="field-legend">City</legend>
                                    <select id="cityName" name="city" required>
                                        <option value="">Agent City</option>
                                        <option value="Bangalore">Bangalore</option>
                                        <option value="Hyderabad">Hyderabad</option>
                                        <option value="Pune">Pune</option>
                                        <option value="Chennai">Chennai</option>
                                        <option value="Mumbai">Mumbai</option>
                                        <option value="Delhi">Delhi</option>
                                        <option value="Gujarat">Gujarat</option>
                                    </select>
                                </fieldset>
                            </div>
                    </div>
                </div>

                <!-- Financial Info Section -->
                <div class="section">
                    <h3>Financial Info</h3>
                    <div class="grid">
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Agreement Value</legend>
                                <div class="input-wrap">
                                    <input id="agreementValue" name="cagreement" type="number" min="0" step="0.01"
                                        placeholder="0.00" required />
                                    <span class="suffix">₹</span>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Commission %</legend>
                                <div class="input-wrap">
                                    <input id="commissionPct" name="ccashback" type="number" min="0" max="100"
                                        step="0.01" placeholder="0" required onkeyup="addCalculate(this.value)" />
                                    <span class="suffix">%</span>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Revenue Amount</legend>
                                <div class="input-wrap">
                                    <input id="revenueAmount" name="crevenue" type="number" placeholder="Auto" readonly
                                        required />
                                    <span class="suffix">₹</span>
                                </div>
                            </fieldset>
                            <p class="hint">Calculated as Agreement Ã— Commission%</p>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Cashback %</legend>
                                <div class="input-wrap">
                                    <input id="cashbackPct" name="cccashback" type="number" min="0" max="100"
                                        step="0.01" placeholder="0" required
                                        onkeyup="addCalculate(this.value); calculateCashbackRevenue(); calculateDeductValue();" />
                                    <span class="suffix">%</span>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Actual Amount</legend>
                                <div class="input-wrap">
                                    <input id="actualAmount" name="ccrevenue" type="number" placeholder="Auto" readonly
                                        required />
                                    <span class="suffix">₹</span>
                                </div>
                            </fieldset>
                            <p class="hint">Revenue âˆ’ Cashback</p>
                        </div>
                        <div class="field">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Lead Source</legend>
                                <div class="custom-select">
                                    <select id="leadSource" name="leadsource" aria-hidden="true" tabindex="-1"
                                        style="display:none">
                                        <option value="">Select Source</option>
                                        <option value="Google">Google</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="Direct">Direct</option>
                                        <option value="Referral">Referral</option>
                                        <option value="Portal">Portal</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                    </select>
                                    <div class="lead-select" id="leadSelect" role="combobox" aria-haspopup="listbox"
                                        aria-expanded="false" tabindex="0">
                                        <button type="button" class="lead-btn" id="leadBtn" aria-labelledby="leadValue">
                                            <span id="leadValue" class="lead-value">Select Source</span>
                                        </button>
                                        <ul class="lead-options" id="leadOptions" role="listbox" tabindex="-1">
                                            <li class="lead-option" role="option" data-value="Google">Google</li>
                                            <li class="lead-option" role="option" data-value="Facebook">Facebook</li>
                                            <li class="lead-option" role="option" data-value="Direct">Direct</li>
                                            <li class="lead-option" role="option" data-value="Referral">Referral</li>
                                            <li class="lead-option" role="option" data-value="Portal">Portal</li>
                                            <li class="lead-option" role="option" data-value="WhatsApp">WhatsApp</li>
                                        </ul>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="field full-row">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Remarks</legend>
                                <textarea id="remarks" name="bremarks" placeholder="Additional notes..."></textarea>
                            </fieldset>
                        </div>
                        <div class="field full-row">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend">Attachments</legend>
                                <div class="uploader" id="uploader"
                                    style="width: 100%; box-sizing: border-box; overflow: hidden;">
                                    <input type="file" id="fileInput" name="document" multiple
                                        accept=".png,.jpg,.jpeg,.heic,.webp,.pdf,.doc,.docx" />
                                    <div id="uploadPrompt"
                                        style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                        <div><strong>Drag & drop files here</strong> or</div>
                                        <div class="actions">
                                            <button class="browse" id="browseBtn" type="button">Browse files</button>
                                        </div>
                                        <div class="hint">PDF. Upto 1 file</div>
                                    </div>
                                    <div class="file-list" id="fileList" aria-live="polite"></div>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                </div>

                <input id="deduct_agreementValue" name="deduct_agreement_value" type="hidden" value="" />
                <input type="hidden" name="cstatus" value="Processing" />
                <input type="hidden" name="brecived" value="0" />

                <div class="alert alert-warning py-1 px-2 small mb-2" role="alert" style="font-size: 13px;">
                    <i class="bi bi-info-circle-fill me-1">â„¹</i> Please ensure all details are accurate before
                    submitting. Incorrect data may affect incentives.
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn btn" onclick="closeAddBookingModal()">
                        Cancel
                    </button>
                    <button type="submit" class="submit-btn btn" id="saveBtn">
                        Add Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="loader-container">
    <div class="loader">
        <div class="loader-circle"></div>
        <div class="loader-circle"></div>
        <div class="loader-circle"></div>
        <div class="loader-logo">
            <!-- You can put your logo initials or icon here -->
            <img src="assets/dataimage/mecntec-icon.png" alt="logo">
        </div>
    </div>
</div>


<!-- Simple toast -->
<div id="toast" aria-live="polite"
    style="position:fixed;right:16px;bottom:16px;display:none;background:#10b981;color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 8px 24px rgba(2,6,23,.18);z-index:120">
    Saved</div>

<!-- Mini popup for adding options -->
<div class="modal-overlay" id="miniOverlay" style="display: none;">
    <div class="modal-container-eoi add-option-modal">
        <div class="modal-header">
            <h3 id="miniTitle">Add</h3>
            <button type="button" class="modal-close-btn" onclick="closeMini()">&times;</button>
        </div>

        <div class="modal-body">
            <form id="miniForm">
                <div class="section">
                    <h3>Details</h3>
                    <div class="">
                        <div class="field full-row">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend" id="miniLabel">Value</legend>
                                <input type="text" id="miniInput" placeholder="Enter value" required
                                    aria-label="New value" />
                            </fieldset>
                            <p class="invalid-feedback">This field is required.</p>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="optionType" />
            </form>
        </div>

        <div class="form-actions">
            <button type="button" class="cancel-btn btn" onclick="closeMini()">Cancel</button>
            <button type="button" class="submit-btn btn" onclick="submitOption()">Save</button>
        </div>
    </div>
</div>


<!-- Global drop overlay -->
<div class="global-drop" id="globalDrop">Drop files to upload</div>

<div id="mobileDetailModal" class="mobile-detail-modal">
    <div class="mobile-modal-content">
        <div class="mobile-modal-header">
            <h3 class="mobile-modal-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path
                        d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-4m-4-11V9a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2z">
                    </path>
                </svg>
                Booking Details
            </h3>
            <button class="mobile-close-btn" onclick="closeMobileModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="mobile-modal-body" id="mobileModalBody">
            <!-- Content will be populated dynamically -->
        </div>
    </div>
</div>

<!-- Mobile Menu Button -->
<!-- Overlay -->
<div class="overlay" onclick="closeSidebar()"></div>


<!-- Main Content -->
<div class="content">
    <!-- Header -->


    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card green" id="cardOverallBookings"
            data-tooltip="<?php echo $overall_stats['booking'] ?? 0; ?> Bookings">
            <div class="stat-title">Overall Bookings</div>
            <div class="stat-value" id="statOverallBookings"><?php echo $overall_stats['booking'] ?? 0; ?></div>
        </div>
        <div class="stat-card purple" id="cardOverallAgreement"
            data-tooltip="<?php echo getFullRupeeValue($overall_stats['agreement']); ?>">
            <div class="stat-title">Overall Agreement</div>
            <div class="stat-value" id="statOverallAgreement">
                <?php echo formatCompactNumber($overall_stats['agreement']); ?>
            </div>
        </div>
        <div class="stat-card teal" id="cardActualAgreement"
            data-tooltip="<?php echo getFullRupeeValue($overall_stats['deduct_agreement'] ?? 0); ?>">
            <div class="stat-title">Actual Agreement</div>
            <div class="stat-value" id="statActualAgreement">
                <?php echo formatCompactNumber($overall_stats['deduct_agreement'] ?? 0); ?>
            </div>
        </div>
        <div class="stat-card teal" id="cardOverallRevenue"
            data-tooltip="<?php echo getFullRupeeValue($overall_stats['revenue'] ?? 0); ?>">
            <div class="stat-title">Overall Revenue</div>
            <div class="stat-value" id="statOverallRevenue">
                <?php echo formatCompactNumber($overall_stats['revenue'] ?? 0); ?>
            </div>
        </div>
        <div class="stat-card orange" id="cardCancelledAgreement"
            data-tooltip="<?php echo getFullRupeeValue($overall_stats['cancelledAgreementValue'] ?? 0); ?> (<?php echo ($overall_stats['cancelledBookingCount'] ?? 0); ?>)">
            <div class="stat-title">Cancelled Agreement</div>
            <div class="stat-value" id="statCancelledAgreement" style="display:flex; align-items:baseline; gap:6px; justify-content:center;">
                <?php echo formatCompactNumber($overall_stats['cancelledAgreementValue'] ?? 0); ?>
                <span style=" opacity: 0.8;">(<?php echo $overall_stats['cancelledBookingCount'] ?? 0; ?>)</span>
            </div>
        </div>
        <div class="stat-card blue" id="cardFinalRemaining" data-tooltip="<?php echo getFullRupeeValue($overall_stats['finalRemaining'] ?? 0); ?>">
            <div class="stat-title">Final Remaining</div>
            <div class="stat-value" id="statFinalRemaining">
                <?php echo formatCompactNumber($overall_stats['finalRemaining'] ?? 0); ?>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-section">
        <div class="search-container">
            <div class="search-filters">
                <div class="search-input-wrapper">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" class="search-input" placeholder="Search bookings..." id="mainSearch"
                        autocomplete="off">
                </div>

                <!-- Only show on mobile -->
                <div class="mobile-pagination-info">
                    
                    <select class="pagination-select">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                    
                </div>


                <!-- Download button left of Filters — visible only when a filter is applied -->
                <button id="downloadFilteredBtn"
                    class="btn btn-outline filter-dependent-download"
                    onclick="downloadFilteredBookings()"
                    title="Download filtered rows as CSV"
                    style="display:none; margin-right:6px;">
                    <i class="fa fa-download"></i>
                </button>


                <button class="btn btn-outline" onclick="openFilterModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"></polygon>
                    </svg>
                    Filters
                </button>

                <?php
                // Only the APPROVAL_MANAGER (defined in action.php) can approve bookings
                $isApprover = ($userid === $approvalManager);
                ?>
                <!-- Approvals Button — visible to all users; popup content differs by role -->
                <button class="btn appr-trigger-btn" id="approvalsBtn" onclick="openApprovalsModal()" title="Booking Approvals">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M9 11l3 3L22 4"/>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    <span class="appr-btn-text">Approvals</span>
                    <span class="appr-pending-dot" id="apprPendingDot" style="display:none;"></span>
                </button>
            </div>

            <div class="add-button-container">
                <button class="btn btn-primary" onclick="openAddBookingModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Booking
                </button>
            </div>

        </div>
    </div>


    <!-- Main Table -->
    <!-- Main Table -->

<!-- ═══════════════════════════════════════════════════════════
     APPROVALS MODAL — CSS + HTML + JS
     ═══════════════════════════════════════════════════════════ -->
<style>
/* ── Approvals trigger button ── */
.appr-trigger-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f0f4ff;
    border: 1.5px solid #c7d2fe !important;
    color: #3730a3 !important;
    font-weight: 600;
    font-size: 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.appr-trigger-btn:hover { background:#e0e7ff !important; border-color:#818cf8 !important; transform:translateY(-1px); box-shadow:0 3px 10px rgba(99,102,241,.2); }
[data-theme="dark"] .appr-trigger-btn { background:#1e1e3a !important; border-color:#4c4f9a !important; color:#a5b4fc !important; }
.appr-pending-dot {
    width:8px; height:8px; background:#ef4444; border-radius:50%;
    position:absolute; top:6px; right:6px;
    animation:dotPulse 1.4s infinite;
}
@keyframes dotPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }

/* Mobile: hide text, show icon-only button — centred between search and Show entries */
@media (max-width: 640px) {
    .appr-btn-text { display: none !important; }
    .appr-trigger-btn {
        padding: 8px 10px !important;
        gap: 0 !important;
        justify-content: center;
    }
    .appr-trigger-btn svg { margin: 0 !important; }
}

/* ── Modal ── */
#approvalsModalBackdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.52); z-index:9000; align-items:center; justify-content:center; }
#approvalsModalBackdrop.open { display:flex; }
#approvalsModalBox { background:#fff; border-radius:18px; width:min(900px,96vw); max-height:88vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.22); animation:amPop .25s cubic-bezier(.4,0,.2,1); overflow:hidden; }
[data-theme="dark"] #approvalsModalBox { background:#1a1a2e; }
@keyframes amPop { from{transform:scale(.93);opacity:0} to{transform:scale(1);opacity:1} }
.am-header { padding:18px 24px 14px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
[data-theme="dark"] .am-header { border-bottom-color:#2a2a40; }
.am-header h2 { font-size:17px; font-weight:800; color:#1a1a2e; margin:0; display:flex; align-items:center; gap:9px; }
[data-theme="dark"] .am-header h2 { color:#e0e0f0; }
.am-count { background:#6366f1; color:#fff; border-radius:50px; padding:2px 10px; font-size:12px; font-weight:700; }
.am-close { background:#f3f4f6; border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; color:#6b7280; transition:background .2s; }
.am-close:hover { background:#e5e7eb; }
[data-theme="dark"] .am-close { background:#2a2a40; color:#aaa; }
.am-tabs { display:flex; padding:0 24px; border-bottom:1px solid #e5e7eb; flex-shrink:0; }
[data-theme="dark"] .am-tabs { border-bottom-color:#2a2a40; }
.am-tab { padding:10px 18px; font-size:13px; font-weight:600; color:#6b7280; border-bottom:3px solid transparent; cursor:pointer; transition:all .2s; margin-bottom:-1px; }
.am-tab.active { color:#6366f1; border-bottom-color:#6366f1; }
.am-tab:hover:not(.active) { color:#374151; }
.am-body { flex:1; overflow-y:auto; padding:16px 24px 20px; }
.am-sbadge { display:inline-block; padding:3px 10px; border-radius:50px; font-size:11px; font-weight:700; }
.am-sbadge.pending      { background:#fff3cd; color:#856404; }
.am-sbadge.approved     { background:#d1e7dd; color:#0f5132; }
.am-sbadge.rejected     { background:#f8d7da; color:#842029; }
.am-sbadge.user_approved{ background:#cff4fc; color:#055160; }
/* Dark mode overrides for am-sbadge */
[data-theme="dark"] .am-sbadge.pending      { background:#78350f; color:#fcd34d; }
[data-theme="dark"] .am-sbadge.approved     { background:#064e3b; color:#6ee7b7; }
[data-theme="dark"] .am-sbadge.rejected     { background:#7f1d1d; color:#fca5a5; }
[data-theme="dark"] .am-sbadge.user_approved{ background:#164e63; color:#67e8f9; }
.am-card { background:#fafafa; border:1px solid #e5e7eb; border-radius:12px; padding:14px 16px; margin-bottom:12px; transition:box-shadow .2s; }
.am-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.07); }
[data-theme="dark"] .am-card { background:#1e1e30; border-color:#2a2a44; }
.am-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.am-card-info { flex:1; min-width:200px; }
.am-cust { font-size:15px; font-weight:700; color:#111827; margin-bottom:4px; }
[data-theme="dark"] .am-cust { color:#e0e0f0; }
.am-meta { font-size:12px; color:#6b7280; display:flex; flex-wrap:wrap; gap:10px; margin-top:4px; }
.am-perms { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; padding-top:10px; border-top:1px solid #f0f0f0; align-items:center; }
[data-theme="dark"] .am-perms { border-top-color:#2a2a44; }
.am-perm-label { font-size:11px; font-weight:700; color:#6b7280; letter-spacing:.4px; text-transform:uppercase; margin-right:4px; }
.am-perm-toggle { display:inline-flex; align-items:center; gap:5px; background:#f3f4f6; border:1.5px solid #d1d5db; border-radius:50px; padding:5px 12px; font-size:12px; font-weight:600; color:#374151; cursor:pointer; transition:all .2s; user-select:none; }
.am-perm-toggle:hover { border-color:#6366f1; color:#6366f1; }
.am-perm-toggle.on { background:#ede9fe; border-color:#6366f1; color:#4f46e5; }
[data-theme="dark"] .am-perm-toggle { background:#252540; border-color:#3a3a5a; color:#aaa; }
[data-theme="dark"] .am-perm-toggle.on { background:#2d2d60; border-color:#6366f1; color:#a5b4fc; }
.am-card-actions { display:flex; gap:8px; flex-shrink:0; align-items:center; }
.am-btn { border:none; padding:7px 16px; border-radius:50px; font-size:12px; font-weight:700; cursor:pointer; transition:all .2s; font-family:inherit; white-space:nowrap; }
.am-btn.approve { background:#059669; color:#fff; }
.am-btn.approve:hover { background:#047857; transform:scale(1.03); }
.am-btn.reject  { background:#dc2626; color:#fff; }
.am-btn.reject:hover  { background:#b91c1c; transform:scale(1.03); }
.am-btn.edit-am  { background:#f59e0b; color:#fff; }
.am-btn.edit-am:hover  { background:#d97706; transform:scale(1.03); }
.am-btn:disabled { opacity:.45; cursor:not-allowed; transform:none !important; }
.am-empty { text-align:center; padding:40px 20px; color:#9ca3af; font-size:14px; }
.am-user-table { width:100%; border-collapse:collapse; font-size:13px; }
.am-user-table th { background:#f0f4ff; color:#374151; font-weight:700; padding:9px 12px; text-align:left; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
[data-theme="dark"] .am-user-table th { background:#1e1e38; color:#c7d2fe; border-bottom-color:#2a2a44; }
.am-user-table td { padding:9px 12px; border-bottom:1px solid #f3f4f6; color:#374151; white-space:nowrap; }
[data-theme="dark"] .am-user-table td { color:#ccc; border-bottom-color:#1e1e30; }
.am-user-table tr:last-child td { border-bottom:none; }
.am-user-table tr:hover td { background:#f9fafb; }
[data-theme="dark"] .am-user-table tr:hover td { background:#1e1e28; }
#amRejectOverlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9100; align-items:center; justify-content:center; }
#amRejectOverlay.open { display:flex; }
#amRejectBox { background:#fff; border-radius:14px; padding:26px; max-width:420px; width:92%; box-shadow:0 12px 40px rgba(0,0,0,.22); animation:amPop .2s ease; }
[data-theme="dark"] #amRejectBox { background:#1a1a2e; }
#amRejectBox h4 { font-size:16px; font-weight:700; color:#dc2626; margin:0 0 10px; }
#amRejectBox textarea { width:100%; height:90px; border:1.5px solid #e5e7eb; border-radius:8px; padding:10px; font-size:14px; font-family:inherit; resize:vertical; outline:none; margin-top:6px; transition:border-color .2s; }
#amRejectBox textarea:focus { border-color:#dc2626; }
[data-theme="dark"] #amRejectBox textarea { background:#252535; border-color:#3a3a50; color:#ddd; }
.am-reject-actions { display:flex; gap:10px; margin-top:14px; justify-content:flex-end; }
/* Force SweetAlert2 above the approvals modal */
.swal2-container { z-index: 99999 !important; }
/* ── Approvals popup dark mode – missing pieces ── */
[data-theme="dark"] #amSearchInput { background:#1e1e30; border-color:#3a3a56; color:#e0e0f0; }
[data-theme="dark"] #amSearchInput::placeholder { color:#6b7280; }
[data-theme="dark"] #amBulkBar { background:#1e1e30 !important; border-color:#2a2a44 !important; color:#c7d2fe !important; }
[data-theme="dark"] #amBulkBar label { color:#c7d2fe !important; }
[data-theme="dark"] #amBulkBar input[type="checkbox"] { accent-color:#6366f1; }
[data-theme="dark"] #amBulkFooter { background:#1a1a2e !important; border-top-color:#2a2a44 !important; }
[data-theme="dark"] .am-tab:hover:not(.active) { color:#c7d2fe; }
[data-theme="dark"] .am-body::-webkit-scrollbar { width:6px; }
[data-theme="dark"] .am-body::-webkit-scrollbar-track { background:#1a1a2e; }
[data-theme="dark"] .am-body::-webkit-scrollbar-thumb { background:#3a3a56; border-radius:4px; }
[data-theme="dark"] .am-empty { color:#6b7280; }
[data-theme="dark"] #amSearchEmpty { color:#6b7280; }
/* Pagination */
.am-pagination { display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 16px 4px; flex-shrink:0; }
.am-pg-btn { border:1.5px solid #e2e8f0; background:#fff; color:#374151; border-radius:8px; padding:6px 16px; font-size:13px; font-weight:600; cursor:pointer; transition:all .18s; font-family:inherit; }
.am-pg-btn:hover:not(:disabled) { border-color:#6366f1; color:#6366f1; background:#f5f3ff; }
.am-pg-btn:disabled { opacity:.4; cursor:not-allowed; }
.am-pg-info { font-size:13px; color:#374151; font-weight:600; min-width:110px; text-align:center; line-height:1.4; }
[data-theme="dark"] .am-pg-btn { background:#1e1e30; border-color:#3a3a56; color:#c7d2fe; }
[data-theme="dark"] .am-pg-btn:hover:not(:disabled) { background:#2d2d50; border-color:#6366f1; color:#a5b4fc; }
[data-theme="dark"] .am-pg-info { color:#9ca3af; }
</style>

<!-- Approvals Modal -->
<div id="approvalsModalBackdrop" onclick="if(event.target===this)closeApprovalsModal()">
    <div id="approvalsModalBox">
        <div class="am-header">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.2">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Booking Approvals
                <span class="am-count" id="amCountBadge">0</span>
            </h2>
            <button class="am-close" onclick="closeApprovalsModal()">&#x2715;</button>
        </div>
        <?php if ($isApprover): ?>
        <div class="am-tabs">
            <div class="am-tab active" id="tabPending" onclick="switchAmTab('pending')">Pending</div>
            <div class="am-tab" id="tabHistory" onclick="switchAmTab('history')">Rejected</div>
        </div>
        <?php endif; ?>
        <!-- Search bar -->
        <div style="padding:8px 14px 0;">
            <div style="position:relative;">
                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:14px;color:#9ca3af;">&#128269;</span>
                <input id="amSearchInput" type="text" placeholder="Search by name, project, builder, unit…"
                    oninput="amFilterCards(this.value)"
                    style="width:100%;box-sizing:border-box;padding:8px 12px 8px 32px;
                           border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;
                           font-family:inherit;outline:none;transition:border-color .2s;"
                    onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
        </div>
        <div class="am-body" id="amBody">
            <div class="am-empty">Loading…</div>
        </div>
        <!-- Pagination bar -->
        <div class="am-pagination" id="amPagination" style="display:none;">
            <button class="am-pg-btn" id="amPgPrev" onclick="amChangePage(-1)" disabled>&#8592; Prev</button>
            <span class="am-pg-info" id="amPgInfo">Page 1 of 1</span>
            <button class="am-pg-btn" id="amPgNext" onclick="amChangePage(1)">Next &#8594;</button>
        </div>
        <!-- Bulk action footer — shown only when items are selected -->
        <div id="amBulkFooter" style="display:none;padding:10px 16px;border-top:1px solid #e5e7eb;justify-content:flex-end;gap:8px;border-radius:0 0 16px 16px;background:#fff;">
            <button id="amBulkApproveBtn" onclick="amBulkApprove()"
                style="background:#059669;color:#fff;border:none;border-radius:50px;padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
                &#10003; Approve (<span id="amApproveCount">0</span>)
            </button>
            <button id="amBulkRejectBtn" onclick="amBulkReject()"
                style="background:#dc2626;color:#fff;border:none;border-radius:50px;padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
                &#10007; Reject (<span id="amRejectCount">0</span>)
            </button>
        </div>
    </div>
</div>

<!-- Reject Overlay -->
<div id="amRejectOverlay" onclick="if(event.target===this)closeRejectOverlay()">
    <div id="amRejectBox">
        <h4>&#9888; Reject Booking</h4>
        <p style="font-size:13px;color:#6b7280;">Reason for rejection (optional):</p>
        <textarea id="amRejectReason" placeholder="Enter reason…"></textarea>
        <input type="hidden" id="amRejectId">
        <div class="am-reject-actions">
            <button class="am-btn" style="background:#6c757d;color:#fff;border-radius:50px;padding:7px 18px;" onclick="closeRejectOverlay()">Cancel</button>
            <button class="am-btn reject" id="amConfirmRejectBtn" onclick="submitAmReject()">Confirm Reject</button>
        </div>
    </div>
</div>

<!-- Edit Booking Approval Modal -->
<div id="amEditOverlay"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;
            background:rgba(9,9,9,.45);backdrop-filter:blur(4px);
            align-items:center;justify-content:center;overflow:auto;">

    <div style="position:relative;margin:auto;max-width:520px;width:90%;max-height:90vh;overflow:hidden;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.3);">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 20px;
                    background:#fff;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
            <h5 style="margin:0;font-size:16px;font-weight:700;color:#111827;">
                &#9998;&nbsp;Edit Booking
            </h5>
            <button onclick="closeEditOverlay()"
                    style="background:#f1f5f9;border:none;border-radius:8px;
                           padding:5px 11px;font-size:17px;color:#6b7280;cursor:pointer;line-height:1;">&#x2715;</button>
        </div>

        <!-- Scrollable body -->
        <div style="overflow-y:auto;max-height:calc(90vh - 130px);background:#fff;padding:20px 22px 0;">

            <input type="hidden" id="amEditId">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Customer Name</label>
                <input id="amEditCname" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Contact Number</label>
                <input id="amEditPhone" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Email</label>
                <input id="amEditEmail" type="email" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Booking Date</label>
                <input id="amEditBdate" type="text" placeholder="YYYY-MM-DD" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Booking Month</label>
                <input id="amEditBmonth" type="text" placeholder="e.g. 2026-06" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Builder</label>
                <input id="amEditBuilder" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Project</label>
                <input id="amEditProject" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Project Type</label>
                <input id="amEditPtype" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Unit No</label>
                <input id="amEditUnit" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Size (sq.ft)</label>
                <input id="amEditSize" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Agreement Value (&#8377;)</label>
                <input id="amEditAgreement" type="number" min="0" oninput="amEditCalculate()" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Commission %</label>
                <input id="amEditCommissionPct" type="number" min="0" step="any" oninput="amEditCalculate()" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Revenue Amount (&#8377;)</label>
                <input id="amEditRevenue" type="number" min="0" readonly style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;background:#f8fafc;color:#64748b;cursor:not-allowed;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Cashback %</label>
                <input id="amEditCashbackPct" type="number" min="0" step="any" oninput="amEditCalculate()" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Actual Amount (&#8377;)</label>
                <input id="amEditActualAmount" type="number" min="0" readonly style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;background:#f8fafc;color:#64748b;cursor:not-allowed;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Received Amt (&#8377;)</label>
                <input id="amEditRecived" type="number" min="0" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">City</label>
                <input id="amEditCity" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Booking Status</label>
                <input id="amEditAstatus" type="text" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></div>

                <div><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Source Lead</label>
                <select id="amEditSlead" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;background:#fff;cursor:pointer;transition:border-color .2s;" onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#e2e8f0'">
                    <option value="">-- Select --</option>
                    <option value="Google">Google</option>
                    <option value="Facebook">Facebook</option>
                    <option value="Direct">Direct</option>
                    <option value="Referral">Referral</option>
                    <option value="Portal">Portal</option>
                    <option value="WhatsApp">WhatsApp</option>
                </select></div>

                <div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Remarks</label>
                <textarea id="amEditRemarks" rows="3" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box;font-family:inherit;transition:border-color .2s;" onfocus="this.style.borderColor='#059669'" onblur="this.style.borderColor='#e2e8f0'"></textarea></div>

            </div><!-- end grid -->

            <!-- Attachment section -->
            <div style="margin-top:16px;padding:14px 16px;background:#f8fafc;border-radius:10px;border:1.5px dashed #cbd5e1;">
                <label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:8px;">&#128206;&nbsp;Attachment</label>
                <div id="amEditCurrentFile" style="margin-bottom:8px;font-size:13px;color:#374151;"></div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6366f1;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                        <input id="amEditFileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none;" onchange="amEditFileChanged(this)">
                        &#128193;&nbsp;Choose new file&hellip;
                    </label>
                    <span id="amEditFileName" style="margin-left:10px;font-size:12px;color:#6b7280;"></span>
                </div>
            </div>


        </div><!-- end scrollable body -->

        <!-- Sticky footer -->
        <div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 22px;
                    background:#fff;border-top:1px solid #e2e8f0;flex-shrink:0;">
            <button onclick="closeEditOverlay()"
                    style="background:#f1f5f9;color:#374151;border:none;border-radius:50px;
                           padding:9px 22px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
            <button id="amEditSaveBtn" onclick="amSubmitEdit()"
                    style="background:#059669;color:#fff;border:none;border-radius:50px;
                           padding:9px 26px;font-size:13px;font-weight:700;cursor:pointer;">Save Changes</button>
        </div>

    </div><!-- end dialog -->
</div><!-- end overlay -->

<!-- Attachment Preview Lightbox -->
<div id="amPreviewModal"
     style="display:none;position:fixed;inset:0;z-index:999999;
            background:rgba(0,0,0,.88);align-items:center;justify-content:center;flex-direction:column;
            padding:16px;box-sizing:border-box;">

    <!-- Top bar -->
    <div style="width:100%;max-width:900px;display:flex;align-items:center;justify-content:space-between;
                margin-bottom:10px;">
        <span id="amPreviewTitle" style="color:#fff;font-size:15px;font-weight:600;opacity:.9;"></span>
        <button onclick="amClosePreview()"
                style="background:rgba(255,255,255,.15);border:none;border-radius:8px;
                       padding:6px 16px;font-size:20px;color:#fff;cursor:pointer;line-height:1;">&#x2715;</button>
    </div>

    <!-- PDF / DOC viewer -->
    <iframe id="amPreviewIframe" src=""
            style="display:none;width:100%;max-width:900px;height:82vh;
                   border:none;border-radius:10px;background:#fff;"></iframe>

    <!-- Image viewer -->
    <img id="amPreviewImg" src="" alt="Attachment preview"
         style="display:none;max-width:90vw;max-height:82vh;border-radius:10px;
                object-fit:contain;box-shadow:0 8px 40px rgba(0,0,0,.5);">

</div>



<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
    const IS_APPROVER = <?php echo $isApprover ? 'true' : 'false'; ?>;
    let currentAmTab = 'pending';
    let amData = [];
    const permState = {};

    function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function fmtRs(v){ return '₹'+(parseFloat(v)||0).toLocaleString('en-IN',{maximumFractionDigits:0}); }
    function fmtDate(s){ return s?String(s).substring(0,10):'-'; }

    window.openApprovalsModal = function(){
        document.getElementById('approvalsModalBackdrop').classList.add('open');
        document.body.style.overflow='hidden';
        const si = document.getElementById('amSearchInput');
        if(si) si.value = '';
        loadAmData();
    };
    window.closeApprovalsModal = function(){
        document.getElementById('approvalsModalBackdrop').classList.remove('open');
        document.body.style.overflow='';
    };

    function loadAmData(){
        document.getElementById('amBody').innerHTML='<div class="am-empty">Loading…</div>';
        const url = IS_APPROVER ? 'action.php?get_pending_approvals=true' : 'action.php?get_user_approvals=true';
        fetch(url).then(r=>r.json()).then(res=>{
            amData = res.data||[];

            const pending = IS_APPROVER ? amData.filter(d=>d.approval_status==='pending') : amData;
            document.getElementById('amCountBadge').textContent = IS_APPROVER ? pending.length : amData.length;
            const dot = document.getElementById('apprPendingDot');
            if(dot) dot.style.display = pending.length>0?'block':'none';
            IS_APPROVER ? renderManagerView() : renderUserView();
        }).catch(()=>{
            document.getElementById('amBody').innerHTML='<div class="am-empty" style="color:#dc2626;">&#9888; Failed to load. Please retry.</div>';
        });
    }

    window.switchAmTab = function(tab){
        currentAmTab=tab;
        document.getElementById('tabPending').classList.toggle('active',tab==='pending');
        document.getElementById('tabHistory').classList.toggle('active',tab==='history');
        const si = document.getElementById('amSearchInput');
        if(si) si.value = '';
        renderManagerView();
    };

    window.amFilterCards = function(query){
        const q = (query||'').toLowerCase().trim();

        if(!q){
            // No query — restore full tab-specific dataset and re-render page 0
            if(IS_APPROVER){
                const base = currentAmTab==='pending'
                    ? amData.filter(d=>d.approval_status==='pending')
                    : amData.filter(d=>d.approval_status!=='pending');
                amFiltered = base;
                renderAmPage(0);
            } else {
                amFiltered = amData;
                renderUserPage(0);
            }
            return;
        }

        // Filter full dataset by query text
        const base = IS_APPROVER
            ? (currentAmTab==='pending'
                ? amData.filter(d=>d.approval_status==='pending')
                : amData.filter(d=>d.approval_status!=='pending'))
            : amData;

        amFiltered = base.filter(function(b){
            const text = [
                b.customer_name, b.submitter_name, b.project,
                b.builder, b.unit_no, b.approval_status, b.rejection_reason
            ].filter(Boolean).join(' ').toLowerCase();
            return text.includes(q);
        });

        if(!amFiltered.length){
            // Show empty message
            const body = document.getElementById('amBody');
            body.innerHTML = `<div class="am-empty">No results for "${query}"</div>`;
            const pg = document.getElementById('amPagination');
            if(pg) pg.style.display = 'none';
            return;
        }

        IS_APPROVER ? renderAmPage(0) : renderUserPage(0);
    };

    /* ── Pagination state ─────────────────────────── */
    const AM_PAGE = 10;
    let amFiltered  = [];
    let amCurPage   = 0;   // 0-based

    function buildCard(b){
        const isPending = b.approval_status==='pending';
        const checkbox = isPending
            ? `<input type="checkbox" class="am-sel-chk" data-id="${b.id}" onchange="amUpdateBulkBar()"
                style="margin-top:3px;margin-right:8px;width:15px;height:15px;cursor:pointer;flex-shrink:0;accent-color:#6366f1;">`
            : '';
        const actHtml = isPending
            ?`<div class="am-card-actions"><button class="am-btn edit-am" onclick="amOpenEdit(${b.id})">&#9998; Edit</button><button class="am-btn approve" id="amAB-${b.id}" onclick="amApprove(${b.id},this)">&#10003; Approve</button><button class="am-btn reject" onclick="amOpenReject(${b.id})">&#10007; Reject</button></div>`
            :`<div class="am-card-actions"><span class="am-sbadge ${b.approval_status}">${esc(b.approval_status.replace('_',' '))}</span></div>`;
        const rejectionNote = (b.approval_status==='rejected'&&b.rejection_reason)
            ? `<div style="margin-top:.4rem;font-size:.75rem;color:#ef4444;font-weight:600;">Reason: ${esc(b.rejection_reason)}</div>` : '';
        return `<div class="am-card" id="amCard-${b.id}">
                <div class="am-card-top">
                    <div class="am-card-info" style="display:flex;align-items:flex-start;">
                        ${checkbox}
                        <div style="flex:1;min-width:0;">
                            <div class="am-cust">${esc(b.customer_name||'-')}</div>
                            <div class="am-meta">
                                <span>&#128100; ${esc(b.submitter_name||b.source_table)}</span>
                                <span>&#127968; ${esc(b.project||'-')}${b.builder?' ('+esc(b.builder)+')':''}</span>
                                <span>&#128203; ${esc(b.unit_no||'-')}</span>
                                <span>&#128176; ${fmtRs(b.agreement_value)}</span>
                                <span>&#128197; ${fmtDate(b.booking_date)}</span>
                                <span style="color:#9ca3af;">Sub: ${fmtDate(b.submitted_at)}</span>
                            </div>
                            ${rejectionNote}
                        </div>
                    </div>${actHtml}
                </div>
            </div>`;
    }

    function renderAmPage(page){
        amCurPage = page;
        const body       = document.getElementById('amBody');
        const isPending  = currentAmTab==='pending';
        const totalPages = Math.ceil(amFiltered.length / AM_PAGE);
        const start      = page * AM_PAGE;
        const slice      = amFiltered.slice(start, start + AM_PAGE);

        // Bulk bar (only on pending tab)
        const bulkBar = isPending ? `
            <div id="amBulkBar" style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#f5f5ff;border-bottom:1px solid #e5e7eb;border-radius:8px 8px 0 0;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;">
                    <input type="checkbox" id="amSelectAll" onchange="amToggleSelectAll(this.checked)" style="width:15px;height:15px;cursor:pointer;accent-color:#6366f1;">
                    Select All
                </label>
                <span id="amSelCount" style="font-size:12px;color:#6366f1;font-weight:600;margin-left:6px;"></span>
            </div>` : '';

        body.innerHTML = bulkBar + slice.map(buildCard).join('');
        body.scrollTop = 0;

        // Update pagination bar
        const pg = document.getElementById('amPagination');
        if(pg){
            pg.style.display = 'flex';
            const from = start + 1;
            const to   = Math.min(start + AM_PAGE, amFiltered.length);
            document.getElementById('amPgInfo').innerHTML =
                `<span style="font-size:11px;display:block;color:#9ca3af;">Showing ${from}–${to} of ${amFiltered.length}</span>
                 <span>Page ${page+1} of ${totalPages}</span>`;
            document.getElementById('amPgPrev').disabled = page === 0;
            document.getElementById('amPgNext').disabled = page >= totalPages - 1;
        }
    }

    window.amChangePage = function(dir){
        const totalPages = Math.ceil(amFiltered.length / AM_PAGE);
        const next = amCurPage + dir;
        if(next >= 0 && next < totalPages) renderAmPage(next);
    };

    function renderManagerView(){
        const filtered = currentAmTab==='pending'
            ? amData.filter(d=>d.approval_status==='pending')
            : amData.filter(d=>d.approval_status!=='pending');
        const body = document.getElementById('amBody');

        amFiltered = filtered;
        amCurPage  = 0;

        if(!filtered.length){
            body.innerHTML=`<div class="am-empty">${currentAmTab==='pending'?'&#10003; No pending bookings. All caught up!':'No history yet.'}</div>`;
            const pg = document.getElementById('amPagination');
            if(pg) pg.style.display='none';
            return;
        }
        renderAmPage(0);
    }

    window.amToggleSelectAll = function(checked){
        document.querySelectorAll('.am-sel-chk').forEach(c => c.checked = checked);
        amUpdateBulkBar();
    };

    window.amUpdateBulkBar = function(){
        const all     = Array.from(document.querySelectorAll('.am-sel-chk'));
        const checked = all.filter(c => c.checked);
        const selAll  = document.getElementById('amSelectAll');
        if (selAll) {
            selAll.indeterminate = checked.length > 0 && checked.length < all.length;
            selAll.checked = all.length > 0 && checked.length === all.length;
        }
        const n = checked.length;
        // Update count label in top bar
        const countEl = document.getElementById('amSelCount');
        if (countEl) countEl.textContent = n > 0 ? n + ' selected' : '';
        // Show/hide footer and update counts on buttons
        const footer = document.getElementById('amBulkFooter');
        if (footer) footer.style.display = n > 0 ? 'flex' : 'none';
        const approveCount = document.getElementById('amApproveCount');
        const rejectCount  = document.getElementById('amRejectCount');
        if (approveCount) approveCount.textContent = n;
        if (rejectCount)  rejectCount.textContent  = n;
    };

    window.amBulkApprove = function(){
        const ids = Array.from(document.querySelectorAll('.am-sel-chk:checked')).map(c => c.dataset.id);
        if (!ids.length) return;
        Swal.fire({
            title: 'Approve ' + ids.length + ' Booking' + (ids.length > 1 ? 's' : '') + '?',
            text: 'All selected bookings will be moved to the main list.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#6366f1', cancelButtonColor: '#6b7280',
            confirmButtonText: '✓ Yes, Approve All', cancelButtonText: 'Cancel'
        }).then(function(res){
            if (!res.isConfirmed) return;
            const fd = new FormData();
            fd.append('bulk_approve_bookings', '1');
            ids.forEach(function(id){ fd.append('approval_ids[]', id); });
            fetch('action.php', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(r){
                    if (r.success) { amToast('&#10003; ' + r.approved + ' booking' + (r.approved !== 1 ? 's' : '') + ' approved!', 'success'); }
                    else { amToast('&#9888; ' + (r.message || 'Failed'), 'error'); }
                    setTimeout(loadAmData, 600);
                }).catch(function(){ amToast('Network error', 'error'); });
        });
    };

    window.amBulkReject = function(){
        const ids = Array.from(document.querySelectorAll('.am-sel-chk:checked')).map(c => c.dataset.id);
        if (!ids.length) return;
        Swal.fire({
            title: 'Reject ' + ids.length + ' Booking' + (ids.length > 1 ? 's' : '') + '?',
            html: '<textarea id="swalBulkReason" class="swal2-textarea" placeholder="Reason for rejection (optional)" style="height:80px;"></textarea>',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
            confirmButtonText: '✗ Reject All', cancelButtonText: 'Cancel',
            preConfirm: function(){ return document.getElementById('swalBulkReason').value.trim(); }
        }).then(function(res){
            if (!res.isConfirmed) return;
            const fd = new FormData();
            fd.append('bulk_reject_bookings', '1');
            ids.forEach(function(id){ fd.append('approval_ids[]', id); });
            fd.append('rejection_reason', res.value || '');
            fetch('action.php', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(r){
                    if (r.success) { amToast('Rejected ' + r.rejected + ' booking' + (r.rejected !== 1 ? 's' : '') + '.', 'warning'); }
                    else { amToast('&#9888; ' + (r.message || 'Failed'), 'error'); }
                    setTimeout(loadAmData, 600);
                }).catch(function(){ amToast('Network error', 'error'); });
        });
    };

    function buildUserRow(b){
        return `<tr>
            <td style="font-weight:600">${esc(b.customer_name||'-')}</td>
            <td>${esc(b.project||'-')}</td><td>${esc(b.unit_no||'-')}</td>
            <td>${fmtRs(b.agreement_value)}</td><td>${fmtDate(b.booking_date)}</td>
            <td><span class="am-sbadge ${b.approval_status}">${esc(b.approval_status.replace('_',' '))}</span>${b.approval_status==='rejected'&&b.rejection_reason?`<br><span style="font-size:11px;color:#ef4444;">Reason: ${esc(b.rejection_reason)}</span>`:''}</td>
            <td style="color:#9ca3af;">${fmtDate(b.submitted_at)}</td>
        </tr>`;
    }

    function renderUserView(){
        const body = document.getElementById('amBody');
        if(!amData.length){
            body.innerHTML='<div class="am-empty">&#10003; No pending bookings.<br><span style="font-size:12px;color:#9ca3af;">Approved bookings appear in your main list. Rejected bookings will show here.</span></div>';
            const pg = document.getElementById('amPagination');
            if(pg) pg.style.display='none';
            return;
        }

        amFiltered = amData;
        amCurPage  = 0;
        renderUserPage(0);
    }

    function renderUserPage(page){
        amCurPage = page;
        const body       = document.getElementById('amBody');
        const totalPages = Math.ceil(amFiltered.length / AM_PAGE);
        const start      = page * AM_PAGE;
        const slice      = amFiltered.slice(start, start + AM_PAGE);

        body.innerHTML = `<div style="overflow-x:auto"><table class="am-user-table"><thead><tr>
            <th>Customer</th><th>Project</th><th>Unit No</th><th>Agreement</th><th>Booking Date</th><th>Status</th><th>Submitted</th>
        </tr></thead><tbody>${slice.map(buildUserRow).join('')}</tbody></table></div>`;
        body.scrollTop = 0;

        // Reuse shared pagination bar
        const pg = document.getElementById('amPagination');
        if(pg){
            pg.style.display = 'flex';
            const from = start + 1;
            const to   = Math.min(start + AM_PAGE, amFiltered.length);
            document.getElementById('amPgInfo').innerHTML =
                `<span style="font-size:11px;display:block;color:#9ca3af;">Showing ${from}–${to} of ${amFiltered.length}</span>
                 <span>Page ${page+1} of ${totalPages}</span>`;
            document.getElementById('amPgPrev').disabled = page === 0;
            document.getElementById('amPgNext').disabled = page >= totalPages - 1;
        }
    }

    // Override amChangePage to handle user table view too
    window.amChangePage = function(dir){
        const totalPages = Math.ceil(amFiltered.length / AM_PAGE);
        const next = amCurPage + dir;
        if(next < 0 || next >= totalPages) return;
        IS_APPROVER ? renderAmPage(next) : renderUserPage(next);
    };


    window.amApprove = function(id,btn){
        Swal.fire({
            title: 'Approve Booking?',
            text: 'This booking will be moved to the main list immediately.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '✓ Yes, Approve',
            cancelButtonText: 'Cancel'
        }).then(result=>{
            if(!result.isConfirmed) return;
            btn.disabled=true; btn.textContent='Approving…';
            const fd=new FormData();
            fd.append('approve_booking','1'); fd.append('approval_id',id); fd.append('approver_type','user');
            fetch('action.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
                if(res.success){ amToast('&#10003; Booking approved!','success'); setTimeout(loadAmData,600); }
                else { amToast('&#9888; '+(res.message||'Failed'),'error'); btn.disabled=false; btn.textContent='✓ Approve'; }
            }).catch(()=>{ amToast('Network error','error'); btn.disabled=false; btn.textContent='✓ Approve'; });
        });
    };

    window.amOpenReject=function(id){ document.getElementById('amRejectId').value=id; document.getElementById('amRejectReason').value=''; document.getElementById('amRejectOverlay').classList.add('open'); };
    window.closeRejectOverlay=function(){ document.getElementById('amRejectOverlay').classList.remove('open'); };
    window.submitAmReject=function(){
        const id=document.getElementById('amRejectId').value;
        const reason=document.getElementById('amRejectReason').value.trim();
        const btn=document.getElementById('amConfirmRejectBtn');
        btn.disabled=true; btn.textContent='Rejecting…';
        const fd=new FormData(); fd.append('reject_booking','1'); fd.append('approval_id',id); fd.append('rejection_reason',reason);
        fetch('action.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
            closeRejectOverlay();
            if(res.success){ amToast('Booking rejected.','warning'); setTimeout(loadAmData,600); }
            else amToast('&#9888; '+(res.message||'Failed'),'error');
            btn.disabled=false; btn.textContent='Confirm Reject';
        }).catch(()=>{ closeRejectOverlay(); amToast('Network error','error'); btn.disabled=false; btn.textContent='Confirm Reject'; });
    };

    window.amOpenEdit = function(id){
        const b = amData.find(function(x){ return x.id == id; });
        if(!b) return;
        document.getElementById('amEditId').value          = id;
        document.getElementById('amEditCname').value       = b.customer_name    || '';
        document.getElementById('amEditPhone').value       = b.contact_number   || '';
        document.getElementById('amEditEmail').value       = b.email_id         || '';
        document.getElementById('amEditBdate').value       = (b.booking_date||'').substring(0,10);
        document.getElementById('amEditBmonth').value      = b.booking_month    || '';
        document.getElementById('amEditBuilder').value     = b.builder          || '';
        document.getElementById('amEditProject').value     = b.project          || '';
        document.getElementById('amEditPtype').value       = b.project_type     || '';
        document.getElementById('amEditUnit').value        = b.unit_no          || '';
        document.getElementById('amEditSize').value        = b.size             || '';
        document.getElementById('amEditAgreement').value   = b.agreement_value  || '';
        document.getElementById('amEditCommissionPct').value = b.cashback        || '';
        document.getElementById('amEditRevenue').value     = b.revenue          || '';
        document.getElementById('amEditCashbackPct').value = b.ccashback        || '';
        document.getElementById('amEditActualAmount').value = b.crevenue         || '';
        document.getElementById('amEditRecived').value     = b.recived_amt      || '';
        document.getElementById('amEditCity').value        = b.city             || '';
        document.getElementById('amEditAstatus').value     = b.astatus          || '';
        document.getElementById('amEditSlead').value       = b.source_lead      || '';
        document.getElementById('amEditRemarks').value     = b.remarks          || '';
        // Attachment
        const filePath = b.document_path || '';
        const curFileEl = document.getElementById('amEditCurrentFile');
        if(filePath){
            const fname = filePath.split('/').pop().replace(/^\d+_/,'');
            curFileEl.innerHTML =
                '&#128994; <strong style="color:#374151;font-size:13px;">' + esc(fname) + '</strong>' +
                ' <button onclick="amShowPreview(' + b.id + ',\'' + esc(fname) + '\')" ' +
                'style="background:#6366f1;color:#fff;border:none;border-radius:6px;padding:4px 14px;' +
                'font-size:12px;font-weight:600;cursor:pointer;margin-left:6px;vertical-align:middle;">' +
                '&#128065;&nbsp;Preview</button>';
        } else {
            curFileEl.innerHTML = '<span style="color:#9ca3af;font-size:13px;">No attachment uploaded</span>';
        }
        document.getElementById('amEditFileInput').value = '';
        document.getElementById('amEditFileName').textContent = '';
        document.getElementById('amEditOverlay').style.display = 'flex';
    };

    window.amEditCalculate = function() {
        const agreement = parseFloat(document.getElementById('amEditAgreement').value) || 0;
        const commission = parseFloat(document.getElementById('amEditCommissionPct').value) || 0;
        const cashback = parseFloat(document.getElementById('amEditCashbackPct').value) || 0;

        const revenue = agreement * (commission / 100);
        const actual = revenue - (agreement * (cashback / 100));

        document.getElementById('amEditRevenue').value = Number.isFinite(revenue) ? revenue.toFixed(2) : '0.00';
        document.getElementById('amEditActualAmount').value = Number.isFinite(actual) ? actual.toFixed(2) : '0.00';
    };

    window.closeEditOverlay = function(){
        document.getElementById('amEditOverlay').style.display = 'none';
    };

    window.amSubmitEdit = function(){
        const id  = document.getElementById('amEditId').value;
        const btn = document.getElementById('amEditSaveBtn');
        if(!id){ amToast('Invalid booking','error'); return; }
        btn.disabled = true; btn.textContent = 'Saving…';
        const fd = new FormData();
        fd.append('update_booking_approval', '1');
        fd.append('approval_id',      id);
        fd.append('customer_name',    document.getElementById('amEditCname').value.trim());
        fd.append('contact_number',   document.getElementById('amEditPhone').value.trim());
        fd.append('email_id',         document.getElementById('amEditEmail').value.trim());
        fd.append('booking_date',     document.getElementById('amEditBdate').value);
        fd.append('booking_month',    document.getElementById('amEditBmonth').value.trim());
        fd.append('builder',          document.getElementById('amEditBuilder').value.trim());
        fd.append('project',          document.getElementById('amEditProject').value.trim());
        fd.append('project_type',     document.getElementById('amEditPtype').value.trim());
        fd.append('unit_no',          document.getElementById('amEditUnit').value.trim());
        fd.append('size',             document.getElementById('amEditSize').value.trim());
        fd.append('agreement_value',  document.getElementById('amEditAgreement').value);
        fd.append('cashback',         document.getElementById('amEditCommissionPct').value);
        fd.append('revenue',          document.getElementById('amEditRevenue').value);
        fd.append('ccashback',        document.getElementById('amEditCashbackPct').value);
        fd.append('crevenue',         document.getElementById('amEditActualAmount').value);
        fd.append('recived_amt',      document.getElementById('amEditRecived').value);
        fd.append('city',             document.getElementById('amEditCity').value.trim());
        fd.append('astatus',          document.getElementById('amEditAstatus').value.trim());
        fd.append('source_lead',      document.getElementById('amEditSlead').value);
        fd.append('remarks',          document.getElementById('amEditRemarks').value.trim());
        // Attach new file if selected
        const fi = document.getElementById('amEditFileInput');
        if(fi && fi.files.length > 0){ fd.append('document', fi.files[0]); }
        fetch('action.php',{method:'POST',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){
                btn.disabled = false; btn.textContent = 'Save Changes';
                if(r.success){
                    closeEditOverlay();
                    amToast('✓ Booking updated!', 'success');
                    setTimeout(loadAmData, 500);
                } else {
                    amToast('⚠ ' + (r.message || 'Update failed'), 'error');
                }
            }).catch(function(){
                btn.disabled = false; btn.textContent = 'Save Changes';
                amToast('Network error', 'error');
            });
    };

    window.amEditFileChanged = function(input){
        const nameEl = document.getElementById('amEditFileName');
        nameEl.textContent = input.files.length ? ('Selected: ' + input.files[0].name) : '';
    };

    window.amShowPreview = function(id, fname){
        const url = 'action.php?preview_attachment=1&id=' + id;
        const ext = (fname||'').split('.').pop().toLowerCase();
        const modal  = document.getElementById('amPreviewModal');
        const iframe = document.getElementById('amPreviewIframe');
        const img    = document.getElementById('amPreviewImg');
        const title  = document.getElementById('amPreviewTitle');
        title.textContent = fname || 'Attachment';
        // Images shown in <img>, PDFs and others in <iframe>
        if(['jpg','jpeg','png','gif','webp'].includes(ext)){
            iframe.style.display = 'none'; iframe.src = '';
            img.src = url; img.style.display = 'block';
        } else {
            img.style.display = 'none'; img.src = '';
            iframe.src = url; iframe.style.display = 'block';
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.amClosePreview = function(){
        const modal  = document.getElementById('amPreviewModal');
        const iframe = document.getElementById('amPreviewIframe');
        const img    = document.getElementById('amPreviewImg');
        modal.style.display = 'none';
        iframe.src = ''; img.src = '';
        document.body.style.overflow = '';
    };

    function amToast(msg,type){
        const c={success:'#059669',error:'#dc2626',warning:'#d97706'};
        const t=document.createElement('div'); t.innerHTML=msg;
        t.style.cssText=`position:fixed;bottom:24px;right:24px;z-index:99999;background:${c[type]||'#333'};color:#fff;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.22);max-width:360px;`;
        document.body.appendChild(t);
        setTimeout(()=>{ t.style.opacity='0'; t.style.transition='.4s'; setTimeout(()=>t.remove(),400); },3200);
    }

    // Show red dot on button if pending items exist
    document.addEventListener('DOMContentLoaded',function(){
        if(!IS_APPROVER) return;
        fetch('action.php?get_pending_approvals=true').then(r=>r.json()).then(res=>{
            const p=(res.data||[]).filter(d=>d.approval_status==='pending');
            const dot=document.getElementById('apprPendingDot');
            if(dot&&p.length>0) dot.style.display='block';
        }).catch(()=>{});
    });
})();
</script>

    <div class="table-card">

        <div class="table-container">
            <table class="table">
                <thead class="table-head">
                    <tr>
                        <th>Month</th>
                        <th class="revenue-col">AG Value</th> <!-- Always show for everyone -->
                        <?php if ($canSeeActualRevenue): ?>
                            <th class="expenses-col">AR Value</th>
                        <?php endif; ?>
                        <th class="build-incentive-col">Build Incentive</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody id="mainTableBody">
                    <?php foreach ($monthlyData as $index => $monthData): ?>
                        <?php
                            // Compute cancelled stats for this month
                            $monthCancelledAmt = 0;
                            $monthCancelledCount = 0;
                            if (isset($groupedRows[$monthData['month']])) {
                                foreach ($groupedRows[$monthData['month']] as $r) {
                                    if (strtolower(trim($r['astatus'] ?? '')) === 'canceled') {
                                        $monthCancelledAmt += (float)($r['agreement_value'] ?? 0);
                                        $monthCancelledCount++;
                                    }
                                }
                            }
                        ?>
                        <tr class="table-row" 
                            onclick="toggleRow('<?php echo $monthData['month']; ?>')"
                            data-month="<?php echo $monthData['month']; ?>"
                            data-bookings="<?php echo $monthData['bookings'] ?? 0; ?>"
                            data-agreement="<?php echo $monthData['agreement'] ?? 0; ?>"
                            data-deduct-agreement="<?php echo $monthData['deduct_agreement'] ?? 0; ?>"
                            data-revenue="<?php echo $monthData['revenue'] ?? 0; ?>"
                            data-invoice-raise="<?php echo $monthData['invoice_raise'] ?? 0; ?>"
                            data-cancelled-agreement="<?php echo $monthCancelledAmt; ?>"
                            data-cancelled-count="<?php echo $monthCancelledCount; ?>">
                            <td class="month-cell">
                                <span class="icon calendar">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="calendar-icon"
                                        width="22" height="22">
                                        <!-- Solid Calendar -->
                                        <rect x="3" y="4" width="18" height="17" rx="3" fill="#090818ff" />
                                        <rect x="3" y="4" width="18" height="4" rx="1" fill="rgba(255,255,255,0.25)" />
                                        <rect x="7" y="2" width="2" height="4" rx="1" fill="#07070eff" />
                                        <rect x="15" y="2" width="2" height="4" rx="1" fill="#09090dff" />
                                        <!-- Month Number (Dynamic) -->
                                        <text x="50%" y="72%" text-anchor="middle" font-size="10"
                                            font-family="Arial, sans-serif" fill="white" font-weight="bold">
                                            <?php echo date('m', strtotime($monthData['month'])); ?>
                                        </text>
                                    </svg>
                                </span>
                                <?php echo $monthData['month'] . ' (' . $monthData['bookings'] . ')'; ?>
                            </td>

                            <!-- Agreement Value - ALWAYS SHOW FOR EVERYONE -->
                            <td
                                class="revenue-cell amount <?php echo ($monthData['agreement'] ?? 0) > 0 ? 'positive' : 'zero'; ?>">
                                <i class="fas fa-wallet"></i>
                                <?php echo formatCompactNumber($monthData['agreement'] ?? 0); ?>
                            </td>

                            <?php if ($canSeeActualRevenue): ?>
                                <td
                                    class="amount <?php echo ($monthData['revenue'] ?? 0) > 0 ? 'positive' : 'zero'; ?> expenses-cell">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <?php echo formatActualRevenueCompact($monthData['revenue'] ?? 0); ?>
                                </td>
                            <?php endif; ?>

                            <td class="build-incentive-cell amount">
                                <i class="fas fa-gift"></i>
                                <?php
                                if ($isBusinessHead) {
                                    $incentive = $db->getTotalIncentive($monthData['month']);
                                } elseif ($user_type === 'manager') {
                                    $incentive = $monthData['paid'];
                                } elseif (($Project_type === 'mandate' || $Project_type === 'retail') && in_array($normalizedUserType, ['user', 'promoter'], true)) {
                                    $incentive = $monthData['paid'];
                                } else {
                                    $incentive = $db->getTotalIncentive($monthData['month']);
                                }
                                echo $incentive ? '₹' . number_format($incentive) : '₹0';
                                ?>
                            </td>

                            <td>
                                <svg id="expand-<?php echo $monthData['month']; ?>" class="expand-icon" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2"
                                    onclick="event.stopPropagation(); toggleRow('<?php echo $monthData['month']; ?>')">
                                    <polyline points="9,18 15,12 9,6"></polyline>
                                </svg>
                            </td>
                        </tr>


                        <tr id="nested-<?php echo $monthData['month']; ?>" style="display: none;">
                            <td colspan="<?php
                            $colCount = 1; // Base: Month column
                            $colCount += 1; // Agreement Value (now always shown)
                            if ($canSeeActualRevenue) {
                                $colCount += 1; // Actual Revenue
                            }

                            $colCount += 1; // Build Incentive
                            $colCount += 1; // Action button column
                            echo $colCount;
                            ?>">
                                <div class="nested-section">
                                    <!-- Mobile summary info -->
                                    <div class="mobile-summary-info">
                                        <?php if ($canSeeActualRevenue): ?>
                                            <div class="summary-item">
                                                <span class="summary-label">Actual Revenue:</span>
                                                <span
                                                    class="summary-value"><?php echo formatActualRevenueCompact($monthData['revenue'] ?? 0); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="summary-item">
                                            <span class="summary-label">Build Incentive:</span>
                                            <span class="summary-value">
                                                <?php
                                                if ($isBusinessHead) {
                                                    $incentive = $db->getTotalIncentive($monthData['month']);
                                                } elseif ($user_type === 'manager') {
                                                    $incentive = $monthData['paid'];
                                                } elseif (($Project_type === 'mandate' || $Project_type === 'retail') && in_array($normalizedUserType, ['user', 'promoter'], true)) {
                                                    $incentive = $monthData['paid'];
                                                } else {
                                                    $incentive = $db->getTotalIncentive($monthData['month']);
                                                }
                                                echo $incentive ? '₹' . number_format($incentive) : '₹0';
                                                ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="nested-controls">
                                        <div class="nested-search-wrapper">
                                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <circle cx="11" cy="11" r="8"></circle>
                                                <path d="m21 21-4.35-4.35"></path>
                                            </svg>
                                            <input type="text" class="nested-search" placeholder="Search details..."
                                                oninput="handleNestedSearch('<?php echo $monthData['month']; ?>', this.value)">
                                        </div>
                                        <div class="per-page-selector">
                                            <span>Show</span>
                                            <select class="per-page-select"
                                                onchange="handlePerPageChange('<?php echo $monthData['month']; ?>', this.value)">
                                                <option value="5">5</option>
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                            </select>
                                            <span>per page</span>
                                        </div>
                                    </div>

                                    <div class="compact-table">
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <thead class="compact-table-head">
                                                <tr>
                                                    <th>S.No</th>
                                                    <th class="unit-col">Unit</th>
                                                    <th class="type-col">Type</th>
                                                    <th>Customer</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="nested-data-<?php echo $monthData['month']; ?>">
                                                <?php
                                                $counter = 1;
                                                if (isset($groupedRows[$monthData['month']])) {
                                                    foreach ($groupedRows[$monthData['month']] as $row):
                                                        $statusClass = '';
                                                        if ($row['astatus'] === 'Processing') {
                                                            $statusClass = 'badge-orange';
                                                        } elseif ($row['astatus'] === 'Received') {
                                                            $statusClass = 'badge-emerald';
                                                        } elseif ($row['astatus'] === 'Canceled') {
                                                            $statusClass = 'badge-red';
                                                        } elseif ($row['astatus'] === 'Completed') {
                                                            $statusClass = 'badge-emerald';
                                                        } elseif ($row['astatus'] === 'Active') {
                                                            $statusClass = 'badge-blue';
                                                        } elseif ($row['astatus'] === 'Pending') {
                                                            $statusClass = 'badge-amber';
                                                        } else {
                                                            $statusClass = 'badge-outline';
                                                        }

                                                        $typeNorm = strtolower(str_replace(['-', ' '], '', $row['project_type']));
                                                        $typeClass = '';
                                                        if (strpos($typeNorm, '3bhk') !== false) {
                                                            $typeClass = 'badge-blue';
                                                        } elseif (strpos($typeNorm, '2bhk') !== false) {
                                                            $typeClass = 'badge-purple';
                                                        } elseif (strpos($typeNorm, '4bhk') !== false) {
                                                            $typeClass = 'badge-orange';
                                                        } elseif (strpos($typeNorm, '5bhk') !== false) {
                                                            $typeClass = 'badge-red';
                                                        } elseif (strpos($typeNorm, '6bhk') !== false) {
                                                            $typeClass = 'badge-red';
                                                        } else {
                                                            $typeClass = 'badge-outline';
                                                        }
                                                        ?>
                                                        <tr class="compact-row">
                                                            <td>
                                                                <span class="badge badge-outline"><?php echo $row['id']; ?></span>
                                                            </td>
                                                            <td class="unit-cell">
                                                                <span
                                                                    class="badge badge-emerald"><?php echo $row['unit_no']; ?></span>
                                                            </td>
                                                            <td class="type-cell">
                                                                <span
                                                                    class="badge <?php echo $typeClass; ?>"><?php echo strtoupper($row['project_type']); ?></span>
                                                            </td>
                                                            <td>

                                                                <div class="customer-info">

                                                                    <div class="customer-avatar">
                                                                        <?php echo strtoupper(substr($row['customer_name'], 0, 1)); ?>
                                                                    </div>
                                                                    <div class="customer-details">
                                                                        <div class="customer-name">
                                                                            <?php echo $row['customer_name']; ?>
                                                                        </div>
                                                                        <div class="customer-contact">
                                                                            <?php echo ($userid === 'rahul00761') ? 'XXXXXXXXXXX' : $row['contact_number']; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo $statusClass; ?>">
                                                                    <?php echo $row['astatus']; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-toggle btn-sm"
                                                                    onclick="toggleDetail('detail-<?php echo $monthData['month']; ?>-<?php echo $row['id']; ?>')">
                                                                    Show More
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr id="detail-<?php echo $monthData['month']; ?>-<?php echo $row['id']; ?>"
                                                            style="display: none;">
                                                            <td colspan="6">
                                                                <div class="expanded-details">
                                                                    <div class="details-grid">
                                                                        <div>
                                                                            <h6 class="section-title">
                                                                                <span class="icon-wrapper icon-blue">
                                                                                    <svg width="18" height="18" viewBox="0 0 24 24"
                                                                                        fill="none" stroke="currentColor"
                                                                                        stroke-width="2">
                                                                                        <circle cx="12" cy="7" r="4"></circle>
                                                                                        <path d="M5.5 21a7.5 7.5 0 0 1 13 0"></path>
                                                                                    </svg>
                                                                                </span>
                                                                                Contact Details
                                                                            </h6>
                                                                            <div class="detail-section">
                                                                                <div class="property-det contact-info">
                                                                                    <div class="detail-item">
                                                                                        <div class="detail-icon-wrapper green">
                                                                                            <svg width="16" height="16"
                                                                                                viewBox="0 0 24 24" fill="none"
                                                                                                stroke="currentColor"
                                                                                                stroke-width="2">
                                                                                                <path
                                                                                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                                                                                </path>
                                                                                            </svg>
                                                                                        </div>
                                                                                        <span
                                                                                            class="detail-text"><?php echo ($userid === 'rahul00761') ? 'XXXXXXXXXXX' : $row['contact_number']; ?></span>
                                                                                    </div>
                                                                                    <div class="detail-item">
                                                                                        <div class="detail-icon-wrapper blue">
                                                                                            <svg width="16" height="16"
                                                                                                viewBox="0 0 24 24" fill="none"
                                                                                                stroke="currentColor"
                                                                                                stroke-width="2">
                                                                                                <path
                                                                                                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                                                                                </path>
                                                                                                <polyline points="22,6 12,13 2,6">
                                                                                                </polyline>
                                                                                            </svg>
                                                                                        </div>
                                                                                        <span
                                                                                            class="detail-text"><?php echo ($userid === 'rahul00761') ? 'XXXXXXXXXXX' : $row['email_id']; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                                <div
                                                                                    style="padding-top: 0.75rem; border-top: 1px solid #f1f5f9; margin-top: 0.75rem;">
                                                                                    <div
                                                                                        style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                                                                                        <!-- Sales Person Section -->
                                                                                        <div style="flex: 1;">
                                                                                            <div class="detail-label"
                                                                                                style="margin: 0 0 6px 0; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                                                                                                SALES PERSON</div>
                                                                                            <div
                                                                                                style="display: flex; align-items: center; gap: 10px;">
                                                                                                <div class="detail-value"
                                                                                                    style="font-size: 13px; color: #333; font-weight: 700;">
                                                                                                    <?php echo htmlspecialchars($row['source_table'] ?? ''); ?>
                                                                                                </div>
                                                                                                <div data-field="status_indicator" style="display:flex;align-items:center;">
                                                                                                <?php
                                                                                                // Status indicator based on booking status
                                                                                                $status = $row['astatus'] ?? '';
                                                                                                if ($status === 'Received' || $status === 'Completed') {
                                                                                                    echo '<div style="width: 28px; height: 28px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                                                                            <polyline points="20 6 9 17 4 12"></polyline>
                                                                                                        </svg>
                                                                                                    </div>';
                                                                                                } elseif ($status === 'Processing' || $status === 'Pending') {
                                                                                                    echo '<div style="width: 28px; height: 28px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                                                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                                                                                        </svg>
                                                                                                    </div>';
                                                                                                } elseif ($status === 'Canceled') {
                                                                                                    echo '<div style="width: 28px; height: 28px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                                                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                                                                                        </svg>
                                                                                                    </div>';
                                                                                                }
                                                                                                ?>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Actions Section -->
                                                                                        <div style="flex-shrink: 0;">
                                                                                            <div class="detail-label"
                                                                                                style="font-size: 11px;">ACTIONS
                                                                                            </div>
                                                                                            <div
                                                                                                style="display: flex; align-items: center; gap: 8px;">
                                                                                                <?php
                                                                                                // Check if attachment exists
                                                                                                $filePath = $row['document_path'] ?? '';
                                                                                                $fileCheckPath = $filePath;
                                                                                                if (!empty($filePath) && strpos($filePath, '../superadmin/') === 0) {
                                                                                                    $fileCheckPath = __DIR__ . '/' . $filePath;
                                                                                                } elseif (!empty($filePath) && strpos($filePath, 'uploads_form/') === 0) {
                                                                                                    $fileCheckPath = __DIR__ . '/../superadmin/' . $filePath;
                                                                                                }
                                                                                                $hasAttachment = !empty($filePath) && file_exists($fileCheckPath);

                                                                                                // Only APPROVAL_MANAGER (see action.php) has full action button access
                                                                                                $canEdit     = ($userid === $approvalManager);
                                                                                                $canDelete   = ($userid === $approvalManager);
                                                                                                $canDownload = ($userid === $approvalManager);

                                                                                                // Edit button
                                                                                                if ($canEdit) {
                                                                                                    echo '<a href="#" id="' . $row['id'] . '" class="btn btn-sm editLink" onclick="event.preventDefault(); event.stopPropagation(); editUser(\'' . $row['id'] . '\'); return false;" style="padding:8px 10px;background:white;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#1a73e8;display:inline-flex;align-items:center;justify-content:center;"><i class="fa fa-edit" style="font-size:14px;"></i></a>';
                                                                                                } else {
                                                                                                    echo '<span style="padding:8px 10px;background:#e5e7eb;border:1px solid #d1d5db;border-radius:4px;color:#6b7280;display:inline-flex;align-items:center;justify-content:center;cursor:not-allowed;"><i class="fa fa-edit" style="font-size:14px;color:#6b7280;"></i></span>';
                                                                                                }

                                                                                                // Download button
                                                                                                if ($canDownload && $hasAttachment) {
                                                                                                    $downloadUrl = 'action.php?download_attachment=1&id=' . urlencode($row['id']);
                                                                                                    echo '<a href="' . htmlspecialchars($downloadUrl) . '" class="btn btn-sm" onclick="event.stopPropagation();" style="padding:8px 10px;background:white;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#1a73e8;display:inline-flex;align-items:center;justify-content:center;"><i class="fa fa-download" style="font-size:14px;"></i></a>';
                                                                                                } else {
                                                                                                    echo '<button type="button" disabled class="btn btn-sm" style="padding:8px 10px;background:#f3f4f6;border:1px solid #ddd;border-radius:4px;color:#9ca3af;display:inline-flex;align-items:center;justify-content:center;cursor:not-allowed;"><i class="fa fa-download" style="font-size:14px;"></i></button>';
                                                                                                }

                                                                                                // Delete button
                                                                                                if ($canDelete) {
                                                                                                    echo '<button type="button" class="btn btn-sm" onclick="event.stopPropagation(); deleteBooking(\'' . $row['id'] . '\');" style="padding:8px 10px;background:#fff1f2;border:1px solid #fecdd3;border-radius:4px;color:#ef4444;display:inline-flex;align-items:center;justify-content:center;"><i class="fa fa-trash" style="font-size:14px;"></i></button>';
                                                                                                } else {
                                                                                                    echo '<button type="button" disabled class="btn btn-sm" style="padding:8px 10px;background:#f3f4f6;border:1px solid #ddd;border-radius:4px;color:#9ca3af;display:inline-flex;align-items:center;justify-content:center;cursor:not-allowed;"><i class="fa fa-trash" style="font-size:14px;"></i></button>';
                                                                                                }
                                                                                                ?>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div>
                                                                            <h6 class="section-title">
                                                                                <span class="icon-wrapper icon-green">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" x="0px"
                                                                                        y="0px" width="24" height="24"
                                                                                        viewBox="0,0,256,256">
                                                                                        <g fill="#ffffff" fill-rule="nonzero"
                                                                                            stroke="none" stroke-width="1"
                                                                                            stroke-linecap="butt"
                                                                                            stroke-linejoin="miter"
                                                                                            stroke-miterlimit="10"
                                                                                            stroke-dasharray=""
                                                                                            stroke-dashoffset="0" font-family="none"
                                                                                            font-weight="none" font-size="none"
                                                                                            text-anchor="none"
                                                                                            style="mix-blend-mode: normal">
                                                                                            <g transform="scale(10.66667,10.66667)">
                                                                                                <path
                                                                                                    d="M12,2.09961l-11,9.90039h3v9h7v-6h2v6h7v-9h3zM12,4.79102l6,5.40039v0.80859v8h-3v-6h-6v6h-3v-8.80859z">
                                                                                                </path>
                                                                                            </g>
                                                                                        </g>
                                                                                    </svg>
                                                                                </span>
                                                                                Property Details
                                                                            </h6>
                                                                            <div class="detail-section">
                                                                                <div class="property-det">
                                                                                    <div>
                                                                                        <div class="detail-label">Unit No</div>
                                                                                        <div class="detail-value" data-field="unit_no">
                                                                                            <?php echo $row['unit_no']; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div class="detail-label">Type</div>
                                                                                        <div class="detail-value" data-field="project_type">
                                                                                            <?php echo strtoupper($row['project_type']); ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="property-det">
                                                                                    <div>
                                                                                        <div class="detail-label">Builder</div>
                                                                                        <div class="detail-value" data-field="builder">
                                                                                            <?php echo $row['builder']; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div class="detail-label">Project</div>
                                                                                        <div class="detail-value" data-field="project">
                                                                                            <?php echo $row['project']; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div
                                                                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; margin-top: 0.75rem;">
                                                                                    <div>
                                                                                        <div class="detail-label">Size</div>
                                                                                        <div class="detail-value" data-field="size">
                                                                                            <?php echo $row['size']; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div class="detail-label">Date</div>
                                                                                        <div class="detail-value" data-field="booking_date">
                                                                                            <?php echo $row['booking_date']; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div
                                                                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; padding-top: 0.5rem;">
                                                                                    <div>
                                                                                        <div class="detail-label">City</div>
                                                                                        <div class="detail-value" data-field="city">
                                                                                            <?php echo !empty($row['city']) ? $row['city'] : 'N/A'; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div>
                                                                            <h6 class="section-title">
                                                                                <span class="icon-wrapper icon-orange">
                                                                                    <svg width="18" height="18" viewBox="0 0 24 24"
                                                                                        fill="none" stroke="currentColor"
                                                                                        stroke-width="2">
                                                                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                                                                        <path
                                                                                            d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6">
                                                                                        </path>
                                                                                    </svg>
                                                                                </span>
                                                                                Financial Details
                                                                            </h6>
                                                                            <div class="detail-section">
                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">Agreement
                                                                                        Value</span>
                                                                                    <span class="financial-value slate" data-field="agreement_value">
                                                                                        ₹<?php echo number_format((float) ($row['agreement_value'] ?? 0)); ?>
                                                                                    </span>
                                                                                </div>

                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">
                                                                                        Total Revenue
                                                                                        <span class="commission-percent">
                                                                                            (<?php echo number_format((float) ($row['cashback'] ?? 0), 2); ?>%
                                                                                            commission)
                                                                                        </span>
                                                                                    </span>
                                                                                    <span class="financial-value emerald" data-field="revenue">
                                                                                        ₹<?php echo number_format((float) ($row['revenue'] ?? 0)); ?>
                                                                                    </span>
                                                                                </div>

                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">
                                                                                        Actual Revenue
                                                                                        <span class="cashback-percent">
                                                                                            (<?php echo number_format((float) ($row['ccashback'] ?? 0), 2); ?>%
                                                                                            cashback)
                                                                                        </span>
                                                                                    </span>
                                                                                    <span class="financial-value emerald" data-field="crevenue">
                                                                                        ₹<?php echo number_format((float) ($row['crevenue'] ?? 0)); ?>
                                                                                    </span>
                                                                                </div>

                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">Received
                                                                                        Amount</span>
                                                                                    <span class="financial-value purple" data-field="recived_amt">
                                                                                        ₹<?php echo number_format((float) ($row['recived_amt'] ?? 0)); ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                        $counter++;
                                                    endforeach;
                                                } else {
                                                    echo '<tr><td colspan="6" style="text-align: center; padding: 1rem;">No bookings found for this month</td></tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>



                                    <div class="nested-pagination">
                                        <div class="nested-pagination-info">
                                            <span>Showing</span>
                                            <span id="showing-start-<?php echo $monthData['month']; ?>">0</span>
                                            <span>to</span>
                                            <span id="showing-end-<?php echo $monthData['month']; ?>">0</span>
                                            <span>of</span>
                                            <span
                                                id="total-records-<?php echo $monthData['month']; ?>"><?php echo $monthData['bookings']; ?></span>
                                            <span>entries</span>
                                        </div>
                                        <div class="nested-pagination-controls">
                                            <button class="btn btn-outline pagination-btn btn-sm"
                                                id="prev-btn-<?php echo $monthData['month']; ?>"
                                                onclick="handleNestedPagination('<?php echo $monthData['month']; ?>', 'prev')"
                                                disabled>
                                                Previous
                                            </button>
                                            <div class="nested-page-numbers"
                                                id="page-numbers-<?php echo $monthData['month']; ?>">
                                                <button class="btn btn-primary btn-sm active-page"
                                                    onclick="goToNestedPage('<?php echo $monthData['month']; ?>', 1)">1</button>
                                            </div>
                                            <button class="btn btn-outline pagination-btn btn-sm"
                                                id="next-btn-<?php echo $monthData['month']; ?>"
                                                onclick="handleNestedPagination('<?php echo $monthData['month']; ?>', 'next')">
                                                Next
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination-top-row" style="text-align: center;">
        <div class="pagination-text-top pagination-text" style="display: inline-block;">Showing 0 To 0 Of 0 Entries
        </div>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <div class="pagination-info">
            <span>Show</span>
            <select class="pagination-select">
                <option>10</option>
                <option>25</option>
                <option>50</option>
            </select>
            <span>Entries</span>
        </div>
        <div class="pagination-controls">
            <button class="btn btn-outline btn-sm" disabled>Previous</button>
            <button class="btn btn-primary btn-sm">1</button>
            <button class="btn btn-outline btn-sm">Next</button>
        </div>
    </div>
</div>

<!-- <button>
    <a href="https://searchhomesindia.in/userlogin6/bookings" target="_blank" class="old-version-btn">
        <span>Use Old Version</span>
    </a>
</button> -->



<script>
    // State management
    const groupedRows = <?php echo json_encode($groupedRows, JSON_HEX_TAG); ?>;
    window.groupedRows = groupedRows; // Make it globally accessible
    const monthlyData = <?php echo json_encode($monthlyData, JSON_HEX_TAG); ?>;

    // Flatten grouped rows for filter dropdowns
    window.allBookingsData = [];
    Object.values(groupedRows).forEach(monthBookings => {
        if (Array.isArray(monthBookings)) {
            window.allBookingsData = window.allBookingsData.concat(monthBookings);
        }
    });


    document.addEventListener('DOMContentLoaded', function () {
        const unitInput = document.getElementById('unitNo');
        if (unitInput && !unitInput.value) {
            unitInput.value = "<?php echo $unit_prefix ?? 'Un-'; ?>";
        }
    });

    // Initialize the application
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize pagination state for each month
        <?php foreach ($monthlyData as $monthData): ?>
            nestedCurrentPages['<?php echo $monthData['month']; ?>'] = 1;
            nestedPerPageValues['<?php echo $monthData['month']; ?>'] = 5;
        <?php endforeach; ?>

        // Apply initial pagination
        Object.keys(nestedCurrentPages).forEach(month => {
            updateNestedPagination(month);
        });
    });

    // Toggle detail row - with auto-close functionality
    function toggleDetail(detailId) {
        const detailRow = document.getElementById(detailId);
        const button = detailRow.previousElementSibling.querySelector('button');

        // Extract month and row ID from detailId
        const [_, month, rowId] = detailId.match(/detail-(.+)-(\d+)/);

        if (detailRow.style.display === 'none') {
            // Close all other detail rows in the same month section
            const allDetailRows = document.querySelectorAll(`[id^="detail-${month}-"]`);
            allDetailRows.forEach(row => {
                if (row.id !== detailId && row.style.display !== 'none') {
                    row.style.display = 'none';
                    const otherButton = row.previousElementSibling.querySelector('button');
                    if (otherButton) otherButton.textContent = 'Show More';

                    // Remove from expanded details
                    expandedDetails = expandedDetails.filter(id => id !== row.id);
                }
            });

            // Open the requested detail row
            detailRow.style.display = 'table-row';
            button.textContent = 'Show Less';
            expandedDetails.push(detailId);
        } else {
            // Close the requested detail row
            detailRow.style.display = 'none';
            button.textContent = 'Show More';
            expandedDetails = expandedDetails.filter(id => id !== detailId);
        }
    }


</script>

<!-- *** Filtered bookings CSV download — isolated script so JSON data cannot break it *** -->
<script>
(function() {
    'use strict';
    window.downloadFilteredBookings = function () {
        try {
            // 1. Get flat booking data
            var allData = window.allBookingsData || [];
            if (!allData.length && window.groupedRows) {
                Object.values(window.groupedRows).forEach(function(arr) {
                    if (Array.isArray(arr)) arr.forEach(function(r){ allData.push(r); });
                });
            }
            if (!allData.length) { alert('No booking data available. Please refresh the page.'); return; }

            // 2. Get active filters (synced from bookings_script.js)
            var filters = window.activeFilters || {};

            // 3. Field mapping (filterKey -> data row key)
            var fieldMap = {
                id:           'id',
                month:        'booking_month',
                builder:      'builder',
                project:      'project',
                city:         'city',
                customerName: 'customer_name',
                type:         'project_type',
                unit:         'unit_no',
                size:         'size',
                status:       'astatus',
                salesperson:  'source_table'
            };

            // 4. Filter rows
            var hasFilters = Object.keys(filters).length > 0;
            var rows = hasFilters ? allData.filter(function(row) {
                for (var fk in fieldMap) {
                    var fv = filters[fk];
                    if (!fv) continue;
                    var rv = String(row[fieldMap[fk]] || '').toLowerCase().trim();
                    if (Array.isArray(fv)) {
                        if (fv.length && !fv.some(function(v){ return rv === String(v).toLowerCase().trim(); })) return false;
                    } else {
                        var fvs = String(fv).toLowerCase().trim();
                        if (fk === 'month') { if (rv.indexOf(fvs) < 0 && fvs.indexOf(rv) < 0) return false; }
                        else { if (rv !== fvs) return false; }
                    }
                }
                if (filters.bookingDateStart && row.booking_date && row.booking_date < filters.bookingDateStart) return false;
                if (filters.bookingDateEnd   && row.booking_date && row.booking_date > filters.bookingDateEnd)   return false;
                return true;
            }) : allData;

            if (!rows.length) { alert('No bookings match the applied filters.'); return; }

            // 5. Build CSV — NO customer_name, NO contact_number, NO email_id
            var cols = [
                ['id',                      'Booking ID'],
                ['booking_date',            'Booking Date'],
                ['booking_month',           'Booking Month'],
                ['builder',                 'Builder'],
                ['project',                 'Project'],
                ['city',                    'City'],
                ['project_type',            'Type'],
                ['unit_no',                 'Unit No'],
                ['size',                    'Size (sq.ft)'],
                ['agreement_value',         'Agreement Value'],
                ['deduct_agreement',        'Deduct Agreement'],
                ['cashback',                'Commission %'],
                ['revenue',                 'Total Revenue'],
                ['ccashback',               'CashBack %'],
                ['crevenue',                'Actual Revenue'],
                ['recived_amt',             'Received Amount'],
                ['send_amt',                'Sent Amount'],
                ['invoice_raise',           'Invoice Raised'],
                ['cashbackverify',          'Cashback Paid'],
                ['astatus',                 'Status'],
                ['source_table',            'Sales Person'],
                ['source_lead',             'Source Lead'],
                ['remarks',                 'Remarks']
            ];
            var q = function(v){ return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; };
            var out = [cols.map(function(c){ return q(c[1]); }).join(',')];
            rows.forEach(function(r){
                out.push(cols.map(function(c){ return q(r[c[0]] != null ? r[c[0]] : ''); }).join(','));
            });
            var csv = out.join('\r\n');

            // 6. Download
            var d = new Date();
            var fname = 'Filtered_Bookings_' + d.getFullYear() + '-'
                      + String(d.getMonth()+1).padStart(2,'0') + '-'
                      + String(d.getDate()).padStart(2,'0') + '.csv';
            var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href   = url;
            a.setAttribute('download', fname);
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            setTimeout(function(){ document.body.removeChild(a); URL.revokeObjectURL(url); }, 300);
        } catch(e) {
            alert('Download failed: ' + e.message);
            console.error('[DL]', e);
        }
    };
})();
</script>

<script>
    function calculateDeductValue() {
        let agreementValue = parseFloat(document.getElementById("agreementValue").value) || 0;
        let cashback = parseFloat(document.getElementById("cashbackPct").value) || 0;

        // Convert cashback % to actual percentage (e.g., 1.5% = 1.5)
        let cashbackPct = cashback;

        let result = 0;

        if (cashbackPct >= 0.1 && cashbackPct <= 0.50) {
            result1 = agreementValue * 0.25;
            result = agreementValue - result1;
        }
        else if (cashbackPct > 0.50 && cashbackPct <= 1.00) {
            result1 = agreementValue * 0.50;
            result = agreementValue - result1;
        }
        else if (cashbackPct > 1.00 && cashbackPct <= 1.50) {
            result1 = agreementValue * 0.75;
            result = agreementValue - result1;
        }
        else if (cashbackPct > 1.50) {
            result1 = agreementValue * 1.00;
            result = agreementValue - result1;
        }
        else {
            result = agreementValue;
        }

        // Set calculated value into hidden field
        document.getElementById("deduct_agreementValue").value = result.toFixed(2);
    }
</script>

<!-- Edit User Modal Start -->
<div id="editUserModal"
    style="display: none; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; z-index: 99999 !important; backdrop-filter: blur(6px) saturate(1.2) !important;background: rgb(9 9 9 / 41%); align-items: center !important; justify-content: center !important; flex-direction: column !important; overflow: auto !important;">
    <div class="modal-dialog modal-dialog-centered"
        style="position: relative !important; margin: auto !important; max-width: 600px !important; width: 90% !important; max-height: 90vh !important; overflow: hidden !important; z-index: 100000 !important;">
        <div class="modal-content"
            style="max-height: 90vh !important; display: flex !important; flex-direction: column !important;border-radius:18px; overflow: hidden !important; z-index: 100001 !important; position: relative !important;">
            <div class="modal-header"
                style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #dee2e6;">
                <h5 class="modal-title">Edit This Booking</h5>
                <button type="button" class="btn-close" aria-label="Close"
                    onclick="document.getElementById('editUserModal').style.display='none';"
                    style="opacity: 1; visibility: visible; display: block; cursor: pointer; background: transparent url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z\'/%3e%3c/svg%3e') center/1em auto no-repeat; border: 0; border-radius: 0.375rem; width: 1em; height: 1em; padding: 0.5em; margin: 0;"></button>
            </div>
            <div class="modal-body"
                style="overflow-y: auto; overflow-x: hidden; max-height: calc(90vh - 120px); flex: 1 1 auto; padding: 1rem;">
                <form id="edit-user-form" name="myform" novalidate="">
                    <input type="hidden" name="id" id="id">
                    <div class="container">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="form-group dropdown-container">
                                    <label for="unique_source_table" class="form-label">Assigned User <b
                                            id="selected_user_label"></b></label>
                                    <input type="text" id="unique_searchInput" class="form-control"
                                        placeholder="Search...">
                                    <ul id="unique_source_table" class="dropdown-options">
                                        <?php
                                        try {
                                            $reflection = new ReflectionClass($db);
                                            $connProperty = $reflection->getProperty('conn');
                                            $connProperty->setAccessible(true);
                                            $conn = $connProperty->getValue($db);

                                            if ($conn) {
                                                $userQuery = "SELECT tablename FROM accounts ORDER BY tablename";
                                                $userStmt = $conn->prepare($userQuery);
                                                $userStmt->execute();
                                                $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

                                                if (!empty($users)) {
                                                    foreach ($users as $user) {
                                                        $tablename = htmlspecialchars($user['tablename'] ?? '');
                                                        if (!empty($tablename)) {
                                                            echo "<li class='unique-option' data-value='{$tablename}'>{$tablename}</li>";
                                                        }
                                                    }
                                                }
                                            }
                                        } catch (Exception $e) {
                                            error_log("Error fetching users for dropdown: " . $e->getMessage());
                                        }
                                        ?>
                                    </ul>
                                    <input type="hidden" id="source_table" name="source_table">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="bdate">Booking date</label>
                                    <input type="date" name="bdate" id="bdate" class="form-control form-control-lg"
                                        required>
                                    <div class="invalid-feedback">Date is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="bmonth">Booking month</label>
                                    <input type="month" name="bmonth" id="bmonth" class="form-control form-control-lg"
                                        required>
                                    <div class="invalid-feedback">Month is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" name="developer" id="developer"
                                        class="form-control form-control-lg" required>
                                    <label for="developer">Builder Name</label>
                                    <div class="invalid-feedback">Builder name is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" name="bproject" id="bproject"
                                        class="form-control form-control-lg" required>
                                    <label for="bproject">Project name</label>
                                    <div class="invalid-feedback">Project name is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="username" name="cname" id="cname" class="form-control form-control-lg"
                                        required>
                                    <label for="cname">Customer name</label>
                                    <div class="invalid-feedback">Customer name is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" id="cnumber_display" class="form-control form-control-lg"
                                        required readonly>
                                    <input type="hidden" name="cnumber" id="cnumber">
                                    <label for="cnumber_display">Customer no.</label>
                                    <div class="invalid-feedback">Contact Number is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" id="cemail_display" class="form-control form-control-lg"
                                        required readonly>
                                    <input type="hidden" name="cemail" id="cemail">
                                    <label for="cemail_display">E-mail</label>
                                    <div class="invalid-feedback">E-mail is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" name="tproject" id="tproject"
                                        class="form-control form-control-lg" required>
                                    <label for="tproject">Project type</label>
                                    <div class="invalid-feedback">Project Type is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" name="unitno" id="unitno" class="form-control form-control-lg"
                                        required>
                                    <label for="unitno">Unit no</label>
                                    <div class="invalid-feedback">Unit Number is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="number" name="psize" id="psize" class="form-control form-control-lg"
                                        required>
                                    <label for="psize">Project size</label>
                                    <div class="invalid-feedback">Project Size is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="number" name="cagreement" id="cagreement"
                                        class="form-control form-control-lg" onkeyup="updateCalculate()" required>
                                    <label for="cagreement">Agreement value</label>
                                    <div class="invalid-feedback">Agreement Value is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" name="ccashback" id="ccashback"
                                        class="form-control form-control-lg" onkeyup="updateCalculate()" required>
                                    <label for="ccashback">Commission %</label>
                                    <div class="invalid-feedback">Commission % is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="number" name="crevenue" id="crevenue"
                                        class="form-control form-control-lg" required>
                                    <label for="crevenue">Total revenue</label>
                                    <div class="invalid-feedback">Total Revenue Amount is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="text" name="cccashback" id="cccashback"
                                        class="form-control form-control-lg" onkeyup="updateCalculate()" required>
                                    <label for="cccashback">CashBack %</label>
                                    <div class="invalid-feedback">CashBack % is required!</div>
                                </div>
                            </div>

                            <div class="col-md-12 mb-2">
                                <div class="form-item">
                                    <input type="number" name="ccrevenue" id="ccrevenue"
                                        class="form-control form-control-lg" required>
                                    <label for="ccrevenue">Actual Revenue</label>
                                    <div class="invalid-feedback">Actual Revenue Amount is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="number" name="brecived" id="brecived"
                                        class="form-control form-control-lg disable" readonly>
                                    <label for="brecived">Received Amount</label>
                                    <div class="invalid-feedback">Received Amt. is required!</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <input type="number" name="invoice_raised" id="invoice_raised"
                                        class="form-control form-control-lg">
                                    <label for="invoice_raised">Invoice Received Amt.</label>
                                </div>
                            </div>

                            <!-- raised invoice -->
                            <div class="col-lg-12">
                                <div class="btncheckboxs">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" class="form-check-input" name="update_invoice_checkbox"
                                            id="update_invoice_checkbox">
                                        <label class="form-check-label" for="update_invoice_checkbox">Raised
                                            Invoice</label>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" class="form-check-input" name="update_user_checkbox"
                                            id="update_user_checkbox">
                                        <label class="form-check-label" for="update_user_checkbox">Update
                                            User</label>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" class="form-check-input" name="cashbackverify"
                                            id="cashbackverify">
                                        <label class="form-check-label" for="cashbackverify">Cashback
                                            Paid</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-12 mb-3">
                                <div class="btnwraps">
                                    <input type="radio" class="btn-check" name="cstatus" id="btn-check-processing"
                                        value="Processing" required style="display: none;">
                                    <label class="btn btn-outline-primary bttm-btn"
                                        for="btn-check-processing">Processing</label>

                                    <input type="radio" class="btn-check" name="cstatus" id="btn-check-received"
                                        value="Received" required style="display: none;">
                                    <label class="btn btn-outline-success bttm-btn"
                                        for="btn-check-received">Received</label>

                                    <input type="radio" class="btn-check" name="cstatus" id="btn-check-canceled"
                                        value="Canceled" required style="display: none;">
                                    <label class="btn btn-outline-danger bttm-btn"
                                        for="btn-check-canceled">Cancelled</label>
                                    <div class="invalid-feedback">Please Select the staus of booking!</div>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="Ubsubmitbtn">
                                    <input type="submit" value="Update Booking" class="btn btn-success btn-block"
                                        id="edit-user-btn" onclick="validateForm()">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Edit User Modal End -->

<script>
    // Pass current user ID from PHP to JavaScript
    const currentUserId = '<?php echo $userid; ?>';
    
    document.addEventListener('DOMContentLoaded', function () {
        initAssignedUserDropdown(document);
    });

    function initAssignedUserDropdown(root) {
        const searchInput = root.getElementById ? root.getElementById('unique_searchInput') : root.querySelector('#unique_searchInput');
        const dropdown = root.getElementById ? root.getElementById('unique_source_table') : root.querySelector('#unique_source_table');
        if (!searchInput || !dropdown) return;

        const hiddenInput = root.getElementById ? root.getElementById('source_table') : root.querySelector('#source_table');
        const labelElement = root.getElementById ? root.getElementById('selected_user_label') : root.querySelector('#selected_user_label');

        function showDropdown() {
            const rect = searchInput.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = rect.bottom + 'px';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
            dropdown.style.display = 'block';
        }

        searchInput.addEventListener('focus', (e) => {
            e.stopPropagation();
            showDropdown();
        });
        searchInput.addEventListener('click', (e) => {
            e.stopPropagation();
            showDropdown();
        });
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const options = dropdown.getElementsByTagName('li');
            Array.from(options).forEach(opt => {
                opt.style.display = opt.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
            if (dropdown.style.display === 'block') showDropdown();
        });

        dropdown.addEventListener('click', function (event) {
            if (event.target && event.target.tagName === 'LI') {
                searchInput.value = event.target.innerText;
                if (hiddenInput) hiddenInput.value = event.target.dataset.value;
                if (labelElement) labelElement.innerHTML = event.target.innerText;
                dropdown.style.display = 'none';
            }
        });

        (root.ownerDocument || root).addEventListener('click', (event) => {
            if (!dropdown.contains(event.target) && event.target !== searchInput) {
                dropdown.style.display = 'none';
            }
        });

        const modalBody = searchInput.closest('.modal-body');
        if (modalBody) {
            modalBody.addEventListener('scroll', () => {
                if (dropdown.style.display === 'block') showDropdown();
            });
        }
    }
    window.initAssignedUserDropdown = initAssignedUserDropdown;

    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            if (e.target.matches('label[for="btn-check-received"]') || e.target.closest('label[for="btn-check-received"]')) {
                setTimeout(function () {
                    var actualRevenue = document.getElementById('ccrevenue');
                    var receivedAmt = document.getElementById('brecived');
                    if (actualRevenue && receivedAmt) {
                        receivedAmt.removeAttribute('readonly');
                        receivedAmt.value = actualRevenue.value;
                        receivedAmt.setAttribute('readonly', true);
                    }
                }, 10);
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.matches('label[for="btn-check-canceled"]') || e.target.closest('label[for="btn-check-canceled"]')) {
                setTimeout(function () {
                    var ccashback = document.getElementById('ccashback');
                    var crevenue = document.getElementById('crevenue');
                    var ccrevenue = document.getElementById('ccrevenue');
                    var brecived = document.getElementById('brecived');

                    if (ccashback) ccashback.value = 0;
                    if (crevenue) crevenue.value = 0;
                    if (ccrevenue) ccrevenue.value = 0;
                    if (brecived) {
                        brecived.removeAttribute('readonly');
                        brecived.value = 0;
                        brecived.setAttribute('readonly', true);
                    }
                }, 10);
            }
        });

        document.addEventListener('change', function (e) {
            if (e.target.matches('#update_invoice_checkbox')) {
                if (e.target.checked) {
                    var actualRevenue = document.getElementById('ccrevenue');
                    var invoiceRaised = document.getElementById('invoice_raised');
                    if (actualRevenue && invoiceRaised) {
                        invoiceRaised.value = actualRevenue.value;
                    }
                }
            }
        });
    });

    function validateForm() {
        const processingChecked = document.getElementById('btn-check-processing')?.checked;
        const receivedChecked = document.getElementById('btn-check-received')?.checked;
        const canceledChecked = document.getElementById('btn-check-canceled')?.checked;
        const invalidFeedback = document.querySelector('.invalid-feedback');
        if (!(processingChecked || receivedChecked || canceledChecked)) {
            if (invalidFeedback) invalidFeedback.style.display = 'block';
            return false;
        }

        if (invalidFeedback) invalidFeedback.style.display = 'none';
        const checkboxes = document.querySelectorAll('.btn-check.Processing');
        const radios = document.querySelectorAll('.btn-check.Received, .btn-check.Canceled');
        if (processingChecked) {
            radios.forEach(radio => radio.removeAttribute('required'));
        } else {
            checkboxes.forEach(checkbox => checkbox.removeAttribute('required'));
        }

        return true;
    }

    // Edit button functionality
    document.addEventListener('click', function (e) {
        const editLink = e.target.closest('a.editLink');
        if (editLink) {
            e.preventDefault();
            e.stopPropagation();
            const id = editLink.getAttribute('id') || editLink.id;
            if (id) {
                editUser(id);
            }
        }
    }, true);

    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function () {
        const editModal = document.getElementById('editUserModal');
        if (editModal) {
            editModal.addEventListener('click', (e) => {
                if (e.target === editModal) editModal.style.display = 'none';
            });
        }
    });

    window.editUser = async function (id) {
        try {
            const data = await fetch(`action.php?edit=1&id=${id}`, {
                method: "GET",
            });

            if (!data.ok) {
                throw new Error(`HTTP error! status: ${data.status}`);
            }

            const response = await data.json();
            const modal = document.getElementById('editUserModal');

            if (!modal) {
                console.error('Edit modal not found.');
                return;
            }

            // Populate form fields
            if (document.getElementById("id")) document.getElementById("id").value = response.id || '';
            if (document.getElementById("bdate")) document.getElementById("bdate").value = response.booking_date || '';
            if (document.getElementById("bmonth")) document.getElementById("bmonth").value = response.booking_month || '';
            if (document.getElementById("developer")) document.getElementById("developer").value = response.builder || '';
            if (document.getElementById("bproject")) document.getElementById("bproject").value = response.project || '';
            if (document.getElementById("cname")) document.getElementById("cname").value = response.customer_name || '';
            
            // Mask contact number and email for specific user (rahul00761)
            const shouldMask = currentUserId === 'rahul00761';
            
            // IMPORTANT: Always populate hidden cnumber with actual value (for submission)
            if (document.getElementById("cnumber")) document.getElementById("cnumber").value = response.contact_number || '';
            // Display field shows masked for all users
            if (document.getElementById("cnumber_display")) document.getElementById("cnumber_display").value = 'xxxxx xxxxx';
            
            // Email field - hidden field for actual value, display field for masking
            if (document.getElementById("cemail")) document.getElementById("cemail").value = response.email_id || '';
            const emailDisplayField = document.getElementById("cemail_display");
            if (emailDisplayField) {
                if (shouldMask) {
                    emailDisplayField.value = 'XXXXXXXXXXX';
                } else {
                    emailDisplayField.value = response.email_id || '';
                }
            }
            if (document.getElementById("tproject")) document.getElementById("tproject").value = response.project_type || '';
            if (document.getElementById("unitno")) document.getElementById("unitno").value = response.unit_no || '';
            if (document.getElementById("psize")) document.getElementById("psize").value = response.size || '';
            if (document.getElementById("cagreement")) document.getElementById("cagreement").value = response.agreement_value || '';
            if (document.getElementById("ccashback")) document.getElementById("ccashback").value = response.cashback || '';
            if (document.getElementById("crevenue")) document.getElementById("crevenue").value = response.revenue || '';
            if (document.getElementById("cccashback")) document.getElementById("cccashback").value = response.ccashback || '';
            if (document.getElementById("ccrevenue")) document.getElementById("ccrevenue").value = response.crevenue || '';
            if (document.getElementById("brecived")) document.getElementById("brecived").value = response.recived_amt || '';
            if (document.getElementById("source_table")) document.getElementById("source_table").value = response.source_table || '';
            if (document.getElementById("unique_searchInput")) document.getElementById("unique_searchInput").value = response.source_table || '';
            if (document.getElementById("invoice_raised")) document.getElementById("invoice_raised").value = response.invoice_raise || '';
            if (document.getElementById("update_user_checkbox")) document.getElementById("update_user_checkbox").checked = response.update_in_user_table == "1";
            if (document.getElementById("update_invoice_checkbox")) document.getElementById("update_invoice_checkbox").checked = response.update_in_invoice_table == "1";
            if (document.getElementById("cashbackverify")) document.getElementById("cashbackverify").checked = response.cashbackverify == "1";
            if (document.getElementById("selected_user_label")) document.getElementById("selected_user_label").innerHTML = response.source_table || '';

            // Set status radio button and trigger visual update
            if (response.astatus) {
                // Only select radio buttons within the edit modal
                const statusRadios = document.querySelectorAll('#editUserModal input[type="radio"][name="cstatus"]');

                // Clear all radio buttons and remove active class from all labels
                statusRadios.forEach(radio => {
                    radio.checked = false;
                    const label = document.querySelector(`#editUserModal label[for="${radio.id}"]`);
                    if (label) {
                        label.classList.remove('active');
                    }
                });

                // Set the correct one with visual feedback
                statusRadios.forEach(radio => {
                    if (radio.value === response.astatus) {
                        radio.checked = true;
                        const label = document.querySelector(`#editUserModal label[for="${radio.id}"]`);
                        if (label) {
                            label.classList.add('active');
                        }
                    }
                });
            }

            // Show modal
            if (modal) {
                modal.style.display = 'flex';
            }

        } catch (error) {
            console.error('Error loading booking data:', error);
            if (typeof showEnhancedNotification === 'function') {
                showEnhancedNotification("Error loading booking data", "error");
            } else {
                alert("Error loading booking data");
            }
        }
    };

    window.deleteBooking = async function (id) {
        if (!id) return;

        if (window.Swal) {
            const result = await Swal.fire({
                title: 'Delete this booking?',
                text: 'This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ef4444',
            });
            if (!result.isConfirmed) return;
        } else {
            const confirmed = confirm('Delete this booking? This cannot be undone.');
            if (!confirmed) return;
        }

        try {
            const response = await fetch(`action.php?delete=1&id=${encodeURIComponent(id)}`, {
                method: 'GET',
            });

            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (error) {
                result = { success: response.ok, message: responseText };
            }

            if (result.success) {
                if (window.Swal) {
                    Swal.fire({
                        toast: true,
                        position: 'bottom',
                        icon: 'success',
                        title: result.message || 'Booking deleted successfully!',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        customClass: {
                            popup: 'custom-toast-popup'
                        }
                    });
                } else if (typeof showNotification === 'function') {
                    showNotification(result.message || 'Booking deleted successfully!', 'success');
                } else {
                    alert(result.message || 'Booking deleted successfully!');
                }
                window.location.reload();
            } else {
                if (window.Swal) {
                    Swal.fire({
                        toast: true,
                        position: 'bottom',
                        icon: 'error',
                        title: result.message || 'Delete failed. Please try again.',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        customClass: {
                            popup: 'custom-toast-popup'
                        }
                    });
                } else if (typeof showNotification === 'function') {
                    showNotification(result.message || 'Delete failed. Please try again.', 'error');
                } else {
                    alert(result.message || 'Delete failed. Please try again.');
                }
            }
        } catch (error) {
            console.error('Error deleting booking:', error);
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: 'Error deleting booking. Please try again.',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
            } else if (typeof showNotification === 'function') {
                showNotification('Error deleting booking. Please try again.', 'error');
            } else {
                alert('Error deleting booking. Please try again.');
            }
        }
    };

    // Update form submission handler
    document.addEventListener('DOMContentLoaded', function () {
        // Add click handler for status buttons to manage active class
        const statusLabels = document.querySelectorAll('#editUserModal label.bttm-btn');
        const statusRadios = document.querySelectorAll('#editUserModal input[type="radio"][name="cstatus"]');

        statusLabels.forEach(label => {
            label.addEventListener('click', function () {
                // Remove active class from all status labels
                statusLabels.forEach(l => l.classList.remove('active'));
                // Add active class to clicked label
                this.classList.add('active');
            });
        });

        // Also add change handler on radio inputs to sync active class
        statusRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.checked) {
                    // Remove active class from all labels
                    statusLabels.forEach(l => l.classList.remove('active'));
                    // Add active to corresponding label
                    const label = document.querySelector(`#editUserModal label[for="${this.id}"]`);
                    if (label) {
                        label.classList.add('active');
                    }

                    // Auto-set CashBack % to 0 when Cancelled is selected
                    if (this.value === 'Canceled') {
                        const cashbackField = document.getElementById('cccashback');
                        if (cashbackField) {
                            cashbackField.value = '0';
                            // Trigger calculation update if function exists
                            if (typeof updateCalculate === 'function') {
                                updateCalculate();
                            }
                        }
                    }
                }
            });
        });

        const updateForm = document.getElementById("edit-user-form");
        if (updateForm) {
            updateForm.addEventListener("submit", async (e) => {
                e.preventDefault();

                const formData = new FormData(updateForm);
                formData.append("update", 1);

                if (updateForm.checkValidity() === false) {
                    e.preventDefault();
                    e.stopPropagation();
                    updateForm.classList.add("was-validated");
                    return false;
                } else {
                    const updateBtn = document.getElementById("edit-user-btn");
                    const originalText = updateBtn.value;
                    updateBtn.value = "Please Wait...";
                    updateBtn.disabled = true;

                    // Debug: Log form data
                    console.log('Submitting form with data:');
                    for (let [key, value] of formData.entries()) {
                        console.log(`  ${key}: ${value}`);
                    }

                    try {
                        const data = await fetch("action.php", {
                            method: "POST",
                            body: formData,
                        });
                        const responseText = await data.text();
                        let response;
                        try {
                            response = JSON.parse(responseText);
                        } catch (e) {
                            response = { success: false, message: responseText };
                        }

                        console.log('Server response:', response);

                        updateBtn.value = originalText;
                        updateBtn.disabled = false;

                        if (response.success) {
                            // ── Close modal ──────────────────────────────────────
                            const modal = document.getElementById('editUserModal');
                            if (modal) modal.style.display = 'none';
                            updateForm.reset();
                            updateForm.classList.remove("was-validated");

                            // ── Update compact-row cells in-place ────────────────
                            const bookingId = response.id;
                            const month    = response.booking_month;

                            // Status badge class map
                            const statusBadgeMap = {
                                'Processing': 'badge-orange',
                                'Received':   'badge-emerald',
                                'Canceled':   'badge-red',
                                'Completed':  'badge-emerald',
                                'Active':     'badge-blue',
                                'Pending':    'badge-amber',
                            };

                            // Find the compact-row for this booking id by looking at
                            // the "Show More" button's onclick which references detail-{month}-{id}
                            const detailRow = document.getElementById(`detail-${month}-${bookingId}`);
                            if (detailRow) {
                                const compactRow = detailRow.previousElementSibling;
                                if (compactRow) {
                                    // Unit
                                    const unitCell = compactRow.querySelector('.unit-cell .badge');
                                    if (unitCell) unitCell.textContent = response.unit_no;

                                    // Type badge — also update badge color
                                    const typeCell = compactRow.querySelector('.type-cell .badge');
                                    if (typeCell) {
                                        const rawType = (response.project_type || '').toUpperCase();
                                        // Normalise: strip dashes/spaces so "3-BHK", "3 BHK", "3BHK" all match
                                        const typeNorm = rawType.replace(/[-\s]/g, '');
                                        let typeBadgeClass = 'badge-outline';
                                        if (typeNorm.includes('3BHK'))      typeBadgeClass = 'badge-blue';
                                        else if (typeNorm.includes('2BHK')) typeBadgeClass = 'badge-purple';
                                        else if (typeNorm.includes('4BHK')) typeBadgeClass = 'badge-orange';
                                        else if (typeNorm.includes('5BHK')) typeBadgeClass = 'badge-red';
                                        else if (typeNorm.includes('6BHK')) typeBadgeClass = 'badge-red';
                                        typeCell.className = `badge ${typeBadgeClass}`;
                                        typeCell.textContent = rawType;
                                    }

                                    // Customer name & avatar
                                    const nameEl = compactRow.querySelector('.customer-name');
                                    if (nameEl) nameEl.textContent = response.customer_name;
                                    const avatarEl = compactRow.querySelector('.customer-avatar');
                                    if (avatarEl) avatarEl.textContent = (response.customer_name || '').charAt(0).toUpperCase();

                                    // Status badge
                                    const statusBadge = compactRow.querySelector('td:nth-child(5) .badge');
                                    if (statusBadge) {
                                        const newClass = statusBadgeMap[response.astatus] || 'badge-outline';
                                        statusBadge.className = `badge ${newClass}`;
                                        statusBadge.textContent = response.astatus;
                                    }
                                }

                                // ── Update expanded detail panel fields ──────────────
                                const formatCurrency = (val) => '₹' + Number(val || 0).toLocaleString('en-IN');

                                const fieldMap = {
                                    'unit_no':          response.unit_no,
                                    'project_type':     (response.project_type || '').toUpperCase(),
                                    'builder':          response.builder,
                                    'project':          response.project,
                                    'size':             response.size,
                                    'booking_date':     response.booking_date,
                                    'city':             response.city || '',
                                    'agreement_value':  formatCurrency(response.agreement_value),
                                    'revenue':          formatCurrency(response.revenue),
                                    'crevenue':         formatCurrency(response.crevenue),
                                    'recived_amt':      formatCurrency(response.recived_amt),
                                };

                                Object.entries(fieldMap).forEach(([field, value]) => {
                                    const el = detailRow.querySelector(`[data-field="${field}"]`);
                                    if (el && value !== undefined && value !== null) {
                                        el.textContent = value;
                                    }
                                });

                                const commissionEl = detailRow.querySelector('.commission-percent');
                                if (commissionEl && response.cashback !== undefined) {
                                    commissionEl.textContent = `(${Number(response.cashback).toFixed(2)}% commission)`;
                                }

                                const cashbackEl = detailRow.querySelector('.cashback-percent');
                                if (cashbackEl && response.ccashback !== undefined) {
                                    cashbackEl.textContent = `(${Number(response.ccashback).toFixed(2)}% cashback)`;
                                }

                                // ── Update salesperson status tick/X icon ────────────
                                const statusIndicator = detailRow.querySelector('[data-field="status_indicator"]');
                                if (statusIndicator) {
                                    const newStatus = response.astatus || '';
                                    let iconHtml = '';
                                    if (newStatus === 'Received' || newStatus === 'Completed') {
                                        iconHtml = `<div style="width:28px;height:28px;background:#22c55e;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg></div>`;
                                    } else if (newStatus === 'Processing' || newStatus === 'Pending' || newStatus === 'Canceled') {
                                        iconHtml = `<div style="width:28px;height:28px;background:#ef4444;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg></div>`;
                                    }
                                    statusIndicator.innerHTML = iconHtml;
                                }
                            }

                            // ── Update window.groupedRows memory state ───────────
                            if (window.groupedRows && window.groupedRows[month]) {
                                const rowIndex = window.groupedRows[month].findIndex(r => String(r.id) === String(bookingId));
                                if (rowIndex !== -1) {
                                    window.groupedRows[month][rowIndex].unit_no = response.unit_no;
                                    window.groupedRows[month][rowIndex].project_type = response.project_type;
                                    window.groupedRows[month][rowIndex].builder = response.builder;
                                    window.groupedRows[month][rowIndex].project = response.project;
                                    window.groupedRows[month][rowIndex].size = response.size;
                                    window.groupedRows[month][rowIndex].booking_date = response.booking_date;
                                    window.groupedRows[month][rowIndex].agreement_value = response.agreement_value;
                                    window.groupedRows[month][rowIndex].revenue = response.revenue;
                                    window.groupedRows[month][rowIndex].crevenue = response.crevenue;
                                    window.groupedRows[month][rowIndex].cashback = response.cashback;
                                    window.groupedRows[month][rowIndex].ccashback = response.ccashback;
                                    window.groupedRows[month][rowIndex].recived_amt = response.recived_amt;
                                    window.groupedRows[month][rowIndex].astatus = response.astatus;
                                    window.groupedRows[month][rowIndex].customer_name = response.customer_name;
                                    window.groupedRows[month][rowIndex].invoice_raise = response.invoice_raise;
                                }
                                
                                // Recalculate top banners dynamically!
                                if (typeof window.updateStatsFromFilteredData === 'function') {
                                    window.updateStatsFromFilteredData();
                                }
                            }

                            // Show success notification
                            if (typeof showNotification === 'function') {
                                showNotification("Booking updated successfully!", "success");
                            } else {
                                console.log('Booking updated successfully!');
                            }
                        } else {
                            // Show error
                            if (typeof showNotification === 'function') {
                                showNotification(response.message || "Update failed. Please try again.", "error");
                            } else {
                                alert(response.message || "Update failed. Please try again.");
                            }
                        }
                    } catch (error) {
                        console.error('Error updating booking:', error);
                        if (typeof showNotification === 'function') {
                            showNotification("Error updating booking. Please try again.", "error");
                        } else {
                            alert('Error updating booking. Please try again.');
                        }
                        updateBtn.value = originalText;
                        updateBtn.disabled = false;
                    }
                }
            });
        }
    });
        setInterval(function(){ 
        let ids = [
            ['filterBookingDateStart', 'display_filterBookingDateStart', 'dd-mm-yyyy', 'date'],
            ['filterBookingDateEnd', 'display_filterBookingDateEnd', 'dd-mm-yyyy', 'date'],
            ['filterMonth', 'display_filterMonth', 'mm-yyyy', 'month'],
            ['bookingDate', 'display_bookingDate', 'dd-mm-yyyy', 'date']
        ];
        ids.forEach(p => {
            let inp = document.getElementById(p[0]); 
            let sp = document.getElementById(p[1]); 
            if(inp && sp){ 
                let v = inp.value;
                if(v) {
                    if (p[3] === 'month') {
                        sp.innerText = v.split('-').reverse().join('-'); 
                    } else {
                        sp.innerText = v.split('-').reverse().join('-'); 
                    }
                } else {
                    sp.innerText = p[2];
                }
                let isDark = document.body.getAttribute('data-theme') === 'dark'; 
                sp.style.color = v ? (isDark ? '#fff' : '#000') : '#6b7280'; 
            }
        }); 
    }, 100);


    setInterval(function(){ 
        let ids = [
            ['filterBookingDateStart', 'display_filterBookingDateStart', 'dd-mm-yyyy', 'date'],
            ['filterBookingDateEnd', 'display_filterBookingDateEnd', 'dd-mm-yyyy', 'date'],
            ['filterMonth', 'display_filterMonth', 'mm-yyyy', 'month'],
            ['bookingDate', 'display_bookingDate', 'dd-mm-yyyy', 'date']
        ];
        ids.forEach(p => {
            let inp = document.getElementById(p[0]); 
            let sp = document.getElementById(p[1]); 
            if(inp && sp){ 
                let v = inp.value;
                if(v) {
                    if (p[3] === 'month') {
                        sp.innerText = v.split('-').reverse().join('-'); 
                    } else {
                        sp.innerText = v.split('-').reverse().join('-'); 
                    }
                } else {
                    sp.innerText = p[2];
                }
                let isDark = document.body.getAttribute('data-theme') === 'dark'; 
                sp.style.color = v ? (isDark ? '#fff' : '#000') : '#6b7280'; 
            }
        }); 
    }, 100);
</script>

<?php
$customJs = [
    "assets/js/bookings_script.js?v=20250930"
];
include "htmlclose.php";
?>
