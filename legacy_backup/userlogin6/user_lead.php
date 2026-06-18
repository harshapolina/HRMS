<?php
/**
 * ============================================================================
 * LEADS PAGE - FRONTEND TEMPLATE
 * ============================================================================
 * 
 * ORGANIZATION STRUCTURE:
 * 
 * 1. CONFIGURATION & INITIALIZATION
 *    - Session, Database connection
 *    - Manager view setup
 * 
 * 2. ROLE & HIERARCHY FUNCTIONS
 *    - Role normalization
 *    - User level management
 * 
 * 3. HTML STRUCTURE
 *    - Filter buttons (11 tag buttons)
 *    - DataTable container
 *    - Modals (Add, Edit, Assign, etc.)
 * 
 * 4. JAVASCRIPT INITIALIZATION
 *    - Global variables
 *    - Manager toggle setup
 *    - Filter auto-apply
 * 
 * ============================================================================
 * NOTE: This file contains the HTML structure only.
 * All JavaScript logic is in assets/js/final_lead.js
 * All backend API logic is in update_status.php
 * ============================================================================
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================================
// SECTION 1: CONFIGURATION & INITIALIZATION
// ============================================================================

include 'config.php';

// Check for manager view parameters
$isManagerView = isset($_GET['managerView']) && $_GET['managerView'] === 'true';
$filterUser = isset($_GET['filterUser']) ? $_GET['filterUser'] : '';

// If manager view is requested, get the username from the tablename
$userName = '';
if ($isManagerView && !empty($filterUser)) {
    try {
        $config = new Config();
        $conn = $config->getConnection();

        $stmt = $conn->prepare("SELECT username FROM accounts_copy WHERE tablename = :tablename");
        $stmt->bindParam(':tablename', $filterUser);
        $stmt->execute();
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow) {
            $userName = $userRow['username'];
        }
    } catch (Exception $e) {
        error_log("Error fetching username: " . $e->getMessage());
    }
}

// Store the parameters in session for JavaScript access
$_SESSION['manager_view_params'] = [
    'isManagerView' => $isManagerView,
    'filterUser' => $filterUser,
    'userName' => $userName
];

// ============================================================================
// SECTION 2: ROLE & HIERARCHY FUNCTIONS
// ============================================================================

// Add hierarchy role normalization
function normalize_role_php($rawType)
{
    $s = strtolower(trim((string) $rawType));
    switch ($s) {
        case 'ceo':
        case 'promoter':
            return 'promoter';
        case 'business_head':
        case 'business head':
        case 'bh':
            return 'business_head';
        case 'manager':
        case 'm':
            return 'manager';
        case 'team_lead':
        case 'team lead':
        case 'tl':
        case 'team_leader':
            return 'team_lead';
        case 'sales_executive':
        case 'sales executive':
        case 'se':
        case 'sales':
        case 'user':
        case 'u':
        default:
            return 'user';
    }
}

function role_level_php($normalizedRole)
{
    $map = [
        'promoter' => 1,
        'business_head' => 2,
        'manager' => 3,
        'team_lead' => 4,
        'user' => 5,
    ];
    return $map[strtolower(trim((string) $normalizedRole))] ?? 5;
}

// Get current user's normalized role
$currentUserType = $_SESSION['user_type'] ?? 'user';
$currentRoleNormalized = normalize_role_php($currentUserType);
$currentUserLevel = role_level_php($currentRoleNormalized);

$pageTitle = "Leads";
$customCss = ['assets/css/user_lead.css'];
include 'htmlopen.php';
include 'header.php';
?>


<div class="container">
    <!-- Enhanced Leads Table -->
    <div class="container">
        <!-- Filter Buttons -->
        <div class="filter-toggle">
            <div class="tab-row-wrapper">
                <button class="badge-arrow left" data-target=".filter-row">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="filter-row" style="">
                    <button class="filter-btn server-true small-btn" id="activeLeads"><i
                            class="fas fa-check-circle"></i>
                        Active <span class="count">0</span></button>
                    <button class="filter-btn server-true small-btn" id="freshLeads"><i class="fas fa-star"></i> New
                        <span class="count">0</span></button>
                    <button class="filter-btn server-true untouch-small" id="pendingLeads"><i class="fas fa-clock"></i>
                        Untouched <span class="count">0</span></button>
                    <button class="filter-btn server-true small-btn" id="followLeads"><i class="fas fa-arrow-up"></i>
                        Follow
                        Up <span class="count">0</span></button>
                    <button class="filter-btn server-true untouch-small" id="overdueLeads"><i
                            class="fas fa-exclamation-triangle"></i>
                        Overdue <span class="count">0</span></button>
                    <!--<button class="filter-btn small-btn" id="untouchedLeads"><i class="bi bi-card-checklist"></i>Untouche (0)</button> -->

                    <!-- <button class="filter-btn small-btn" id="activeLeads"><i class="fas fa-check-circle"></i> Active <span class="count">36</span></button>
                <button class="filter-btn small-btn" id="freshLeads"><i class="fas fa-star"></i> New <span class="count">0</span></button>
                <button class="filter-btn small-btn" id="pendingLeads"><i class="fas fa-clock"></i> Pending <span class="count">26</span></button>
                <button class="filter-btn small-btn" id="followLeads"><i class="fas fa-arrow-up"></i> Follow Up <span class="count">3</span></button> 
                <button class="filter-btn small-btn" id="untouchedLeads"><i class="fas fa-user-clock"></i> Untouched <span class="count">5</span></button> -->
                    <!-- Status Filter Dropdown Button -->
                    <div class="status-filter-container">
                        <button class="filter-btn server-false small-btn status-filter-btn" id="filterStatus">
                            <i class="fas fa-filter"></i> Status <i class="fas fa-caret-down"></i>
                        </button>
                        <div class="status-filter-dropdown">
                            <div class="status-filter-search">
                                <input type="text" placeholder="Search status..." class="status-search-input">

                            </div>
                            <div class="status-options">

                                <button class="status-option" data-status="All">
                                    <span class="status-badge all">All</span>
                                </button>
                                <button class="status-option" data-status="Pending">
                                    <span class="status-badge pending">Pending</span>
                                </button>
                                <button class="status-option" data-status="Fake">
                                    <span class="status-badge fake">Fake</span>
                                </button>
                                <button class="status-option" data-status="RNR">
                                    <span class="status-badge rnr">RNR</span>
                                </button>
                                <button class="status-option" data-status="Call Back">
                                    <span class="status-badge call-back">Call Back</span>
                                </button>
                                <button class="status-option" data-status="Already Booked">
                                    <span class="status-badge already-booked">Already Booked</span>
                                </button>
                                <button class="status-option" data-status="Not Interested">
                                    <span class="status-badge not-interested">Not Interested</span>
                                </button>
                                <button class="status-option" data-status="Interested">
                                    <span class="status-badge interested">Interested</span>
                                </button>
                                <button class="status-option" data-status="EOI">
                                    <span class="status-badge eoi">EOI</span>
                                </button>
                                <button class="status-option" data-status="Fix Site Visit">
                                    <span class="status-badge fix-site-visit">Fix Site Visit</span>
                                </button>
                                <button class="status-option" data-status="Site Visit Done">
                                    <span class="status-badge site-visit-done">Site Visit Done</span>
                                </button>
                                <button class="status-option" data-status="VC Done">
                                    <span class="status-badge vc-done">VC Done</span>
                                </button>
                                <button class="status-option" data-status="Converted">
                                    <span class="status-badge converted">Converted</span>
                                </button>
                                <button class="status-option" data-status="Re site visit">
                                    <span class="status-badge re-site-visit">Re site visit</span>
                                </button>
                                <button class="status-option" data-status="NQFTP">
                                    <span class="status-badge nqftp">NQFTP</span>
                                </button>
                                <button class="status-option" data-status="Not Connected">
                                    <span class="status-badge not-connected">Not Connected</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <style>
                        .tag-filter-container {
                            position: static;
                            display: inline-block;
                        }

                        .tag-filter-btn {
                            position: relative;
                            padding-right: 30px !important;
                        }

                        .tag-filter-btn.active {
                            background-color: #f8f9fa !important;
                            border-color: #e0e2e5 !important;
                            color: #3c4043 !important;
                            transform: none !important;
                        }

                        .tag-filter-btn.active i {
                            color: #5f6368 !important;
                            transform: none !important;
                        }

                        .tag-filter-btn i.fa-caret-down {
                            position: absolute;
                            right: 10px;
                            top: 50%;
                            transform: translateY(-50%);
                            font-size: 12px;
                            transition: transform 0.2s;
                        }

                        .tag-filter-container.active .tag-filter-btn i.fa-caret-down {
                            transform: translateY(-50%) rotate(180deg);
                        }

                        .tag-filter-dropdown {
                            position: absolute;
                            top: 100%;
                            right: 0;
                            background: #fff;
                            border: 1px solid #e0e0e0;
                            border-radius: 8px;
                            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                            width: 220px;
                            z-index: 1000;
                            display: none;
                            margin-top: 5px;
                        }

                        .tag-options {
                            max-height: 300px;
                            overflow-y: auto;
                            padding: 5px 0;
                            scrollbar-width: none;
                        }

                        .tag-option {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            width: 100%;
                            padding: 8px 15px;
                            background: transparent;
                            border: none;
                            text-align: left;
                            cursor: pointer;
                            transition: background 0.2s;
                            border-radius: 4px !important;
                        }

                        .tag-option:hover {
                            background: #f5f5f5;
                        }

                        .tag-option .tag-badge {
                            flex: 1;
                            margin-right: 10px;
                            text-align: left;
                            font-size: 12px;
                            padding: 4px 8px;
                            border-radius: 4px !important;
                            font-weight: 600;
                            color: white !important;
                        }

                        .tag-badge.all {
                            background: #6b7280 !important;
                        }

                        .tag-badge.hot {
                            background: #dc3545 !important;
                        }

                        .tag-badge.warm {
                            background: #fd7e14 !important;
                        }

                        .tag-badge.cold {
                            background: #0dcaf0 !important;
                        }

                        @media (max-width: 900px) {
                            .tag-filter-dropdown {
                                position: fixed;
                                left: 25vw;
                                top: auto;
                                z-index: 1100;
                                max-height: 60vh;
                                max-width: 60vh;
                                overflow-y: auto;
                                border-radius: 8px 8px 0 0;
                                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.15);
                            }
                        }

                        /* Dark Theme Overrides */
                        [data-theme="dark"] .tag-filter-dropdown {
                            background: #1f2937;
                            border-color: #374151;
                        }

                        [data-theme="dark"] .tag-option {
                            color: #f3f4f6;
                        }

                        [data-theme="dark"] .tag-option:hover {
                            background: #374151;
                        }
                    </style>
                    <div class="tag-filter-container">
                        <button class="filter-btn server-false small-btn tag-filter-btn" id="filterTag">
                            <i class="fas fa-tags"></i> Tag <i class="fas fa-caret-down"></i>
                        </button>
                        <div class="tag-filter-dropdown">
                            <div class="tag-options">
                                <button class="tag-option" data-tag="All">
                                    <span class="tag-badge all">All</span>
                                </button>
                                <button class="tag-option" data-tag="Hot">
                                    <span class="tag-badge hot">Hot</span>
                                </button>
                                <button class="tag-option" data-tag="Warm">
                                    <span class="tag-badge warm">Warm</span>
                                </button>
                                <button class="tag-option" data-tag="Cold">
                                    <span class="tag-badge cold">Cold</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="badge-arrow right" data-target=".filter-row">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!--  -->
            <?php if ($currentRoleNormalized !== 'user'): ?>
                <div class="manager-toggle-container desktop-manager-toggle">
                    <label class="toggle-switch manager-toggle-switch" for="managerToggle" aria-label="Team view toggle">
                        <input type="checkbox" id="managerToggle">
                        <span class="slider round"></span>
                    </label>
                    <span class="toggle-label">Team View</span>
                    <span class="toggle-helper"></span>
                </div>
            <?php else: ?>
                <!-- Hidden toggle for regular users to maintain JS consistency -->
                <span id="managerToggle" style="display: none;"></span>
            <?php endif; ?>
        </div>
        <!-- Enhanced Leads Table -->
        <div class="dashboard-card-leads">
            <div class="card-header-leads">
            </div>

            <!-- Fixed Controls Container -->
            <div class="table-controls-fixed">
                <div id="tableControlsContainer"></div>
            </div>

            <!-- Scrollable Table Container -->
            <div class="table-container">
                <table class="enhanced-table" id="leadsTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" id="selectAll">
                            </th>

                            <th class="filter-header filter-lead">
                                <button class="filter-header-btn">LEAD <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search leads..." class="filter-search-input"
                                            data-column="1">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="1"
                                                value=""> All Leads</label>

                                    </div>

                                </div>
                            </th>
                            <th class="filter-header  ">
                                <button class="filter-header-btn">ASSIGNED PROJECT <i
                                        class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search Projects..." class="filter-search-input"
                                            data-column="2">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="2"
                                                value=""> All Projects</label>

                                    </div>
                                </div>
                            </th>
                            <th class="filter-header filter-id" style="width: 75px;">
                                <button class="filter-header-btn">ID <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search IDs..." class="filter-search-input"
                                            data-column="3">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="3"
                                                value=""> All IDs</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3"
                                                value="LD003"> LD003</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3"
                                                value="LD004"> LD004</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3"
                                                value="LD005"> LD005</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3"
                                                value="LD006"> LD006</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3"
                                                value="LD007"> LD007</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3"
                                                value="LD008"> LD008</label>
                                    </div>

                                </div>
                            </th>
                            <th class="filter-header   filter-created">
                                <button class="filter-header-btn">CREATED AT <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">

                                    <div class="filter-search">
                                        <input type="text" placeholder="Search dates..." class="filter-search-input"
                                            data-column="4">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="4"
                                                value=""> All Dates</label>
                                        <label><input type="checkbox" class="filter-option" data-column="4"
                                                value="Today"> Today</label>
                                        <label><input type="checkbox" class="filter-option" data-column="4"
                                                value="Yesterday"> Yesterday</label>
                                        <label><input type="checkbox" class="filter-option" data-column="4"
                                                value="This Week"> This Week</label>
                                        <label><input type="checkbox" class="filter-option" data-column="4"
                                                value="Last Week"> Last Week</label>
                                        <label><input type="checkbox" class="filter-option" data-column="4"
                                                value="This Month"> This Month</label>
                                        <label><input type="checkbox" class="filter-option" data-column="4"
                                                value="Last Month"> Last Month</label>
                                    </div>
                                </div>
                            </th>

                            <th class="filter-header  ">
                                <button class="filter-header-btn">EMAIL <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search contacts..." class="filter-search-input"
                                            data-column="5">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="5"
                                                value=""> All Emails</label>
                                        <label><input type="checkbox" class="filter-option" data-column="5"
                                                value="michael.b@example.com"> michael.b@example.com</label>
                                        <label><input type="checkbox" class="filter-option" data-column="5"
                                                value="ayush@example.com"> ayush@example.com</label>
                                        <label><input type="checkbox" class="filter-option" data-column="5"
                                                value="abhay@example.com"> abhay@example.com</label>
                                        <label><input type="checkbox" class="filter-option" data-column="5"
                                                value="sarah.w@example.com"> sarah.w@example.com</label>
                                        <label><input type="checkbox" class="filter-option" data-column="5"
                                                value="david.j@example.com"> david.j@example.com</label>
                                        <label><input type="checkbox" class="filter-option" data-column="5"
                                                value="emily.d@example.com"> emily.d@example.com</label>
                                    </div>
                                </div>
                            </th>


                            <th class="filter-header   filter-location">
                                <button class="filter-header-btn">LOCATION <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search locations..." class="filter-search-input"
                                            data-column="6">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="6"
                                                value=""> All Locations</label>

                                    </div>
                                </div>
                            </th>
                            <th class="filter-header   filter-budget">
                                <button class="filter-header-btn">BUDGET <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search budgets..." class="filter-search-input"
                                            data-column="7">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="7"
                                                value=""> All Budgets</label>
                                    </div>
                                </div>
                            </th>

                            <th class="filter-header filter-remarks">
                                <button class="filter-header-btn">REMARKS <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search remarks..." class="filter-search-input"
                                            data-column="8">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="8"
                                                value=""> All Remarks</label>
                                    </div>
                                </div>
                            </th>

                            <th class="filter-header filter-updated-at">
                                <button class="filter-header-btn">UPDATED AT <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search updated dates..."
                                            class="filter-search-input" data-column="9">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="9"
                                                value=""> All Updated Dates</label>
                                    </div>
                                </div>
                            </th>

                            <th class="filter-header   filter-location">
                                <button class="filter-header-btn">ASSIGNED LEAD <i
                                        class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search assigned leads..."
                                            class="filter-search-input" data-column="10">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="10"
                                                value=""> All Leads</label>
                                    </div>
                                </div>
                            </th>
                            <th class="filter-header   filter-location">
                                <button class="filter-header-btn">LEAD SOURCE <i class="fas fa-caret-down"></i></button>

                                <div class="filter-dropdown">
                                    <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search leads source..."
                                            class="filter-search-input" data-column="11">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="11"
                                                value=""> All Leads</label>
                                    </div>
                                </div>
                            </th>
                            <th class="expand-btn-cell"></th>
                            <th class="filter-header   filter-action">
                                <button class="filter-header-btn">ACTIONS </i></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Table rows will be populated by DataTables -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button class="clear-filters-btn" id="clearAllFiltersBtn" style="display: none;">
        <i class="fas fa-times-circle"></i>
        Clear Filters
    </button>
    <div class="floating-add-btn">
        <div class="selection-actions" style="display: none;">
            <?php if ($currentRoleNormalized !== 'user'): ?>
                <button class="action-button assign-users-btn assign-m"><i class="fas fa-user-friends"></i> Assign</button>
            <?php else: ?>
                <button class="action-button assign-u"><i class="fas fa-user-friends"></i> Assign</button>
            <?php endif; ?>
            <button class="action-button whatsapp-bulk-btn" id="whatsappBulkBtn" title="Send WhatsApp via REOS AI">
                <i class="fab fa-whatsapp"></i>
            </button>
        </div>
        <button class="action-button salarybutton accessbtn" data-toggle="modal" data-target="#addLeadModal"><i
                class="fas fa-plus"></i></button>
    </div>
    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav">
        <button class="mobile-nav-btn filter-btn-mobile">
            <i class="fas fa-filter bottom-icon"></i>
            <span>Filter</span>
        </button>
        <?php if ($currentRoleNormalized !== 'user'): ?>
            <button class="mobile-nav-btn assign-users-btn assign-btn-mobile assign-m">
                <i class="fas fa-user-friends bottom-icon"></i>
                <div class="assign">
                    <span>Assign</span>
                    <span class="selection-count">0</span>
                </div>
            </button>
        <?php else: ?>
            <button class="mobile-nav-btn assign-btn-mobile-u assign-u">
                <i class="fas fa-user-friends bottom-icon"></i>
                <div class="assign">
                    <span>Assign</span>
                    <span class="selection-count">0</span>
                </div>
            </button>
        <?php endif; ?>

        <?php if ($currentRoleNormalized !== 'user'): ?>
            <div class="mobile-nav-btn mobile-team-toggle-wrap" aria-label="Team view toggle">
                <button type="button" class="mobile-team-toggle-btn" id="mobileManagerToggleBtn"
                    aria-controls="managerToggle" aria-label="Toggle Team View">
                    <span id="mobileManagerToggleIconTeam" class="mobile-manager-icon-wrap">
                        <i class="fas fa-users bottom-icon"></i>
                    </span>
                    <span id="mobileManagerToggleIconMy" class="mobile-manager-icon-wrap" style="display: none;">
                        <i class="fas fa-user bottom-icon"></i>
                    </span>
                    <span id="mobileManagerToggleText">Team View</span>
                </button>
            </div>
        <?php else: ?>
            <div class="mobile-nav-center-spacer" aria-hidden="true"></div>
        <?php endif; ?>

        <button class="mobile-nav-btn add-btn-mobile">

            <i class="fas fa-user-plus bottom-icon"></i>
            <span class="add-lead-btn">Add Lead</span>
        </button>

        <button class="mobile-nav-btn remarks-btn-mobile columns-btn-mobile">
            <i class="fas fa-comment-dots bottom-icon"></i>
            <span>Remarks</span>
        </button>

        <button class="mobile-nav-btn whatsapp-btn-mobile" style="display: none; margin-top: -5px, gap: 0px;">
            <svg xmlns="http://www.w3.org/2000/svg" class="bottom-icon" viewBox="0 0 640 640"
                style="fill: #03ac47; width: 25px; height: 25px; margin-bottom: -5px;"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                <path
                    d="M476.9 161.1C435 119.1 379.2 96 319.9 96C197.5 96 97.9 195.6 97.9 318C97.9 357.1 108.1 395.3 127.5 429L96 544L213.7 513.1C246.1 530.8 282.6 540.1 319.8 540.1L319.9 540.1C442.2 540.1 544 440.5 544 318.1C544 258.8 518.8 203.1 476.9 161.1zM319.9 502.7C286.7 502.7 254.2 493.8 225.9 477L219.2 473L149.4 491.3L168 423.2L163.6 416.2C145.1 386.8 135.4 352.9 135.4 318C135.4 216.3 218.2 133.5 320 133.5C369.3 133.5 415.6 152.7 450.4 187.6C485.2 222.5 506.6 268.8 506.5 318.1C506.5 419.9 421.6 502.7 319.9 502.7zM421.1 364.5C415.6 361.7 388.3 348.3 383.2 346.5C378.1 344.6 374.4 343.7 370.7 349.3C367 354.9 356.4 367.3 353.1 371.1C349.9 374.8 346.6 375.3 341.1 372.5C308.5 356.2 287.1 343.4 265.6 306.5C259.9 296.7 271.3 297.4 281.9 276.2C283.7 272.5 282.8 269.3 281.4 266.5C280 263.7 268.9 236.4 264.3 225.3C259.8 214.5 255.2 216 251.8 215.8C248.6 215.6 244.9 215.6 241.2 215.6C237.5 215.6 231.5 217 226.4 222.5C221.3 228.1 207 241.5 207 268.8C207 296.1 226.9 322.5 229.6 326.2C232.4 329.9 268.7 385.9 324.4 410C359.6 425.2 373.4 426.5 391 423.9C401.7 422.3 423.8 410.5 428.4 397.5C433 384.5 433 373.4 431.6 371.1C430.3 368.6 426.6 367.2 421.1 364.5z" />
            </svg>
            <span style="color: #03ac47;">WhatsApp</span>
        </button>
    </div>

    <!-- Add New Lead Modal -->
    <div class="modal-overlay" id="addLeadModal" style="display: none;">
        <div class="modal-container-eoi add-booking-modal">
            <div class="modal-header">
                <h3>Add New Lead</h3>
                <button type="button" class="modal-close-btn" id="closeAddLeadModal">&times;</button>
            </div>

            <div class="modal-body">
                <form id="addLeadForm" novalidate>
                    <input type="hidden" id="leadlocation" name="leadlocation">

                    <!-- Lead Info Section -->
                    <div class="section">
                        <h3>Lead Info</h3>
                        <div class="">

                            <!-- Name -->
                            <div class="field">
                                <fieldset class="fieldset-label">
                                    <legend class="field-legend">Full Name</legend>
                                    <input type="text" id="leadName" name="name" placeholder="Enter full name">
                                </fieldset>
                            </div>

                            <!-- Phone -->
                            <div class="field">
                                <fieldset class="fieldset-label">
                                    <legend class="field-legend">Phone Number</legend>
                                    <div class="input-wrap">
                                        <span class="suffix">+91</span>
                                        <input type="tel" id="leadPhone" name="number"
                                            placeholder="10-digit mobile number" maxlength="10" pattern="[6-9][0-9]{9}">
                                    </div>
                                </fieldset>
                            </div>

                            <!-- Email -->
                            <div class="field">
                                <fieldset class="fieldset-label">
                                    <legend class="field-legend">Email</legend>
                                    <input type="email" id="leadEmail" name="email" placeholder="Enter email address">
                                </fieldset>
                            </div>

                            <!-- Project -->
                            <div class="field">
                                <fieldset class="fieldset-label">
                                    <legend class="field-legend">Project</legend>
                                    <input type="text" id="leadProject" name="project" placeholder="Enter project name">
                                </fieldset>
                            </div>

                            <!-- Lead Source (Updated with Select2) -->
                            <div class="field full-row">
                                <fieldset class="fieldset-label">
                                    <legend class="field-legend">Lead Source</legend>
                                    <select id="leadsource" name="leadsource" class="select-input">
                                        <option value="">Select Source</option>
                                        <option value="Google Ads">Google</option>
                                        <option value="Facebook Ads">Facebook</option>
                                        <option value="Direct">Direct</option>
                                        <option value="Referral">Referral</option>
                                        <option value="Portal">Portal</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                    </select>
                                    <div class="error-message" id="leadsourceError"></div>
                                </fieldset>
                            </div>

                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="form-actions">
                        <button type="button" class="cancel-btn btn" id="cancelAddLead">Cancel</button>
                        <button type="submit" class="submit-btn btn" id="submitLead">Add Lead</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Updated Filter Modal with Two-Column Layout -->




        <style>
            /* Pencil icon color for Edit IVR Lead */
            .edit-ivr-lead-btn {
                color: #e53935 !important;
                /* Red color for light mode */
            }

            [data-theme="dark"] .edit-ivr-lead-btn {
                color: #fff !important;
                /* White color for dark mode */
            }

            /* 100% caching proof dark mode fix */
            [data-theme="dark"] .date-overlay-wrapper {
                border-color: #d4d4d8 !important;
                background: rgba(30, 30, 30, 0.95) !important;
                color: #ffffff !important;
            }

            [data-theme="dark"] .date-overlay-wrapper .icon-calendar {
                color: #ffffff !important;
                fill: #ffffff !important;
            }
        </style>
        <div class="modal-overlay" id="filterModal" style="display: none;">
            <div class="modal-container-eoi">
                <div class="modal-header">
                    <h3>FILTER DATA</h3>
                    <button class="modal-close-btn" id="closeFilterModal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="filterForm">

                        <!-- Date Range -->
                        <div class="section">
                            <div class="grid">
                                <div class="field">
                                    <div class="form-item">
                                        <label>From Date</label>

                                        <div style="position: relative; width: 100%; height: 53px; border: 1px solid #ced4da; border-radius: 6px; display: flex; align-items: center; padding: 0 16px; background: transparent; box-sizing: border-box; overflow: hidden;"
                                            class="date-overlay-wrapper">
                                            <span id="displayStartDate"
                                                style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="currentColor" class="bi bi-calendar icon-calendar"
                                                viewBox="0 0 16 16"
                                                style="color: #495057; pointer-events: none; z-index: 1;">
                                                <path
                                                    d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z" />
                                                <path
                                                    d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z" />
                                            </svg>
                                            <input type="date" id="isolatedFilterStartDate"
                                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; appearance: none; -webkit-appearance: none; box-sizing: border-box; z-index: 2;"
                                                onchange="let d = this.value; document.getElementById('displayStartDate').innerText = d ? d.split('-').reverse().join('-') : 'dd-mm-yyyy'; document.getElementById('displayStartDate').style.color = d ? 'inherit' : '#6b7280';">
                                        </div>

                                    </div>
                                </div>
                                <div class="field">
                                    <div class="form-item">
                                        <label>To Date</label>

                                        <div style="position: relative; width: 100%; height: 53px; border: 1px solid #ced4da; border-radius: 6px; display: flex; align-items: center; padding: 0 16px; background: transparent; box-sizing: border-box; overflow: hidden;"
                                            class="date-overlay-wrapper">
                                            <span id="displayEndDate"
                                                style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="currentColor" class="bi bi-calendar icon-calendar"
                                                viewBox="0 0 16 16"
                                                style="color: #495057; pointer-events: none; z-index: 1;">
                                                <path
                                                    d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z" />
                                                <path
                                                    d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z" />
                                            </svg>
                                            <input type="date" id="isolatedFilterEndDate"
                                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; appearance: none; -webkit-appearance: none; box-sizing: border-box; z-index: 2;"
                                                onchange="let d = this.value; document.getElementById('displayEndDate').innerText = d ? d.split('-').reverse().join('-') : 'dd-mm-yyyy'; document.getElementById('displayEndDate').style.color = d ? 'inherit' : '#6b7280';">
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="section">
                            <div class="grid">
                                <div class="field">
                                    <div class="form-item">
                                        <label>Customer Name</label>
                                        <div class="multi-select-container" data-field-key="name">
                                            <div class="multi-select-tags" id="tags-isolatedFilterCustumername"></div>
                                            <input type="text" id="isolatedFilterCustumername"
                                                class="lead-filter-suggest multi-select-input border"
                                                data-field-key="name" placeholder="Search & select" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="form-item">
                                        <label>Email</label>
                                        <div class="multi-select-container" data-field-key="email">
                                            <div class="multi-select-tags" id="tags-isolatedFilterEmail"></div>
                                            <input type="text" id="isolatedFilterEmail"
                                                class="lead-filter-suggest multi-select-input" data-field-key="email"
                                                placeholder="Search & select" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact & Location -->
                        <div class="section">
                            <div class="grid">
                                <div class="field">
                                    <div class="form-item">
                                        <label>Contact No.</label>
                                        <div class="multi-select-container" data-field-key="number">
                                            <div class="multi-select-tags" id="tags-isolatedFilterContactnumber"></div>
                                            <input type="text" id="isolatedFilterContactnumber"
                                                class="lead-filter-suggest multi-select-input" data-field-key="number"
                                                placeholder="Search & select" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="form-item">
                                        <label>Location</label>
                                        <div class="multi-select-container" data-field-key="location">
                                            <div class="multi-select-tags" id="tags-isolatedFilterLocation"></div>
                                            <input type="text" id="isolatedFilterLocation"
                                                class="lead-filter-suggest multi-select-input" data-field-key="location"
                                                placeholder="Search & select" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lead Details -->
                        <div class="section">
                            <div class="grid">
                                <div class="field">
                                    <div class="form-item">
                                        <label>Source of Lead</label>
                                        <div class="multi-select-container" data-field-key="source_of_lead">
                                            <div class="multi-select-tags" id="tags-isolatedFilterSourceOfLead"></div>
                                            <input type="text" id="isolatedFilterSourceOfLead"
                                                class="lead-filter-suggest multi-select-input"
                                                data-field-key="source_of_lead" placeholder="Search & select"
                                                autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="form-item">
                                        <label>Status</label>
                                        <div class="multi-select-container" data-field-key="status">
                                            <div class="multi-select-tags" id="tags-isolatedFilterStatus"></div>
                                            <input type="text" id="isolatedFilterStatus"
                                                class="lead-filter-suggest multi-select-input" data-field-key="status"
                                                placeholder="Search & select" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Budget and Lead Identity -->
                        <div class="section">
                            <div class="grid">
                                <div class="field">
                                    <div class="form-item">
                                        <label>Budget Range</label>
                                        <div class="multi-select-container" data-field-key="budget">
                                            <div class="multi-select-tags" id="tags-isolatedFilterBudget"></div>
                                            <input type="text" id="isolatedFilterBudget"
                                                class="lead-filter-suggest multi-select-input" data-field-key="budget"
                                                placeholder="Search & select" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="form-item">
                                        <label>Lead Identity</label>
                                        <div class="multi-select-container" data-field-key="lead_identity">
                                            <div class="multi-select-tags" id="tags-isolatedFilterAssignedIdentity">
                                            </div>
                                            <input type="text" id="isolatedFilterAssignedIdentity"
                                                class="lead-filter-suggest multi-select-input"
                                                data-field-key="lead_identity" placeholder="Search & select"
                                                autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assignment -->
                        <div class="section">
                            <div class="grid">
                                <div class="field">
                                    <div class="form-item">
                                        <label>Assigned Project</label>
                                        <div class="multi-select-container" data-field-key="assign_project_name">
                                            <div class="multi-select-tags" id="tags-isolatedFilterAssignedProjectName">
                                            </div>
                                            <input type="text" id="isolatedFilterAssignedProjectName"
                                                class="lead-filter-suggest multi-select-input"
                                                data-field-key="assign_project_name" placeholder="Search & select"
                                                autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="form-item">
                                        <label>User Name</label>
                                        <div class="multi-select-container" data-field-key="user">
                                            <div class="multi-select-tags" id="tags-isolatedFilterAssigneduserName">
                                            </div>
                                            <input type="text" id="isolatedFilterAssigneduserName"
                                                class="lead-filter-suggest multi-select-input" data-field-key="user"
                                                placeholder="Search & select" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <!-- Actions -->
                        <div class="form-actions">
                            <button type="button" class="cancel-btn btn colour" id="closeFilterBtn">Close</button>
                            <button type="button" class="cancel-btn btn" id="isolatedClearFiltersBtn">Clear
                                Filters</button>
                            <button type="submit" class="submit-btn btn" id="isolatedApplyFiltersBtn">Apply
                                Filters</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- Reassign Lead Modal -->
        <div class="modal-overlay" id="reassignModal"
            style="display: none; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw; height: 100vh; overflow-y: auto; z-index: 9999; align-items: center; justify-content: center;">
            <div class="modal-container-eoi add-booking-modal">
                <div class="modal-header">
                    <div class="header-content">
                        <div class="header-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h3>Reassign Lead</h3>
                    </div>
                    <button class="modal-close-btn" id="closeReassignModal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Lead Details -->
                    <h4>Lead Details</h4>
                    <div class="whatsapp-recipient-info">
                        <div class="recipient-name" id="reassignLeadName"></div>
                        <div class="recipient-phone" id="reassignLeadId"></div>
                    </div>

                    <!-- Response Message -->
                    <div id="responseMessage" class="response-message" style="display: none;"></div>

                    <!-- Reassignment Form -->
                    <form id="reassignForm">
                        <input type="hidden" id="reassignRowId" name="reassignRowId" value="">

                        <div class="section">
                            <h3>Reassignment Info</h3>
                            <div class="">
                                <!-- Assign User (Updated with Select2) -->
                                <div class="field">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-user"></i> Assign To <span class="required">*</span>
                                        </legend>
                                        <select id="assignUser" name="assignUser" class="select-input">
                                            <option value="">Select User</option>
                                            <!-- Options will be populated dynamically -->
                                        </select>
                                        <div class="error-message" id="assignUserError"></div>
                                    </fieldset>
                                </div>

                                <!-- Project Name -->
                                <div class="field">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-project-diagram"></i> Project Name <span
                                                class="required">*</span>
                                        </legend>
                                        <input type="text" class="filter-text-input" id="projectName" name="projectName"
                                            placeholder="Enter project name">
                                        <div class="error-message" id="projectNameError"></div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>

                        <!-- History Toggle Section -->
                        <div class="section">
                            <h3>Lead History Transfer</h3>
                            <div class="history-section">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="fas fa-history"></i>
                                        <span>History Options</span>
                                    </div>
                                    <div class="toggle-switch-container">
                                        <label class="toggle-switch-2 toggle-assign">
                                            <input type="checkbox" id="includeHistoryToggle" name="includeHistory"
                                                checked>
                                            <span class="slider-2 slider-3"></span>
                                            <div class="toggle-labels lable-1">
                                                <span class="toggle-label-3 toggle-text label-2 on">With History</span>
                                                <span class="toggle-label-3 toggle-text label-2 off">Without
                                                    History</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="history-info">
                                    <div class="info-content">
                                        <div class="info-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="info-text">
                                            <p id="historyDescription">All previous interactions, notes, and status
                                                updates will be transferred to the new assignee.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="form-actions">
                            <button type="button" class="cancel-btn" id="cancelReassign">Cancel</button>
                            <button type="button" class="submit-btn btn btn-primary" id="submitReassign">Reassign
                                Lead</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <input type="hidden" id="rowId" name="rowId">

        <div id="responseMessage" style="margin-top: 10px;"></div>

        <!-- <div id="reassignModal" class="custom-modal">
  <div class="custom-modal-content">
    <span class="close-modal">&times;</span>
    <h2>Reassign Lead</h2>
    
    <label for="reassignSelect">Select User:</label>
    <select id="reassignSelect" class="select2-dropdown">
      <option value="user1">User 1</option>
      <option value="user2">User 2</option>
      <option value="user3">User 3</option>
    </select>

    <button id="confirmReassign">Confirm</button>
  </div>
</div> -->

        <!-- Add this modal just before the closing </div> of the container -->
        <div class="modal-overlay" id="deleteModal" style="display: none;">
            <div class="modal-container">
                <div class="modal-header">
                    <h3>Delete Leads</h3>
                    <button class="modal-close-btn" id="closeDeleteModal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="deleteForm">
                        <div class="form-group">
                            <p id="deleteMessage">Are you sure you want to delete the selected leads? This action cannot
                                be undone.</p>
                            <div id="selectedLeadsList"
                                style="max-height: 200px; overflow-y: auto; margin-top: 15px; display: none;">
                                <h4>Selected Leads:</h4>
                                <ul id="leadsToDelete" style="list-style-type: none; padding-left: 0;"></ul>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="cancel-btn" id="cancelDelete">Cancel</button>
                            <button type="submit" class="btn submit-btn delete-btn">Delete Leads</button>
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


        <!-- Assign Leads Modal -->
        <div class="modal-overlay" id="assignModal" style="display: none;">
            <div class="modal-container-eoi add-booking-modal">
                <div class="modal-header">
                    <h3>Assign Leads</h3>
                    <button type="button" class="modal-close-btn" id="closeAssignModal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="selected-leads-info">
                        <p><i class="fas fa-users"></i> <span id="selectedLeadsCount">0</span> leads selected</p>
                        <div class="lead-ids-container" id="selectedLeadsList"></div>
                    </div>

                    <form id="assignForm">
                        <!-- Assign To -->
                        <!-- Assign To -->
                        <div class="section">
                            <h3>Assignment Info</h3>
                            <div class="">
                                <div class="field">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">Assign To <span
                                                class="required">*</span></legend>
                                        <select id="assignTo" name="assignTo" class="select-input user-select">
                                            <option value="">Select User</option>
                                            <!-- Options will be populated dynamically -->
                                        </select>
                                        <div class="error-message" id="assignToError"></div>
                                    </fieldset>
                                </div>
                                <div class="field">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">Project Name</legend>
                                        <input type="text" id="assignProjectName" name="assignProjectName"
                                            placeholder="Enter Project Name" />
                                        <div class="error-message" id="assignProjectNameError"></div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>

                        <!-- Lead History Transfer Section (Now Sibling, not Child) -->
                        <div class="section">
                            <h3>Lead History Transfer</h3>
                            <div class="history-section">
                                <div class="section-header">
                                    <div class="section-title">
                                        <i class="fas fa-history"></i>
                                        <span>History Options</span>
                                    </div>
                                    <div class="toggle-switch-container">
                                        <label class="toggle-switch-2 toggle-assign">
                                            <input type="checkbox" id="bulkIncludeHistoryToggle" name="includeHistory"
                                                checked>
                                            <span class="slider-2 slider-3"></span>
                                            <div class="toggle-labels lable-1">
                                                <span class="toggle-label-3 label-2 on">With History</span>
                                                <span class="toggle-label-3 label-2 off">Without History</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="history-info">
                                    <div class="info-content">
                                        <div class="info-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="info-text">
                                            <p id="historyDescription2"> All previous interactions, notes, and
                                                status updates will be transferred to the new assignee.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>




                        <!-- Actions -->
                        <div class="form-actions">
                            <button type="button" class="cancel-btn btn" id="cancelAssign"> Cancel </button>
                            <button type="submit" class="submit-btn btn"> Assign Leads </button>
                        </div>
                    </form>

                    <div id="assignResponseMessage"></div>
                </div>
            </div>
        </div>



        <!-- Status Modal -->
        <div class="modal-overlay" id="statusModal" style="display: none;">
            <div class="modal-container-eoi add-booking-modal">
                <div class="modal-header">
                    <h3>Update Lead Status</h3>
                    <button type="button" class="modal-close-btn" id="closeStatusModal">&times;</button>
                </div>
                <div class="modal-body">
                    <h4>Lead Details</h4>
                    <div class="whatsapp-recipient-info">
                        <div class="recipient-name" id="statusLeadName"></div>
                        <div class="recipient-phone" id="statusLeadId"></div>
                    </div>

                    <form id="statusForm">
                        <input type="hidden" id="rowId" name="rowId" value="">

                        <div class="section">
                            <div class="">
                                <!-- Status Dropdown (already styled) -->
                                <div class="field" id="statusField">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-tag"></i> Status <span class="required">*</span>
                                        </legend>
                                        <select id="newStatus" class="select-input">
                                            <option value="">Select Status</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Fake">Fake</option>
                                            <option value="RNR">RNR</option>
                                            <option value="Call Back">Call Back</option>
                                            <option value="Already Booked">Already Booked</option>
                                            <option value="Not Interested">Not Interested</option>
                                            <option value="Interested">Interested</option>
                                            <option value="EOI">EOI</option>
                                            <option value="Follow Up">Follow Up</option>
                                            <option value="Fix Site Visit">Fix Site Visit</option>
                                            <option value="Site Visit Done">Site Visit Done</option>
                                            <option value="VC Done">VC Done</option>
                                            <option value="Converted">Converted</option>
                                            <option value="Re site visit">Re site visit</option>
                                            <option value="NQFTP">NQFTP</option>
                                            <option value="Not Connected">Not Connected</option>
                                        </select>
                                        <div class="error-message" id="statusError"></div>
                                    </fieldset>
                                </div>

                                <!-- Budget Dropdown (updated to match status style) -->
                                <div class="field" id="budgetField">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-money-bill-wave"></i> Budget Range
                                        </legend>
                                        <select id="budget" name="budget" class="select-input">
                                            <option value="">-- Select Budget Range --</option>
                                            <option value="4000000-5000000">40,00,000 - 50,00,000</option>
                                            <option value="5000000-6000000">50,00,000 - 60,00,000</option>
                                            <option value="6000000-7000000">60,00,000 - 70,00,000</option>
                                            <option value="7000000-8000000">70,00,000 - 80,00,000</option>
                                            <option value="8000000-9000000">80,00,000 - 90,00,000</option>
                                            <option value="9000000-10000000">90,00,000 - 1,00,00,000</option>
                                            <option value="10000000-20000000">1,00,00,000 - 2,00,00,000</option>
                                            <option value="20000000-30000000">2,00,00,000 - 3,00,00,000</option>
                                            <option value="30000000-40000000">3,00,00,000 - 4,00,00,000</option>
                                            <option value="40000000-50000000">4,00,00,000 - 5,00,00,000</option>
                                            <option value="50000000-60000000">5,00,00,000 - 6,00,00,000</option>
                                            <option value="60000000-70000000">6,00,00,000 - 7,00,00,000</option>
                                        </select>
                                    </fieldset>
                                </div>

                                <!-- Preferred Location Input -->
                                <div class="field" id="locationField">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-map-marker-alt"></i> Preferred Location
                                        </legend>
                                        <input type="text" id="preferredLocation" name="location_status"
                                            class="filter-text-input" placeholder="Enter preferred location">
                                    </fieldset>
                                </div>

                                <!-- Lead Identity (Moved here) -->
                                <div class="field" id="leadIdentityDiv">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-fire"></i> Lead Identity
                                        </legend>
                                        <select id="leadIdentitySelect" name="leadIdentity" class="select-input">
                                            <option value="">None</option>
                                            <option value="Hot">🔥 Hot</option>
                                            <option value="Warm">☀️ Warm</option>
                                            <option value="Cold">❄️ Cold</option>
                                        </select>
                                        <input type="hidden" id="leadIdentityValue" name="leadIdentity" value="">
                                    </fieldset>
                                </div>



                                <!-- Date and Time Fields -->
                                <div class="field" id="dateField">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-calendar-alt"></i> Follow-up Date
                                        </legend>
                                        <div class="date-overlay-wrapper"
                                            style="position: relative; display: flex; align-items: center; padding: 10px 12px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;">
                                            <span id="displayFollowUpDate"
                                                style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="currentColor" viewBox="0 0 16 16"
                                                style="color: #6b7280; pointer-events: none; z-index: 1;">
                                                <path
                                                    d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z" />
                                                <path
                                                    d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z" />
                                            </svg>
                                            <input type="date" id="followUpDate"
                                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; z-index: 2; border: none; appearance: none; -webkit-appearance: none;">
                                        </div>
                                        <div class="error-message" id="dateError" style="display: none;"></div>
                                    </fieldset>
                                </div>

                                <div class="field" id="timeField">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-clock"></i> Follow-up Time
                                        </legend>
                                        <div class="date-overlay-wrapper"
                                            style="position: relative; display: flex; align-items: center; padding: 10px 12px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;">
                                            <span id="displayFollowUpTime"
                                                style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">--:--</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="currentColor" viewBox="0 0 16 16"
                                                style="color: #6b7280; pointer-events: none; z-index: 1;">
                                                <path
                                                    d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z" />
                                                <path
                                                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0" />
                                            </svg>
                                            <input type="time" id="followUpTime"
                                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; z-index: 2; border: none; appearance: none; -webkit-appearance: none;">
                                        </div>
                                        <div class="error-message" id="timeError" style="display: none;"></div>
                                    </fieldset>
                                </div>
                                <script>
                                    setInterval(function () {
                                        let d = document.getElementById('followUpDate');
                                        let dt = document.getElementById('displayFollowUpDate');
                                        if (d && dt) {
                                            let v = d.value;
                                            dt.innerText = v ? v.split('-').reverse().join('-') : 'dd-mm-yyyy';
                                            let isDark = document.body.getAttribute('data-theme') === 'dark';
                                            dt.style.color = v ? (isDark ? '#fff' : '#000') : '#6b7280';
                                        }
                                        let t = document.getElementById('followUpTime');
                                        let tt = document.getElementById('displayFollowUpTime');
                                        if (t && tt) {
                                            let v = t.value;
                                            if (v) {
                                                let pts = v.split(':');
                                                let h = parseInt(pts[0], 10);
                                                let ampm = h >= 12 ? 'PM' : 'AM';
                                                h = h % 12 || 12;
                                                tt.innerText = (h < 10 ? '0' + h : h) + ':' + pts[1] + ' ' + ampm;
                                            } else {
                                                tt.innerText = '--:--';
                                            }
                                            let isDark = document.body.getAttribute('data-theme') === 'dark';
                                            tt.style.color = v ? (isDark ? '#fff' : '#000') : '#6b7280';
                                        }
                                    }, 100);
                                </script>

                                <!-- Notes Field -->
                                <div class="field full-row" id="notesField">
                                    <fieldset class="fieldset-label">
                                        <legend class="field-legend top-legend">
                                            <i class="fas fa-sticky-note"></i> Notes <span class="required">*</span>
                                        </legend>
                                        <textarea id="statusNotes" class="filter-text-input"
                                            placeholder="Enter notes here"></textarea>
                                        <div class="error-message" id="notesError" style="display: none;"></div>
                                    </fieldset>
                                </div>


                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="cancel-btn" id="cancelStatusUpdate">Cancel</button>
                            <button type="submit" class="submit-btn btn" id="statusSubmit">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <div class="unique-status-sidebar" id="uniqueLeadHistorySidebar" style="z-index: 100150;">
            <div class="unique-top-sect"
                style="display: flex; align-items: center; justify-content: space-between; padding-right: 40px;">
                <h1 class="unique-sidebar-title" style="margin: 0;"><b>Lead History</b></h1>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="globalHistoryExpandToggle"
                        style="cursor: pointer; width: 16px; height: 16px;" checked>
                    <label for="globalHistoryExpandToggle"
                        style="margin: 0; cursor: pointer; font-size: 12px; font-weight: 600; color: #444;">Hide
                        Details</label>
                </div>
                <button class="unique-close-btn" id="uniqueCloseSidebar">&times;</button>
            </div>
            <div class="unique-mid-sect">
                <div class="unique-bottom-boxes">
                    <div class="unique-left-box" style="border-right:1px solid #ccc">
                        <h4>Cus. Name</h4>
                        <h6 id="lead_user_name"></h6>
                    </div>
                    <div class="unique-right-box">
                        <h4>Cus. Number</h4>
                        <h6 id="lead_user_number"></h6>
                    </div>
                </div>
                <ul class="unique-lead-history" id="followUpHistory">
                    <!-- History items will be added here dynamically -->
                </ul>
            </div>
            <div class="unique-btm-sect">
                <div class="unique-bottom-static d-flex">
                    <div class="unique-left-static" style="border-right:1px solid #ccc">
                        <h4>Lead assigned on</h4>
                        <h6 id="assigned_date_leads"></h6>
                    </div>
                    <div class="unique-right-static">
                        <h4>Lead assigned by</h4>
                        <h6 id="assigned_by_user"></h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Call History Sidebar -->
    <div class="unique-status-sidebar" id="uniqueCallHistorySidebar" style="z-index: 9999;">
        <div class="unique-top-sect">
            <h1 class="unique-sidebar-title"><b>Call History</b></h1>
            <button class="unique-close-btn" id="uniqueCloseCallSidebar">&times;</button>
        </div>
        <div class="unique-mid-sect">
            <div class="unique-bottom-boxes">
                <div class="unique-left-box" style="border-right:1px solid #ccc">
                    <h4>Cus. Name</h4>
                    <h6 id="lead_user_callname"></h6>
                </div>
                <div class="unique-right-box">
                    <h4>Cus. Number</h4>
                    <h6 id="lead_user_callnumber"></h6>
                </div>
            </div>
            <ul class="unique-lead-history" id="followUpCallHistory">
                <!-- Call history items will be added here dynamically -->
            </ul>
        </div>
        <div class="unique-btm-sect">
            <div class="unique-bottom-static d-flex">
                <div class="unique-left-static" style="border-right:1px solid #ccc">
                    <h4>Lead assigned on</h4>
                    <h6 id="assigned_date_callleads"></h6>
                </div>
                <div class="unique-right-static">
                    <h4>Lead assigned by</h4>
                    <h6 id="assigned_by_calluserr"></h6>
                </div>
            </div>
        </div>
    </div>


    <div id="filterCounterPopup" class="filter-counter-popup">
        <div class="filter-counter-content">
            <span id="filteredLeadsCount">0</span> leads filtered
            <button id="closeFilterCounter" class="filter-counter-close">&times;</button>
        </div>
    </div>

    <!-- ============================================================================
         OVERDUE LEADS POPUP - ONE BY ONE
         This popup shows overdue leads one at a time with update status option
         ============================================================================ -->

    <!-- Dedicated CSS for Overdue Popup -->


    <div class="modal-overlay todays-followup-modal" id="overdueNotificationModal">
        <div class="modal-container-eoi">
            <div class="modal-header">
                <div class="overdue-header-content">
                    <div class="overdue-header-left">
                        <div class="overdue-icon-circle">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="overdue-header-title">
                            <h3>Overdue Lead Alert</h3>
                            <p>Please update this lead</p>
                        </div>
                    </div>
                    <div class="overdue-header-right">
                        <div id="overdueRemainingCount">0</div>
                        <div class="overdue-remaining-text">remaining</div>
                    </div>
                </div>
            </div>

            <div class="modal-body">
                <!-- Lead Information -->
                <div class="overdue-lead-info">
                    <div class="overdue-lead-header">
                        <div class="overdue-lead-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="overdue-lead-details">
                            <h4 id="overdueLeadName">Loading...</h4>
                            <p class="overdue-lead-phone">
                                <i class="fas fa-phone phone-pulse"></i>
                                <span id="overdueLeadPhone">-</span>
                            </p>
                        </div>
                    </div>

                    <div class="overdue-lead-grid">
                        <div>
                            <p class="overdue-field-label">Status</p>
                            <p class="overdue-field-value" id="overdueLeadStatus">-</p>
                        </div>
                        <div>
                            <p class="overdue-field-label">Project</p>
                            <p class="overdue-field-value" id="overdueLeadProject">-</p>
                        </div>
                        <div>
                            <p class="overdue-field-label">Followup Date</p>
                            <p class="overdue-field-value text-danger" id="overdueLeadDate">-</p>
                        </div>
                        <div>
                            <p class="overdue-field-label">Followup Time</p>
                            <p class="overdue-field-value text-danger" id="overdueLeadTime">-</p>
                        </div>
                    </div>
                </div>

                <!-- Warning Message -->
                <div class="overdue-warning">
                    <p>
                        <i class="fas fa-clock"></i>
                        This lead is overdue. Please update the status to proceed.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="overdue-actions">
                    <button id="updateOverdueLeadBtn">
                        <i class="fas fa-edit"></i>
                        Update Status
                    </button>
                    <button id="overdueHistoryBtn" class="overdue-history-btn">
                        <i class="fas fa-history"></i>
                    </button>
                    <button id="skipOverdueLeadBtn">
                        Skip
                    </button>
                </div>
                <button id="skipAllOverdueLeadsBtn">
                    <i class="fas fa-times-circle"></i>
                    Skip All Remaining Leads
                </button>
            </div>
        </div>
    </div>

    <!-- Exact Time Follow-up Modal -->
    <div class="modal-overlay" id="exactTimeFollowupModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 100000; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);">
        <div class="modal-container-eoi"
            style="max-width: 500px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div class="modal-header"
                style="background: linear-gradient(135deg, #f093fb, #f5576c); color: white; border-radius: 16px 16px 0 0; padding: 20px; position: relative;">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div
                            style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-clock" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 20px; font-weight: 600;">⏰ Follow-up Time!</h3>
                            <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">It's time to follow up with
                                this lead</p>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: 700;" id="exactTimeRemainingCount">0</div>
                        <div style="font-size: 11px; opacity: 0.9;">remaining</div>
                    </div>
                </div>
            </div>

            <div class="modal-body" style="padding: 30px;">
                <!-- Lead Information -->
                <div
                    style="background: linear-gradient(135deg, #667eea15, #764ba215); border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 2px solid #667eea30;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <div
                            style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0; font-size: 18px; font-weight: 600; color: #2c3e50;"
                                id="exactTimeLeadName">Loading...</h4>
                            <p style="margin: 0; color: #7f8c8d; font-size: 14px;">
                                <i class="fas fa-phone phone-pulse" style="margin-right: 5px;"></i>
                                <span id="exactTimeLeadPhone">-</span>
                            </p>
                        </div>
                    </div>

                    <div
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #667eea30;">
                        <div>
                            <p
                                style="margin: 0 0 5px 0; font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">
                                Status</p>
                            <p style="margin: 0; font-size: 14px; color: #2c3e50; font-weight: 500;"
                                id="exactTimeLeadStatus">-</p>
                        </div>
                        <div>
                            <p
                                style="margin: 0 0 5px 0; font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">
                                Project</p>
                            <p style="margin: 0; font-size: 14px; color: #2c3e50; font-weight: 500;"
                                id="exactTimeLeadProject">-</p>
                        </div>
                        <div>
                            <p
                                style="margin: 0 0 5px 0; font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">
                                Scheduled Date</p>
                            <p style="margin: 0; font-size: 14px; color: #667eea; font-weight: 600;"
                                id="exactTimeLeadDate">-</p>
                        </div>
                        <div>
                            <p
                                style="margin: 0 0 5px 0; font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">
                                Scheduled Time</p>
                            <p style="margin: 0; font-size: 14px; color: #667eea; font-weight: 600;"
                                id="exactTimeLeadTime">-</p>
                        </div>
                    </div>

                    <!-- Remarks Display -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #667eea30;"
                        id="exactTimeRemarksSection">
                        <p
                            style="margin: 0 0 8px 0; font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">
                            Previous Remarks</p>
                        <p style="margin: 0; font-size: 13px; color: #2c3e50; line-height: 1.5;"
                            id="exactTimeLeadRemarks">-</p>
                    </div>
                </div>

                <!-- Info Message -->
                <div class="schedule-note"
                    style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #155724; font-size: 13px;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                        Scheduled follow-up time has arrived. Please update the lead status now.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: nowrap;">
                    <button id="updateExactTimeLeadBtn" class="btn submit-btn"
                        style="flex: 1; min-width: 0; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap;">
                        <i class="fas fa-edit"></i>
                        Update Status
                    </button>
                    <button id="exactTimeHistoryBtn" class="btn"
                        style="background: #4a6ee0; width: max-content; color: white; border: none; padding: 14px; border-radius: 8px; font-size: 18px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-history"></i>
                    </button>
                    <button id="skipExactTimeLeadBtn" class="btn cancel-btn"
                        style="background: #dc3545; color: white; border: none; padding: 14px 4px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s;width: fit-content; white-space: nowrap;">
                        Skip (24h)
                    </button>
                </div>
                <button id="skipAllExactTimeLeadsBtn" class="btn"
                    style="width: 100%; background: #fff; color: #dc3545; border: 2px solid #dc3545; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-times-circle"></i>
                    Skip All (Reschedule for 24h)
                </button>
            </div>
        </div>
    </div>

    <!-- WhatsApp Message Modal -->
    <div class="modal-overlay" id="whatsappModal" style="display: none;">
        <div class="modal-container-eoi add-booking-modal">
            <div class="modal-header">
                <h3>Send WhatsApp Message</h3>
                <button class="modal-close-btn" id="closeWhatsappModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Lead Details -->
                <h4 class="lead-detail-wp">Lead Details</h4>
                <div class="whatsapp-recipient-info">
                    <div class="recipient-name" id="whatsappRecipientName"></div>
                    <div class="recipient-phone" id="whatsappRecipientPhone"></div>
                </div>

                <!-- Quick Messages -->
                <div class="section">
                    <h3>Quick Messages</h3>
                    <div class="whatsapp-message-options grid">
                        <div class="field full-row">
                            <a class="message-option whatsapp-link"
                                data-message="Hello [Name], I hope you're doing well! This is [Your Name] from [Company]. We'd love to connect with you about your interest in our properties.">
                                <i class="fas fa-handshake"></i> Greeting
                            </a>
                        </div>
                        <div class="field full-row">
                            <a class="message-option whatsapp-link"
                                data-message="Hi [Name], I wanted to follow up on our previous conversation about [Project]. Do you have any questions I can help with?">
                                <i class="fas fa-sync-alt"></i> Follow-up
                            </a>
                        </div>
                        <div class="field full-row">
                            <a class="message-option whatsapp-link"
                                data-message="Hello [Name], we have an exciting new offer on [Project] that might interest you. Would you like me to share the details?">
                                <i class="fas fa-gift"></i> Special Offer
                            </a>
                        </div>
                        <div class="field full-row">
                            <a class="message-option whatsapp-link"
                                data-message="Dear [Name], thank you for your interest in [Project]. Here are the details you requested: [Details]">
                                <i class="fas fa-info-circle"></i> Information
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Custom Message -->
                <div class="section">
                    <h3>Custom Message</h3>
                    <div class="field full-row">
                        <fieldset class="fieldset-label custom-msg-fieldset">
                            <legend class="field-legend top-legend">Your Message</legend>
                            <textarea id="customMessage" rows="4"
                                placeholder="Type your custom message here..."></textarea>
                        </fieldset>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="button" class="cancel-btn" id="cancelWhatsapp">Cancel</button>
                    <a id="sendWhatsappLink" class="submit-btn btn" target="_blank">Send Message</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================================
         SECTION 4: JAVASCRIPT INITIALIZATION
         ============================================================================ -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Expose the current user's tablename globally for downstream scripts
            window.currentUserTableName = "<?php echo $_SESSION['tablename'] ?? ''; ?>";
            window.currentUserName = "<?php echo $_SESSION['username'] ?? ''; ?>";
            window.currentUserDisplayName = "<?php echo $_SESSION['name'] ?? ''; ?>";


            const managerToggle = document.getElementById('managerToggle');
            const managerDescription = document.getElementById('managerDescription'); // add this element in HTML

            // --- Manager View Description ---
            function updateManagerDescription() {
                if (managerToggle && managerToggle.checked) {
                    managerDescription.innerHTML = 'Manager view is enabled. You are viewing leads filtered for the selected user.';
                } else {
                    managerDescription.innerHTML = 'Manager view is disabled. You are seeing your own leads only.';
                }
            }

            if (managerToggle && managerDescription) {
                updateManagerDescription();

                managerToggle.addEventListener('change', function () {
                    updateManagerDescription();
                    const section = document.querySelector('.manager-section');
                    if (section) {
                        section.style.transform = 'scale(0.99)';
                        setTimeout(() => {
                            section.style.transform = 'scale(1)';
                        }, 150);
                    }
                });
            }

        });

    </script>





    <?php

    ?>

    <script>
        // Expose current user identifiers before loading bundled scripts
        window.currentUserTableName = "<?php echo $_SESSION['tablename'] ?? ''; ?>";
        window.currentUserName = "<?php echo $_SESSION['username'] ?? ''; ?>";
        window.currentUserDisplayName = "<?php echo $_SESSION['name'] ?? ''; ?>";
        window.canDeleteLeads = <?php echo json_encode(((($_SESSION['tablename'] ?? '') === 'rahul00761') || (($_SESSION['username'] ?? '') === 'rahul00761'))); ?>;
    </script>

    <!-- Audio Popup Player Styles -->


    <!-- Audio Popup Player HTML -->
    <div id="audioPopupPlayer">
        <audio id="popupAudio"></audio>
        <div class="controls">
            <button class="player-btn" id="popupPlayPause">▶</button>
            <span class="time-display" id="popupCurrent">0:00</span>
            <input type="range" id="popupProgress" value="0" min="0" step="1">
            <span class="time-display" id="popupDuration">0:00</span>
            <span id="popupClose">&times;</span>
        </div>
    </div>

    <!-- Audio Popup Player Script -->

    <!-- IVR Edit Lead Popup -->
    <div class="modal-overlay" id="editIvrLeadModal" style="display: none; z-index: 10050;">
        <div class="modal-container-eoi add-booking-modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Edit IVR Lead</h3>
                <button type="button" class="modal-close-btn" id="closeEditIvrModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editIvrLeadForm" novalidate>
                    <input type="hidden" id="editIvrRowId" name="edit_ivr_lead_id">

                    <div class="section">
                        <div class="field full-row">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend top-legend">Full Name <span class="required">*</span>
                                </legend>
                                <input type="text" id="editIvrName" name="edit_ivr_name" class="filter-text-input"
                                    placeholder="Enter full name" required>
                                <div class="error-message" id="ivrNameError" style="display: none;"></div>
                            </fieldset>
                        </div>
                        <div class="field full-row" style="margin-top:15px;">
                            <fieldset class="fieldset-label">
                                <legend class="field-legend top-legend">Email Address</legend>
                                <input type="email" id="editIvrEmail" name="edit_ivr_email" class="filter-text-input"
                                    placeholder="Enter email address">
                                <div class="error-message" id="ivrEmailError" style="display: none;"></div>
                            </fieldset>
                        </div>
                    </div>

                    <div class="form-actions mt-3">
                        <button type="button" class="cancel-btn btn" id="cancelEditIvrModal">Cancel</button>
                        <button type="submit" class="submit-btn btn" id="submitEditIvrBtn">Update Lead</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- DEBUG: Test Overdue Lead System -->
    <!-- COMMENTED OUT TO PREVENT POPUP FROM REAPPEARING AFTER SKIP ALL
    
    -->

    <!-- WhatsApp Bulk Send Modal -->
    <div class="modal-overlay" id="whatsappBulkModal" style="display:none;">
        <div class="modal-container-eoi" style="max-width:580px;width:94%;">
            <div class="modal-header">
                <h3><i class="fab fa-whatsapp" style="color:#25D366;margin-right:7px;"></i>Send via REOS AI</h3>
                <button type="button" class="modal-close-btn" id="closeWhatsappModal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="field-legend" style="margin-top: 10px; margin-bottom:14px;">REOS AI will send a WhatsApp
                    outreach to the leads below. Review and confirm.</p>
                <div
                    style="max-height: 300px; overflow-x:auto;border:1px solid var(--border-color,#e5e7eb);border-radius:8px;">
                    <table id="whatsappLeadsTable" style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border-color,#e5e7eb);">
                                <th class="field-legend" style="padding:9px 12px;text-align:left;font-weight:600;">#
                                </th>
                                <th class="field-legend" style="padding:9px 12px;text-align:left;font-weight:600;">Name
                                </th>
                                <th class="field-legend" style="padding:9px 12px;text-align:left;font-weight:600;">Phone
                                </th>
                                <th class="field-legend" style="padding:9px 12px;text-align:left;font-weight:600;">
                                    Project</th>
                                <th class="field-legend" style="padding:9px 12px;text-align:center;font-weight:600;">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody id="whatsappLeadsTbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
                <div id="whatsappSendSummary"
                    style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
            </div>
            <div class="form-actions mt-3">
                <button type="button" class="cancel-btn btn" id="cancelWhatsappModal">Cancel</button>
                <button type="button" class="submit-btn btn" id="confirmWhatsappSend">
                    <i class="fab fa-whatsapp" style="margin-right:5px;"></i>Confirm &amp; Send
                </button>
            </div>
        </div>
    </div>

    <!-- WhatsApp History Sidebar — Dark Mode -->
    <style>
        /* ── WA Sidebar: dark mode overrides ─────────────────────────────── */
        [data-theme="dark"] #waHistorySidebar {
            background: #111b21 !important;
            box-shadow: -4px 0 32px rgba(0, 0, 0, 0.55) !important;
        }

        /* Chat body — dark WA green-tinted bg */
        [data-theme="dark"] #waHistoryChatBody {
            background-image: url('https://image2url.com/r2/default/images/1774295951971-b6316e6a-1738-447b-afe6-a74eb04ce228.jpg') !important;
            background-size: cover !important;
            background-repeat: repeat !important;
        }

        /* Footer */
        [data-theme="dark"] #waHistorySidebar>div:last-child {
            background: #0b1418 !important;
            border-top-color: #2a3942 !important;
            color: #8696a0 !important;
        }

        /* Bubble wrappers — text colour overrides for inline styles */
        [data-theme="dark"] #waHistoryChatBody .wa-bubble-esha {
            background: #202c33 !important;
            color: #e9edef !important;
        }

        [data-theme="dark"] #waHistoryChatBody .wa-bubble-lead {
            background: #005c4b !important;
            color: #e9edef !important;
        }

        [data-theme="dark"] #waHistoryChatBody .wa-bubble-text {
            color: #e9edef !important;
        }

        [data-theme="dark"] #waHistoryChatBody .wa-bubble-time {
            color: #8696a0 !important;
        }

        [data-theme="dark"] #waHistoryChatBody .wa-bubble-sender-esha {
            color: #53bdeb !important;
        }

        [data-theme="dark"] #waHistoryChatBody .wa-bubble-sender-lead {
            color: #25D366 !important;
        }

        /* Empty state text */
        [data-theme="dark"] #waHistoryChatBody .wa-empty-state {
            color: #8696a0 !important;
        }

        /* Overlay */
        [data-theme="dark"] #waHistoryOverlay {
            background: rgba(0, 0, 0, 0.55) !important;
        }

        [data-theme="dark"] .schedule-note {
            background: linear-gradient(135deg, #667eea15, #764ba215) !important;
        }

        [data-theme="dark"] .waHistoryInputContainer {
            background: #f0f0f000 !important;
        }

        [data-theme="dark"] #waHistoryMsgInput {
            background: #202c33 !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .waHistoryHeader {
            background: #202c33 !important;
        }

        .whatsapp-btn-mobile {
            margin-top: -5px !important;
        }

        /* Attachment Menu - Light */
        .wa-attach-btn:hover {
            background-color: #f0f2f5 !important;
        }

        /* Attachment Menu - Dark */
        [data-theme="dark"] #waAttachMenu {
            background-color: #233138 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5) !important;
        }

        [data-theme="dark"] .wa-attach-btn {
            color: #d1d7db !important;
        }

        [data-theme="dark"] .wa-attach-btn:hover {
            background-color: #182229 !important;
        }

        [data-theme="dark"] #waAttachmentPreview {
            background-color: #202c33 !important;
            color: #d1d7db !important;
            border-bottom: 1px solid #111b21 !important;
        }

        [data-theme="dark"] #waAttachToggleBtn,
        [data-theme="dark"] #waRemoveAttachmentBtn {
            color: #8696a0 !important;
        }
    </style>

    <!-- WhatsApp History Sidebar -->
    <div id="waHistorySidebar" style="
        position:fixed; top:0; bottom:0; right:-420px; width:340px; max-width:100vw;
        background:var(--card-bg,#fff); box-shadow:-4px 0 24px rgba(0,0,0,0.13);
        z-index:20000; display:flex; flex-direction:column;
        transition:right 0.32s cubic-bezier(.4,0,.2,1); font-family:inherit;">

        <input type="hidden" id="waHistoryCurrentRowId" value="">
        <input type="hidden" id="waHistoryCurrentUploadId" value="">

        <div class="waHistoryHeader"
            style="background:#075E54; color:#fff; padding:14px 16px; display:flex; align-items:center; gap:10px; flex-shrink:0;">
            <i class="fab fa-whatsapp" style="font-size:20px; color:#25D366;"></i>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600; font-size:14px;" id="waHistoryLeadName">WhatsApp History</div>
                <div style="font-size:11px; opacity:.75;" id="waHistoryLeadPhone"></div>
                <div style="margin-top:4px; font-size:10px; display:flex; align-items:center; gap:4px;">
                    <label for="waHistoryAutoReplyToggle"
                        style="cursor:pointer; display:flex; align-items:center; gap:4px;">
                        <input type="checkbox" id="waHistoryAutoReplyToggle" checked
                            style="accent-color:#25D366; cursor:pointer;"
                            title="Turn on/off Esha AI Auto-Reply for this lead">
                        <span class="auto-reply-text" style="color: #fff;">REOS Auto-Reply</span>
                    </label>
                </div>
            </div>
            <button id="refreshWaHistoryBtn"
                style="background:none;border:none;color:#fff;font-size:14px; margin-top:5px; cursor:pointer;padding:0 12px;line-height:1;"
                title="Refresh Chat"><i class="fas fa-sync-alt"></i></button>
            <button id="closeWaHistorySidebar"
                style="background:none;border:none;color:#fff;font-size:25px;cursor:pointer;padding:0 4px;line-height:1;">&times;</button>
        </div>

        <div id="waHistoryChatBody"
            style="flex:1; overflow-y:auto; padding:16px 12px; display:flex; flex-direction:column; gap:10px; background-image: url('https://image2url.com/r2/default/images/1774296427195-ddd8f6a8-238d-4e2f-b733-2710ed7e6b03.jpg'); background-size: cover; background-repeat: repeat">
        </div>

        <div id="waAttachmentPreview"
            style="display:none; padding: 6px 10px; background: #e9edef; font-size: 13px; color: #333; align-items: center; justify-content: space-between; border-bottom: 1px solid #d1d7db; flex-shrink: 0; max-height: 40px;">
            <div style="display: flex; align-items: center; gap: 8px; overflow: hidden; width:100%;">
                <div id="waAttachmentPreviewIconBg"
                    style="background:#54656f; color:white; width:26px; height:26px; border-radius:6px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i id="waAttachmentPreviewIcon" class="fas fa-file-alt"
                        style="font-size: 13px; text-align: center;"></i>
                </div>
                <span id="waAttachmentName"
                    style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; font-size: 13px; display:inline-block; max-width:85%;">filename</span>
            </div>
            <button id="waRemoveAttachmentBtn"
                style="background:none; border:none; color: #54656f; cursor:pointer; padding:4px; font-size: 14px; flex-shrink: 0;"
                title="Remove attachment"><i class="fas fa-times"></i></button>
        </div>

        <div class="waHistoryInputContainer"
            style="background:#ECE5DD; padding:10px; border-top:1px solid var(--border-color, #333); display:flex; gap:8px; align-items:center; flex-shrink:0; position:relative;">

            <!-- Attachment Menu Popup (keep it positioned relative to the container) -->
            <div id="waAttachMenu"
                style="display:none; position:absolute; bottom:55px; right:60px; background:white; border-radius:12px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); padding:10px; flex-direction:column; gap:8px; z-index:20005;">
                <button class="wa-attach-btn" data-type="audio"
                    style="border:none; background:none; display:flex; align-items:center; gap:12px; cursor:pointer; padding:8px 16px; border-radius:8px; transition:background 0.2s; width:100%; text-align:left; font-size:14px; font-weight:500; color:#333;">
                    <div
                        style="background:#0FB2A9; color:white; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-headphones" style="font-size:16px;"></i>
                    </div> <span>Audio</span>
                </button>
                <button class="wa-attach-btn" data-type="document"
                    style="border:none; background:none; display:flex; align-items:center; gap:12px; cursor:pointer; padding:8px 16px; border-radius:8px; transition:background 0.2s; width:100%; text-align:left; font-size:14px; font-weight:500; color:#333;">
                    <div
                        style="background:#5F66CD; color:white; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-file-alt" style="font-size:16px;"></i>
                    </div> <span>Document</span>
                </button>
                <button class="wa-attach-btn" data-type="image"
                    style="border:none; background:none; display:flex; align-items:center; gap:12px; cursor:pointer; padding:8px 16px; border-radius:8px; transition:background 0.2s; width:100%; text-align:left; font-size:14px; font-weight:500; color:#333;">
                    <div
                        style="background:#1BA4ED; color:white; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-image" style="font-size:16px;"></i>
                    </div> <span>Image / Gallery</span>
                </button>
                <button class="wa-attach-btn" data-type="video"
                    style="border:none; background:none; display:flex; align-items:center; gap:12px; cursor:pointer; padding:8px 16px; border-radius:8px; transition:background 0.2s; width:100%; text-align:left; font-size:14px; font-weight:500; color:#333;">
                    <div
                        style="background:#EC407A; color:white; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-video" style="font-size:16px;"></i>
                    </div> <span>Video</span>
                </button>
            </div>

            <input type="file" id="waHistoryAttachmentInput" style="display:none;" />
            <input type="hidden" id="waHistoryAttachmentIsDoc" value="false" />

            <input type="text" id="waHistoryMsgInput" placeholder="Type a message..."
                style="flex:1; background:white; color:black; border:1px solid var(--border-color, #444); border-radius:20px; padding:10px 14px; font-size:13px; outline:none; box-shadow:none;">

            <button id="waQrScanBtn" title="Link WhatsApp number via QR"
                style="background:none; border:none; color:#54656f; font-size:19px; cursor:pointer; padding:6px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-qrcode"></i>
            </button>

            <button id="waAttachToggleBtn"
                style="background:none; border:none; color:#54656f; font-size:20px; cursor:pointer; padding:6px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-paperclip"></i>
            </button>

            <button id="waHistorySendMsgBtn"
                style="background:#25D366; color:#fff; border:none; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                <i class="fas fa-paper-plane" style="margin-left:-2px;"></i>
            </button>
        </div>

        <div
            style="background:#ECE5DD; padding:6px 14px 10px; font-size:10px; color:var(--text-muted, #aaa); text-align:center; flex-shrink:0;">
            Outreach sent via <strong>REOS AI</strong>
        </div>
    </div>
    <div id="waQrModal"
        style="display:none; position:fixed; inset:0; z-index:30000; background:rgba(0,0,0,0.55); align-items:center; justify-content:center;">
        <div
            style="background:#fff; border-radius:18px; padding:28px 24px 20px; max-width:340px; width:92%; text-align:center; position:relative; box-shadow:0 8px 40px rgba(0,0,0,0.25);">
            <button id="waQrModalClose"
                style="position:absolute; top:12px; right:14px; background:none; border:none; font-size:20px; cursor:pointer; color:#54656f;">
                <i class="fas fa-times"></i>
            </button>
            <div style="font-size:15px; font-weight:700; color:#111; margin-bottom:4px;">Link WhatsApp</div>
            <div style="font-size:12px; color:#54656f; margin-bottom:14px;">Scan this QR code with your WhatsApp to link
                your number.</div>
            <div id="waQrImageWrap" style="display:flex; align-items:center; justify-content:center; min-height:220px;">
                <div id="waQrSpinner"
                    style="display:none; flex-direction:column; align-items:center; gap:10px; color:#54656f;">
                    <i class="fas fa-spinner fa-spin" style="font-size:28px;"></i>
                    <span style="font-size:13px;">Generating QR...</span>
                </div>
                <img id="waQrImage" src="" alt="QR Code"
                    style="display:none; width:220px; height:220px; border-radius:10px; border:2px solid #e9edef;">
            </div>
            <div id="waQrExpiry" style="font-size:11px; color:#54656f; margin-top:8px; min-height:18px;"></div>
            <button id="waQrRefreshBtn"
                style="margin-top:14px; background:#25D366; color:#fff; border:none; border-radius:22px; padding:9px 28px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px; margin-left:auto; margin-right:auto;">
                <i class="fas fa-sync-alt"></i> Refresh QR
            </button>
        </div>
    </div>

    <div id="waHistoryOverlay"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.28); z-index:19999;"></div>

    <!-- Inject current user data for Esha API salesperson payload -->
    <script>
        window.eshaCurrentUser = {
            tablename: <?= json_encode($_SESSION['tablename'] ?? '') ?>,
            username: <?= json_encode($_SESSION['username'] ?? '') ?>,
            phone: <?= json_encode($_SESSION['phone'] ?? '') ?>,
            tenantId: 'tenant_omega_ba8790e7364b',
            webhookSecret: 'esha-demo-client-2026'
        };
    </script>

    <?php
    $customJs = [
        "assets/js/final_lead.js?v=" . time(),
        "assets/js/whatsapp_esha.js?v=" . time()
    ];
    include "htmlclose.php";
    ?>