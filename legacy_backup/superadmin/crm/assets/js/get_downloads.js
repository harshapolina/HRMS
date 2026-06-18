(function () {
  // Build URLs relative to the script location to avoid hard-coded paths and 404s.
  const scriptEl = document.currentScript || document.querySelector('script[src*="get_downloads.js"]');
  const scriptUrl = scriptEl ? new URL(scriptEl.src, window.location.href) : new URL(window.location.href);
  // get_downloads.js lives in /crm/assets/js/, but PHP endpoints live one level above /crm/.
  const baseUrl = new URL('../..', scriptUrl); // resolves to the CRM root directory
  const requestUrl = (path) => new URL(path, baseUrl).toString();

  const downloadBtn = document.getElementById('downloadCsv');
  const statusEl = document.getElementById('exportStatus');
  const originalHtml = downloadBtn ? downloadBtn.innerHTML : '';
  const originalTitle = downloadBtn ? downloadBtn.getAttribute('title') : '';

  if (!downloadBtn) return;

  // Helpers
  function setStatus(text, busy = false) {
    if (!statusEl) return;
    statusEl.innerHTML = busy
      ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + text
      : text;
  }
  function disableButton() { downloadBtn.disabled = true; downloadBtn.classList.add('disabled'); }
  function enableButton() { downloadBtn.disabled = false; downloadBtn.classList.remove('disabled'); }

  // --- IMPORTANT: collect the exact filters used by your table UI ---
  // Replace your getCurrentFilters() with this
  function getCurrentFilters() {
    const params = {};

    // Check for selected checkboxes first - if any leads are selected, only export those
    const selectedCheckboxes = document.querySelectorAll('input.select-row:checked');
    if (selectedCheckboxes.length > 0) {
      // User has selected specific leads, collect their IDs
      params.selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
      console.log(`[Download] ${selectedCheckboxes.length} leads selected for export:`, params.selectedIds);
    }

    // search / pagination inputs — adapt selectors if different
    const search = document.querySelector('#searchQuery, input[name="searchQuery"]');
    if (search) params.searchQuery = search.value || '';

    const rowsPerPage = document.querySelector('#rowsPerPage, select[name="rowsPerPage"]');
    if (rowsPerPage) params.rowsPerPage = rowsPerPage.value;

    const pageEl = document.querySelector('#page, input[name="page"]');
    if (pageEl) params.page = pageEl.value || 1;

    // top-level date range (keep for backward compatibility)
    const startTop = document.querySelector('#isolatedFilterStartDate, input[name="startDate"]');
    const endTop = document.querySelector('#isolatedFilterEndDate, input[name="endDate"]');
    if (startTop) params.startDate = startTop.value || '';
    if (endTop) params.endDate = endTop.value || '';

    // currentFilter and showDeletedOnly
    const cf = document.querySelector('#currentFilter, select[name="currentFilter"]');
    if (cf) params.currentFilter = cf.value || '';

    const sd = document.querySelector('#showDeletedOnly, input[name="showDeletedOnly"]');
    if (sd) params.showDeletedOnly = sd.checked ? '1' : '0';

    // Helper to ensure any single value becomes an array
    const toArray = (v) => {
      if (v === null || v === undefined || v === '') return [];
      if (Array.isArray(v)) return v;
      return [v];
    };

    // Build multiFilters: prefer window.multifilters (set by your modal)
    let mf = {};
    try {
      if (window.multifilters && typeof window.multifilters === 'object' && Object.keys(window.multifilters).length) {
        mf = window.multifilters;
        // normalize - ensure non-date keys are arrays
        Object.keys(mf).forEach(k => {
          if (k === 'start_date' || k === 'end_date') return;
          mf[k] = (mf[k] === null || mf[k] === undefined) ? [] : toArray(mf[k]);
        });
      }
    } catch (e) {
      mf = {};
    }

    // fallback: if window.multifilters empty, read specific modal inputs (so download works even if not persisted)
    if (!mf || Object.keys(mf).length === 0) {
      const readVal = (sel) => {
        const el = document.querySelector(sel);
        return el ? (el.value || '') : '';
      };

      const nameVal = readVal('#isolatedFilterCustumername');
      if (nameVal) mf.name = toArray(nameVal);

      const emailVal = readVal('#isolatedFilterEmail');
      if (emailVal) mf.email = toArray(emailVal);

      const numberVal = readVal('#isolatedFilterContactnumber');
      if (numberVal) mf.number = toArray(numberVal);

      const locationVal = readVal('#isolatedFilterLocation');
      if (locationVal) mf.location = toArray(locationVal);

      const sourceVal = readVal('#isolatedFilterSourceOfLead');
      if (sourceVal) mf.source_of_lead = toArray(sourceVal);

      const projectVal = readVal('#isolatedFilterAssignedProjectName');
      if (projectVal) mf.project = toArray(projectVal);

      const assignedUserVal = readVal('#isolatedFilterAssignedUserName');
      if (assignedUserVal) mf.assign_to_user = toArray(assignedUserVal);

      const statusVal = readVal('#isolatedFilterStatus');
      if (statusVal) mf.status = toArray(statusVal);

      // date fields inside multiFilters in the same naming as your old code
      const startDate = readVal('#isolatedFilterStartDate');
      const endDate = readVal('#isolatedFilterEndDate');
      if (startDate) mf.start_date = startDate.trim();
      if (endDate) mf.end_date = endDate.trim();
    } else {
      // if window.multifilters existed and we have top-level startDate/endDate, sync both ways for safety
      if (params.startDate && params.startDate !== '') mf.start_date = params.startDate;
      if (params.endDate && params.endDate !== '') mf.end_date = params.endDate;
    }

    // ensure mf exists and set on params
    params.multiFilters = mf || {};

    return params;
  }

  function qsFromParams(params) {
    const qp = new URLSearchParams();
    for (const k in params) {
      if (params[k] === undefined || params[k] === null) continue;
      if (k === 'multiFilters' || k === 'selectedIds') {
        qp.append(k, JSON.stringify(params[k])); // always send JSON string for objects/arrays
      } else {
        qp.append(k, params[k]);
      }
    }
    qp.append('download', '1');
    return qp.toString();
  }

  // Kick the PHP worker via HTTP so jobs get processed even if cron is not running.
  async function triggerWorkerOnce(job) {
    try {
      if (!job || !job.jobId || !job.token) return;
      // Fire and forget; do not block UI. Pass jobId so worker processes this job immediately.
      fetch(requestUrl(`export_worker.php?jobId=${encodeURIComponent(job.jobId)}&token=${encodeURIComponent(job.token)}`), { credentials: 'same-origin' });
    } catch (e) {
      console.warn('Export worker trigger failed (will rely on cron):', e);
    }
  }

  // Poll status until done
  function pollJobStatus(jobId, token, { interval = 3000, timeout = 1000 * 60 * 10 } = {}) {
    return new Promise((resolve, reject) => {
      const start = Date.now();
      async function tick() {
        try {
          const u = `${requestUrl('export_status.php')}?jobId=${encodeURIComponent(jobId)}&token=${encodeURIComponent(token)}`;
          const r = await fetch(u, { credentials: 'same-origin' });
          if (!r.ok) throw new Error('Status fetch failed: ' + r.status);
          const j = await r.json();
          if (!j || !j.status) throw new Error('Malformed status response');
          if (j.status === 'done') return resolve(j);
          if (j.status === 'failed') return reject(new Error(j.error || 'Server export failed'));
          if (Date.now() - start > timeout) return reject(new Error('Export timed out'));
          setTimeout(tick, interval);
        } catch (err) {
          if (Date.now() - start > timeout) return reject(err);
          setTimeout(tick, interval);
        }
      }
      tick();
    });
  }

  // Download button click handler
  downloadBtn.addEventListener('click', async function (ev) {
    ev.preventDefault();

    // Clear any cached download URL and reset state to ensure fresh export based on current selection
    if (downloadBtn.dataset.downloadUrl) {
      delete downloadBtn.dataset.downloadUrl;
      downloadBtn.dataset.state = '';
      downloadBtn.innerHTML = originalHtml;
      if (originalTitle) downloadBtn.setAttribute('title', originalTitle);
    }

    if (downloadBtn.dataset.state === 'processing') return; // already working

    disableButton();
    downloadBtn.dataset.state = 'processing';
    setStatus('Creating export job...', true);
    // Keep the icon layout; append a tiny spinner without replacing button content.
    if (!downloadBtn.querySelector('.dl-spin')) {
      downloadBtn.insertAdjacentHTML('beforeend', '<span class="dl-spin spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>');
    }

    try {
      const params = getCurrentFilters();
      const qs = qsFromParams(params);
      const url = requestUrl('export_request.php') + '?' + qs;

      const resp = await fetch(url, { credentials: 'same-origin' });
      if (!resp.ok) {
        const txt = await resp.text().catch(() => null);
        throw new Error('Failed to create job: ' + (txt || resp.status));
      }
      const job = await resp.json();
      if (!job.jobId || !job.token) throw new Error('Invalid job response from server');

      setStatus('Export queued. Processing on server...', true);

      // Ensure the worker runs at least once for this job (useful on hosts without cron).
      triggerWorkerOnce(job);

      // Poll until job done and automatically download the file
      const result = await pollJobStatus(job.jobId, job.token, { interval: 3000, timeout: 1000 * 60 * 10 });
      if (!result.downloadUrl) throw new Error('No download URL returned');

      // Automatically trigger download
      setStatus('Download starting...', false);
      const a = document.createElement('a');
      a.href = result.downloadUrl;
      a.target = '_blank';
      a.download = ''; // Force download
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);

      // Reset button state
      downloadBtn.dataset.state = '';
      downloadBtn.innerHTML = originalHtml;
      if (originalTitle) downloadBtn.setAttribute('title', originalTitle);
      const spin = downloadBtn.querySelector('.dl-spin');
      if (spin) spin.remove();
      setStatus('Download complete!', false);
      setTimeout(() => setStatus('', false), 3000);
      enableButton();

    } catch (err) {
      console.error(err);
      downloadBtn.dataset.state = '';
      downloadBtn.innerHTML = originalHtml;
      if (originalTitle) downloadBtn.setAttribute('title', originalTitle);
      const spin = downloadBtn.querySelector('.dl-spin');
      if (spin) spin.remove();
      setStatus('Error: ' + (err.message || 'Unknown'), false);
      enableButton();
      alert('Export failed: ' + (err.message || 'Unknown'));
    }
  });

})();
