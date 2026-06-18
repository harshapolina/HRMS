<!-- Vendor JS -->
<!-- Core JS (load early for libraries like jQuery) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="<?php echo asset_url('assets/js/jquery.dataTables.min.js'); ?>"></script>
<script src="<?php echo asset_url('assets/js/select2.min.js'); ?>"></script>
<script src="<?php echo asset_url('assets/js/dataTables.buttons.min.js'); ?>"></script>
<script src="<?php echo asset_url('assets/js/buttons.colVis.min.js'); ?>"></script>
<script src="<?php echo asset_url('assets/js/all.min.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo asset_url('assets/js/chart_cdn.js'); ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-zoom/2.0.1/chartjs-plugin-zoom.min.js"></script>
<script defer src="<?php echo asset_url('assets/js/dashboard_data.js'); ?>"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->

<!-- Global JS -->
<script defer src="assets/js/header_script.js"></script>


<!-- Page-Specific JS -->
<?php
if (!empty($customJs) && is_array($customJs)) {
  foreach ($customJs as $jsFile) {
    echo '<script defer src="' . htmlspecialchars(asset_url($jsFile)) . '"></script>' . PHP_EOL;
  }
}
?>

<?php
// At top of your PHP file (before output)
$dashboard_filter_month = $_SESSION['dashboard_filter_month'] ?? null;
$dashboard_filter_year = $_SESSION['dashboard_filter_year'] ?? null;

// Define $startDate and $endDate safely. Replace the source with where you actually set them.
// Example: get from GET parameters or from earlier logic
$startDate = isset($startDate) ? $startDate : ($_GET['start_date'] ?? null);
$endDate = isset($endDate) ? $endDate : ($_GET['end_date'] ?? null);
?>
<script>
  // Use json_encode so PHP null becomes JS null and strings are quoted properly
  const dashboardMonth = <?php echo json_encode($dashboard_filter_month, JSON_UNESCAPED_SLASHES); ?>;
  const dashboardYear = <?php echo json_encode($dashboard_filter_year, JSON_UNESCAPED_SLASHES); ?>;

  document.addEventListener('DOMContentLoaded', function () {
    // Only run date code if both startDate and endDate are defined in PHP
    <?php if (!empty($startDate) && !empty($endDate)): ?>
      // PHP outputs safe, quoted JS strings via json_encode
      const startDate = <?php echo json_encode(date('Y-m-d', strtotime($startDate))); ?>;
      const endDate = <?php echo json_encode(date('Y-m-d', strtotime($endDate))); ?>;

      // Helper to set element value only if element exists
      function setIfExists(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
      }

      // Set the isolated filter dates and the main date inputs, safely
      setIfExists('isolatedFilterStartDate', startDate);
      setIfExists('isolatedFilterEndDate', endDate);
      setIfExists('dateFrom', startDate);
      setIfExists('dateTo', endDate);

      // Update or create window.multiFilters safely
      window.multiFilters = window.multiFilters || {};
      window.multiFilters.start_date = startDate;
      window.multiFilters.end_date = endDate;

      console.log('Date filters applied from URL:', startDate, 'to', endDate);
    <?php else: ?>
      // No PHP start/end dates were provided; do nothing (or set defaults)
      // console.log('No start/end PHP dates available');
    <?php endif; ?>
  });
</script>

<!-- Inline JS that depends on DOM -->
<script>
  document.addEventListener("DOMContentLoaded", function () {

    // Dashboard Stats Initialization
    // Skip on dashboard page (has its own logic) and leads page (uses update_status.php for tag counts)
    const pathname = window.location.pathname;
    const skipDashboardData = pathname.includes('dashboard');

    if (!skipDashboardData) {
      fetch(`dashboard_data.php?total=true`)
        .then(response => response.json())
        .then(data => {
          const leadsElem = document.getElementById("user-total-leads");
          const bookingsElem = document.getElementById("user-total-bookings");
          const revenueElem = document.getElementById("user-total-revenue");

          if (leadsElem) leadsElem.textContent = data.myLeads || 0;
          if (bookingsElem) bookingsElem.textContent = data.total_bookings || 0;
          if (revenueElem) revenueElem.textContent = formatRevenue(data.total_revenue || 0);
        })
        .catch(error => console.error('Error fetching dashboard data:', error));
    }

    // Loader initialization or other DOM-dependent code
    const loader = document.getElementById('loader');
    // init loaders etc

  });

  // Helper function for formatting revenue
  function formatRevenue(amount) {
    if (!amount || amount === 0) return "₹0";
    const num = parseFloat(amount);
    if (num >= 1e9) return `₹${(num / 1e9).toFixed(1)}B`;
    if (num >= 1e6) return `₹${(num / 1e6).toFixed(1)}M`;
    if (num >= 1e3) return `₹${(num / 1e3).toFixed(1)}K`;
    return `₹${num}`;
  }




  <!-- Inline Scripts (keep minimal, better move to js file) -->

  function updateCalculate() {
    let agreement = parseFloat(document.getElementById("cagreement").value) || 0;
    let cashback = parseFloat(document.getElementById("ccashback").value) || 0;
    let extraCB = parseFloat(document.getElementById("cccashback").value) || 0;

    let revenue = agreement * (cashback / 100);
    document.getElementById("crevenue").value = revenue;
    document.getElementById("ccrevenue").value = revenue - (agreement * (extraCB / 100));
  }
</script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const loader = document.getElementById('loader');
    // init loaders etc
  });
</script>
<script>
  (async function () {
    // --- PHP values exposed to JS (keep your PHP variables) ---
    const USER_ID = <?php echo json_encode($userid); ?>;
    const NOTIFY_TOKEN = <?php echo json_encode($notify_token); ?>;

    // --- constants / state ---
    const TOGGLE_KEY = 'pushEnabled';
    let sdkLoaded = false;

    // --- device detection ---
    const ua = navigator.userAgent || '';
    const isIOS = /iP(ad|hone|od)/i.test(ua);
    const isAndroid = /Android/i.test(ua);

    // Wait for DOMContentLoaded so elements exist
    if (document.readyState === 'loading') {
      await new Promise(res => document.addEventListener('DOMContentLoaded', res, { once: true }));
    }

    // DOM elements (now safe to query)
    const container = document.getElementById('push-container');
    const toggle = document.getElementById('push-toggle');
    const pushNote = document.getElementById('push-note');
    const setNote = (t) => { if (pushNote) pushNote.textContent = t; };

    // Helper functions
    function loadMntSdkIfNeeded() {
      // SDK was already injected in head; the loader promise is the single source of truth.
      if (window.MNT_SDK_LOADER && window.MNT_SDK_LOADER.promise) return window.MNT_SDK_LOADER.promise;
      // If for some reason loader not present, create fallback injection:
      return new Promise((resolve, reject) => {
        if (document.querySelector('script[data-mnt-sdk]')) {
          // script tag exists but no loader object — assume it will load soon
          const chk = setInterval(() => {
            if (window.MNTNotifier) { clearInterval(chk); resolve(); }
          }, 200);
          // fallback timeout
          setTimeout(() => { clearInterval(chk); reject(new Error('SDK load timeout')); }, 8000);
          return;
        }
        // inject fallback
        const s = document.createElement('script');
        s.type = 'module';
        s.src = 'https://notification.mnts.inn/sdk/mntpushnotifier.js';
        s.setAttribute('data-mnt-sdk', '1');
        s.onload = () => resolve();
        s.onerror = (e) => reject(e);
        document.head.appendChild(s);
      });
    }

    async function ensureSdkReady() {
      try {
        await loadMntSdkIfNeeded();
        sdkLoaded = true;
        console.log('SDK ready (bottom).');
      } catch (err) {
        sdkLoaded = false;
        console.warn('SDK failed to load or timed out (bottom).', err);
        throw err;
      }
    }

    function safeCallVerify() {
      if (sdkLoaded && window.MNTNotifier && typeof window.MNTNotifier.verify === 'function') {
        try {
          window.MNTNotifier.verify(USER_ID, NOTIFY_TOKEN);
          console.log('MNTNotifier.verify() invoked.');
          setNote(isAndroid ? 'Push enabled (Android).' : 'Push enabled — you may be prompted for permission.');
        } catch (err) {
          console.error('Error calling MNTNotifier.verify:', err);
          setNote('Error enabling push (see console).');
        }
      } else {
        console.warn('verify() not available yet; ensure SDK loaded and provides MNTNotifier.verify');
        setNote('Waiting for push SDK to be available...');
      }
    }

    function tryDisablePush() {
      if (sdkLoaded && window.MNTNotifier && typeof window.MNTNotifier.unsubscribe === 'function') {
        try {
          window.MNTNotifier.unsubscribe(USER_ID, NOTIFY_TOKEN);
          setNote('Push disabled.');
          return;
        } catch (e) {
          console.warn('unsubscribe failed', e);
        }
      }
      setNote('Push disabled locally. For full unsubscribe call your backend or SDK API.');
    }

    // Attach listener if toggle exists
    if (toggle) {
      toggle.addEventListener('change', async (ev) => {
        const enabled = !!ev.target.checked;
        localStorage.setItem(TOGGLE_KEY, enabled ? '1' : '0');
        if (enabled) {
          // Ask permission when available
          try {
            if ('Notification' in window && Notification.requestPermission) {
              const perm = await Notification.requestPermission();
              if (perm === 'denied') {
                setNote('Notification permission denied. Open site settings to enable.');
                return;
              }
            }
          } catch (e) {
            console.warn('Notification permission request failed', e);
          }

          // Ensure SDK loaded (we await the head loader promise)
          try {
            await ensureSdkReady();
            safeCallVerify();
          } catch (_err) {
            setNote('Unable to load push SDK. Try opening site in browser.');
          }
        } else {
          tryDisablePush();
        }
      });
    } else {
      console.warn('Toggle element missing — no listener attached.');
    }

    // Initialization flow (Android auto, iOS toggle rules)
    (async function initFlow() {
      // Android: show the toggle and auto-run verify if not explicitly disabled
      if (isAndroid) {
        if (container) container.style.display = 'flex';
        // Default to enabled on Android unless user explicitly turned it off (set to '0')
        const enabled = localStorage.getItem(TOGGLE_KEY) !== '0';
        if (toggle) toggle.checked = enabled;
        if (enabled) {
          localStorage.setItem(TOGGLE_KEY, '1');
          try {
            await ensureSdkReady();
            safeCallVerify();
          } catch (err) {
            setNote('Push SDK failed to load on Android.');
          }
          // Re-run on visibility change (app resume)
          document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
              if (sdkLoaded) safeCallVerify();
              else ensureSdkReady().then(safeCallVerify).catch(() => {/*ignore*/ });
            }
          });
        }
        return;
      }

      // iOS: show toggle (if present) and only call verify when user enables
      if (isIOS) {
        if (container) container.style.display = 'flex';
        const enabled = localStorage.getItem(TOGGLE_KEY) === '1';
        if (toggle) toggle.checked = enabled;
        if (enabled) {
          try {
            await ensureSdkReady();
            safeCallVerify();
          } catch (err) {
            setNote('Unable to load push SDK (iOS).');
          }
        } else {
          setNote('Enable push to receive notifications (iOS requires explicit toggle).');
        }
        return;
      }

      // Other platforms (desktop): show toggle optionally, attempt to restore previous choice
      if (container) container.style.display = 'flex';
      const enabled = localStorage.getItem(TOGGLE_KEY) === '1';
      if (toggle) toggle.checked = enabled;
      if (enabled) {
        try {
          await ensureSdkReady();
          safeCallVerify();
        } catch (err) {
          setNote('Unable to load push SDK.');
        }
      }
    })();
  })();
</script>
</body>

</html>