// assets/js/user_perf_modal.js
// Isolated module for UPM (User Performance Modal)
(function () {
  'use strict';

  const API = './user_perf.php';
  const openBtn = document.getElementById('upm-open-btn');
  const modal = document.getElementById('upm-modal');
  const closeBtns = [document.getElementById('upm-close'), document.getElementById('upm-close-2')].filter(Boolean);
  const userSelect = document.getElementById('upm-user-select');
  const monthSelect = document.getElementById('upm-month-select');
  const startInp = document.getElementById('upm-start');
  const endInp = document.getElementById('upm-end');
  const loadBtn = document.getElementById('upm-load');
  const cardsContainer = document.getElementById('upm-cards');

  let dailyChart = null;
  let statusChart = null;

  function showModal() { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  function hideModal() { modal.style.display = 'none'; document.body.style.overflow = ''; }

  // Update title and subtitle dynamically based on selections
  function updateTitle() {
    const titleEl = document.getElementById('upm-title');
    const subtitleEl = document.getElementById('upm-subtitle');
    if (!titleEl) return;

    const userName = userSelect.options[userSelect.selectedIndex]?.textContent || '';
    const monthValue = monthSelect.value;
    
    const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    let displayText = 'User Performance';
    let subtitleText = 'Daily activity, status distribution & counts';
    
    if (userName && userName !== 'Select user' && userName !== 'Loading users...' && userName !== 'No users available' && userName !== 'Failed to load users') {
      displayText = `User Performance — ${userName}`;
      
      if (monthValue && monthValue !== '' && monthValue !== 'custom') {
        const monthName = monthNames[parseInt(monthValue)] || '';
        displayText += ` (${monthName})`;
        subtitleText = `${monthName} activity, status distribution & counts`;
      } else if (monthValue === 'custom') {
        const start = startInp.value;
        const end = endInp.value;
        if (start && end) {
          displayText += ` (${start} to ${end})`;
          subtitleText = `Custom range activity, status distribution & counts`;
        } else {
          displayText += ` (Custom Range)`;
        }
      }
    }
    
    titleEl.textContent = displayText;
    if (subtitleEl) {
      subtitleEl.textContent = subtitleText;
    }
  }

  // Toggle custom date inputs
  monthSelect.addEventListener('change', () => {
    if (monthSelect.value === 'custom') {
      startInp.style.display = 'inline-block'; endInp.style.display = 'inline-block';
    } else {
      startInp.style.display = 'none'; endInp.style.display = 'none';
    }
    updateTitle(); // Update title when month changes
  });

  // Set default user and month selections
  function setDefaults(currentUser) {
    // Set current user if available
    if (currentUser) {
      userSelect.value = currentUser;
      // Update combobox display
      const selectedOption = Array.from(userSelect.options).find(opt => opt.value === currentUser);
      if (selectedOption) {
        const display = document.getElementById('upm-user-display');
        if (display) {
          display.textContent = selectedOption.textContent;
        }
        console.log('Default user set to:', currentUser, selectedOption.textContent);
      }
    }

    // Set current month
    const currentMonth = new Date().getMonth() + 1; // JavaScript months are 0-indexed
    monthSelect.value = currentMonth.toString();
    console.log('Default month set to:', currentMonth);

    // Update the title with defaults
    updateTitle();

    // Trigger auto-load if both are set
    setTimeout(() => {
      tryAutoLoad();
    }, 100);
  }

  async function loadUsers() {
    userSelect.innerHTML = '<option value="">Loading users...</option>';
    try {
      // Check if we're in embedded mode with impersonate parameter
      const urlParams = new URLSearchParams(window.location.search);
      const impersonateUser = urlParams.get('impersonate');

      // Get current user from global variable (set in dashboard.php)
      const currentUser = typeof currentUserTableName !== 'undefined' && currentUserTableName
        ? currentUserTableName
        : (typeof window.CURRENT_TABLENAME !== 'undefined' && window.CURRENT_TABLENAME
          ? window.CURRENT_TABLENAME
          : null);

      // Build API URL - prioritize impersonate parameter for embedded mode

      // 🔥 STEP 1: try using Dashboard API (same dropdown source as main dashboard)
      let apiUrl = (window.DASHBOARD_ASSIGNED_USERS_API || 'dashboard_data.php?total=true');

      // keep impersonation support
      if (impersonateUser) {
        apiUrl += (apiUrl.includes('?') ? '&' : '?') + 'impersonate=' + encodeURIComponent(impersonateUser);
      } else if (currentUser) {
        apiUrl += (apiUrl.includes('?') ? '&' : '?') + 'current_user=' + encodeURIComponent(currentUser);
      }

      console.log('Loading users from Dashboard API:', apiUrl);

      let json = null;

      try {
        const res = await fetch(apiUrl, { cache: 'no-store', credentials: 'same-origin' });
        json = await res.json();
        console.log('Dashboard users response:', json);

        userSelect.innerHTML = '<option value="">Select user</option>';

        // ✅ Dashboard returns assigned_users
        if (json.assigned_users && Array.isArray(json.assigned_users) && json.assigned_users.length) {
          json.assigned_users.forEach(u => {
            const opt = document.createElement('option');
            // ✅ dashboard_data.php provides: tablename + username
            opt.value = u.tablename;     // this is the table name used everywhere in backend
            opt.textContent = u.username; // this is what you want to show in dropdown
            userSelect.appendChild(opt);
          });
          setDefaults(currentUser); // Set defaults after loading users
          return; // ✅ success, no need fallback
        }

        console.warn('Dashboard API returned empty assigned_users, falling back...');
      } catch (err) {
        console.warn('Dashboard API failed, falling back to old user_perf users API:', err);
      }

      // 🔁 STEP 2: fallback to old API (your previous working logic)
      let fallbackUrl = API + '?action=users';
      if (impersonateUser) {
        fallbackUrl += '&impersonate=' + encodeURIComponent(impersonateUser);
      } else if (currentUser) {
        fallbackUrl += '&current_user=' + encodeURIComponent(currentUser);
      }

      console.log('Fallback loading users from:', fallbackUrl);
      const res2 = await fetch(fallbackUrl, { cache: 'no-store', credentials: 'same-origin' });
      const json2 = await res2.json();

      userSelect.innerHTML = '<option value="">Select user</option>';
      if (json2.users && Array.isArray(json2.users) && json2.users.length) {
        json2.users.forEach(u => {
          const opt = document.createElement('option');
          opt.value = u.id;
          opt.textContent = u.label;
          userSelect.appendChild(opt);
        });
        setDefaults(currentUser); // Set defaults after loading users
      } else {
        userSelect.innerHTML = '<option value="">No users available</option>';
      }


    } catch (e) {
      console.error('Error loading users:', e);
      userSelect.innerHTML = '<option value="">Failed to load users</option>';
    }
  }

  function renderCards(statusCounts) {
    cardsContainer.innerHTML = '';
    const order = ['Pending', 'RNR', 'Fake', 'Call Back', 'Already Booked', 'Not Interested', 'Interested', 'Follow Up', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Converted', 'Not Connected'];
    // Normalize keys: case-insensitive mapping
    const norm = {};
    Object.keys(statusCounts || {}).forEach(k => { norm[k.trim()] = statusCounts[k]; });
    order.forEach(lbl => {
      const val = (norm[lbl] !== undefined) ? norm[lbl] : (norm[lbl.toLowerCase()] !== undefined ? norm[lbl.toLowerCase()] : 0);
      const card = document.createElement('div');
      card.className = 'upm-card';
      card.innerHTML = `<div class="count">${val}</div><div class="label">${lbl}</div>`;
      cardsContainer.appendChild(card);
    });
    // other statuses
    Object.keys(norm).forEach(k => {
      if (order.indexOf(k) === -1) {
        const card = document.createElement('div');
        card.className = 'upm-card';
        card.innerHTML = `<div class="count">${norm[k]}</div><div class="label">${k}</div>`;
        cardsContainer.appendChild(card);
      }
    });
  }

  function renderCharts(dailyArr, statusCounts) {
    // --- DAILY LINE CHART ---
    const labels = dailyArr.map(d => d.date);
    const values = dailyArr.map(d => d.count);

    const ctxDaily = document.getElementById('upm-daily-chart').getContext('2d');
    if (dailyChart) dailyChart.destroy();

    dailyChart = new Chart(ctxDaily, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Calls',
          data: values,
          borderColor: 'rgba(43,124,255,0.95)',
          backgroundColor: 'rgba(43,124,255,0.12)',
          pointBackgroundColor: '#fff',
          pointBorderColor: 'rgba(43,124,255,0.95)',
          pointRadius: 4,
          pointHoverRadius: 6,
          tension: 0.28,
          fill: true,
          borderWidth: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: true,
            mode: 'nearest',
            intersect: true,
            callbacks: {
              label: function (context) {
                return context.dataset.label + ': ' + context.parsed.y;
              }
            }
          },
          zoom: {
            pan: {
              enabled: true,
              mode: 'x'
            },
            zoom: {
              wheel: {
                enabled: true
              },
              pinch: {
                enabled: true
              },
              mode: 'x'
            }
          }
        },
        interaction: {
          mode: 'nearest',
          intersect: true,
          axis: 'x'
        },
        scales: {
          x: {
            ticks: { maxRotation: 40, minRotation: 20, autoSkip: true, maxTicksLimit: 12 }
          },
          y: {
            beginAtZero: true,
            suggestedMax: Math.max(...values, 5),
            ticks: { stepSize: 1 }
          }
        },
      }
    });

    // --- STATUS DONUT ---
    const statusLabels = Object.keys(statusCounts || {});
    const statusData = statusLabels.map(k => statusCounts[k]);

    const ctxStatus = document.getElementById('upm-status-chart').getContext('2d');
    if (statusChart) statusChart.destroy();
    statusChart = new Chart(ctxStatus, {
      type: 'doughnut',
      data: {
        labels: statusLabels,
        datasets: [{
          data: statusData,
          backgroundColor: [
            '#f7b500', '#2b7cff', '#ff6b6b', '#9b59b6', '#4cd964',
            '#ff9f43', '#74b9ff', '#a29bfe', '#00cec9', '#fd79a8',
            '#1abc9c', '#e74c3c', '#3498db', '#9b59b6', '#f39c12'
          ].slice(0, statusLabels.length),
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 6,
              usePointStyle: true,
              font: {
                size: window.innerWidth <= 768 ? 9 : 10
              },
              boxWidth: window.innerWidth <= 768 ? 8 : 10,
              generateLabels: function (chart) {
                const data = chart.data;
                if (data.labels.length && data.datasets.length) {
                  return data.labels.map((label, i) => {
                    const value = data.datasets[0].data[i];
                    let displayLabel = label;
                    if (window.innerWidth > 768 && displayLabel.length > 12) {
                      displayLabel = label.substring(0, 10) + '...';
                    }
                    return {
                      text: `${displayLabel} (${value})`,
                      fillStyle: data.datasets[0].backgroundColor[i],
                      strokeStyle: data.datasets[0].borderColor || '#fff',
                      fontColor: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e5e7eb' : '#1f2937',
                      lineWidth: 1,
                      hidden: false,
                      index: i
                    };
                  });
                }
                return [];
              }
            }
          },
          tooltip: {
            enabled: true,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#333',
            bodyColor: '#333',
            borderColor: '#ddd',
            borderWidth: 1,
            titleFont: {
              size: window.innerWidth <= 768 ? 10 : 11
            },
            bodyFont: {
              size: window.innerWidth <= 768 ? 9 : 10
            }
          }
        },
        cutout: '65%',
        layout: {
          padding: {
            top: 3,
            bottom: 3,
            left: 3,
            right: 3
          }
        }
      }
    });

    // Remove any existing click-info element completely
    const existingClickInfo = document.querySelector('.upm-chart-panel--right .upm-click-info');
    if (existingClickInfo) {
      existingClickInfo.remove();
    }
  }


  async function loadData() {
    const user = userSelect.value;
    if (!user) return alert('Please select a user.');
    const month = monthSelect.value || '';
    const start = startInp.value || '';
    const end = endInp.value || '';

    const params = new URLSearchParams({ action: 'data', user, month, start, end });
    try {
      if (loadBtn) { loadBtn.disabled = true; loadBtn.textContent = 'Loading...'; }
      const res = await fetch(API + '?' + params.toString());
      const json = await res.json();
      if (json.error) { alert('Error: ' + json.error); return; }

      let dailyData = json.daily || [];
      let startDateSpan = null;
      let endDateSpan = null;

      if (month && month !== 'custom') {
        const currYear = new Date().getFullYear();
        const m = parseInt(month, 10) - 1; // 0-indexed
        startDateSpan = new Date(currYear, m, 1);
        endDateSpan = new Date(currYear, m + 1, 0);

        // Don't extend past today for the current month
        const today = new Date();
        if (m === today.getMonth() && currYear === today.getFullYear()) {
          endDateSpan = today;
        }
      } else if (month === 'custom' && start && end) {
        startDateSpan = new Date(start);
        endDateSpan = new Date(end);
      } else if (dailyData.length > 0) {
        // Sort ascending
        dailyData.sort((a, b) => new Date(a.date) - new Date(b.date));
        startDateSpan = new Date(dailyData[0].date);
        endDateSpan = new Date(dailyData[dailyData.length - 1].date);
      }

      if (startDateSpan && endDateSpan) {
        const dailyMap = {};
        dailyData.forEach(d => { if (d && d.date) dailyMap[d.date] = d.count; });

        const continuousDaily = [];
        // Force time to noon to avoid daylight saving time skips
        let dDate = new Date(startDateSpan.getFullYear(), startDateSpan.getMonth(), startDateSpan.getDate(), 12, 0, 0);
        const eDate = new Date(endDateSpan.getFullYear(), endDateSpan.getMonth(), endDateSpan.getDate(), 23, 59, 59);

        while (dDate <= eDate) {
          const yyyy = dDate.getFullYear();
          const mm = String(dDate.getMonth() + 1).padStart(2, '0');
          const dd = String(dDate.getDate()).padStart(2, '0');
          const dStr = `${yyyy}-${mm}-${dd}`;

          continuousDaily.push({
            date: dStr,
            count: dailyMap[dStr] || 0
          });
          dDate.setDate(dDate.getDate() + 1);
        }
        dailyData = continuousDaily;
      }

      renderCards(json.statusCounts || {});
      renderCharts(dailyData, json.statusCounts || {});
    } catch (e) {
      console.error(e);
      alert('Failed to load data. See console.');
    } finally {
      if (loadBtn) { loadBtn.disabled = false; loadBtn.textContent = 'Load'; }
    }
  }

  // Event binding
  openBtn.addEventListener('click', () => { showModal(); loadUsers(); });
  closeBtns.forEach(b => b.addEventListener('click', hideModal));
  window.addEventListener('click', (ev) => { if (ev.target === modal) hideModal(); });
  // loadBtn.addEventListener('click', loadData); // hidden button

  // Auto-load logic
  function tryAutoLoad() {
    const user = userSelect.value;
    const month = monthSelect.value;
    if (!user || !month) return;

    if (month === 'custom') {
      if (startInp.value && endInp.value) loadData();
    } else {
      loadData();
    }
  }

  userSelect.addEventListener('change', () => { 
    updateTitle(); 
    tryAutoLoad(); 
  });
  monthSelect.addEventListener('change', tryAutoLoad);
  startInp.addEventListener('change', () => {
    updateTitle();
    tryAutoLoad();
  });
  endInp.addEventListener('change', () => {
    updateTitle();
    tryAutoLoad();
  });

})();

//this is profile status Start
// Updated refreshUserPerfCard — uses backend goal/diff/percent/message fields
async function refreshUserPerfCard(tablename) {
  console.groupCollapsed("🔎 refreshUserPerfCard Debug");
  try {
    console.log('input tablename param:', tablename);

    // Build query params. If tablename is provided we include it, otherwise we omit it
    const params = new URLSearchParams({
      action: 'perf_status',
      days: 7,
      daily_threshold: 5,
      debug: 1
    });

    if (typeof tablename === 'string' && tablename.trim() !== '') {
      params.set('user', tablename.trim());
      console.log('Requesting perf_status for:', tablename.trim());
    } else {
      console.log('No tablename provided — requesting perf_status for session user');
      // do not set 'user' param, backend will use $_SESSION['tablename']
    }

    const url = './user_perf.php?' + params.toString();
    console.log('Calling URL:', url);

    const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
    console.log('Fetch status:', res.status, res.ok);

    const text = await res.text();
    console.log('Raw response text:', text);

    let data;
    try {
      data = JSON.parse(text);
    } catch (err) {
      console.error('JSON parse error:', err);
      console.groupEnd();
      return;
    }
    console.log('Parsed JSON:', data);
    if (data.debug) console.table(data.debug);
    if (data.error) {
      // Silently handle authorization errors (expected when user doesn't have permission)
      if (data.error.includes('Not authorized') || data.error.includes('authorized')) {
        console.groupEnd();
        return;
      }
      console.error('Backend error:', data.error);
      console.groupEnd();
      return;
    }

    // find card and apply classes
    const card = document.querySelector('#borderColorChange.dashboard-card.next-game-card')
      || document.querySelector('.dashboard-card.next-game-card');
    if (!card) {
      console.warn('Card element not found: .dashboard-card.next-game-card or #borderColorChange');
      console.groupEnd();
      return;
    }

    // clear inline overrides (so CSS classes control appearance)
    card.style.border = '';
    card.style.boxShadow = '';
    card.style.outline = '';

    // remove old classes then apply new
    card.classList.remove('border-low', 'border-medium', 'border-high', 'border-dangerous');

    // decide classes — backend provides status + dangerous
    if (data.dangerous) {
      card.classList.add('border-low', 'border-dangerous');
      console.warn('Applied: border-low + border-dangerous');
    } else if (data.status === 'low') {
      card.classList.add('border-low');
      console.warn('Applied: border-low');
    } else if (data.status === 'warning') {
      card.classList.add('border-medium');
      console.log('Applied: border-medium');
    } else {
      card.classList.add('border-high');
      console.log('Applied: border-high');
    }

    // update / create badge (enhanced with goal, diff, percent, message, small progress bar)
    let badge = card.querySelector('.perf-badge');
    if (!badge) {
      badge = document.createElement('div');
      badge.className = 'perf-badge';
      card.appendChild(badge);
    }

    // normalize backend values with safe fallbacks
    const today = (typeof data.today === 'number') ? data.today : (Number(data.today) || 0);
    const rollingSum = (data.rolling && data.rolling.sum !== undefined) ? data.rolling.sum : '';
    const rollingDays = (data.rolling && data.rolling.days !== undefined) ? data.rolling.days : '';
    const goal = (typeof data.goal === 'number') ? data.goal : (Number(data.goal) || 100);
    const diff = (typeof data.diff === 'number') ? data.diff : (Number(data.diff) || Math.max(0, goal - today));
    const percent = (typeof data.percent === 'number') ? data.percent : (goal > 0 ? Math.round((today / goal) * 1000) / 10 : 0);
    const message = typeof data.message === 'string'
      ? data.message
      : (diff === 0 ? 'Goal met — great job!' : `${diff} calls to reach today's goal of ${goal}.`);

    const pct = Math.max(0, Math.min(100, Number(percent) || 0));

    // 🆕 Use badge-left / mid / right segments for responsive CSS layout
    badge.innerHTML = `
      <span class="badge-left"><strong>${today}</strong> Today</span>
      <span class="badge-mid">• ${rollingSum}/${rollingDays || 0}</span>
      <span class="badge-right">${message} • ${pct}%</span>
      <div class="perf-progress" aria-hidden="true">
        <div class="perf-progress-fill" style="width:${pct}%;"></div>
      </div>
    `;

    badge.title = `Last ${rollingDays || 7}d: ${rollingSum || 0} updates (avg ${data.rolling ? data.rolling.avg : '-'})`;

    // Check computed style; if CSS overridden, apply inline fallback and log
    const cs = window.getComputedStyle(card);
    console.log('Computed border:', cs.borderStyle, cs.borderWidth, cs.borderColor);

    // If global CSS overrides and border still transparent/none, set inline border as fallback
    if (cs.borderStyle === 'none' || cs.borderColor === 'transparent' || cs.borderWidth === '0px') {
      console.error('Border not visible via CSS classes — applying inline fallback (important).');
      let color = '#43aa8b'; // green by default
      if (data.dangerous || data.status === 'low') color = '#e63946'; // red
      else if (data.status === 'warning') color = '#f9c74f'; // yellow

      // apply inline styles with !important
      card.style.setProperty('border', `3px solid ${color}`, 'important');
      card.style.setProperty('box-shadow', `0 10px 30px ${color}22`, 'important');
    }

  } catch (err) {
    console.error('Exception in refreshUserPerfCard:', err);
  } finally {
    console.groupEnd();
  }
}
// --- Auto-run perf status on page load (uses session user if no param passed) ---
(function autoInitPerfCard() {
  // Run as soon as DOM is ready
  document.addEventListener('DOMContentLoaded', function () {
    try {
      // call with no arg so backend uses session tablename if present
      if (typeof refreshUserPerfCard === 'function') {
        refreshUserPerfCard();
        console.log('refreshUserPerfCard() called on DOMContentLoaded');
      } else {
        console.warn('refreshUserPerfCard not defined yet');
      }
    } catch (e) {
      console.error('Error calling refreshUserPerfCard:', e);
    }
  });

  // Retry after short delay in case session/filters load later (helps SPA or deferred scripts)
  setTimeout(() => {
    try {
      if (typeof refreshUserPerfCard === 'function') {
        refreshUserPerfCard();
        console.log('refreshUserPerfCard() called by retry timeout');
      }
    } catch (e) {
      console.error('Retry error calling refreshUserPerfCard:', e);
    }
  }, 1800);
})();

//this is profile status End
// Custom searchable dropdown for User Performance modal
(function () {
  const nativeSelect = document.getElementById('upm-user-select');
  const combo = document.getElementById('upm-user-combobox');
  const dropdown = document.getElementById('upm-user-dropdown');
  const searchInput = document.getElementById('upm-user-search');
  const optionList = document.getElementById('upm-user-options');
  const display = document.getElementById('upm-user-display');
  if (!nativeSelect || !combo || !dropdown || !searchInput || !optionList || !display) {
    return;
  }

  let isOpen = false;

  const setOpen = (open) => {
    isOpen = open;
    dropdown.classList.toggle('open', open);
    combo.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      searchInput.focus();
      searchInput.select();
    }
  };

  const closeOnOutsideClick = (event) => {
    if (!dropdown.contains(event.target) && !combo.contains(event.target)) {
      setOpen(false);
      document.removeEventListener('mousedown', closeOnOutsideClick);
    }
  };

  let allOptions = [];
  let currentFiltered = [];
  let displayCount = 15;

  const extractOptions = () => {
    allOptions = Array.from(nativeSelect.options).map((opt, index) => ({
      value: opt.value,
      text: opt.textContent || '',
      isPlaceholder: index === 0 && opt.value === ''
    }));
  };

  const renderCurrentFiltered = (append = false) => {
    if (!append) {
      optionList.innerHTML = '';
      optionList.scrollTop = 0;
    }

    const startIndex = append ? displayCount - 15 : 0;
    const endIndex = displayCount;
    const subset = currentFiltered.slice(startIndex, endIndex);

    subset.forEach(opt => {
      const div = document.createElement('div');
      div.className = 'upm-option';
      div.setAttribute('role', 'option');
      div.dataset.value = opt.value;
      div.textContent = opt.text;
      if (opt.isPlaceholder) {
        div.dataset.placeholder = 'true';
      }
      optionList.appendChild(div);
    });

    const term = searchInput.value.trim().toLowerCase();
    if (!append && term) {
      const firstReal = Array.from(optionList.children).find(n => n.dataset.placeholder !== 'true');
      if (firstReal) {
        optionList.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
        firstReal.classList.add('active');
      }
    }
  };

  const applySelection = (value, label) => {
    nativeSelect.value = value;
    display.textContent = label || 'Select User';
    const changeEvent = new Event('change', { bubbles: true });
    nativeSelect.dispatchEvent(changeEvent);
  };

  const filterOptions = () => {
    const term = searchInput.value.trim().toLowerCase();
    
    if (term === '') {
      currentFiltered = allOptions;
    } else {
      currentFiltered = allOptions.filter(opt => 
        opt.isPlaceholder || opt.text.toLowerCase().includes(term)
      );
    }
    
    displayCount = 15;
    renderCurrentFiltered(false);
  };

  optionList.addEventListener('scroll', (e) => {
    const { scrollTop, scrollHeight, clientHeight } = e.target;
    if (scrollTop + clientHeight >= scrollHeight - 20) {
      if (displayCount < currentFiltered.length) {
        displayCount += 15;
        renderCurrentFiltered(true);
      }
    }
  });

  optionList.addEventListener('click', (e) => {
    const option = e.target.closest('.upm-option');
    if (!option) return;
    applySelection(option.dataset.value || '', option.textContent || 'Select User');
    setOpen(false);
    document.removeEventListener('mousedown', closeOnOutsideClick);
  });

  combo.addEventListener('click', () => {
    setOpen(!isOpen);
    if (isOpen) {
      document.addEventListener('mousedown', closeOnOutsideClick);
    } else {
      document.removeEventListener('mousedown', closeOnOutsideClick);
    }
  });

  combo.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      combo.click();
    } else if (e.key === 'Escape') {
      setOpen(false);
    }
  });

  searchInput.addEventListener('input', filterOptions);

  const observer = new MutationObserver(() => {
    extractOptions();
    filterOptions();
  });
  observer.observe(nativeSelect, { childList: true });

  extractOptions();
  filterOptions();
})();
