<?php
// Start session (only call once at top of each request)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// -------------------------
// Current page detection
// -------------------------
$currentPage = basename($_SERVER['PHP_SELF']);
$isLeadsPage = $currentPage === 'user_lead.php';
$isEOIPage = $currentPage === 'user_eoi.php';
$isBookingPage = $currentPage === 'user_booking.php';
$dashboard = $currentPage === 'dashboard.php';
$isProfilePage = $currentPage === 'profile.php';

require_once 'config.php';
$config = new Config();
$conn = $config->getConnection();

// Default user values in case session/tablename is missing or DB returns nothing
$currentUsername = 'User';
$currentEmail = 'user@company.com';
$currentRole = 'user';

try {
  // Ensure we have a tablename in session to look up (adjust key if you use a different session key)
  $tablename = $_SESSION['tablename'] ?? null;

  // For superadmin viewing via impersonation (e.g. from superadmin dashboard),
  // allow an override of the tablename using a safe GET parameter.
  if (isset($_SESSION['role']) && $_SESSION['role'] === 'superuseradmin') {
    if (!empty($_GET['impersonate'])) {
      $tablename = trim((string) $_GET['impersonate']);
    }
  }

  if (!empty($tablename)) {
    $stmt = $conn->prepare("SELECT username, useremail, user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
    $stmt->bindParam(':tablename', $tablename, PDO::PARAM_STR);
    $stmt->execute();
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userRow) {
      $currentUsername = $userRow['username'] ?? $currentUsername;
      $currentEmail = $userRow['useremail'] ?? $currentEmail;
      $currentRole = $userRow['user_type'] ?? $currentRole;
    }
  }
} catch (PDOException $e) {
  // Log this error in your real app. Do not expose DB errors to users in production.
  error_log("DB error fetching user: " . $e->getMessage());
}

// Generate secure auto-login token for hrlogin employee portal
$secret = 'MecntecIntegrationSecret2026';
$email = $_SESSION['name'] ?? $currentEmail;
$timestamp = time();
$token = md5($email . $timestamp . $secret);
$attendance_url = "https://mnts.in/incentiveapp_integration/userlogin1/hrlogin/employee_portal.php?email=" . urlencode($email) . "&ts=" . $timestamp . "&token=" . $token;

// -------------------------
// Role helpers / normalization
// -------------------------
function normalize_role($role)
{
  $r = strtolower(trim((string) $role));
  return match ($r) {
    'promoter' => 'promoter',
    'business head', 'business_head' => 'business_head',
    'manager' => 'manager',
    'team lead', 'team_lead' => 'team_lead',
    'user' => 'user',
    default => 'user',
  };
}

function role_level($normalized)
{
  // Lower number => higher in hierarchy
  $map = [
    'promoter' => 1,
    'business_head' => 2,
    'manager' => 3,
    'team_lead' => 4,
    'user' => 5,
  ];
  return $map[$normalized] ?? 5;
}

function display_role($normalized)
{
  return match ($normalized) {
    'promoter' => 'Promoter',
    'business_head' => 'Business Head',
    'manager' => 'Manager',
    'team_lead' => 'Team Lead',
    default => 'User',
  };
}

// Compute normalized/displayable role and numeric level
$currentRoleNormalized = normalize_role($currentRole);
$currentRoleLevel = role_level($currentRoleNormalized);
$currentRoleDisplay = display_role($currentRoleNormalized);

// Keep bookings header filters aligned with the same effective-user rule used for Edit visibility.
$bookingFilterUserParam = isset($filterUser) ? trim((string) $filterUser) : '';
$bookingManagerViewParam = isset($managerView) ? trim((string) $managerView) : '';
$bookingSessionUserType = strtolower(trim((string) ($_SESSION['user_type'] ?? '')));
$bookingEffectiveUserId = trim((string) ($_SESSION['tablename'] ?? ''));

if (
  $bookingFilterUserParam !== '' &&
  $bookingManagerViewParam === 'true' &&
  $bookingSessionUserType !== 'user'
) {
  $bookingEffectiveUserId = $bookingFilterUserParam;
}

$canShowBookingYearCityFilters = true;
?>
<style>
  /* ===== Scoped to the widget only - safe to include site-wide ===== */
  [data-push-widget="mnt"] {
    box-sizing: border-box;
  }

  [data-push-widget="mnt"],
  [data-push-widget="mnt"] * {
    box-sizing: inherit;
  }

  /* --- layout: keep the old .mnt-card container but align to new visual --- */
  [data-push-widget="mnt"] .mnt-card {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    width: 100% !important;
    padding: 10px 12px !important;
    border-radius: 12px !important;
    background: transparent !important;
    /* keep it neutral so it won't clash */
    border: none !important;
    box-shadow: none !important;
  }

  /* --- keep .mnt-meta in DOM for screen readers but collapse visually without removing it --- */
  [data-push-widget="mnt"] .mnt-meta {
    /* visually collapse while remaining accessible */
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    margin: -1px !important;
    padding: 0 !important;
    overflow: hidden !important;
    clip: rect(0 0 0 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
  }

  /* --- Visual label (bell + text + slider) --- */
  [data-push-widget="mnt"] .user-action.notification-toggle {
    display: inline-flex !important;
    align-items: center !important;
    gap: 10px !important;
    cursor: pointer !important;
    user-select: none !important;
    padding: 6px 8px !important;
    /* small hit area */
    border-radius: 10px !important;
    min-height: 36px !important;
  }

  /* bell icon */
  [data-push-widget="mnt"] .user-action.notification-toggle .fas {
    font-size: 18px !important;
    line-height: 1 !important;
    display: inline-block !important;
  }

  /* notification text visible beside bell */
  [data-push-widget="mnt"] .notification-label {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #0f172a !important;
    display: none !important;
    line-height: 1 !important;
    margin-right: 6px !important;
    white-space: nowrap !important;
  }

  /* Tooltip container for notification */
  [data-push-widget="mnt"] .user-action.notification-toggle {
    position: relative !important;
  }

  /* Tooltip styling */
  [data-push-widget="mnt"] .user-action.notification-toggle::after {
    content: 'Notifications' !important;
    position: absolute !important;
    bottom: 100% !important;
    left: 50% !important;
    transform: translateX(-50%) translateY(-8px) !important;
    background: rgba(15, 23, 42, 0.95) !important;
    color: white !important;
    padding: 6px 12px !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    white-space: nowrap !important;
    opacity: 0 !important;
    pointer-events: none !important;
    transition: opacity 0.2s ease, transform 0.2s ease !important;
    z-index: 1000 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
  }

  /* Tooltip arrow */
  [data-push-widget="mnt"] .user-action.notification-toggle::before {
    content: '' !important;
    position: absolute !important;
    bottom: 100% !important;
    left: 50% !important;
    transform: translateX(-50%) translateY(-2px) !important;
    border: 5px solid transparent !important;
    border-top-color: rgba(15, 23, 42, 0.95) !important;
    opacity: 0 !important;
    pointer-events: none !important;
    transition: opacity 0.2s ease, transform 0.2s ease !important;
    z-index: 1000 !important;
  }

  /* Show tooltip on hover */
  [data-push-widget="mnt"] .user-action.notification-toggle:hover::after {
    opacity: 1 !important;
    transform: translateX(-50%) translateY(-4px) !important;
  }

  [data-push-widget="mnt"] .user-action.notification-toggle:hover::before {
    opacity: 1 !important;
    transform: translateX(-50%) translateY(2px) !important;
  }

  /* Dark theme tooltip */
  [data-theme="dark"] [data-push-widget="mnt"] .user-action.notification-toggle::after {
    background: rgba(255, 255, 255, 0.95) !important;
    color: #0f172a !important;
  }

  [data-theme="dark"] [data-push-widget="mnt"] .user-action.notification-toggle::before {
    border-top-color: rgba(255, 255, 255, 0.95) !important;
  }

  /* keep the native input present for JS; visually hide but keep focusable for accessibility */
  [data-push-widget="mnt"] .mnt-input {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    clip: rect(0 0 0 0) !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    opacity: 0 !important;
    pointer-events: auto !important;
    /* still receives clicks/keyboard */
    z-index: 2 !important;
  }

  /* --- slider (styled to look like modern toggle) --- */
  [data-push-widget="mnt"] .toggle-slider,
  [data-push-widget="mnt"] .mnt-slider {
    display: inline-block !important;
    width: 48px !important;
    height: 26px !important;
    border-radius: 999px !important;
    background: #e6eefc !important;
    /* neutral background */
    position: relative !important;
    transition: background .18s ease, box-shadow .18s ease !important;
    vertical-align: middle !important;
  }

  /* knob */
  [data-push-widget="mnt"] .toggle-slider::after,
  [data-push-widget="mnt"] .mnt-slider::after {
    content: '' !important;
    position: absolute !important;
    top: 3px !important;
    left: 4px !important;
    width: 20px !important;
    height: 20px !important;
    border-radius: 50% !important;
    background: #fff !important;
    box-shadow: 0 4px 10px rgba(2, 6, 23, 0.12) !important;
    transition: left .18s cubic-bezier(.2, .9, .3, 1) !important;
  }

  /* checked state - rely on DOM sibling relationship: .mnt-input:checked + .toggle-slider */
  [data-push-widget="mnt"] .mnt-input:checked+.toggle-slider,
  [data-push-widget="mnt"] .mnt-input:checked+.mnt-slider {
    background: #3B82F6 !important;
    box-shadow: 0 8px 22px rgba(0, 102, 255, 0.12) !important;
  }

  /* knob when checked */
  [data-push-widget="mnt"] .mnt-input:checked+.toggle-slider::after,
  [data-push-widget="mnt"] .mnt-input:checked+.mnt-slider::after {
    left: 24px !important;
  }

  /* focus-visible ring for keyboard users */
  [data-push-widget="mnt"] .mnt-input:focus-visible+.toggle-slider,
  [data-push-widget="mnt"] .mnt-input:focus+.toggle-slider {
    outline: 3px solid rgba(0, 102, 255, 0.12) !important;
    outline-offset: 4px !important;
    border-radius: 999px !important;
  }

  /* small-screen tweaks (keeps layout tight) */
  @media (max-width: 520px) {

    [data-push-widget="mnt"] .toggle-slider,
    [data-push-widget="mnt"] .mnt-slider {
      width: 48px !important;
      height: 26px !important;
    }

    [data-push-widget="mnt"] .toggle-slider::after,
    [data-push-widget="mnt"] .mnt-slider::after {
      width: 16px !important;
      height: 16px !important;
    }
  }

  /* ============================================================ */
  /* OVERDUE POPUP TOGGLE STYLES */
  /* ============================================================ */

  /* Container */
  .overdue-toggle-container {
    padding: 0 !important;
  }

  /* Main toggle label */
  .overdue-toggle-switch {
    display: inline-flex !important;
    align-items: center !important;
    gap: 10px !important;
    cursor: pointer !important;
    user-select: none !important;
    padding: 8px 12px !important;
    border-radius: 12px !important;
    min-height: 40px !important;
    width: 100% !important;
    background: rgba(248, 250, 252, 0.5) !important;
    transition: background 0.2s ease !important;
  }

  .overdue-toggle-switch:hover {
    background: rgba(241, 245, 249, 0.8) !important;
  }

  [data-theme="dark"] label.overdue-toggle-switch,
  [data-theme="dark"] .overdue-toggle-switch {
    background: transparent !important;
  }

  [data-theme="dark"] .overdue-toggle-label {
    color: white !important;
  }

  [data-theme="dark"] .overdue-toggle-label {
    color: white !important;
  }

  /* Clock icon */
  .overdue-toggle-switch .fa-clock {
    font-size: 18px !important;
    color: #3B82F6 !important;
    line-height: 1 !important;
    display: inline-block !important;
    transition: color 0.2s ease !important;
  }

  .overdue-toggle-switch:hover .fa-clock {
    color: #2563EB !important;
  }

  /* Label text */
  .overdue-toggle-label {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #1e293b !important;
    display: none !important;
    line-height: 1.4 !important;
    margin-right: 6px !important;
    white-space: nowrap !important;
    flex: 1 !important;
  }

  /* Tooltip container for overdue */
  .overdue-toggle-switch {
    position: relative !important;
  }

  /* Tooltip styling for overdue */
  .overdue-toggle-switch::after {
    content: 'Overdue Popups' !important;
    position: absolute !important;
    bottom: 100% !important;
    left: 50% !important;
    transform: translateX(-50%) translateY(-8px) !important;
    background: rgba(15, 23, 42, 0.95) !important;
    color: white !important;
    padding: 6px 12px !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    white-space: nowrap !important;
    opacity: 0 !important;
    pointer-events: none !important;
    transition: opacity 0.2s ease, transform 0.2s ease !important;
    z-index: 1000 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
  }

  /* Tooltip arrow for overdue */
  .overdue-toggle-switch::before {
    content: '' !important;
    position: absolute !important;
    bottom: 100% !important;
    left: 50% !important;
    transform: translateX(-50%) translateY(-2px) !important;
    border: 5px solid transparent !important;
    border-top-color: rgba(15, 23, 42, 0.95) !important;
    opacity: 0 !important;
    pointer-events: none !important;
    transition: opacity 0.2s ease, transform 0.2s ease !important;
    z-index: 1000 !important;
  }

  /* Show tooltip on hover for overdue */
  .overdue-toggle-switch:hover::after {
    opacity: 1 !important;
    transform: translateX(-50%) translateY(-4px) !important;
  }

  .overdue-toggle-switch:hover::before {
    opacity: 1 !important;
    transform: translateX(-50%) translateY(2px) !important;
  }

  /* Dark theme tooltip for overdue */
  [data-theme="dark"] .overdue-toggle-switch::after {
    background: rgba(255, 255, 255, 0.95) !important;
    color: #0f172a !important;
  }

  [data-theme="dark"] .overdue-toggle-switch::before {
    border-top-color: rgba(255, 255, 255, 0.95) !important;
  }

  /* Hide the checkbox input - FORCE HIDE */
  #overdue-popup-toggle,
  input#overdue-popup-toggle,
  .overdue-toggle-switch input[type="checkbox"] {
    position: absolute !important;
    width: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    clip-path: inset(50%) !important;
    margin: -1px !important;
    padding: 0 !important;
    border: 0 !important;
    opacity: 0 !important;
    pointer-events: none !important;
    visibility: hidden !important;
    white-space: nowrap !important;
  }

  /* Toggle slider - Default OFF state (GRAY) */
  .overdue-toggle-slider,
  span.overdue-toggle-slider {
    position: relative !important;
    display: inline-block !important;
    width: 48px !important;
    height: 26px !important;
    background: #E5E7EB !important;
    border-radius: 999px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    min-width: 48px !important;
    min-height: 26px !important;
    overflow: hidden !important;
  }

  /* Knob element inside slider (real DOM element) */
  .overdue-toggle-slider>span,
  span.overdue-toggle-slider>span {
    content: '' !important;
    position: absolute !important;
    top: 3px !important;
    left: 3px !important;
    width: 20px !important;
    height: 20px !important;
    border-radius: 50% !important;
    background: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2), 0 1px 2px rgba(0, 0, 0, 0.15) !important;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: block !important;
    pointer-events: none !important;
    z-index: 999 !important;
  }

  /* Slider knob - Using real span element, no ::after needed */
  .overdue-toggle-slider::after,
  span.overdue-toggle-slider::after {
    content: none !important;
    display: none !important;
  }

  /* Checked state - BLUE when ON */
  #overdue-popup-toggle:checked+.overdue-toggle-slider {
    background: #3B82F6 !important;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3) !important;
  }

  /* Knob when checked - moves to right (using span element) */
  #overdue-popup-toggle:checked+.overdue-toggle-slider>span {
    left: 25px !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15), 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  }

  /* Hover effects */
  .overdue-toggle-switch:hover .overdue-toggle-slider {
    transform: scale(1.02) !important;
  }

  .overdue-toggle-switch:hover .overdue-toggle-slider>span {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2), 0 2px 4px rgba(0, 0, 0, 0.15) !important;
  }

  /* Active/click effect */
  .overdue-toggle-switch:active .overdue-toggle-slider>span {
    width: 24px !important;
  }

  #overdue-popup-toggle:checked+.overdue-toggle-slider:active>span {
    left: 21px !important;
  }

  /* Lock indicator */
  .overdue-lock-indicator {
    margin-top: 6px !important;
    font-size: 11px !important;
    color: #ef4444 !important;
    padding-left: 36px !important;
    display: none !important;
  }

  [data-theme="dark"] #userInfoPopup .overdue-lock-indicator {
    background: transparent !important;
    color: #fbbf24 !important;
    /* Amber for warning/locked state in dark mode */
  }

  .overdue-lock-indicator .fa-lock {
    margin-right: 4px !important;
  }

  /* Disabled state (when locked by manager) */
  .overdue-toggle-switch.locked {
    opacity: 0.6 !important;
    cursor: not-allowed !important;
  }

  .overdue-toggle-switch.locked .overdue-toggle-slider {
    cursor: not-allowed !important;
  }

  /* Focus state for accessibility */
  #overdue-popup-toggle:focus-visible+.overdue-toggle-slider {
    outline: 3px solid rgba(5, 150, 105, 0.3) !important;
    outline-offset: 3px !important;
  }

  /* Hover effects */
  .overdue-toggle-switch:not(.locked):hover .overdue-toggle-slider {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;
  }

  /* ============================================================ */
  /* TEAM TOGGLES DROPDOWN - IMPROVED STYLING */
  /* ============================================================ */

  /* Manage Team Popups button */
  .manage-team-toggles {
    padding: 12px 16px !important;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
    border-radius: 10px !important;
    border: 1px solid #cbd5e1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    transition: all 0.2s ease !important;
    text-decoration: none !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
  }

  .manage-team-toggles:hover {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%) !important;
    border-color: #94a3b8 !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;
    transform: translateY(-1px);
  }

  .manage-team-toggles:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
  }

  [data-theme="dark"] #userInfoPopup .manage-team-toggles {
    background: transparent !important;
  }

  [data-theme="dark"] #userInfoPopup .manage-team-toggles span,
  [data-theme="dark"] .manage-team-toggles span {
    color: white !important;
  }

  /* Dropdown panel */
  /* ============================================
   SCOPED STYLES FOR USER INFO POPUP - TEAM TOGGLES
   Scoped to #userInfoPopup to avoid conflicts
   ============================================ */

  #userInfoPopup .team-toggles-dropdown {
    animation: slideDown 0.3s ease-out !important;
    margin-top: 8px !important;
    background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%) !important;
    border-radius: 10px !important;
    padding: 16px !important;
    border: 1px solid #cbd5e1 !important;
    max-height: 110px !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.5) !important;
    width: 100% !important;
    position: relative !important;
    scrollbar-width: none !important;
  }

  [data-theme="dark"] #userInfoPopup .team-toggles-dropdown {
    background: #454545 !important;
    color: white !important;
  }

  [data-theme="dark"] #userInfoPopup .team-member-name {
    color: white !important;
  }

  [data-theme="dark"] #userInfoPopup .team-member-role {
    color: rgba(255, 255, 255, 0.7) !important;
  }

  [data-theme="dark"] #userInfoPopup .team-member-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
  }

  [data-theme="dark"] #userInfoPopup .toggle-lock-btn {
    border-color: rgba(255, 255, 255, 0.1) !important;
  }

  [data-theme="dark"] #userInfoPopup .toggle-lock-btn i,
  [data-theme="dark"] #userInfoPopup .toggle-lock-btn svg {
    color: rgba(255, 255, 255, 0.8) !important;
  }

  /* User Profile Dropdown - Dark Glass Mode (Migrated from dashboard_styles.css for global use) */
  [data-theme="dark"] .user-info-popup {
    background: rgba(40, 40, 40, 0.6) !important;
    backdrop-filter: blur(35px);
    -webkit-backdrop-filter: blur(35px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4) !important;
  }

  [data-theme="dark"] .user-info-header {
    background: rgba(255, 255, 255, 0.03) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }

  [data-theme="dark"] .user-info-stats {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }

  /* Stats Cards */
  [data-theme="dark"] .user-stat {
    background: rgba(255, 255, 255, 0.08) !important;
    border-radius: 8px;
    padding: 10px;
    border: 1px solid rgba(255, 255, 255, 0.05);
  }

  [data-theme="dark"] .user-stat .stat-value {
    color: #ffffff !important;
  }

  [data-theme="dark"] .user-stat .stat-label {
    color: rgba(255, 255, 255, 0.6) !important;
  }

  /* Actions/Logout */
  [data-theme="dark"] .user-info-actions {
    background: transparent !important;
  }

  [data-theme="dark"] .user-action {
    color: rgba(255, 255, 255, 0.8) !important;
  }

  [data-theme="dark"] .user-action:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
  }

  [data-theme="dark"] .user-action.logout {
    border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
    color: #ff6b6b !important;
  }

  /* SweetAlert2 Dark Mode Compatibility */
  [data-theme="dark"] .swal2-popup {
    background: #1a1a1a !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    border-radius: 16px !important;
  }

  [data-theme="dark"] .swal2-title,
  [data-theme="dark"] .swal2-html-container {
    color: #ffffff !important;
  }

  [data-theme="dark"] .swal2-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.7) !important;
  }

  [data-theme="dark"] .swal2-close {
    color: rgba(255, 255, 255, 0.7) !important;
  }

  [data-theme="dark"] .swal2-close:hover {
    color: #ffffff !important;
  }

  [data-theme="dark"] .swal2-icon {
    border-color: rgba(255, 255, 255, 0.2) !important;
  }

  [data-theme="dark"] p,
  [data-theme="dark"] span:not(.badge):not(.status-badge),
  [data-theme="dark"] label {
    color: #ffffff !important;
  }

  #userInfoPopup .manage-team-section {
    position: relative !important;
    width: 100% !important;
  }

  #userInfoPopup .team-toggles-dropdown {
    position: absolute !important;
    top: calc(100% + 4px) !important;
    left: 0 !important;
    width: 100% !important;
    background: white !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12), 0 4px 10px rgba(0, 0, 0, 0.08) !important;
    z-index: 1000 !important;
    padding: 12px !important;
    box-sizing: border-box !important;
    backdrop-filter: blur(10px) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    animation: slideDownFade 0.25s ease-out !important;
  }

  #userInfoPopup .no-results-message {
    padding: 20px;
    text-align: center;
    color: #64748b;
    font-size: 14px;
  }

  [data-theme="dark"] #userInfoPopup .no-results-message {
    color: rgba(255, 255, 255, 0.6);
  }

  @keyframes slideDownFade {
    from {
      opacity: 0;
      transform: translateY(-8px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  #userInfoPopup #team-members-list {
    width: 100% !important;
    max-height: 260px !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    display: block !important;
    padding-right: 4px !important;
  }

  #userInfoPopup #team-members-list::-webkit-scrollbar {
    width: 6px !important;
  }

  #userInfoPopup #team-members-list::-webkit-scrollbar-track {
    background: #f1f5f9 !important;
    border-radius: 10px !important;
  }

  #userInfoPopup #team-members-list::-webkit-scrollbar-thumb {
    background: #cbd5e1 !important;
    border-radius: 10px !important;
  }

  #userInfoPopup #team-members-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8 !important;
  }

  #userInfoPopup #team-toggle-arrow {
    transition: transform 0.3s ease !important;
  }

  #userInfoPopup #team-toggle-arrow.rotated {
    transform: rotate(180deg) !important;
  }

  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-10px);
      max-height: 0;
    }

    to {
      opacity: 1;
      transform: translateY(0);
      max-height: 400px;
    }
  }

  /* Team member cards */
  #userInfoPopup .team-member-card {
    background: white !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    padding: 12px 16px !important;
    margin-bottom: 10px !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
    overflow: visible !important;
    width: 100% !important;
    box-sizing: border-box !important;
  }

  #userInfoPopup .team-member-card:last-child {
    margin-bottom: 0 !important;
  }

  #userInfoPopup .team-member-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.06) !important;
    border-color: #cbd5e1 !important;
    transform: translateY(-1px);
  }

  #userInfoPopup .team-member-header {
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 12px !important;
    width: 100% !important;
    overflow: visible !important;
    flex-wrap: nowrap !important;
    box-sizing: border-box !important;
  }

  #userInfoPopup .team-member-info {
    flex: 1 !important;
    min-width: 0 !important;
    display: flex !important;
    flex-direction: row !important;
    gap: 6px !important;
    align-items: center !important;
    overflow: visible !important;
    flex-shrink: 1 !important;
    flex-wrap: nowrap !important;
  }

  #userInfoPopup .team-member-name {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #0f172a !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1.3 !important;
    display: inline-block !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    flex-shrink: 1 !important;
    max-width: 120px !important;
  }

  #userInfoPopup .team-member-role {
    font-size: 11px !important;
    color: #64748b !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    border-radius: 0 !important;
    display: inline-block !important;
    font-weight: 700 !important;
    border: none !important;
    width: auto !important;
    line-height: 1.3 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
  }

  #userInfoPopup .team-member-controls {
    display: flex !important;
    flex-direction: row !important;
    gap: 10px !important;
    align-items: center !important;
    justify-content: flex-end !important;
    flex-shrink: 0 !important;
    flex-wrap: nowrap !important;
    min-width: auto !important;
    overflow: visible !important;
    background: transparent !important;
    padding: 0 !important;
    border-radius: 4px !important;
    visibility: visible !important;
  }

  /* Team toggle switch (mini version) - BASE STYLES FOR ALL SCREENS */
  #userInfoPopup .team-toggle-switch,
  #userInfoPopup div.team-toggle-switch {
    position: relative !important;
    display: block !important;
    width: 44px !important;
    height: 24px !important;
    min-width: 44px !important;
    min-height: 24px !important;
    background: #E5E7EB !important;
    border-radius: 999px !important;
    cursor: pointer !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    flex-shrink: 0 !important;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    overflow: visible !important;
    opacity: 1 !important;
    visibility: visible !important;
    z-index: 10 !important;
    border: none !important;
    outline: none !important;
  }

  /* Knob element inside team toggle */
  #userInfoPopup .team-toggle-switch>span.knob,
  #userInfoPopup div.team-toggle-switch>span.knob {
    content: '' !important;
    position: absolute !important;
    top: 3px !important;
    left: 3px !important;
    width: 18px !important;
    height: 18px !important;
    border-radius: 50% !important;
    background: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2), 0 1px 2px rgba(0, 0, 0, 0.15) !important;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: block !important;
    pointer-events: none !important;
    z-index: 11 !important;
    visibility: visible !important;
  }

  #userInfoPopup .team-toggle-switch.active,
  #userInfoPopup div.team-toggle-switch.active {
    background: #3B82F6 !important;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3) !important;
  }

  #userInfoPopup .team-toggle-switch.active>span.knob,
  #userInfoPopup div.team-toggle-switch.active>span.knob {
    left: 23px !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15), 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  }

  #userInfoPopup .team-toggle-switch:hover,
  #userInfoPopup div.team-toggle-switch:hover {
    transform: scale(1.05) !important;
    box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.12), inset 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  }

  #userInfoPopup .team-toggle-switch.active:hover,
  #userInfoPopup div.team-toggle-switch.active:hover {
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15), 0 2px 8px rgba(59, 130, 246, 0.3) !important;
  }

  #userInfoPopup .team-toggle-switch:hover>span.knob,
  #userInfoPopup div.team-toggle-switch:hover>span.knob {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2), 0 2px 4px rgba(0, 0, 0, 0.15) !important;
  }

  #userInfoPopup .team-toggle-switch.updating,
  #userInfoPopup div.team-toggle-switch.updating {
    opacity: 0.6 !important;
    pointer-events: none !important;
  }

  /* Locked state for toggle */
  #userInfoPopup .team-toggle-switch.locked,
  #userInfoPopup div.team-toggle-switch.locked {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
  }

  /* Lock/Unlock button beside toggle */
  #userInfoPopup .toggle-lock-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    min-height: 32px !important;
    border-radius: 8px !important;
    background: transparent !important;
    border: 1px solid #e2e8f0 !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    margin-left: 0 !important;
    flex-shrink: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
  }

  #userInfoPopup .toggle-lock-btn:hover {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
  }

  #userInfoPopup .toggle-lock-btn.locked {
    background: #fef3c7 !important;
    border-color: #fbbf24 !important;
    color: #d97706 !important;
  }

  #userInfoPopup .toggle-lock-btn.locked:hover {
    background: #fde68a !important;
  }

  #userInfoPopup .toggle-lock-btn i,
  #userInfoPopup .toggle-lock-btn svg {
    font-size: 14px !important;
    width: 14px !important;
    height: 14px !important;
    color: #64748b !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  #userInfoPopup .toggle-lock-btn.locked i,
  #userInfoPopup .toggle-lock-btn.locked svg {
    color: #d97706 !important;
  }

  #userInfoPopup .toggle-lock-btn.updating {
    opacity: 0.6 !important;
    pointer-events: none !important;
  }

  /* EXPLICIT STYLES FOR LARGE SCREENS (520px and above) */
  @media screen and (min-width: 521px) {
    #userInfoPopup .team-member-card {
      padding: 12px 16px !important;
    }

    #userInfoPopup .team-member-header {
      gap: 12px !important;
    }

    #userInfoPopup .team-member-info {
      gap: 6px !important;
    }

    #userInfoPopup .team-toggle-switch,
    #userInfoPopup div.team-toggle-switch {
      width: 44px !important;
      height: 24px !important;
      min-width: 44px !important;
      min-height: 24px !important;
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
    }

    #userInfoPopup .team-toggle-switch>span.knob,
    #userInfoPopup div.team-toggle-switch>span.knob {
      width: 18px !important;
      height: 18px !important;
      top: 3px !important;
      display: block !important;
      visibility: visible !important;
    }

    #userInfoPopup .team-toggle-switch.active>span.knob,
    #userInfoPopup div.team-toggle-switch.active>span.knob {
      left: 23px !important;
    }

    #userInfoPopup .toggle-lock-btn {
      width: 32px !important;
      height: 32px !important;
      min-width: 32px !important;
      min-height: 32px !important;
      display: inline-flex !important;
      visibility: visible !important;
    }

    #userInfoPopup .toggle-lock-btn i,
    #userInfoPopup .toggle-lock-btn svg {
      font-size: 14px !important;
      width: 14px !important;
      height: 14px !important;
    }

    #userInfoPopup .team-member-name {
      font-size: 14px !important;
      max-width: 120px !important;
    }

    #userInfoPopup .team-member-role {
      font-size: 11px !important;
    }

    #userInfoPopup .team-member-controls {
      gap: 10px !important;
      display: flex !important;
      flex-direction: row !important;
      visibility: visible !important;
    }
  }

  /* Responsive Design for Mobile/Tablet */
  @media screen and (max-width: 768px) {
    #userInfoPopup .team-member-info {
      gap: 6px !important;
    }

    #userInfoPopup .team-member-name {
      font-size: 14px !important;
    }

    #userInfoPopup .team-member-role {
      font-size: 11px !important;
    }

    #userInfoPopup .team-member-controls {
      gap: 8px !important;
    }

    #userInfoPopup .team-toggle-switch,
    #userInfoPopup div.team-toggle-switch {
      width: 40px !important;
      height: 22px !important;
      min-width: 40px !important;
      min-height: 22px !important;
    }

    #userInfoPopup .team-toggle-switch>span.knob,
    #userInfoPopup div.team-toggle-switch>span.knob {
      width: 16px !important;
      height: 16px !important;
      top: 3px !important;
      left: 3px !important;
    }

    #userInfoPopup .team-toggle-switch.active>span.knob,
    #userInfoPopup div.team-toggle-switch.active>span.knob {
      left: 21px !important;
    }

    #userInfoPopup .toggle-lock-btn {
      width: 28px !important;
      height: 28px !important;
      min-width: 28px !important;
      min-height: 28px !important;
    }

    #userInfoPopup .toggle-lock-btn i,
    #userInfoPopup .toggle-lock-btn svg {
      font-size: 12px !important;
      width: 12px !important;
      height: 12px !important;
    }
  }

  @media screen and (max-width: 480px) {
    #userInfoPopup .team-member-card {
      padding: 12px 14px !important;
    }

    #userInfoPopup .team-member-header {
      gap: 10px !important;
    }

    #userInfoPopup .team-member-info {
      flex-direction: row !important;
      gap: 4px !important;
    }

    #userInfoPopup .team-member-name {
      font-size: 13px !important;
    }

    #userInfoPopup .team-member-role {
      font-size: 10px !important;
    }

    #userInfoPopup .team-member-controls {
      gap: 6px !important;
    }

    #userInfoPopup .team-toggle-switch,
    #userInfoPopup div.team-toggle-switch {
      width: 38px !important;
      height: 20px !important;
      min-width: 38px !important;
      min-height: 20px !important;
    }

    #userInfoPopup .team-toggle-switch>span.knob,
    #userInfoPopup div.team-toggle-switch>span.knob {
      width: 14px !important;
      height: 14px !important;
      top: 3px !important;
      left: 3px !important;
    }

    #userInfoPopup .team-toggle-switch.active>span.knob,
    #userInfoPopup div.team-toggle-switch.active>span.knob {
      left: 21px !important;
    }

    #userInfoPopup .toggle-lock-btn {
      width: 26px !important;
      height: 26px !important;
      min-width: 26px !important;
      min-height: 26px !important;
      border-radius: 6px !important;
    }

    #userInfoPopup .toggle-lock-btn i,
    #userInfoPopup .toggle-lock-btn svg {
      font-size: 11px !important;
      width: 11px !important;
      height: 11px !important;
    }
  }

  @media screen and (max-width: 425px) {
    #userInfoPopup .team-toggles-dropdown {
      padding: 12px !important;
    }

    #userInfoPopup .team-member-card {
      padding: 10px 12px !important;
      margin-bottom: 8px !important;
    }

    #userInfoPopup .team-member-header {
      gap: 8px !important;
    }

    #userInfoPopup .team-member-info {
      flex-direction: row !important;
      gap: 4px !important;
      min-width: 0 !important;
    }

    #userInfoPopup .team-member-name {
      font-size: 12px !important;
    }

    #userInfoPopup .team-member-role {
      font-size: 9px !important;
    }

    #userInfoPopup .team-member-controls {
      gap: 6px !important;
    }

    #userInfoPopup .team-toggle-switch,
    #userInfoPopup div.team-toggle-switch {
      width: 36px !important;
      height: 20px !important;
      min-width: 36px !important;
      min-height: 20px !important;
    }

    #userInfoPopup .team-toggle-switch>span.knob,
    #userInfoPopup div.team-toggle-switch>span.knob {
      width: 14px !important;
      height: 14px !important;
      top: 3px !important;
      left: 3px !important;
    }

    #userInfoPopup .team-toggle-switch.active>span.knob,
    #userInfoPopup div.team-toggle-switch.active>span.knob {
      left: 19px !important;
    }

    #userInfoPopup .toggle-lock-btn {
      width: 24px !important;
      height: 24px !important;
      min-width: 24px !important;
      min-height: 24px !important;
      border-radius: 6px !important;
    }

    #userInfoPopup .toggle-lock-btn i,
    #userInfoPopup .toggle-lock-btn svg {
      font-size: 10px !important;
      width: 10px !important;
      height: 10px !important;
    }
  }

  @media screen and (max-width: 375px) {
    #userInfoPopup .team-member-card {
      padding: 8px 10px !important;
    }

    #userInfoPopup .team-member-header {
      gap: 6px !important;
    }

    #userInfoPopup .team-member-info {
      gap: 3px !important;
    }

    #userInfoPopup .team-member-name {
      font-size: 11px !important;
    }

    #userInfoPopup .team-member-role {
      font-size: 9px !important;
    }

    #userInfoPopup .team-member-controls {
      gap: 5px !important;
    }
  }

  /* Lock indicator for user's own toggle */
  .overdue-lock-indicator {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 8px 12px !important;
    margin-top: 8px !important;
    background: #fef3c7 !important;
    border: 1px solid #fbbf24 !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    color: #92400e !important;
  }

  .overdue-lock-indicator i {
    color: #d97706 !important;
    font-size: 14px !important;
  }

  /* Loading/error states */
  #team-toggles-loading,
  #team-toggles-error {
    text-align: center !important;
    padding: 24px !important;
  }

  #team-toggles-loading i,
  #team-toggles-error i {
    font-size: 28px !important;
    margin-bottom: 8px !important;
  }

  #team-toggles-loading i {
    color: #3b82f6 !important;
  }

  #team-toggles-error i {
    color: #ef4444 !important;
  }

  #team-toggles-loading p,
  #team-toggles-error p {
    margin-top: 10px !important;
    color: #64748b !important;
    font-size: 13px !important;
    font-weight: 500 !important;
  }

  /* Enhanced scrollbar */
  /* Chevron arrow animation */
  #team-toggle-arrow {
    transition: transform 0.3s ease !important;
  }

  #team-toggle-arrow.rotated {
    transform: rotate(180deg) !important;
  }


  [data-push-widget="mnt"] .mnt-slider::after {
    width: 20px !important;
    height: 20px !important;
    top: 3px !important;
  }


  /* Embedded mode (when loaded inside superadmin iframe with ?embed=1) */
  .embedded-main {
    margin-left: 0 !important;
    max-width: 100% !important;
    padding: 0 !important;
    background: transparent !important;
  }

  .main-content.embedded-main {
    background: transparent !important;
    padding: 0 !important;
  }

  .content.embedded-main {
    padding: 0 !important;
  }

  /* Ensure header and dropdowns are visible in embed mode */
  .embedded-main .header {
    display: flex !important;
    visibility: visible !important;
    justify-content: center !important;
  }

  .embedded-main .header-center {
    display: flex !important;
    visibility: visible !important;
    justify-content: center !important;
    flex: 1 !important;
    align-items: center !important;
    gap: 15px !important;
  }

  .embedded-main .month-filter,
  .embedded-main .header-name-select {
    visibility: visible !important;
  }

  .embedded-main .header-left {
    display: none !important;
  }

  .embedded-main .header-right {
    display: none !important;
  }
</style>
<?php $isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1'; ?>
<?php if (!$isEmbed): ?>
  <!-- Enhanced Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo-container">
        <!-- Full logo (shown when expanded) -->
        <img class="logo full-logo" src="assets/dataimage/hlogo.png" alt="SHI Logo">
        <!-- Icon logo (shown when collapsed) -->
        <img class="logo icon-logo" src="assets/dataimage/mecntec-icon.png" alt="Mecntec Icon">
      </div>
      <button class="sidebar-user-profile-trigger" id="sidebarUserProfileBtn" type="button" aria-label="Profile">
        <img class="user-avatar-small" src="assets/dataimage/ayu.jpg" alt="">
      </button>
      <!-- Add the toggle button (will only show on desktop) -->
      <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fa-solid fa-angles-left"></i>
      </button>
    </div>

    <div class="nav-section">
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="/incentiveapp_integration/userlogin1/userlogin6/dashboard"
            class="nav-link <?php echo ($_SERVER['PHP_SELF'] == 'dashboard.php' || basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>"
            data-page="dashboard">
            <div class="nav-icon">
              <i class="fas fa-th-large"></i>
            </div>
            <span class="nav-text">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="/incentiveapp_integration/userlogin1/userlogin6/user_lead"
            class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_lead.php') ? 'active' : ''; ?>"
            data-page="leads">
            <div class="nav-icon">
              <i class="fas fa-users"></i>
            </div>
            <span class="nav-text">Leads</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="/incentiveapp_integration/userlogin1/userlogin6/user_eoi"
            class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_eoi.php') ? 'active' : ''; ?>"
            data-page="eoi">
            <div class="nav-icon">
              <i class="fas fa-file-contract"></i>
            </div>
            <span class="nav-text">EOI</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="/incentiveapp_integration/userlogin1/userlogin6/user_booking"
            class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_booking.php') ? 'active' : ''; ?>"
            data-page="booking">
            <div class="nav-icon">
              <i class="fas fa-calendar-check"></i>
            </div>
            <span class="nav-text">Bookings</span>
          </a>
        </li>

        <!-- Profile Module -->
        <li class="nav-item">
          <a href="/incentiveapp_integration/userlogin1/userlogin6/profile"
            class="nav-link <?php echo $isProfilePage ? 'active' : ''; ?>"
            data-page="profile">
            <div class="nav-icon">
              <i class="fas fa-user-circle"></i>
            </div>
            <span class="nav-text">Profile</span>
          </a>
        </li>

      </ul>

      <ul class="nav-menu">
        <li class="nav-item theme-toggle-item">
          <a href="#" class="nav-link" id="themeToggleLink">
            <div class="nav-icon">
              <i class="fas fa-moon" id="themeIcon"></i>
            </div>
            <span class="nav-text">Dark Mode</span>
            <label class="theme-switch">
              <input type="checkbox" id="themeToggle">
              <span class="slider"></span>
            </label>
          </a>
        </li>

        <li class="nav-item">
          <a href="logout.php" class="nav-link" data-page="logout" style="color: #dc3545;">
            <div class="nav-icon">
              <i class="fas fa-sign-out-alt"></i>
            </div>
            <span class="nav-text">Logout</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-footer">
    </div>
  </aside>
<?php endif; ?>

<main
  class="main-content <?php echo ($isLeadsPage || $isEOIPage) ? 'leads-main-content' : ''; ?> <?php echo $isEmbed ? 'embedded-main' : ''; ?>"
  id="mainContent">
  <header class="header leads-header" data-current-role="<?php echo $currentRoleNormalized; ?>"
    data-current-level="<?php echo (int) $currentRoleLevel; ?>">
    <div class="header-left">
      <!-- Mobile logo for bookings page -->
      <?php if ($isBookingPage): ?>
        <div class="mobile-logo" style="display: none;">
          <img src="assets/dataimage/hlogo.png" alt="SHI Logo" style="height: 30px;">
        </div>
      <?php endif; ?>

      <?php if (!$isLeadsPage && !$isEOIPage && !$isBookingPage && !$isEmbed && empty($isProfilePage)): ?>
        <?php
        $displayName = htmlspecialchars($currentUsername);
        $maxLength = 15; // set max length before "..."
        if (mb_strlen($displayName) > $maxLength) {
          $displayName = mb_substr($displayName, 0, $maxLength) . '...';
        }
        ?>
        <div class="welcome-text welcome-1">
          Hello, <?php echo $displayName; ?> 👋
        </div>
      <?php endif; ?>
      <?php if (!$isEmbed): ?>
        <div class="page-title-row<?php echo $isBookingPage ? ' bookings-title-row' : ''; ?>" <?php echo $isBookingPage ? 'style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;"' : ''; ?>>
          <h1 class="page-title leads-title" style="margin:0;">
            <?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?>
          </h1>
          <?php if ($isBookingPage && $canShowBookingYearCityFilters): ?>
            <form class="fy-selector" method="get" action="user_booking.php"
              style="display:flex;align-items:center;gap:8px;">
              <label for="financialYearSelect" style="position:absolute;left:-9999px;">Financial Year</label>
              <select id="financialYearSelect" name="fy" class="form-select form-select-sm"
                style="padding:6px 10px 6px 10px;" onchange="submitFormWithBothParams()">
                <?php foreach ($financialYearOptions ?? [] as $fyOption): ?>
                  <option value="<?php echo htmlspecialchars($fyOption); ?>" <?php echo ($fyOption === ($selectedFinancialYear ?? '')) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($fyOption); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <label for="citySelect" style="position:absolute;left:-9999px;">City Filter</label>
              <select id="citySelect" name="city" class="form-select form-select-sm" style="padding:6px 10px 6px 10px;"
                onchange="submitFormWithBothParams()">
                <option value="" <?php echo empty($selectedCity) ? 'selected' : ''; ?>>All Cities</option>
                <?php foreach ($cityOptions ?? [] as $cityOption): ?>
                  <option value="<?php echo htmlspecialchars($cityOption); ?>" <?php echo ($cityOption === ($selectedCity ?? '')) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cityOption); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (!empty($filterUser)): ?>
                <input type="hidden" name="filterUser" value="<?php echo htmlspecialchars($filterUser); ?>">
              <?php endif; ?>
              <?php if (!empty($managerView)): ?>
                <input type="hidden" name="managerView" value="<?php echo htmlspecialchars($managerView); ?>">
              <?php endif; ?>
              <noscript>
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
              </noscript>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($isLeadsPage): ?>
        <div class="tab-row-wrapper">
          <button class="badge-arrow left" data-target=".tab-row">
            <i class="fas fa-chevron-left"></i>
          </button>
          <div class="tab-row">
            <button class="filter-btn server-true accessbtn " id="myLeads"><i class="fa-solid fa-user"></i> My Leads <span
                class="count">0</span></button>
            <button class="filter-btn server-true accessbtn booked" id="bookedLeads"><i class="fas fa-book"></i> Booked
              <span class="count">0</span></button>
            <button class="filter-btn server-true accessbtn" id="today_collection"><i class="fas fa-calendar-day"></i>
              Today FollowUp's <span class="count">0</span></button>
            <button class="filter-btn server-true accessbtn dropped" id="droppedLeads"><i class="fas fa-tint-slash"></i>
              Dropped <span class="count">0</span></button>
            <button class="filter-btn server-true accessbtn ads" id="paidAds"><i class="fab fa-google"
                style="color: red;"></i><i class="fab fa-facebook-f" style="color: blue;"></i>Ads <span
                class="count">0</span></button>
            <button class="filter-btn server-true accessbtn" id="SHI_D"><i class="fas fa-database"></i>SHI-D<span
                class="count">0</span></button>
          </div>
          <button class="badge-arrow right" data-target=".tab-row">
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      <?php elseif ($isEOIPage): ?>
        <div class="tab-row-wrapper">
          <button class="badge-arrow left" data-target="#eoiHierarchyContainer">
            <i class="fas fa-chevron-left"></i>
          </button>
          <div class="tab-row" id="eoiHierarchyContainer">
            <!-- EOI User Hierarchy will be loaded here dynamically -->
            <div class="hierarchy-loading" id="headerHierarchyLoading" style="display: none;">
              <i class="fas fa-spinner fa-spin"></i> Loading users...
            </div>
          </div>
          <button class="badge-arrow right" data-target="#eoiHierarchyContainer">
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$isEmbed): ?>
      <!-- Notification popup and user info popup only shown when not embedded -->
      <div class="notification-popup" id="notificationPopup">
        <div class="notification-header">
          <h3>Notifications</h3>
          <button class="mark-all-read">Mark all as read</button>
          <button class="popup-close-btn" id="notificationCloseBtn">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="notification-search-wrapper"
          style="padding:0.6rem 1rem;border-bottom:1px solid var(--border-color,#e2e8f0);">
          <input type="text" id="notifSearchInput" placeholder="Search notifications…"
            style="width:100%;padding:0.45rem 0.75rem;border:1px solid var(--border-color,#ddd);border-radius:8px;font-size:0.88rem;outline:none;">
        </div>
        <div class="notification-list" id="notifListEl">
          <!-- notification items will be injected here by JS -->
          <div class="notification-item placeholder">
            <div class="notification-icon"><i class="fas fa-bell"></i></div>
            <div class="notification-content">
              <p>Loading notifications…</p>
              <span class="notification-time">—</span>
            </div>
          </div>
        </div>
      </div>
      <div class="user-info-popup" id="userInfoPopup">
        <!-- Add X button -->
        <button class="popup-close-btn" id="userInfoCloseBtn">
          <i class="fas fa-times"></i>
        </button>
        <div class="user-info-header">
          <div class="user-avatar-large">
            <img src="assets/dataimage/ayu.jpg" alt="user">
          </div>
          <div class="user-details">
            <h3><?php echo htmlspecialchars($currentUsername); ?></h3>
            <p class="user-role">
              <?php
              // Convert role to a nice display format
              echo htmlspecialchars($currentRoleDisplay);
              ?>
            </p>
            <p class="user-email"><?php echo htmlspecialchars($currentEmail); ?></p>
          </div>
        </div>
        <div class="user-info-stats">
          <div class="user-stat">
            <span class="stat-value" id="user-total-leads">0</span>
            <span class="stat-label">Total<br> Leads</span>
          </div>
          <div class="user-stat">
            <span class="stat-value" id="user-total-bookings">0</span>
            <span class="stat-label">Total<br> Bookings</span>
          </div>
          <div class="user-stat">
            <span class="stat-value" id="user-total-revenue">₹0</span>
            <span class="stat-label">Total<br> Agreement</span>
          </div>
        </div>
        <div class="user-info-actions">
          <!-- Manage Team Toggles Dropdown (for managers/higher only) -->
          <?php if (in_array($currentRoleNormalized, ['promoter', 'business_head', 'manager', 'team_lead'])): ?>
            <div class="manage-team-section" style="margin-bottom: 12px;">
              <a href="#" class="user-action manage-team-toggles" id="manage-team-toggles-btn">
                <div style="display: flex; align-items: center; width: 100%;">
                  <div id="manage-team-title-container" style="display: flex; align-items: center; width: 100%;">
                    <i class="fas fa-users-cog" style="margin-right: 8px; color: #3b82f6;"></i>
                    <span style="font-size: 14px; font-weight: 600;">Manage Team Popups</span>
                  </div>
                  <input type="text" id="team-search-input" placeholder="Search team members..."
                    style="display: none; width: 100%; border: none; background: transparent; font-size: 14px; font-weight: 600; outline: none; padding: 0; color: inherit;">
                </div>
                <i class="fas fa-chevron-down" id="team-toggle-arrow" style="font-size: 13px; color: #64748b;"></i>
              </a>

              <!-- Expandable dropdown panel -->
              <div id="team-toggles-dropdown" class="team-toggles-dropdown" style="display: none;">
                <div id="team-toggles-loading">
                  <i class="fas fa-spinner fa-spin"></i>
                  <p>Loading team settings...</p>
                </div>

                <div id="team-toggles-content" style="display: none;">
                  <div id="team-members-list">
                    <!-- Team members will be loaded here -->
                  </div>
                </div>

                <div id="team-toggles-error" style="display: none;">
                  <i class="fas fa-exclamation-triangle"></i>
                  <p id="team-toggles-error-message">Failed to load team settings</p>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: nowrap;">
            <!-- Container expected by the script -->
            <div id="push-container" data-push-widget="mnt" role="region" aria-label="Push notification settings"
              style="flex: 1; min-width: 0;">
              <!-- Card element used previously (keeps semantics for script and screen readers) -->
              <div class="mnt-card" id="mnt-push-card" aria-hidden="false" style="margin: 0;">
                <div class="mnt-meta" style="display: none;">
                  <!-- Title kept for accessibility (script doesn't require this but it's semantic) -->
                  <label for="push-toggle" class="mnt-title">Push Notifications</label>
                  <!-- The script expects an element with id="push-note" to show status text -->
                  <div id="push-note" class="mnt-subtitle">Enable to receive updates and alerts.</div>
                </div>

                <!-- Use your new visual label but keep input id "push-toggle" -->
                <label class="user-action notification-toggle mnt-switch" aria-hidden="false"
                  style="margin: 0; padding: 8px 10px !important; border-radius: 12px !important; display: flex !important; align-items: center !important; justify-content: center !important; width: 100% !important; box-sizing: border-box; gap: 8px !important;">
                  <i class="fas fa-bell" aria-hidden="true"
                    style="flex-shrink: 0; color: #3b82f6; font-size: 18px;"></i>
                 <span class="notification-label"
                    style="font-size: 11px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Notifications</span>

                  <!-- IMPORTANT: keep the id and type the script expects -->
                  <input id="push-toggle" class="mnt-input" type="checkbox" aria-label="Enable push notifications"
                    style="display: none;" />

                  <!-- keep your new slider element for styling -->
                  <span class="toggle-slider mnt-slider" aria-hidden="true" style="flex-shrink: 0;"></span>
                </label>
              </div>
            </div>

            <!-- Overdue Popup Toggle -->
            <div class="overdue-toggle-container" style="flex: 1; padding: 0; min-width: 0;">
              <label class="overdue-toggle-switch user-action"
                style="display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; padding: 8px 10px !important; border-radius: 12px !important; width: 100% !important; margin: 0; box-sizing: border-box; gap: 8px !important;">
                <i class="fas fa-clock" style="font-size: 18px; color: #3B82F6; flex-shrink: 0;"></i>
                <span class="overdue-toggle-label"
                  style="font-size: 11px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Overdue
                  Popups</span>
                <input id="overdue-popup-toggle" type="checkbox" checked
                  style="position: absolute !important; width: 0 !important; height: 0 !important; opacity: 0 !important; pointer-events: none !important; visibility: hidden !important;" />
                <span class="overdue-toggle-slider"
                  style="position: relative; display: inline-block; width: 34px; height: 20px; background: #3B82F6; border-radius: 999px; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3); flex-shrink: 0;">
                  <span
                    style="content: ''; position: absolute; top: 2px; left: 16px; width: 16px; height: 16px; border-radius: 50%; background: #ffffff; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); transition: all 0.3s; display: block;"
                    id="overdue-slider-circle"></span>
                </span>
              </label>
            </div>
          </div>

          <div style="display: flex; align-items: center; gap: 8px;">
            <a href="logout.php" class="user-action logout" style="flex: 1;">
              <i class="fas fa-sign-out-alt"></i>
              Logout
            </a>
            <!-- Attendance Button -->
            <a href="<?php echo htmlspecialchars($attendance_url); ?>"
              id="attendance-btn"
              class="user-action"
              title="Mark Attendance"
              style="display: inline-flex !important;
                     align-items: center !important;
                     justify-content: center !important;
                     gap: 6px !important;
                     padding: 8px 14px !important;
                     border-radius: 999px !important;
                     background: linear-gradient(135deg, #10b981, #059669) !important;
                     color: #fff !important;
                     font-size: 12px !important;
                     font-weight: 700 !important;
                     white-space: nowrap;
                     text-decoration: none !important;
                     box-shadow: 0 2px 8px rgba(16,185,129,0.35) !important;
                     border: none !important;
                     cursor: pointer;
                     flex-shrink: 0;
                     transition: filter 0.18s, box-shadow 0.18s, transform 0.1s !important;"
              onmouseover="this.style.filter='brightness(1.1)';this.style.boxShadow='0 4px 14px rgba(16,185,129,0.5)';"
              onmouseout="this.style.filter='';this.style.boxShadow='0 2px 8px rgba(16,185,129,0.35)';"
              onmousedown="this.style.transform='scale(0.96)';"
              onmouseup="this.style.transform='';">
              <i class="fas fa-fingerprint" style="font-size: 14px; flex-shrink: 0;"></i>
              <span id="attendance-btn-label">Punch In</span>
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <div class="header-center">


      <?php if (
        !$isLeadsPage &&
        !$isEOIPage &&
        !$isBookingPage &&
        in_array($currentRoleNormalized, ['promoter', 'business_head', 'manager', 'team_lead'])
      ): ?>
        <div class="header-name-select desktop-only-1250">
          <div class="searchable-select" id="searchableNameSelect">
            <div class="searchable-select-container">
              <input type="text" id="nameSearchInput" class="searchable-select-input"
                placeholder="<?php echo $isEmbed ? 'koushik' : 'Search or select a name...'; ?>"
                value="<?php echo $isEmbed ? 'koushik' : ''; ?>" readonly>
              <div class="searchable-select-arrow">
                <i class="fas fa-chevron-down"></i>
              </div>
            </div>
            <div class="searchable-select-dropdown" id="nameDropdownList">
              <div class="searchable-select-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading users...
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!$isLeadsPage && !$isEOIPage && !$isBookingPage && $currentPage !== 'bookings_superadmin.php' && empty($isProfilePage)): ?>
        <!-- Enhanced Month/Year Dropdown (Dashboard only) -->
        <div class="month-filter" style="justify-content: center;">
          <!-- Desktop View (Custom Dropdowns) -->
          <div class="month-filter-select" id="monthYearSelectors" style="display: inline-flex;">
            <!-- Month Custom Dropdown -->
            <div class="custom-dropdown" id="monthDropdown">
              <div class="custom-dropdown-selected" id="monthSelected" style="
    display: flex;
    min-width: 100px;
    justify-content: space-between;
    align-items: center;
">
                <?php echo date("F"); ?>
                <i class="fas fa-chevron-down"></i>
              </div>
              <div class="custom-dropdown-options" id="monthOptions">
                <?php
                for ($m = 1; $m <= 12; $m++) {
                  $monthName = date("F", mktime(0, 0, 0, $m, 1));
                  $isSelected = ($m == date('n')) ? "selected" : "";
                  echo "<div class='custom-dropdown-option $isSelected' data-value='$m'>$monthName</div>";
                }
                ?>
                <div class="custom-dropdown-option" data-value="custom">Custom</div>
                <div class="custom-dropdown-option" data-value="fy">FY</div>
              </div>
            </div>
            <input type="hidden" id="monthSelect" value="<?php echo date('n'); ?>">

            <!-- Year Custom Dropdown -->
            <div class="custom-dropdown" id="yearDropdown">
              <div class="custom-dropdown-selected" id="yearSelected" style="
    display: flex;
    min-width: 80px;
    justify-content: space-between;
    align-items: center;
">
                <?php echo date("Y"); ?>
                <i class="fas fa-chevron-down"></i>
              </div>
              <div class="custom-dropdown-options" id="yearOptions">
                <?php
                $currentYear = date("Y");
                for ($y = $currentYear; $y >= $currentYear - 3; $y--) {
                  $isSelected = ($y == $currentYear) ? "selected" : "";
                  echo "<div class='custom-dropdown-option $isSelected' data-value='$y'>$y</div>";
                }
                ?>
              </div>
            </div>
            <input type="hidden" id="yearSelect" value="<?php echo date('Y'); ?>">
          </div>
          

          <!-- Date Range Picker (Hidden Initially) -->
          <div class="custom-range-select " id="dateRangeFilter"
            style="display: none; align-items: center; gap: 4px; flex-wrap: wrap; overflow-x: hidden; padding-bottom: 2px; max-width: 100%; justify-content: center;">
            <div class="date-overlay-wrapper"
              style="position: relative; display: inline-flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 12px; border: 1px solid var(--border-color, #ccc); overflow: hidden; background-color: var(--card-bg, #daeaea); color: var(--text-dark, #333); box-sizing: border-box; min-width: 120px; max-width: 140px;">
              <span id="display_startDate"
                style="font-size: 13px; font-family: inherit; pointer-events: none; color: inherit; white-space: nowrap; flex-shrink: 0; margin-right: 6px;">dd-mm-yy</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"
                style="pointer-events: none; opacity: 0.7; color: inherit; flex-shrink: 0;">
                <path
                  d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z" />
                <path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z" />
              </svg>
              <input type="date" id="startDate"
                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; border: none; margin: 0; padding: 0; appearance: none; -webkit-appearance: none; z-index: 2;">
            </div>
            <span style="font-size: 13px; font-weight: 500; margin: 0 2px;">To</span>
            <div class="date-overlay-wrapper"
              style="position: relative; display: inline-flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 12px; border: 1px solid var(--border-color, #ccc); overflow: hidden; background-color: var(--card-bg, #daeaea); color: var(--text-dark, #333); box-sizing: border-box; min-width: 120px; max-width: 140px;">
              <span id="display_endDate"
                style="font-size: 13px; font-family: inherit; pointer-events: none; color: inherit; white-space: nowrap; flex-shrink: 0; margin-right: 6px;">dd-mm-yy</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"
                style="pointer-events: none; opacity: 0.7; color: inherit; flex-shrink: 0;">
                <path
                  d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z" />
                <path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z" />
              </svg>
              <input type="date" id="endDate"
                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; border: none; margin: 0; padding: 0; appearance: none; -webkit-appearance: none; z-index: 2;">
            </div>


            <button id="cancelRangeBtn">X</button>
          </div>
        </div>

      <?php endif; ?>
    </div>


    <?php if (!$isEmbed): ?>
      <div class="header-right">

        <!--<button class="header-btn tooltip" id="notificationBtn" data-tooltip="Notifications">-->
        <!--  <i class="fas fa-bell"></i>-->
        <!--  <div class="notification-badge"></div>-->
        <!--</button>-->
        <button class="header-btn tooltip" id="notificationBtn" data-tooltip="Notifications">
          <span id="notifBadge" style="display:none"></span>
          <i class="fas fa-bell"></i>
          <div class="notification-badge"></div>
        </button>

        <button class="header-btn tooltip" id="mobileMenuBtn" data-tooltip="Menu" style="display: none;">
          <i class="fas fa-bars"></i>
        </button>

        <div class="user-profile-sidebar">
          <img class="user-avatar-small" src="assets/dataimage/ayu.jpg" alt="">
          <div class="user-info">
            <?php
            $displayName = htmlspecialchars($currentUsername);
            $maxLength = 15; // max characters before adding ...
            if (mb_strlen($displayName) > $maxLength) {
              $displayName = mb_substr($displayName, 0, $maxLength) . '...';
            }
            ?>
            <div class="user-name-small">
              <!-- <?php echo $displayName; ?> -->
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </header>

  <!-- ===== Attendance Iframe Popup ===== -->
  <style>
    #attendancePopupOverlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.55);
      z-index: 99999;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
      box-sizing: border-box;
    }
    #attendancePopupOverlay.open { display: flex; }
    .attendance-popup-box {
      background: #fff;
      border-radius: 14px;
      width: min(960px, 95vw);
      height: 90vh;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      box-shadow: 0 24px 64px rgba(0,0,0,0.38);
      overflow: hidden;
      animation: attendancePopIn 0.22s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes attendancePopIn {
      from { opacity:0; transform: scale(0.92) translateY(20px); }
      to   { opacity:1; transform: scale(1)   translateY(0); }
    }
    .attendance-popup-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      border-bottom: 1px solid #e2e8f0;
      background: #f8fafc;
      flex-shrink: 0;
    }
    .attendance-popup-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 15px;
      font-weight: 700;
      color: #1e293b;
    }
    .attendance-popup-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    #attendanceNewTabBtn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      background: #3b82f6;
      color: #fff;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.18s;
    }
    #attendanceNewTabBtn:hover { background: #2563eb; }
    #attendancePopupClose {
      width: 32px;
      height: 32px;
      border: none;
      background: rgba(0,0,0,0.06);
      border-radius: 8px;
      cursor: pointer;
      font-size: 20px;
      color: #64748b;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.18s, color 0.18s;
      line-height: 1;
    }
    #attendancePopupClose:hover { background: rgba(239,68,68,0.12); color: #ef4444; }
    #attendanceIframe {
      flex: 1;
      border: none;
      width: 100%;
      min-height: 0;
      height: 100%;
    }
    /* Dark mode */
    [data-theme="dark"] .attendance-popup-box {
      background: rgba(22,23,28,0.97);
      border: 1px solid rgba(255,255,255,0.08);
    }
    [data-theme="dark"] .attendance-popup-header {
      background: rgba(30,31,36,0.98);
      border-bottom-color: rgba(255,255,255,0.08);
    }
    [data-theme="dark"] .attendance-popup-title { color: #fff; }
    [data-theme="dark"] #attendancePopupClose {
      background: rgba(255,255,255,0.08);
      color: #e0e0e0;
    }
    [data-theme="dark"] #attendancePopupClose:hover {
      background: rgba(239,68,68,0.2);
      color: #ff4444;
    }
  </style>

  <div id="attendancePopupOverlay">
    <div class="attendance-popup-box">
      <div class="attendance-popup-header">
        <div class="attendance-popup-title">
          <i class="fas fa-fingerprint" style="color:#10b981;"></i>
          Attendance
        </div>
        <div class="attendance-popup-actions">
          <a id="attendanceNewTabBtn" href="#" target="_blank">
            <i class="fas fa-external-link-alt" style="font-size:11px;"></i>
            New Tab
          </a>
          <button id="attendancePopupClose" title="Close">&times;</button>
        </div>
      </div>
      <iframe id="attendanceIframe" src="about:blank" allow="geolocation" loading="lazy"></iframe>
    </div>
  </div>

  <script>
    (function () {
      var attendanceUrl = <?php echo json_encode($attendance_url ?? ''); ?>;
      var overlay   = document.getElementById('attendancePopupOverlay');
      var iframe    = document.getElementById('attendanceIframe');
      var closeBtn  = document.getElementById('attendancePopupClose');
      var newTabBtn = document.getElementById('attendanceNewTabBtn');
      var btn       = document.getElementById('attendance-btn');
      var btnLabel  = document.getElementById('attendance-btn-label');

      // Track whether a punch button was clicked inside the iframe
      var punchClicked = false;

      // Update the label shown on the Attendance button in the profile popup
      function setProfileLabel(text) {
        if (btnLabel) btnLabel.textContent = text;
      }

      // Read iframe DOM (same-origin) to find punch buttons, attach click listeners
      // and detect current punch state to update the profile button label.
      function attachIframeListeners() {
        try {
          var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
          if (!doc || !doc.body) return;

          var allBtns = Array.prototype.slice.call(doc.querySelectorAll('button, [class*="punch"], [class*="Punch"]'));
          // Also include any anchor/div acting as a punch button
          var punchBtns = allBtns.filter(function (b) {
            return /punch/i.test(b.textContent.trim());
          });

          // Update profile label based on what the iframe is currently showing
          var hasPunchOut = punchBtns.some(function (b) { return /punch\s*out/i.test(b.textContent); });
          var hasPunchIn  = punchBtns.some(function (b) { return /punch\s*in/i.test(b.textContent); });
          if (hasPunchOut) setProfileLabel('Punch Out');
          else if (hasPunchIn) setProfileLabel('Punch In');

          // Listen for a punch button click inside the iframe
          // Close popup directly after click (app may update state client-side,
          // not necessarily triggering a full page reload)
          punchBtns.forEach(function (b) {
            var isPunchOut = /punch\s*out/i.test(b.textContent);
            b.addEventListener('click', function () {
              punchClicked = true;
              // Flip label immediately so profile reflects the new state
              setProfileLabel(isPunchOut ? 'Punch In' : 'Punch Out');
              // Close popup after a short delay (lets the punch animation play)
              setTimeout(function () {
                closePopup();
              }, 1500);
            }, { once: true });
          });
        } catch (e) {
          // silently ignore – cross-origin or doc not ready
        }
      }

      // Every time the iframe (re)loads:
      iframe.addEventListener('load', function () {
        if (punchClicked) {
          // A punch was performed and the page just reloaded → close popup + flip label
          var nextLabel = (btnLabel && btnLabel.textContent.trim() === 'Punch In') ? 'Punch Out' : 'Punch In';
          punchClicked = false;
          setTimeout(function () {
            setProfileLabel(nextLabel);
            closePopup();
          }, 600);
        } else {
          // First (normal) load – give React/Vue a moment to paint, then inspect
          setTimeout(attachIframeListeners, 500);
        }
      });

      function openPopup() {
        if (!overlay) return;

        // Auto-close the profile popup when attendance opens
        var userInfoCloseBtn = document.getElementById('userInfoCloseBtn');
        if (userInfoCloseBtn) {
          userInfoCloseBtn.click();
        } else {
          var userInfoPopup = document.getElementById('userInfoPopup');
          if (userInfoPopup) userInfoPopup.style.display = 'none';
        }

        punchClicked = false;
        if (newTabBtn) newTabBtn.href = attendanceUrl;
        if (iframe)   iframe.src = attendanceUrl;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
      }

      function closePopup() {
        if (!overlay) return;
        overlay.classList.remove('open');
        if (iframe) iframe.src = 'about:blank';
        document.body.style.overflow = '';
      }

      if (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          openPopup();
        });
      }

      if (closeBtn) closeBtn.addEventListener('click', closePopup);

      if (overlay) {
        overlay.addEventListener('click', function (e) {
          if (e.target === overlay) closePopup();
        });
      }

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && overlay.classList.contains('open')) {
          closePopup();
        }
      });
    })();
  </script>


  <script>
    window.HEADER_PAGE_CONFIG = {
      notificationApiBase: './notifications/',
      notificationViewAllUrl: '/notifications/view-all-notifications.php',
      currentUserCode: <?php echo json_encode((string) ($_SESSION['tablename'] ?? '')); ?>,
      fyFilterUser: <?php echo json_encode((string) ($filterUser ?? '')); ?>,
      fyManagerView: <?php echo json_encode((string) ($managerView ?? '')); ?>
    };
  </script>
  <script src="assets/js/header_extracted.js?v=20260415v1"></script>
<!-- MNT PUSH:
       The old manual PushManager code was removed from here.
       CRM push login/logout/token flow now lives in /crm-push-init.js.
       Keep only the #push-toggle and #push-note markup above so the shared module can bind it once. -->



  