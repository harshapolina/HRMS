(function () {
  'use strict';

  const cfg = window.HEADER_PAGE_CONFIG || {};
  const API_BASE = String(cfg.notificationApiBase || './notifications/');
  const VIEW_ALL_URL = String(cfg.notificationViewAllUrl || '/notifications/view-all-notifications.php');
  const CURRENT_USER_CODE = String(cfg.currentUserCode || '');
  const FY_FILTER_USER = String(cfg.fyFilterUser || '');
  const FY_MANAGER_VIEW = String(cfg.fyManagerView || '');

  // Called by onchange attributes in the booking filter form.
  window.submitFormWithBothParams = function submitFormWithBothParams() {
    const form = document.querySelector('.fy-selector');
    const fySelect = document.getElementById('financialYearSelect');
    const citySelect = document.getElementById('citySelect');
    if (!form || !fySelect || !citySelect) return;

    const url = new URL(form.action, window.location.origin);
    url.searchParams.set('fy', fySelect.value);
    if (citySelect.value) {
      url.searchParams.set('city', citySelect.value);
    }

    if (FY_FILTER_USER) {
      url.searchParams.set('filterUser', FY_FILTER_USER);
    }
    if (FY_MANAGER_VIEW) {
      url.searchParams.set('managerView', FY_MANAGER_VIEW);
    }

    window.location.href = url.toString();
  };

  (function initNotifications() {
    document.addEventListener('DOMContentLoaded', () => {
      const popup = document.getElementById('notificationPopup');
      if (!popup) return;

      if (!popup.querySelector('.notification-header')) {
        popup.innerHTML = `
          <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all-read">Mark all as read</button>
            <button class="popup-close-btn" id="notificationCloseBtn"><i class="fas fa-times"></i></button>
          </div>
          <div class="notification-search-wrapper" style="padding:0.6rem 1rem;border-bottom:1px solid var(--border-color,#e2e8f0);">
            <input type="text" id="notifSearchInput" placeholder="Search notifications..."
              style="width:100%;padding:0.45rem 0.75rem;border:1px solid var(--border-color,#ddd);border-radius:8px;font-size:0.88rem;outline:none;">
          </div>
          <div class="notification-list" id="notifListEl"></div>
          <div class="notification-footer">
            <a href="#" class="view-all">View all notifications</a>
          </div>
        `;
      }

      const listEl = popup.querySelector('#notifListEl') || popup.querySelector('.notification-list');
      const markAllBtn = popup.querySelector('.mark-all-read');
      const closeBtn = document.getElementById('notificationCloseBtn');
      const viewAllLink = popup.querySelector('.view-all');
      const searchInput = document.getElementById('notifSearchInput');
      const badge = document.getElementById('notifBadge');
      const bellBtn = document.getElementById('notificationBtn');
      const dotBadge = bellBtn ? bellBtn.querySelector('.notification-badge') : null;

      if (!listEl || !bellBtn) return;

      let notifications = [];
      let offset = 0;
      const limit = 10;
      let searchQuery = '';
      let isLoading = false;
      let hasMore = true;

      async function openNotifications() {
        popup.style.display = 'block';
        if (dotBadge) dotBadge.style.display = 'none';
        await resetAndLoad();
      }

      async function resetAndLoad() {
        offset = 0;
        hasMore = true;
        notifications = [];
        listEl.innerHTML = '<div style="padding:1.5rem;text-align:center;color:#888;">Loading...</div>';
        await loadMore();
      }

      async function loadMore() {
        if (isLoading || !hasMore) return;
        isLoading = true;
        try {
          let url = `${API_BASE}get-notifications.php?limit=${limit}&offset=${offset}`;
          if (searchQuery) url += `&q=${encodeURIComponent(searchQuery)}`;

          const res = await fetch(url, { credentials: 'same-origin' });
          const json = await res.json();
          if (json.status !== 'success') {
            isLoading = false;
            return;
          }

          const newItems = json.notifications || [];
          if (offset === 0) {
            notifications = newItems;
          } else {
            notifications = notifications.concat(newItems);
          }

          hasMore = json.has_more;
          offset += newItems.length;
          renderList(notifications, offset === newItems.length);
          updateBadge(json.unread_count);
        } catch (err) {
          console.error('Failed to load notifications', err);
        }
        isLoading = false;
      }

      async function loadBadgeCount() {
        try {
          const res = await fetch(`${API_BASE}get-notifications.php?limit=1&offset=0`, { credentials: 'same-origin' });
          const json = await res.json();
          if (json.status === 'success') updateBadge(json.unread_count);
        } catch (e) {
          console.error(e);
        }
      }

      function renderList(items, replace = true) {
        if (replace) listEl.innerHTML = '';

        if (!items || items.length === 0) {
          listEl.innerHTML = '<div style="padding:2rem;text-align:center;color:#888;">No notifications found</div>';
          return;
        }

        const startIdx = replace ? 0 : listEl.querySelectorAll('.notification-item').length;
        const toRender = replace ? items : items.slice(startIdx);

        toRender.forEach((n) => {
          const item = document.createElement('div');
          item.className = 'notification-item' + (n.is_read == 0 ? ' unread' : '');
          item.dataset.nid = n.notification_id;
          item.dataset.url = n.url || '';

          let iconHtml = '<div class="notification-icon"><i class="fas fa-bell"></i></div>';
          if (n.type === 'lead') iconHtml = '<div class="notification-icon"><i class="fas fa-user-plus"></i></div>';
          if (n.type === 'booking') iconHtml = '<div class="notification-icon"><i class="fas fa-calendar-check"></i></div>';
          if (n.type === 'followup_reminder') iconHtml = '<div class="notification-icon"><i class="fas fa-clock"></i></div>';
          if (n.type === 'alert') iconHtml = '<div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>';
          if (n.icon) iconHtml = `<div class="notification-icon"><i class="${escapeHtml(n.icon)}"></i></div>`;

          const bodyText = n.body || n.title || '';
          const timeText = timeAgo(new Date(n.created_at));
          item.innerHTML = `
            ${iconHtml}
            <div class="notification-content">
              <p>${escapeHtml(bodyText)}</p>
              <span class="notification-time">${escapeHtml(timeText)}</span>
            </div>`;

          item.addEventListener('click', async () => {
            const nid = Number(item.dataset.nid);
            if (!Number.isFinite(nid) || nid <= 0) return;
            const targetUrl = resolveNotificationTargetUrl(n);
            const markResult = item.classList.contains('unread') ? await markAsRead([nid]) : true;
            if (markResult) item.classList.remove('unread');
            if (targetUrl) window.location.href = targetUrl;
          });

          listEl.appendChild(item);
        });
      }

      function resolveNotificationTargetUrl(notification) {
        const leadBaseUrl = 'https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead';
        const rawUrl = String(notification?.url || '').trim();
        const type = String(notification?.type || '').trim().toLowerCase();
        const text = `${notification?.title || ''} ${notification?.body || ''}`.toLowerCase();

        if (type === 'followup_skip_alert' || text.includes('skipped/rescheduled an exact-time follow-up')) {
          return buildFollowupSkipUrl(leadBaseUrl, notification);
        }

        if (isAssignedLeadNotification(notification, text)) {
          return buildAssignedLeadUrl(leadBaseUrl, notification);
        }

        if (type === 'overdue_skip_alert' || text.includes('overdue lead')) {
          return buildOverdueTeamViewUrl(leadBaseUrl, notification);
        }

        if (rawUrl && !isGenericNotificationUrl(rawUrl)) {
          return rawUrl;
        }

        if (type === 'followup_reminder' || text.includes('follow-up') || text.includes('follow up')) {
          return `${leadBaseUrl}?filter=followLeads`;
        }

        if (text.includes('lead')) {
          return leadBaseUrl;
        }

        return rawUrl || leadBaseUrl;
      }

      function parseFollowupSkipFromBody(bodyText) {
        const text = String(bodyText || '').replace(/\s+/g, ' ').trim();
        const regex = /exact-time\s+follow-up\s+for\s+(.+?)\s*\((.*?)\)(?:\s*-\s*(.+?))?(?:\.\s*new\s+follow-up\s+set|\.|$)/i;
        const match = text.match(regex);
        if (!match) {
          return { leadName: '', leadNumber: '', projectName: '' };
        }

        return {
          leadName: (match[1] || '').trim(),
          leadNumber: (match[2] || '').trim(),
          projectName: (match[3] || '').trim()
        };
      }

      function buildFollowupSkipUrl(leadBaseUrl, notification) {
        const parsedUrl = new URL(leadBaseUrl, window.location.origin);
        const body = String(notification?.body || '');
        const meta = notification?.meta || {};
        const parsedFromBody = parseFollowupSkipFromBody(body);

        const leadName = String(meta.lead_name || parsedFromBody.leadName || '').trim();
        const projectName = String(meta.project_name || parsedFromBody.projectName || '').trim();
        const skippedById = String(meta.skipped_by_id || '').trim();

        parsedUrl.searchParams.set('filter', 'followLeads');

        if (leadName) parsedUrl.searchParams.set('lead_name', leadName);
        if (projectName) parsedUrl.searchParams.set('project_name', projectName);

        if (skippedById && CURRENT_USER_CODE && skippedById !== CURRENT_USER_CODE) {
          parsedUrl.searchParams.set('teamView', 'on');
          parsedUrl.searchParams.set('managerView', 'true');
          parsedUrl.searchParams.set('filterUser', skippedById);
        } else {
          parsedUrl.searchParams.set('teamView', 'off');
          parsedUrl.searchParams.set('managerView', 'false');
        }

        return parsedUrl.toString();
      }

      function isAssignedLeadNotification(notification, textLower) {
        const type = String(notification?.type || '').trim().toLowerCase();
        const body = String(notification?.body || '').trim();
        return (
          type === 'lead' ||
          textLower.includes('has been assigned to you') ||
          /lead\s+.+\s+has\s+been\s+assigned\s+to\s+you/i.test(body)
        );
      }

      function parseAssignedLeadFromBody(bodyText) {
        const text = String(bodyText || '').replace(/\s+/g, ' ').trim();
        const regex = /lead\s+(.+?)\s+has\s+been\s+assigned\s+to\s+you(?:\s+for\s+project\s+(.+?))?[.!]?$/i;
        const match = text.match(regex);
        if (!match) {
          return { leadName: '', projectName: '' };
        }

        return {
          leadName: (match[1] || '').trim(),
          projectName: (match[2] || '').trim()
        };
      }

      function buildAssignedLeadUrl(leadBaseUrl, notification) {
        const parsedUrl = new URL(leadBaseUrl, window.location.origin);
        const body = String(notification?.body || '');
        const meta = notification?.meta || {};
        const parsedFromBody = parseAssignedLeadFromBody(body);

        const leadName = String(meta.lead_name || parsedFromBody.leadName || '').trim();
        const projectName = String(meta.project_name || meta.project || parsedFromBody.projectName || '').trim();

        parsedUrl.searchParams.set('filter', 'myLeads');
        if (leadName) parsedUrl.searchParams.set('lead_name', leadName);
        if (projectName) parsedUrl.searchParams.set('project_name', projectName);

        return parsedUrl.toString();
      }

      function buildOverdueTeamViewUrl(leadBaseUrl, notification) {
        const parsedUrl = new URL(leadBaseUrl, window.location.origin);
        parsedUrl.searchParams.set('filter', 'overdueLeads');
        parsedUrl.searchParams.set('teamView', 'on');
        parsedUrl.searchParams.set('managerView', 'true');

        const skippedById = notification?.meta?.skipped_by_id;
        if (skippedById) parsedUrl.searchParams.set('filterUser', String(skippedById));

        return parsedUrl.toString();
      }

      function isGenericNotificationUrl(url) {
        if (!url) return true;
        try {
          const parsedUrl = new URL(url, window.location.origin);
          if (parsedUrl.search && parsedUrl.search.length > 1) return false;

          const genericPaths = [
            '/',
            '/index.html',
            '/index1.html',
            '/incentiveapp_integration/userlogin1/userlogin6/user_lead',
            '/incentiveapp_integration/userlogin1/userlogin6/user_lead.php',
            '/userlogin6/',
            '/userlogin6/user_lead',
            '/userlogin6/user_lead.php',
            '/userlogin6/index.html',
            '/userlogin6/index1.html'
          ];
          return genericPaths.includes(parsedUrl.pathname) || parsedUrl.pathname === '';
        } catch (_error) {
          return false;
        }
      }

      listEl.addEventListener('scroll', () => {
        const nearBottom = listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 60;
        if (nearBottom && hasMore && !isLoading) loadMore();
      });

      let searchTimer;
      if (searchInput) {
        searchInput.addEventListener('input', () => {
          clearTimeout(searchTimer);
          searchTimer = setTimeout(async () => {
            searchQuery = searchInput.value.trim();
            await resetAndLoad();
          }, 400);
        });
      }

      async function markAsRead(ids = []) {
        if (!ids.length) return false;
        try {
          const res = await fetch(`${API_BASE}mark-read.php`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_ids: ids })
          });
          if (!res.ok) return false;

          const json = await res.json();
          if (json.status === 'success') {
            let newlyRead = 0;
            notifications.forEach((n) => {
              if (ids.includes(n.notification_id) && n.is_read == 0) {
                n.is_read = 1;
                newlyRead += 1;
              }
            });
            const currentBadgeCount = parseInt(badge ? badge.textContent : '0', 10) || 0;
            updateBadge(Math.max(0, currentBadgeCount - newlyRead));
            return true;
          }
        } catch (err) {
          console.error('markAsRead error', err);
        }
        return false;
      }

      async function markAllRead() {
        try {
          const res = await fetch(`${API_BASE}mark-all-read.php`, { method: 'POST', credentials: 'same-origin' });
          const json = await res.json();
          if (json.status === 'success') {
            listEl.querySelectorAll('.notification-item.unread').forEach((el) => el.classList.remove('unread'));
            notifications.forEach((n) => {
              n.is_read = 1;
            });
            updateBadge(0);
          }
        } catch (err) {
          console.error('markAllRead error', err);
        }
      }

      function updateBadge(count) {
        if (!badge) return;
        if (count > 0) {
          badge.textContent = count;
          badge.style.display = 'inline-block';
        } else {
          badge.textContent = '';
          badge.style.display = 'none';
        }
      }

      function timeAgo(d) {
        const s = Math.floor((Date.now() - d.getTime()) / 1000);
        if (s < 60) return `${s}s ago`;
        if (s < 3600) return `${Math.floor(s / 60)} min ago`;
        if (s < 86400) return `${Math.floor(s / 3600)} hrs ago`;
        const days = Math.floor(s / 86400);
        return days === 1 ? 'Yesterday' : `${days} days ago`;
      }

      function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, (c) => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        }[c]));
      }

      if (markAllBtn) {
        markAllBtn.addEventListener('click', async (e) => {
          e.preventDefault();
          await markAllRead();
        });
      }

      if (closeBtn) closeBtn.addEventListener('click', () => {
        popup.style.display = 'none';
      });

      if (viewAllLink) {
        viewAllLink.addEventListener('click', (e) => {
          e.preventDefault();
          window.location.href = VIEW_ALL_URL;
        });
      }

      bellBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (popup.style.display === 'block') {
          popup.style.display = 'none';
        } else {
          openNotifications();
        }
      });

      document.addEventListener('click', (e) => {
        if (popup.style.display !== 'block') return;
        const isClickInside = popup.contains(e.target) || bellBtn.contains(e.target);
        if (!isClickInside) popup.style.display = 'none';
      });

      loadBadgeCount();
      window.refreshNotificationBadge = loadBadgeCount;
    });
  })();

  const initBadgeArrows = () => {
    const updateArrowVisibility = (track, leftArrow, rightArrow) => {
      if (!track) return;

      const scrollLeft = track.scrollLeft;
      const maxScroll = track.scrollWidth - track.clientWidth;

      if (leftArrow) {
        if (scrollLeft > 0) leftArrow.classList.add('visible');
        else leftArrow.classList.remove('visible');
      }

      if (rightArrow) {
        if (scrollLeft >= maxScroll - 1) rightArrow.classList.add('hidden');
        else rightArrow.classList.remove('hidden');
      }
    };

    const arrowGroups = {};
    document.querySelectorAll('.badge-arrow').forEach((btn) => {
      const targetSelector = btn.dataset.target;
      if (!targetSelector) return;

      if (!arrowGroups[targetSelector]) {
        arrowGroups[targetSelector] = { left: null, right: null, track: null };
      }

      arrowGroups[targetSelector].track = document.querySelector(targetSelector);

      if (btn.classList.contains('left')) arrowGroups[targetSelector].left = btn;
      else if (btn.classList.contains('right')) arrowGroups[targetSelector].right = btn;
    });

    Object.keys(arrowGroups).forEach((targetSelector) => {
      const { left, right, track } = arrowGroups[targetSelector];
      if (!track) return;

      if (left) {
        left.addEventListener('click', () => {
          track.scrollBy({ left: -220, behavior: 'smooth' });
        });
      }

      if (right) {
        right.addEventListener('click', () => {
          track.scrollBy({ left: 220, behavior: 'smooth' });
        });
      }

      track.addEventListener('scroll', () => {
        updateArrowVisibility(track, left, right);
      });

      updateArrowVisibility(track, left, right);
      window.addEventListener('resize', () => {
        updateArrowVisibility(track, left, right);
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBadgeArrows);
  } else {
    initBadgeArrows();
  }

  (function initOverdueToggles() {
    const API_URL = 'update_status.php';
    let currentToggleSettings = null;
    let isTogglingInProgress = false;

    let teamTogglePage = 1;
    const teamTogglePerPage = 15;
    let teamToggleHasMore = false;
    let teamToggleIsLoading = false;

    document.addEventListener('DOMContentLoaded', () => {
      initOverdueToggle();
      initTeamTogglesModal();
    });

    function initOverdueToggle() {
      const toggle = document.getElementById('overdue-popup-toggle');
      const toggleSwitch = document.querySelector('.overdue-toggle-switch');
      const toggleSlider = document.querySelector('.overdue-toggle-slider');

      if (!toggle) return;

      if (toggleSlider) {
        const afterStyle = window.getComputedStyle(toggleSlider, '::after');
        void afterStyle;
        toggleSlider.style.visibility = 'visible';
        toggleSlider.style.display = 'inline-block';
        toggleSlider.style.opacity = '1';
      }

      loadToggleSettings().then((settings) => {
        if (settings && settings.user) {
          currentToggleSettings = settings;
          updateToggleUI(settings.user);
          checkRawDatabaseValue();
        }
      }).catch((error) => {
        console.error('Error loading toggle settings:', error);
      });

      toggle.addEventListener('change', async function onToggleChange() {
        if (isTogglingInProgress) return;

        const newState = this.checked;
        const slider = toggleSlider;
        const knob = slider ? slider.querySelector('span') : null;
        if (slider && knob) {
          if (newState) {
            slider.style.background = '#3B82F6';
            knob.style.left = '25px';
          } else {
            slider.style.background = '#E5E7EB';
            knob.style.left = '3px';
          }
        }

        if (currentToggleSettings && currentToggleSettings.user && !currentToggleSettings.user.can_modify) {
          this.checked = !this.checked;
          if (slider && knob) {
            if (!newState) {
              slider.style.background = '#3B82F6';
              knob.style.left = '25px';
            } else {
              slider.style.background = '#E5E7EB';
              knob.style.left = '3px';
            }
          }
          showNotification('Your toggle is locked by your manager. Contact them to make changes.', 'warning');
          return;
        }

        isTogglingInProgress = true;
        const enabled = this.checked;

        try {
          const result = await updateToggle(null, enabled, false);
          if (result.status === 'success') {
            showNotification(result.message, 'success');

            if (!enabled && typeof window.clearOverdueLeadsQueue === 'function') {
              window.clearOverdueLeadsQueue();
            }

            await loadToggleSettings().then((settings) => {
              if (settings && settings.user) {
                currentToggleSettings = settings;
                setTimeout(() => checkRawDatabaseValue(), 500);
              }
            });
          } else {
            this.checked = !this.checked;
            if (slider && knob) {
              if (this.checked) {
                slider.style.background = '#3B82F6';
                knob.style.left = '25px';
              } else {
                slider.style.background = '#E5E7EB';
                knob.style.left = '3px';
              }
            }
            showNotification(result.message || 'Failed to update toggle', 'error');
          }
        } catch (error) {
          console.error('Toggle update error:', error);
          this.checked = !this.checked;
          if (slider && knob) {
            if (this.checked) {
              slider.style.background = '#3B82F6';
              knob.style.left = '25px';
            } else {
              slider.style.background = '#E5E7EB';
              knob.style.left = '3px';
            }
          }
          showNotification('Failed to update toggle', 'error');
        } finally {
          isTogglingInProgress = false;
        }
      });

      void toggleSwitch;
    }

    function updateToggleUI(userSettings) {
      const toggle = document.getElementById('overdue-popup-toggle');
      const toggleSwitch = document.querySelector('.overdue-toggle-switch');
      const toggleSlider = document.querySelector('.overdue-toggle-slider');
      const lockIndicator = document.getElementById('overdue-popup-lock-indicator');
      const lockText = document.getElementById('overdue-lock-text');

      if (!toggle) return;

      toggle.checked = userSettings.enabled;

      if (toggleSlider) {
        const knob = toggleSlider.querySelector('span');
        if (userSettings.enabled) {
          toggleSlider.style.background = '#3B82F6';
          toggleSlider.style.boxShadow = '0 2px 8px rgba(59, 130, 246, 0.3)';
          if (knob) knob.style.left = '25px';
        } else {
          toggleSlider.style.background = '#E5E7EB';
          toggleSlider.style.boxShadow = 'inset 0 1px 3px rgba(0, 0, 0, 0.1)';
          if (knob) knob.style.left = '3px';
        }
      }

      if (!userSettings.can_modify) {
        if (toggleSwitch) toggleSwitch.classList.add('locked');
        toggle.disabled = false;
        if (lockIndicator) lockIndicator.style.display = 'block';
        if (lockText && userSettings.locked_by) lockText.textContent = 'Locked by manager';
      } else {
        if (toggleSwitch) toggleSwitch.classList.remove('locked');
        toggle.disabled = false;
        if (lockIndicator) lockIndicator.style.display = 'none';
      }
    }

    function initTeamTogglesModal() {
      const dropdown = document.getElementById('team-toggles-dropdown');
      const openBtn = document.getElementById('manage-team-toggles-btn');
      const arrow = document.getElementById('team-toggle-arrow');
      const manageSection = document.querySelector('.manage-team-section');
      const titleContainer = document.getElementById('manage-team-title-container');
      const searchInput = document.getElementById('team-search-input');
      const membersList = document.getElementById('team-members-list');

      if (!dropdown || !openBtn || !searchInput || !membersList || !manageSection) return;

      let isOpen = false;

      const filterTeamMembers = (query) => {
        const cards = membersList.querySelectorAll('.team-member-card');
        const normalizedQuery = query.toLowerCase().trim();
        let hasMatch = false;

        cards.forEach((card) => {
          const nameEl = card.querySelector('.team-member-name');
          const roleEl = card.querySelector('.team-member-role');
          const name = nameEl ? nameEl.textContent.toLowerCase() : '';
          const role = roleEl ? roleEl.textContent.toLowerCase() : '';

          if (name.includes(normalizedQuery) || role.includes(normalizedQuery)) {
            card.style.display = 'block';
            hasMatch = true;
          } else {
            card.style.display = 'none';
          }
        });

        let noResultsMsg = membersList.querySelector('.no-results-message');
        if (!hasMatch && query !== '') {
          if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'no-results-message';
            noResultsMsg.innerHTML = '<i class="fas fa-search" style="display:block; font-size: 24px; margin-bottom: 8px; opacity: 0.5;"></i> No team members match your search';
            membersList.appendChild(noResultsMsg);
          }
        } else if (noResultsMsg) {
          noResultsMsg.remove();
        }
      };

      const closeDropdown = () => {
        dropdown.style.display = 'none';
        if (arrow) arrow.classList.remove('rotated');

        if (titleContainer) titleContainer.style.display = 'flex';
        if (searchInput) {
          searchInput.style.display = 'none';
          searchInput.value = '';
        }

        filterTeamMembers('');
        isOpen = false;
      };

      const openDropdown = async () => {
        dropdown.style.display = 'block';
        if (arrow) arrow.classList.add('rotated');

        if (titleContainer) titleContainer.style.display = 'none';
        if (searchInput) {
          searchInput.style.display = 'block';
          setTimeout(() => searchInput.focus(), 100);
        }

        teamTogglePage = 1;
        teamToggleHasMore = false;
        teamToggleIsLoading = false;
        membersList.innerHTML = '';

        isOpen = true;
        await loadTeamToggles(true);
      };

      openBtn.addEventListener('click', async (e) => {
        if (e.target === searchInput) {
          e.stopPropagation();
          return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (isOpen) closeDropdown();
        else await openDropdown();
      });

      membersList.addEventListener('scroll', async () => {
        if (!teamToggleHasMore || teamToggleIsLoading) return;
        const threshold = 40;
        if (membersList.scrollTop + membersList.clientHeight >= membersList.scrollHeight - threshold) {
          teamToggleIsLoading = true;
          teamTogglePage += 1;
          try {
            await loadTeamToggles(false);
          } finally {
            teamToggleIsLoading = false;
          }
        }
      });

      searchInput.addEventListener('input', (e) => {
        filterTeamMembers(e.target.value);
      });

      searchInput.addEventListener('click', (e) => {
        e.stopPropagation();
      });

      document.addEventListener('click', (e) => {
        if (isOpen && !manageSection.contains(e.target)) {
          closeDropdown();
        }
      });

      const profileCloseBtn = document.getElementById('userInfoCloseBtn');
      if (profileCloseBtn) {
        profileCloseBtn.addEventListener('click', closeDropdown);
      }

      window.closeTeamTogglesDropdown = closeDropdown;
    }

    async function loadTeamToggles(resetPage = false) {
      const loadingDiv = document.getElementById('team-toggles-loading');
      const contentDiv = document.getElementById('team-toggles-content');
      const errorDiv = document.getElementById('team-toggles-error');
      const membersList = document.getElementById('team-members-list');

      if (!membersList || !loadingDiv || !contentDiv || !errorDiv) return;

      if (resetPage) {
        loadingDiv.style.display = 'block';
        contentDiv.style.display = 'none';
        errorDiv.style.display = 'none';
      } else {
        loadingDiv.style.display = 'block';
      }

      try {
        const settings = await loadToggleSettings(teamTogglePage, teamTogglePerPage);

        if (!settings || settings.status !== 'success') {
          throw new Error((settings && settings.message) || 'Failed to load settings');
        }

        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';

        teamToggleHasMore = !!settings.has_more;
        const subordinates = settings.subordinates || [];

        if (subordinates.length > 0) {
          const fragmentWrapper = document.createElement('div');
          fragmentWrapper.innerHTML = subordinates.map((member) => createTeamMemberCard(member)).join('');
          attachTeamMemberEvents(fragmentWrapper);

          const newCards = Array.from(fragmentWrapper.children);
          newCards.forEach((card) => membersList.appendChild(card));
        } else if (resetPage) {
          membersList.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #64748b;">
              <i class="fas fa-users" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
              <p style="font-size: 15px;">No team members found</p>
            </div>
          `;
        }
      } catch (error) {
        console.error('Load team toggles error:', error);
        loadingDiv.style.display = 'none';
        errorDiv.style.display = 'block';
        const msg = document.getElementById('team-toggles-error-message');
        if (msg) msg.textContent = error.message || 'Failed to load team settings';
      }
    }

    function createTeamMemberCard(member) {
      const roleDisplay = member.user_type.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
      const isEnabled = member.enabled;
      const isLocked = member.locked || false;

      const toggleBg = isEnabled ? '#3B82F6' : '#E5E7EB';
      const toggleShadow = isEnabled ? '0 2px 8px rgba(59, 130, 246, 0.3)' : 'inset 0 1px 3px rgba(0, 0, 0, 0.1)';
      const knobLeft = isEnabled ? '23px' : '3px';
      const lockIcon = isLocked ? 'fa-lock' : 'fa-unlock';
      const lockClass = isLocked ? 'locked' : '';
      const toggleOpacity = isLocked ? '0.5' : '1';
      const toggleCursor = isLocked ? 'not-allowed' : 'pointer';

      return `
        <div class="team-member-card" data-tablename="${member.tablename}">
          <div class="team-member-header">
            <div class="team-member-info">
              <div class="team-member-name">${member.username}</div>
              <span class="team-member-role">${roleDisplay}</span>
            </div>
            <div class="team-member-controls" style="display: flex !important; flex-direction: row !important; gap: 10px !important; align-items: center !important; visibility: visible !important; flex-shrink: 0 !important;">
              <div class="team-toggle-switch ${isEnabled ? 'active' : ''} ${lockClass}"
                   data-tablename="${member.tablename}"
                   data-enabled="${isEnabled}"
                   data-locked="${isLocked}"
                   style="display: block !important; position: relative !important; width: 44px !important; height: 24px !important; min-width: 44px !important; min-height: 24px !important; background: ${toggleBg} !important; box-shadow: ${toggleShadow} !important; opacity: ${toggleOpacity} !important; cursor: ${toggleCursor} !important; border-radius: 999px !important; visibility: visible !important; flex-shrink: 0 !important;"
                   title="${isLocked ? 'Locked by you' : (isEnabled ? 'Enabled' : 'Disabled')}">
                <span class="knob" style="position: absolute !important; top: 3px !important; left: ${knobLeft} !important; width: 18px !important; height: 18px !important; background: #ffffff !important; border-radius: 50% !important; display: block !important; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important; transition: left 0.3s !important; visibility: visible !important;"></span>
              </div>
              <button class="toggle-lock-btn ${lockClass}"
                      data-tablename="${member.tablename}"
                      data-locked="${isLocked}"
                      style="display: inline-flex !important; visibility: visible !important;"
                      title="${isLocked ? 'Click to unlock' : 'Click to lock'}">
                <i class="fas ${lockIcon}"></i>
              </button>
            </div>
          </div>
        </div>
      `;
    }

    function attachTeamMemberEvents(rootElement) {
      const scope = rootElement || document;

      scope.querySelectorAll('.team-member-card').forEach((card) => {
        card.addEventListener('click', (e) => {
          e.stopPropagation();
        });
      });

      scope.querySelectorAll('.team-toggle-switch').forEach((toggle) => {
        toggle.addEventListener('click', async function onTeamToggleClick(e) {
          e.stopPropagation();
          e.preventDefault();
          if (this.classList.contains('updating')) return;

          const tablename = this.dataset.tablename;
          const currentEnabled = this.dataset.enabled === 'true';
          const isLocked = this.dataset.locked === 'true';
          const newEnabled = !currentEnabled;

          if (isLocked) {
            showNotification('Toggle is locked. Unlock it first to make changes.', 'warning');
            return;
          }

          this.classList.add('updating');

          try {
            const result = await updateToggle(tablename, newEnabled, false);

            if (result.status === 'success') {
              this.dataset.enabled = String(newEnabled);
              const knob = this.querySelector('span');
              if (newEnabled) {
                this.classList.add('active');
                this.style.background = '#3B82F6';
                this.style.boxShadow = '0 2px 8px rgba(59, 130, 246, 0.3)';
                if (knob) knob.style.left = '23px';
              } else {
                this.classList.remove('active');
                this.style.background = '#E5E7EB';
                this.style.boxShadow = 'inset 0 1px 3px rgba(0, 0, 0, 0.1)';
                if (knob) knob.style.left = '3px';
              }
              this.title = newEnabled ? 'Enabled' : 'Disabled';
              showNotification(result.message, 'success');
            } else {
              showNotification(result.message || 'Failed to update toggle', 'error');
            }
          } catch (error) {
            console.error('Team toggle update error:', error);
            showNotification('Failed to update toggle', 'error');
          } finally {
            this.classList.remove('updating');
          }
        });
      });

      scope.querySelectorAll('.toggle-lock-btn').forEach((btn) => {
        btn.addEventListener('click', async function onLockClick(e) {
          e.stopPropagation();
          e.preventDefault();

          if (this.classList.contains('updating')) return;

          const tablename = this.dataset.tablename;
          const currentlyLocked = this.dataset.locked === 'true';
          const shouldLock = !currentlyLocked;

          this.classList.add('updating');

          try {
            const result = await lockUnlockToggle(tablename, shouldLock);

            if (result.status === 'success') {
              this.dataset.locked = String(shouldLock);

              if (shouldLock) {
                this.classList.add('locked');
                this.innerHTML = '<i class="fas fa-lock"></i>';
                this.title = 'Click to unlock';
              } else {
                this.classList.remove('locked');
                this.innerHTML = '<i class="fas fa-unlock"></i>';
                this.title = 'Click to lock';
              }

              const card = this.closest('.team-member-card');
              const toggle = card ? card.querySelector('.team-toggle-switch') : null;

              if (toggle) {
                toggle.dataset.locked = String(shouldLock);
                if (shouldLock) {
                  toggle.classList.add('locked');
                  toggle.style.opacity = '0.5';
                  toggle.style.cursor = 'not-allowed';
                  toggle.title = 'Locked by you';
                } else {
                  toggle.classList.remove('locked');
                  toggle.style.opacity = '1';
                  toggle.style.cursor = 'pointer';
                  const isEnabled = toggle.dataset.enabled === 'true';
                  toggle.title = isEnabled ? 'Enabled' : 'Disabled';
                }
              }

              showNotification(result.message, 'success');
            } else {
              showNotification(result.message || `Failed to ${shouldLock ? 'lock' : 'unlock'} toggle`, 'error');
            }
          } catch (error) {
            console.error('Lock/unlock error:', error);
            showNotification(`Failed to ${shouldLock ? 'lock' : 'unlock'} toggle`, 'error');
          } finally {
            this.classList.remove('updating');
          }
        });
      });
    }

    async function checkRawDatabaseValue() {
      try {
        const response = await fetch(`${API_URL}?debug_toggle_state=1`, {
          method: 'GET',
          credentials: 'same-origin'
        });
        await response.json();
      } catch (error) {
        console.error('Debug check database error:', error);
      }
    }

    async function loadToggleSettings(page = 1, perPage = teamTogglePerPage) {
      try {
        const response = await fetch(`${API_URL}?get_overdue_toggle_settings=1&page=${page}&perPage=${perPage}`, {
          method: 'GET',
          credentials: 'same-origin'
        });

        const text = await response.text();
        try {
          return JSON.parse(text);
        } catch (parseError) {
          console.error('JSON parse error:', parseError);
          console.error('Response text:', text);
          throw new Error('Invalid JSON response from server');
        }
      } catch (error) {
        console.error('Load toggle settings error:', error);
        return { status: 'error', message: error.message };
      }
    }

    async function updateToggle(targetUser, enabled, lock) {
      try {
        const formData = new FormData();
        formData.append('update_overdue_toggle', '1');
        if (targetUser) formData.append('target_user', targetUser);
        formData.append('enabled', enabled ? '1' : '0');
        formData.append('lock', lock ? '1' : '0');

        const response = await fetch(API_URL, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });

        const text = await response.text();
        try {
          return JSON.parse(text);
        } catch (parseError) {
          console.error('JSON parse error:', parseError);
          console.error('Response text:', text);
          throw new Error('Invalid JSON response from server');
        }
      } catch (error) {
        console.error('Update toggle error:', error);
        throw error;
      }
    }

    async function lockUnlockToggle(targetUser, shouldLock) {
      try {
        const formData = new FormData();
        formData.append('lock_overdue_toggle', '1');
        formData.append('target_user', targetUser);
        formData.append('lock', shouldLock ? '1' : '0');

        const response = await fetch(API_URL, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });

        const text = await response.text();
        try {
          return JSON.parse(text);
        } catch (parseError) {
          console.error('JSON parse error:', parseError);
          console.error('Response text:', text);
          throw new Error('Invalid JSON response from server');
        }
      } catch (error) {
        console.error('Lock/unlock toggle error:', error);
        throw error;
      }
    }

    function showNotification(message, type = 'info') {
      if (window.showNotification && typeof window.showNotification === 'function' && window.showNotification !== showNotification) {
        window.showNotification(message, type);
        return;
      }

      const config = {
        success: { bg: '#10b981', icon: 'fa-check-circle', color: '#fff' },
        error: { bg: '#ef4444', icon: 'fa-exclamation-circle', color: '#fff' },
        warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle', color: '#fff' },
        info: { bg: '#3b82f6', icon: 'fa-info-circle', color: '#fff' }
      };

      const settings = config[type] || config.info;

      const toast = document.createElement('div');
      toast.className = 'custom-toast-notification';
      toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${settings.bg};
        color: ${settings.color};
        padding: 16px 20px;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2), 0 2px 8px rgba(0,0,0,0.1);
        z-index: 10001;
        font-size: 14px;
        font-weight: 500;
        max-width: 380px;
        min-width: 280px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(8px);
      `;

      toast.innerHTML = `
        <i class="fas ${settings.icon}" style="font-size: 18px; flex-shrink: 0;"></i>
        <span style="flex: 1; line-height: 1.5;">${message}</span>
        <button onclick="this.parentElement.remove()" style="background: transparent; border: none; color: ${settings.color}; cursor: pointer; font-size: 18px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; opacity: 0.8; transition: opacity 0.2s; flex-shrink: 0;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">
          <i class="fas fa-times"></i>
        </button>
      `;

      document.body.appendChild(toast);

      setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        setTimeout(() => toast.remove(), 400);
      }, 4000);
    }

    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInRight {
        from { transform: translateX(500px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(500px); opacity: 0; }
      }
      .custom-toast-notification:hover {
        box-shadow: 0 12px 32px rgba(0,0,0,0.25), 0 4px 12px rgba(0,0,0,0.15) !important;
        transform: translateY(-2px);
        transition: all 0.2s ease;
      }
      @media (max-width: 576px) {
        .custom-toast-notification {
          top: 20px !important;
          right: 10px !important;
          left: 10px !important;
          max-width: none !important;
          min-width: 0 !important;
          padding: 12px 16px !important;
          font-size: 13px !important;
        }
      }
    `;
    document.head.appendChild(style);
  })();

  setInterval(() => {
    const ids = [
      ['startDate', 'display_startDate', 'dd-mm-yy'],
      ['endDate', 'display_endDate', 'dd-mm-yy']
    ];

    ids.forEach((p) => {
      const inp = document.getElementById(p[0]);
      const sp = document.getElementById(p[1]);
      if (!inp || !sp) return;

      const v = inp.value;
      if (v) {
        const parts = v.split('-');
        if (parts.length === 3) sp.innerText = `${parts[2]}-${parts[1]}-${parts[0].substring(2)}`;
        else sp.innerText = v;
      } else {
        sp.innerText = p[2];
      }
    });
  }, 100);

  window.history.pushState({ page: 'current' }, '', window.location.href);
})();
