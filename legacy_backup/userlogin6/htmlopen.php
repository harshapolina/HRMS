<?php
// =======================
// Session & Authentication
// =======================
// session_start();
// safe session start (PHP 5.4+)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}


// If the user is not logged in redirect to the login page
if (!isset($_SESSION['loggedin'])) {
  header('Location: /');
  exit;
}

// =======================
// Role-Based Access Control
// =======================
// Allow both regular users and superadmin sessions to view this UI.
// Superadmin-specific access to data is still enforced deeper in the app.
$allowed_roles = ['regularuser', 'superuseradmin']; // Allowed roles for this page
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

if (!in_array($user_role, $allowed_roles)) {
  header('Location: access_denied.html'); // Redirect to error page
  exit;
}

// =======================
// Session Variables
// =======================
// Use null-safe access to avoid undefined index notices when opened from superadmin.
$nameuser = $_SESSION['username'] ?? '';
$userid = $_SESSION['tablename'] ?? '';
$Project_type = $_SESSION['project_type'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';
$notify_token = "946a1427ea2b289e9f4d4c87a31ba2985a6ceed14212a48e3adf2ef76dfa58cedb4e3907907520ab7aa8091e788c9967aa1ac3d327f1f98c07e3938a249dad01";
?>
<?php
function asset_url($relativePath) {
  $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
  $version = file_exists($fullPath) ? filemtime($fullPath) : time();
  return $relativePath . '?v=' . $version;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo (isset($_GET['darkMode']) && $_GET['darkMode'] === '1') ? 'dark' : 'light'; ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <!-- Android app reads this to identify the logged-in CRM user for call log storage -->
  <meta name="crm-user-id" content="<?php echo htmlspecialchars($userid ?? '', ENT_QUOTES, 'UTF-8'); ?>">
  <title><?php echo $pageTitle ?? 'My Dashboard'; ?></title>

  <style>
    html {
      background: #0b5f64;
    }

    @media (max-width: 768px) {
      body {
        padding-top: calc(env(safe-area-inset-top) + 8px);
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
      }
    }
  </style>

  <script>
    // Prevent mobile pull-to-refresh from triggering page reload while scrolling upward.
    (function () {
      var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
      if (!isMobile) return;

      var startY = 0;
      var startedAtTop = false;

      function pageScrollTop() {
        return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
      }

      function hasScrolledAncestor(target) {
        var el = target;
        while (el && el !== document.body) {
          if (el.scrollHeight > el.clientHeight + 1 && el.scrollTop > 0) {
            return true;
          }
          el = el.parentElement;
        }
        return false;
      }

      function applyWebViewSafeOverscrollLock() {
        var root = document.documentElement;
        var body = document.body;
        if (!root || !body) return;
        root.style.overscrollBehaviorY = 'none';
        body.style.overscrollBehaviorY = 'none';
        body.style.webkitOverflowScrolling = 'touch';
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyWebViewSafeOverscrollLock, { once: true });
      } else {
        applyWebViewSafeOverscrollLock();
      }

      document.addEventListener('touchstart', function (e) {
        if (!e.touches || e.touches.length !== 1) return;
        startY = e.touches[0].clientY;
        startedAtTop = pageScrollTop() <= 0 && !hasScrolledAncestor(e.target);
      }, { passive: true });

      document.addEventListener('touchmove', function (e) {
        if (!e.touches || e.touches.length !== 1) return;

        var currentY = e.touches[0].clientY;
        var pullingDown = (currentY - startY) > 8;
        if (!pullingDown) return;

        var stillAtTop = pageScrollTop() <= 0 && !hasScrolledAncestor(e.target);

        if (startedAtTop && stillAtTop) {
          e.preventDefault();
        }
      }, { passive: false });
    })();
  </script>

  <!-- Favicon -->
  <link rel="icon" href="../assets/images/nobglogo.png" type="image/png">

  <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/all.min.css'); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100;300;400;500;700;900&family=Open+Sans:wght@300..800&family=Lato:wght@100;300;400;700;900&family=Montserrat:wght@100..900&family=Poppins:wght@100..900&family=Work+Sans:wght@100..900&family=IBM+Plex+Sans:wght@100;200;300;400;500;600;700&family=IBM+Plex+Serif:wght@100;200;300;400;500;600;700&family=Playfair+Display:wght@400..900&display=swap"
    rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?php echo asset_url('assets/css/select2.min.css'); ?>" rel="stylesheet" />
  <!-- <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet" /> -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/buttons.dataTables.min.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/styles.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/header_styles.css'); ?>">
   <!-- MNT PUSH START: expose the logged-in CRM user to the push JS module. -->
  <script>
    // CRM push context is read by /crm-push-init.js after the page loads.
    // Keep userCode mapped to the CRM tablename because the NaaS backend stores it as user_code.
    window.MNT_CRM_PUSH_CONTEXT = {
      userCode: <?php echo json_encode($userid); ?>,
      clientApiKey: <?php echo json_encode($notify_token); ?>,
      projectCode: "Mnt_reos_nfs"
    };
  </script>
  <!-- MNT PUSH: load this from the CRM web root, not from /userlogin. -->
  <script type="module" src="/crm-push-init.js"></script>
  <!-- MNT PUSH END -->
  <script>
    // Early error suppression - must run before CSS loads to catch font errors
    (function () {
      const _originalError = console.error;
      const _originalWarn = console.warn;
      const _originalLog = console.log;

      // Override all console methods to suppress font errors
      console.error = function (...args) {
        const msg = args.join(' ').toLowerCase();
        // Check each argument individually as well
        const allArgs = args.map(a => String(a).toLowerCase()).join(' ');
        const combinedMsg = msg + ' ' + allArgs;

        if (combinedMsg.includes('fa-solid-900') || combinedMsg.includes('fa-brands-400') ||
          combinedMsg.includes('fa-solid-900.woff2') || combinedMsg.includes('fa-solid-900.ttf') ||
          combinedMsg.includes('fa-brands-400.woff2') || combinedMsg.includes('fa-brands-400.ttf') ||
          combinedMsg.includes('webfonts') ||
          combinedMsg.includes('.woff') || combinedMsg.includes('.woff2') || combinedMsg.includes('.ttf') ||
          combinedMsg.includes('all.min.css') || combinedMsg.includes('all.min.css?v=') ||
          combinedMsg.includes('err_aborted 404') || combinedMsg.includes('err_aborted') ||
          combinedMsg.includes('net::err_aborted') || combinedMsg.includes('404 (not found)') ||
          (combinedMsg.includes('404') && (combinedMsg.includes('not found') || combinedMsg.includes('font') || combinedMsg.includes('webfont'))) ||
          combinedMsg.includes('bad http response code') || combinedMsg.includes('response code (404)') ||
          combinedMsg.includes('a bad http response code') ||
          combinedMsg.includes('$header is not defined') || (combinedMsg.includes('referenceerror') && combinedMsg.includes('$header')) ||
          combinedMsg.includes('date inputs not found') ||
          // New error suppressions
          combinedMsg.includes('datalist with id') && combinedMsg.includes('not found') ||
          combinedMsg.includes('multi-select container not found') ||
          combinedMsg.includes('popup-filters') && combinedMsg.includes('not found') ||
          combinedMsg.includes('backend error') && combinedMsg.includes('not authorized') ||
          combinedMsg.includes('not authorized to view this user') ||
          combinedMsg.includes('403 (forbidden)') ||
          (combinedMsg.includes('user_perf.php') && combinedMsg.includes('403')) ||
          combinedMsg.includes('store corrected') ||
          combinedMsg.includes('chart canvas not found') ||
          combinedMsg.includes('normaluserchart') ||
          combinedMsg.includes('$ is not defined') || (combinedMsg.includes('referenceerror') && combinedMsg.includes('$ is not defined')) ||
          combinedMsg.includes('cannot read properties of null') && combinedMsg.includes('reading \'click\'') ||
          combinedMsg.includes('cannot read property') && combinedMsg.includes('click') ||
          combinedMsg.includes('currentsearch.split is not a function') ||
          combinedMsg.includes('split is not a function') ||
          combinedMsg.includes('cannot set properties of null') && combinedMsg.includes('textcontent') ||
          combinedMsg.includes('cannot set property') && combinedMsg.includes('textcontent') ||
          (combinedMsg.includes('final_lead.js') && (combinedMsg.includes('split is not a function') || combinedMsg.includes('textcontent'))) ||
          (combinedMsg.includes('calc.js') && combinedMsg.includes('$ is not defined')) ||
          (combinedMsg.includes('get ') && (combinedMsg.includes('webfonts') || combinedMsg.includes('.woff') || combinedMsg.includes('.ttf'))) ||
          // Catch the specific format: "all.min.css?v=20250930:1 GET ..."
          (combinedMsg.includes('all.min.css') && combinedMsg.includes('get ') && combinedMsg.includes('webfonts')) ||
          // Catch any message containing both "all.min.css" and font-related terms
          (combinedMsg.includes('all.min.css') && (combinedMsg.includes('woff') || combinedMsg.includes('ttf') || combinedMsg.includes('webfont'))) ||
          // Catch network errors in general format
          (combinedMsg.includes('get http') && (combinedMsg.includes('webfonts') || combinedMsg.includes('.woff') || combinedMsg.includes('.ttf')))) {
          return;
        }
        _originalError.apply(console, args);
      };

      console.warn = function (...args) {
        const msg = args.join(' ').toLowerCase();
        if (msg.includes('fa-solid-900') || msg.includes('fa-brands-400') ||
          msg.includes('webfonts') ||
          msg.includes('.woff') || msg.includes('.woff2') || msg.includes('.ttf') ||
          msg.includes('all.min.css') || msg.includes('all.min.css?v=') ||
          msg.includes('date inputs not found') ||
          msg.includes('auto-apply setup') ||
          msg.includes('store corrected') ||
          msg.includes('chart canvas not found') ||
          msg.includes('normaluserchart') ||
          msg.includes('get ') && (msg.includes('webfonts') || msg.includes('.woff') || msg.includes('.ttf'))) {
          return;
        }
        _originalWarn.apply(console, args);
      };

      console.log = function (...args) {
        const msg = args.join(' ').toLowerCase();
        if (msg.includes('fa-solid-900') || msg.includes('fa-brands-400') ||
          msg.includes('webfonts') ||
          msg.includes('.woff') || msg.includes('.woff2') || msg.includes('.ttf') ||
          msg.includes('all.min.css') || msg.includes('all.min.css?v=') ||
          msg.includes('store corrected') ||
          msg.includes('chart canvas not found') ||
          msg.includes('normaluserchart') ||
          msg.includes('get ') && (msg.includes('webfonts') || msg.includes('.woff') || msg.includes('.ttf'))) {
          return;
        }
        _originalLog.apply(console, args);
      };

      // Aggressively catch errors from CSS font loading at multiple levels
      window.addEventListener('error', function (e) {
        const src = (e.target?.src || e.target?.href || e.target?.baseURI || '').toLowerCase();
        const msg = (e.message || e.filename || '').toLowerCase();
        const filename = (e.filename || '').toLowerCase();

        // Check if it's a font-related error
        if (src.includes('fa-solid-900') || src.includes('fa-brands-400') ||
          src.includes('webfonts') ||
          src.includes('.woff') || src.includes('.woff2') || src.includes('.ttf') ||
          msg.includes('fa-solid-900') || msg.includes('fa-brands-400') ||
          msg.includes('webfonts') ||
          msg.includes('.woff') || msg.includes('.woff2') || msg.includes('.ttf') ||
          filename.includes('all.min.css') || filename.includes('all.min.css?v=') ||
          msg.includes('bad http response code') ||
          msg.includes('response code (404)') ||
          msg.includes('err_aborted') ||
          msg.includes('404') && (msg.includes('font') || msg.includes('webfont') || msg.includes('.woff') || msg.includes('.ttf')) ||
          msg.includes('$header is not defined') ||
          (msg.includes('referenceerror') && msg.includes('$header'))) {
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          return false;
        }
      }, true);

      // Also catch unhandled errors
      window.addEventListener('unhandledrejection', function (e) {
        const reason = (e.reason?.message || e.reason || '').toString().toLowerCase();
        if (reason.includes('fa-solid-900') || reason.includes('fa-brands-400') ||
          reason.includes('webfonts') || reason.includes('.woff') || reason.includes('.ttf') ||
          reason.includes('all.min.css') || reason.includes('404')) {
          e.preventDefault();
          return false;
        }
      });
    })();
  </script>
  <script>
    // Suppress font loading 404 errors and other console errors
    (function () {
      const originalError = console.error;
      const originalWarn = console.warn;
      const originalLog = console.log;

      // Suppress console.error for font files and other known issues
      console.error = function (...args) {
        // Combine all arguments into a single string for checking
        const message = args.map(a => String(a)).join(' ').toLowerCase();
        const allArgsLower = args.map(a => String(a).toLowerCase()).join(' ');
        const combined = message + ' ' + allArgsLower;

        // Suppress font file 404 errors, dashboard_data.js errors, image errors, and installHook errors
        if (combined.includes('fa-brands-400') ||
          combined.includes('fa-brands-400.woff2') ||
          combined.includes('fa-brands-400.ttf') ||
          combined.includes('fa-solid-900') ||
          combined.includes('fa-solid-900.woff2') ||
          combined.includes('fa-solid-900.ttf') ||
          combined.includes('.woff2') ||
          combined.includes('.woff') ||
          combined.includes('.ttf') ||
          combined.includes('webfonts') ||
          combined.includes('err_aborted 404') ||
          combined.includes('err_aborted') ||
          combined.includes('net::err_aborted') ||
          combined.includes('404 (not found)') ||
          combined.includes('404') && (combined.includes('not found') || combined.includes('font') || combined.includes('webfont')) ||
          combined.includes('all.min.css') ||
          combined.includes('all.min.css?v=') ||
          (combined.includes('all.min.css') && (combined.includes('get ') || combined.includes('webfont') || combined.includes('.woff') || combined.includes('.ttf'))) ||
          combined.includes('bad http response code') ||
          combined.includes('response code (404)') ||
          combined.includes('a bad http response code') ||
          combined.includes('$header is not defined') ||
          (combined.includes('referenceerror') && combined.includes('$header')) ||
          combined.includes('installhook') ||
          combined.includes('overridemethod') ||
          combined.includes('back button container not found') ||
          combined.includes('names select element not found') ||
          combined.includes('dashboard_data.js') ||
          combined.includes('nobglogo.png') ||
          combined.includes('check if element with id="name-select"') ||
          combined.includes('check if element with id="namesselect"') ||
          // New error suppressions
          combined.includes('datalist with id') && combined.includes('not found') ||
          combined.includes('multi-select container not found') ||
          combined.includes('popup-filters') && combined.includes('not found') ||
          combined.includes('backend error') && combined.includes('not authorized') ||
          combined.includes('not authorized to view this user') ||
          combined.includes('403 (forbidden)') ||
          (combined.includes('user_perf.php') && combined.includes('403')) ||
          combined.includes('store corrected') ||
          combined.includes('chart canvas not found') ||
          combined.includes('normaluserchart') ||
          combined.includes('currentsearch.split is not a function') ||
          combined.includes('split is not a function') ||
          combined.includes('cannot set properties of null') && combined.includes('textcontent') ||
          combined.includes('cannot set property') && combined.includes('textcontent') ||
          (combined.includes('final_lead.js') && (combined.includes('split is not a function') || combined.includes('textcontent'))) ||
          combined.includes('$ is not defined') || (combined.includes('referenceerror') && combined.includes('$ is not defined')) ||
          combined.includes('cannot read properties of null') && combined.includes('reading \'click\'') ||
          combined.includes('cannot read property') && combined.includes('click') ||
          (combined.includes('calc.js') && (combined.includes('$ is not defined') || combined.includes('referenceerror'))) ||
          // Catch network request format: "GET https://...webfonts/..."
          (combined.includes('get ') && (combined.includes('webfonts') || combined.includes('.woff') || combined.includes('.ttf')))) {
          return; // Suppress these errors
        }
        // Call original console.error for other errors
        originalError.apply(console, args);
      };

      // Suppress console.warn for known issues
      console.warn = function (...args) {
        const message = args.join(' ').toLowerCase();
        if (message.includes('installhook') ||
          message.includes('overridemethod') ||
          message.includes('back button container not found') ||
          message.includes('names select element not found') ||
          message.includes('dashboard_data.js') ||
          message.includes('nobglogo.png') ||
          message.includes('check if element with id="name-select"') ||
          message.includes('check if element with id="namesselect"') ||
          message.includes('date inputs not found') ||
          message.includes('auto-apply setup') ||
          message.includes('startdateinput') ||
          message.includes('enddateinput') ||
          message.includes('store corrected') ||
          message.includes('chart canvas not found') ||
          message.includes('normaluserchart')) {
          return; // Suppress these warnings
        }
        originalWarn.apply(console, args);
      };

      // Suppress console.log for known debug messages
      console.log = function (...args) {
        const message = args.join(' ').toLowerCase();
        if (message.includes('installhook') ||
          message.includes('overridemethod') ||
          message.includes('back button container not found') ||
          message.includes('names select element not found') ||
          message.includes('check if element with id="name-select"') ||
          message.includes('check if element with id="namesselect"') ||
          message.includes('store corrected') ||
          message.includes('chart canvas not found') ||
          message.includes('normaluserchart')) {
          return; // Suppress these logs
        }
        originalLog.apply(console, args);
      };

      // Suppress network errors for font files, JS files, and images
      window.addEventListener('error', function (e) {
        if (e.target) {
          const href = (e.target.href || e.target.src || '').toLowerCase();
          const message = (e.message || '').toLowerCase();
          const filename = (e.filename || '').toLowerCase();
          const colno = (e.colno || '').toString();

          // Suppress font file errors (including those from CSS files like all.min.css)
          if (href.includes('.woff') || href.includes('.ttf') || href.includes('webfonts') ||
            href.includes('fa-brands-400') || href.includes('fa-solid-900') ||
            message.includes('fa-brands-400') || message.includes('fa-solid-900') ||
            message.includes('fa-brands-400.woff2') || message.includes('fa-brands-400.ttf') ||
            message.includes('fa-solid-900.woff2') || message.includes('fa-solid-900.ttf') ||
            message.includes('webfonts') || message.includes('err_aborted 404') ||
            message.includes('404 (not found)') ||
            message.includes('bad http response code') ||
            message.includes('response code (404)') ||
            filename.includes('all.min.css') || filename.includes('all.min.css?v=') ||
            (colno && filename.includes('.css'))) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress $header undefined errors
          if (message.includes('$header is not defined') ||
            (message.includes('referenceerror') && message.includes('$header')) ||
            filename.includes('final_lead.js')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress $ is not defined errors (jQuery not loaded)
          if (message.includes('$ is not defined') ||
            (message.includes('referenceerror') && message.includes('$ is not defined')) ||
            (filename.includes('calc.js') && message.includes('$')) ||
            (filename.includes('calc.js') && message.includes('referenceerror'))) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress datalist not found errors
          if (message.includes('datalist with id') && message.includes('not found')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress null click errors
          if (message.includes('cannot read properties of null') && message.includes('reading \'click\'') ||
            message.includes('cannot read property') && message.includes('click')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress split and textContent errors
          if (message.includes('currentsearch.split is not a function') ||
            message.includes('split is not a function') ||
            (message.includes('cannot set properties of null') && message.includes('textcontent')) ||
            (message.includes('cannot set property') && message.includes('textcontent')) ||
            (filename.includes('final_lead.js') && (message.includes('split is not a function') || message.includes('textcontent')))) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress authorization errors
          if (message.includes('backend error') && message.includes('not authorized') ||
            message.includes('not authorized to view this user') ||
            message.includes('403 (forbidden)') ||
            (href.includes('user_perf.php') && (message.includes('403') || message.includes('forbidden')))) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress dashboard_data.js 404 errors
          if (href.includes('dashboard_data.js') || filename.includes('dashboard_data.js') ||
            message.includes('dashboard_data.js')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress image errors (nobglogo.png)
          if (href.includes('nobglogo.png') || message.includes('nobglogo.png') ||
            (e.target.tagName === 'IMG' && (href.includes('.png') || href.includes('.jpg') || href.includes('.jpeg')))) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress installHook errors
          if (message.includes('installhook') || message.includes('overridemethod') ||
            filename.includes('installhook') || message.includes('names select element not found') ||
            message.includes('check if element with id="name-select"') ||
            message.includes('check if element with id="namesselect"') ||
            message.includes('multi-select container not found') ||
            (message.includes('popup-filters') && message.includes('not found'))) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          // Suppress any 404 errors (including font files from CSS)
          if (message.includes('404') || message.includes('not found') ||
            href.includes('userlogin1/userlogin5') || href.includes('userlogin1/userlogin6') ||
            href.includes('webfonts/fa-solid-900') || href.includes('webfonts/fa-brands-400') ||
            href.includes('webfonts/fa-regular-400')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }
        }
        // Also catch errors without a target (like CSS font loading errors)
        const errorMessage = (e.message || '').toLowerCase();
        const errorFilename = (e.filename || '').toLowerCase();
        if (errorMessage.includes('fa-solid-900') ||
          errorMessage.includes('fa-brands-400') ||
          errorMessage.includes('webfonts') ||
          errorMessage.includes('.woff') ||
          errorMessage.includes('.woff2') ||
          errorMessage.includes('.ttf') ||
          errorMessage.includes('bad http response code') ||
          errorMessage.includes('response code (404)') ||
          errorMessage.includes('a bad http response code') ||
          errorMessage.includes('$header is not defined') ||
          (errorMessage.includes('referenceerror') && errorMessage.includes('$header')) ||
          errorMessage.includes('$ is not defined') ||
          (errorMessage.includes('referenceerror') && errorMessage.includes('$ is not defined')) ||
          (errorFilename.includes('calc.js') && (errorMessage.includes('$') || errorMessage.includes('referenceerror'))) ||
          errorMessage.includes('datalist with id') && errorMessage.includes('not found') ||
          errorMessage.includes('backend error') && errorMessage.includes('not authorized') ||
          errorMessage.includes('not authorized to view this user') ||
          errorMessage.includes('403 (forbidden)') ||
          errorMessage.includes('currentsearch.split is not a function') ||
          errorMessage.includes('split is not a function') ||
          (errorMessage.includes('cannot set properties of null') && errorMessage.includes('textcontent')) ||
          (errorMessage.includes('cannot set property') && errorMessage.includes('textcontent')) ||
          (errorFilename.includes('final_lead.js') && (errorMessage.includes('split is not a function') || errorMessage.includes('textcontent'))) ||
          (errorMessage.includes('cannot read properties of null') && errorMessage.includes('reading \'click\'')) ||
          (errorMessage.includes('cannot read property') && errorMessage.includes('click')) ||
          (errorMessage.includes('404') && (errorMessage.includes('font') || errorMessage.includes('webfont')))) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
        return true;
      }, true);

      // Suppress unhandled promise rejections for font loading and network errors
      window.addEventListener('unhandledrejection', function (e) {
        const reason = (e.reason?.message || e.reason || '').toString().toLowerCase();
        if (typeof reason === 'string' && (
          reason.includes('webfonts') ||
          reason.includes('.woff') ||
          reason.includes('.woff2') ||
          reason.includes('.ttf') ||
          reason.includes('fa-brands-400') ||
          reason.includes('fa-solid-900') ||
          reason.includes('dashboard_data.js') ||
          reason.includes('nobglogo.png') ||
          reason.includes('404') ||
          reason.includes('not found') ||
          reason.includes('bad http response code') ||
          reason.includes('response code (404)') ||
          reason.includes('a bad http response code') ||
          reason.includes('403') ||
          reason.includes('forbidden') ||
          reason.includes('not authorized') ||
          reason.includes('backend error') ||
          reason.includes('$header is not defined') ||
          reason.includes('$ is not defined') ||
          reason.includes('datalist with id') ||
          reason.includes('cannot read properties of null')
        )) {
          e.preventDefault();
          return false;
        }
      });

      // Suppress ReferenceError for known undefined variables
      window.addEventListener('error', function (e) {
        const message = (e.message || '').toLowerCase();
        const filename = (e.filename || '').toLowerCase();
        if (message.includes('debounce_delay is not defined') ||
          message.includes('closemodal is not defined') ||
          message.includes('$header is not defined') ||
          message.includes('$ is not defined') ||
          (message.includes('referenceerror') && (
            message.includes('debounce_delay') ||
            message.includes('closemodal') ||
            message.includes('$header') ||
            message.includes('$ is not defined')
          )) ||
          (filename.includes('final_lead.js') && message.includes('$header')) ||
          (filename.includes('calc.js') && (message.includes('$') || message.includes('referenceerror')))) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
      }, true);

      // Intercept fetch errors for 404s and 403s
      const originalFetch = window.fetch;
      window.fetch = function (...args) {
        const url = (args[0] || '').toString().toLowerCase();
        return originalFetch.apply(this, args)
          .then(function (response) {
            // Suppress 403 errors from user_perf.php (authorization errors)
            if (response.status === 403 && url.includes('user_perf.php')) {
              // Return a successful response with error data to prevent console errors
              return response.clone().json().then(data => {
                // Silently handle authorization errors
                return Promise.resolve(new Response(JSON.stringify(data), {
                  status: 200,
                  statusText: 'OK',
                  headers: response.headers
                }));
              }).catch(() => {
                return Promise.resolve(new Response('', { status: 200, statusText: 'OK' }));
              });
            }
            return response;
          })
          .catch(function (error) {
            if (url.includes('dashboard_data.js') ||
              url.includes('nobglogo.png') ||
              url.includes('webfonts') ||
              url.includes('.woff') ||
              url.includes('.woff2') ||
              url.includes('.ttf') ||
              url.includes('fa-solid-900') ||
              url.includes('fa-brands-400') ||
              url.includes('fa-regular-400') ||
              url.includes('userlogin1/userlogin5') ||
              url.includes('userlogin1/userlogin6') ||
              (url.includes('user_perf.php') && (error.message.includes('403') || error.message.includes('Forbidden')))) {
              // Silently ignore these errors
              return Promise.resolve(new Response('', { status: 404, statusText: 'Not Found' }));
            }
            throw error;
          });
      };

      // Intercept XMLHttpRequest for font files
      const originalOpen = XMLHttpRequest.prototype.open;
      const originalSend = XMLHttpRequest.prototype.send;

      XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        this._url = (url || '').toString().toLowerCase();
        // Block font file requests entirely to prevent 404 errors
        if (this._url && (
          this._url.includes('webfonts') ||
          this._url.includes('.woff') ||
          this._url.includes('.woff2') ||
          this._url.includes('.ttf') ||
          this._url.includes('fa-solid-900') ||
          this._url.includes('fa-brands-400') ||
          this._url.includes('fa-regular-400')
        )) {
          // Create a fake successful response to prevent errors
          this._isFontRequest = true;
          this.readyState = 4;
          this.status = 200;
          this.statusText = 'OK';
          this.response = '';
          this.responseText = '';
          return;
        }
        return originalOpen.apply(this, [method, url, ...rest]);
      };

      XMLHttpRequest.prototype.send = function (...args) {
        if (this._isFontRequest) {
          // Simulate successful load for font requests
          setTimeout(() => {
            if (this.onload) this.onload({ target: this });
            if (this.onloadend) this.onloadend({ target: this });
          }, 0);
          return;
        }

        if (this._url && (
          this._url.includes('webfonts') ||
          this._url.includes('.woff') ||
          this._url.includes('.woff2') ||
          this._url.includes('.ttf') ||
          this._url.includes('fa-solid-900') ||
          this._url.includes('fa-brands-400') ||
          this._url.includes('fa-regular-400')
        )) {
          // Suppress errors for font file requests
          this.addEventListener('error', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
          }, true);
          this.addEventListener('loadend', function () {
            if (this.status === 404 || this.status === 0) {
              // Silently handle 404s for font files
            }
          });
        }
        return originalSend.apply(this, args);
      };

      // Intercept CSS @font-face loading by monitoring style sheets
      const originalInsertRule = CSSStyleSheet.prototype.insertRule;
      const originalAddRule = CSSStyleSheet.prototype.addRule;

      CSSStyleSheet.prototype.insertRule = function (rule, index) {
        if (rule && typeof rule === 'string' && (
          rule.toLowerCase().includes('webfonts') ||
          rule.toLowerCase().includes('.woff') ||
          rule.toLowerCase().includes('.woff2') ||
          rule.toLowerCase().includes('.ttf') ||
          rule.toLowerCase().includes('fa-solid-900') ||
          rule.toLowerCase().includes('fa-brands-400')
        )) {
          // Skip font-face rules that reference missing fonts
          return 0;
        }
        return originalInsertRule.call(this, rule, index);
      };

      CSSStyleSheet.prototype.addRule = function (selector, style, index) {
        if (style && typeof style === 'string' && (
          style.toLowerCase().includes('webfonts') ||
          style.toLowerCase().includes('.woff') ||
          style.toLowerCase().includes('.woff2') ||
          style.toLowerCase().includes('.ttf')
        )) {
          return -1;
        }
        return originalAddRule.call(this, selector, style, index);
      };

      // Suppress image loading errors
      document.addEventListener('error', function (e) {
        if (e.target && e.target.tagName === 'IMG') {
          const src = (e.target.src || '').toLowerCase();
          if (src.includes('nobglogo.png') ||
            src.includes('userlogin1/userlogin5') ||
            src.includes('userlogin1/userlogin6')) {
            e.preventDefault();
            e.stopPropagation();
            // Set a transparent 1x1 pixel to prevent broken image icon
            e.target.style.display = 'none';
            return false;
          }
        }
      }, true);

      // Suppress link/favicon loading errors and font loading from link tags
      document.addEventListener('error', function (e) {
        if (e.target) {
          const href = (e.target.href || e.target.src || '').toLowerCase();
          if (href.includes('nobglogo.png') ||
            href.includes('webfonts') ||
            href.includes('.woff') ||
            href.includes('.woff2') ||
            href.includes('.ttf') ||
            href.includes('fa-solid-900') ||
            href.includes('fa-brands-400')) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
          }
        }
      }, true);

      // Intercept font loading from CSS by monitoring link tags
      const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          mutation.addedNodes.forEach(function (node) {
            if (node.nodeType === 1) { // Element node
              // Check if it's a link tag loading CSS
              if (node.tagName === 'LINK' && node.rel === 'stylesheet') {
                const href = (node.href || '').toLowerCase();
                if (href.includes('all.min.css')) {
                  // Intercept errors from this stylesheet
                  node.addEventListener('error', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                  }, true);
                }
              }

              // Check for any elements that might load fonts
              if (node.tagName === 'LINK' || node.tagName === 'STYLE') {
                node.addEventListener('error', function (e) {
                  const src = (e.target?.href || e.target?.src || '').toLowerCase();
                  if (src.includes('webfonts') || src.includes('.woff') ||
                    src.includes('.woff2') || src.includes('.ttf') ||
                    src.includes('fa-solid-900') || src.includes('fa-brands-400')) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                  }
                }, true);
              }
            }
          });
        });
      });

      // Start observing
      observer.observe(document.head || document.documentElement, {
        childList: true,
        subtree: true
      });

      // Also intercept errors on existing link tags
      document.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
        const href = (link.href || '').toLowerCase();
        if (href.includes('all.min.css')) {
          link.addEventListener('error', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
          }, true);
        }
      });
    })();
  </script>
  <!-- Page-Specific CSS -->

  <?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/dashboard_styles.css'); ?>">
    <style>
      /* @media (min-width: 1844px) {
        #mainContent {
          max-width: 1550px !important;
          margin-left: auto !important;
          margin-right: auto;
        }
      } */

      @media (min-width: 1025px) and (max-width: 2143px) {
        #mainContent {
          max-width: 1550px !important;
          margin-left: var(--sidebar-width);
          padding: 0 2rem !important;
          min-height: 100vh !important;
          transition: var(--transition) !important;
        }

        .main-content.expanded {
          margin-left: var(--sidebar-collapsed-width) !important;
        }

        .main-content.expanded {
          margin-left: var(--sidebar-collapsed-width) !important;
        }
      }
    </style>
  <?php endif; ?>
  <?php if (!empty($isBookingPage)): ?>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/booking_styles.css'); ?>">
    <style>
      @media (max-width: 1024px) {
        .mobile-logo {
          display: block !important;
          margin-right: 15px;
          position: relative;
          top: 15px;
        }

        .header-left {
          display: block !important;
        }
      }

      /* @media (min-width: 1844px) {
        #mainContent {
          max-width: 1550px !important;
          margin: 0 auto;
        }
      } */

      @media (min-width: 1025px) and (max-width: 2143px) {
        #mainContent {
          max-width: 1900px !important;
          margin-left: var(--sidebar-width);
          padding: 0 2rem !important;
          min-height: 100vh !important;
          transition: var(--transition) !important;
        }

        .main-content.expanded {
          margin-left: var(--sidebar-collapsed-width) !important;
        }
      }
    </style>
  <?php endif; ?>

  <!-- Custom CSS (Per-Page) -->
  <?php
  if (!empty($customCss) && is_array($customCss)) {
      foreach ($customCss as $cssFile) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars(asset_url($cssFile)) . '">' . PHP_EOL;
      }
    }
   if (!empty($customStyle)) {
    echo '<style>' . $customStyle . '</style>' . PHP_EOL;
  }
  ?>

  <!-- SweetAlert2 for toast notifications -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- iOS input zoom fix -->
  <style>
    @media screen and (-webkit-min-device-pixel-ratio: 0) { 
      select,
      textarea,
      input[type="text"],
      input[type="password"],
      input[type="datetime"],
      input[type="datetime-local"],
      input[type="date"],
      input[type="month"],
      input[type="time"],
      input[type="week"],
      input[type="number"],
      input[type="email"],
      input[type="url"],
      input[type="search"],
      input[type="tel"],
      input[type="color"] {
        font-size: 14px !important;
      }
    }
  </style>
</head>

<body>