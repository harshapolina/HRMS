
// ============================================================================
// WHATSAPP BULK SEND — OMEGA REACH ESHA AI INTEGRATION
// ============================================================================
(function () {
    'use strict';

    function getEshaUser() { return window.eshaCurrentUser || {}; }

    function getSelectedLeadData() {
        var leads = [];
        $('.row-checkbox:checked').each(function () {
            var $row = $(this).closest('tr');
            var remarkId = $row.find('.remark-id-hidden').text().trim();
            var uploadId = $row.find('.lead-id-hidden').text().trim();
            var leadId = remarkId || uploadId;
            var leadName = ($row.find('.lead-info h4').first().text() ||
                $row.find('.lead-name h4').first().text())
                .replace(/\s+/g, ' ').trim();
            var phone = ($row.find('.phone-info').data('real-phone') ||
                $row.find('.phone-info').text())
                .toString().replace(/\s+/g, '').trim();
            // Extract project name without picking up nested status badges.
            // Strategy: try direct text nodes → first child element → full text fallback.
            var $proj = $row.find('.project-info, .assign-project-info').first();
            var project = '';
            if ($proj.length) {
                // Level 1: bare text nodes (project as direct text, status in a child span)
                project = $proj.contents().filter(function () { return this.nodeType === 3; })
                    .text().replace(/\s+/g, ' ').trim();
                // Level 2: project name is in first child element, status in a later sibling
                if (!project) {
                    project = $proj.children().first().text().replace(/\s+/g, ' ').trim();
                }
                // Level 3: anything — just take full cell text
                if (!project) {
                    project = $proj.text().replace(/\s+/g, ' ').trim();
                }
            }
            // Extract lead CRM status — look in common status selectors,
            // or fall back to the last child of the project cell (badge).
            var leadStatus = $row.find('.status-info, .lead-status-badge, .status-badge').first().text().trim() ||
                $row.find('.project-info, .assign-project-info').first().children().last().text().trim() || '';
            if (!leadId) return;
            leads.push({ leadId: leadId, remarkId: remarkId, uploadId: uploadId, leadName: leadName, phone: phone, project: project, status: leadStatus });
        });
        return leads;
    }

    // Format phone to E.164 (+91...), returns null if cannot format
    window.formatPhone = function (ph) {
        var clean = (ph || '').replace(/\D/g, '');
        if (clean.length === 10) return '+91' + clean;
        if (clean.length === 12 && clean.startsWith('91')) return '+' + clean;
        if (clean.length === 11 && clean.startsWith('0')) return '+91' + clean.substring(1);
        return clean.length >= 10 ? '+' + clean : null;
    };

    // ---- Open Modal ----
    $(document).on('click', '#whatsappBulkBtn', function () {
        var leads = getSelectedLeadData();
        if (!leads.length) { alert('Please select at least one lead first.'); return; }

        var tbody = document.getElementById('whatsappLeadsTbody');
        tbody.innerHTML = '';
        leads.forEach(function (l, i) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-lead-id', l.leadId);
            tr.setAttribute('data-remark-id', l.remarkId || '');
            tr.setAttribute('data-upload-id', l.uploadId || '');
            tr.setAttribute('data-lead-name', l.leadName);
            tr.setAttribute('data-lead-phone', l.phone);
            tr.setAttribute('data-project', l.project);
            tr.style.borderBottom = '1px solid var(--border-color,#e5e7eb)';
            var statusColor = '#6b7280';
            var s = (l.status || '').toLowerCase();
            if (s === 'converted') statusColor = '#16a34a';
            else if (s === 'interested' || s === 'fix site visit' || s === 'site visit done' || s === 'vc done') statusColor = '#2563eb';
            else if (s === 'call back' || s === 'follow up') statusColor = '#d97706';
            else if (s === 'rnr' || s === 'not connected') statusColor = '#dc2626';
            else if (s === 'pending') statusColor = '#9ca3af';
            tr.innerHTML =
                '<td style="padding:9px 12px;font-size:13px;">' + (i + 1) + '</td>' +
                '<td style="padding:9px 12px;font-size:13px;font-weight:500;">' + (l.leadName || '—') + '</td>' +
                '<td style="padding:9px 12px;font-size:13px;font-family:monospace;">' + (l.phone || '—') + '</td>' +
                '<td style="padding:9px 12px;font-size:13px;">' + (l.project || '—') + '</td>' +
                '<td style="padding:9px 12px;font-size:12px;text-align:center;" class="wa-status-cell">' +
                '<span style="color:' + statusColor + ';font-weight:500;">' + (l.status || 'Pending') + '</span>' +
                '</td>';
            tbody.appendChild(tr);
        });

        $('#whatsappSendSummary').hide().html('');
        $('#confirmWhatsappSend').prop('disabled', false)
            .html('<i class="fab fa-whatsapp" style="margin-right:5px;"></i>Confirm &amp; Send');

        $('body').css('overflow', 'hidden');
        $('#whatsappBulkModal').show();
    });

    // ---- Close Modal ----
    $(document).on('click', '#closeWhatsappModal, #cancelWhatsappModal', function () {
        $('#whatsappBulkModal').hide();
        $('body').css('overflow', '');
    });

    $(document).on('click', '#whatsappBulkModal', function (e) {
        if ($(e.target).is('#whatsappBulkModal')) {
            $('#whatsappBulkModal').hide();
            $('body').css('overflow', '');
        }
    });

    // ---- Confirm & Send ----
    $(document).on('click', '#confirmWhatsappSend', function () {
        var $btn = $(this);
        var user = getEshaUser();
        var leads = [];

        $('#whatsappLeadsTbody tr').each(function () {
            var $tr = $(this);
            leads.push({
                leadId: ($tr.data('lead-id') || '').toString(),
                remarkId: ($tr.data('remark-id') || '').toString(),
                uploadId: ($tr.data('upload-id') || '').toString(),
                leadName: ($tr.data('lead-name') || '').toString(),
                phone: ($tr.data('lead-phone') || '').toString(),
                project: ($tr.data('project') || '').toString(),
                $statusCell: $tr.find('.wa-status-cell')
            });
        });

        if (!leads.length) return;

        $('#whatsappBulkModal').hide();
        $('body').css('overflow', '');

        if ($('#whatsappBulkToast').length) $('#whatsappBulkToast').remove();
        var $toast = $('<div id="whatsappBulkToast" style="position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;padding:16px 20px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.2);z-index:10001;display:flex;align-items:center;gap:12px;opacity:0;transition:opacity 0.3s;"><i class="fas fa-spinner fa-spin" style="font-size:18px;"></i><span id="whatsappBulkToastMsg">Preparing to send...</span></div>');
        $('body').append($toast);
        setTimeout(function () { $('#whatsappBulkToast').css('opacity', '1'); }, 10);

        var groups = {};
        leads.forEach(function (lead) {
            var safeId = (lead.project || 'project').toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
            var projId = 'proj_' + safeId;
            if (!groups[projId]) {
                groups[projId] = {
                    projectId: projId,
                    projectName: (lead.project || 'Not Specified').replace(/\s+/g, ' ').trim(),
                    leads: []
                };
            }
            groups[projId].leads.push(lead);
        });

        var groupList = Object.values(groups);
        var successCount = 0, failCount = 0, totalCount = leads.length;

        function updateToast() {
            var processed = successCount + failCount;
            $('#whatsappBulkToastMsg').text('Sent ' + processed + ' of ' + totalCount + ' leads...');
        }

        function processGroup(gIndex) {
            if (gIndex >= groupList.length) {
                var isAllOk = failCount === 0;
                var isAllFail = successCount === 0;
                var bg = isAllOk ? '#10b981' : (isAllFail ? '#ef4444' : '#f59e0b');
                var icon = isAllOk ? 'fa-check-circle' : (isAllFail ? 'fa-times-circle' : 'fa-exclamation-triangle');
                $('#whatsappBulkToast').css('background', bg);
                $('#whatsappBulkToast').find('i').removeClass('fa-spinner fa-spin').addClass('fas ' + icon);
                $('#whatsappBulkToastMsg').html('<strong>Done!</strong> ' + successCount + ' of ' + totalCount + ' sent.' + (failCount > 0 ? ' (' + failCount + ' failed)' : ''));

                setTimeout(function () {
                    $('#whatsappBulkToast').fadeOut(400, function () { $(this).remove(); });
                }, 4000);
                return;
            }

            updateToast();

            var group = groupList[gIndex];
            var rowIds = [];
            var leadPhones = [];

            group.leads.forEach(function (lead) {
                lead.$statusCell.html(
                    '<span style="color:var(--text-muted,#9ca3af);font-size:12px;">' +
                    '<i class="fas fa-spinner fa-spin"></i> Sending…</span>'
                );
                var rawId = lead.remarkId || lead.leadId || '';
                rowIds.push('row_' + rawId);
                leadPhones.push(formatPhone(lead.phone) || lead.phone);
            });

            var spPhone = formatPhone(user.phone || '') || '+919632056699';

            var salespersonIdentifier = 'sp_' + (user.tablename || 'agent') + '(' + (user.username || 'Agent') + ')';

            var payload = {
                rowIds: rowIds.join(','),
                leadPhones: leadPhones.join(','),
                projectIds: group.projectId,
                projectNames: group.projectName,
                salespersonId: salespersonIdentifier,
                salespersonPhone: spPhone,
                tenantId: user.tenantId || 'tenant_omega_ba8790e7364b',
                accountId: 'omega_ba8790e7364b'
            };

            var ESHA_PROXY_BULK = '/incentiveapp_integration/userlogin1/superadmin/myapicontainer/whatsapp_history/whatsapp_history.php?action=bulk_assign';
            console.warn("ESHA DEBUG - Sending Bulk Assign payload:", payload);

            fetch(ESHA_PROXY_BULK, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (r) {
                    return r.json().catch(function () {
                        return { ok: false, error: 'parse_error', reason: 'Non-JSON response' };
                    });
                })
                .then(function (data) {
                    if (data && data.ok) {
                        group.leads.forEach(function (lead) {
                            successCount++;
                            lead.$statusCell.html(
                                '<span style="color:#16a34a;font-size:12px;font-weight:600;">' +
                                '<i class="fas fa-check-circle"></i> Sent</span>'
                            );
                        });
                    } else {
                        var reason = (data && (data.error || data.reason || data.message))
                            ? [data.error, data.reason, data.message].filter(Boolean).join(' — ')
                            : 'Request failed';
                        console.error('Esha Bulk Assign Failed! Reason: ' + reason);
                        group.leads.forEach(function (lead) {
                            failCount++;
                            lead.$statusCell.html(
                                '<span style="color:#dc2626;font-size:12px;" title="' +
                                reason.replace(/"/g, "'") + '">' +
                                '<i class="fas fa-times-circle"></i> Failed</span>'
                            );
                        });
                    }
                    setTimeout(function () { processGroup(gIndex + 1); }, 300);
                })
                .catch(function () {
                    console.error('Esha Bulk Assign network/server error.');
                    group.leads.forEach(function (lead) {
                        failCount++;
                        lead.$statusCell.html(
                            '<span style="color:#dc2626;font-size:12px;">' +
                            '<i class="fas fa-times-circle"></i> Error</span>'
                        );
                    });
                    setTimeout(function () { processGroup(gIndex + 1); }, 300);
                });
        }

        processGroup(0);
    });

})();

// ============================================================================
// WHATSAPP HISTORY — Action Icon + Sidebar
// ============================================================================
(function () {
    'use strict';

    var WA_HISTORY_PHP = '/incentiveapp_integration/userlogin1/superadmin/myapicontainer/whatsapp_history/whatsapp_history.php';
    var currentChatAssignUser = '';
    var chatOffset = 0;
    var chatLimit = 7;
    var hasMoreChat = true;
    var isFetchingChat = false;
    var MANUAL_DUPLICATE_COOLDOWN_MS = 20000;
    var manualSendGuard = {};

    // ── Sidebar open/close ────────────────────────────────────────────────────
    function openWaHistorySidebar(leadId, leadName, leadPhone, remarkId, uploadId, assignUser) {
        var $body = $('#waHistoryChatBody');

        currentChatAssignUser = assignUser || '';
        chatOffset = 0;
        hasMoreChat = true;
        isFetchingChat = false;

        // Clear unread badge from the DOM immediately upon opening the chat
        var $rowTarget = $('tr').filter(function () {
            var rId = $(this).find('.remark-id-hidden').text().trim();
            var uId = $(this).find('.lead-id-hidden').text().trim();
            return (rId && rId === String(remarkId)) || (!rId && uId && uId === String(uploadId));
        });
        if ($rowTarget.length) {
            $rowTarget.find('.wa-unread-badge').remove();
            $rowTarget.find('.unread-wa-hidden').text('0');
            if ($rowTarget.next('.details-row').length) {
                $rowTarget.next('.details-row').find('.wa-unread-badge').remove();
            }
        }

        $('#waHistoryLeadName').text(leadName || 'Lead');
        $('#waHistoryLeadPhone').text(leadPhone || '');

        // Store current IDs for the chat input and toggle
        $('#waHistoryCurrentRowId').val(remarkId || leadId || '');
        $('#waHistoryCurrentUploadId').val(uploadId || '');
        $('#waHistoryMsgInput').val('');

        $body.html(
            '<div style="text-align:center;padding:30px;color:#888;font-size:13px;">' +
            '<i class="fas fa-spinner fa-spin"></i> Loading conversation…</div>'
        );
        $('#waHistoryOverlay').show();
        $('#waHistorySidebar').css('right', '0');
        $('body').css('overflow', 'hidden');

        // Fetch from local DB — pass both row_id (exact row) and lead_phone (fallback)
        isFetchingChat = true;
        $.getJSON(WA_HISTORY_PHP
            + '?limit=' + chatLimit + '&offset=' + chatOffset
            + '&lead_phone=' + encodeURIComponent(leadPhone)
            + (remarkId ? '&row_id=' + encodeURIComponent(remarkId) : '&row_id=' + encodeURIComponent(leadId))
            + (uploadId ? '&lead_id=' + encodeURIComponent(uploadId) : '')
            + '&_ts=' + Date.now())
            .done(function (res) {
                isFetchingChat = false;
                if (typeof res.has_more !== 'undefined') hasMoreChat = res.has_more;

                // Apply the saved auto-reply toggle state from the database
                if (typeof res.auto_reply !== 'undefined') {
                    $('#waHistoryAutoReplyToggle').prop('checked', res.auto_reply);
                } else {
                    $('#waHistoryAutoReplyToggle').prop('checked', true); // Safe fallback
                }

                if (res.status === 'success' && res.messages && res.messages.length > 0) {
                    renderEshaConversation($body, res.messages);
                } else {
                    $body.html(
                        '<div class="wa-empty-state" style="text-align:center;padding:40px 16px;color:#888;font-size:13px;">' +
                        'No WhatsApp conversation yet.<br><small style="font-size:11px;"></small></div>'
                    );
                }
            })
            .fail(function () {
                isFetchingChat = false;
                $body.html('<div style="text-align:center;padding:30px;color:#e74c3c;font-size:13px;"><i class="fas fa-exclamation-circle"></i> Could not load conversation.</div>');
            });


    }

    // Load older messages (pagination)
    function loadOlderMessages() {
        if (!hasMoreChat || isFetchingChat) return;

        var $body = $('#waHistoryChatBody');
        var leadPhone = $('#waHistoryLeadPhone').text().trim();
        var remarkId = $('#waHistoryCurrentRowId').val();
        var uploadId = $('#waHistoryCurrentUploadId').val();
        var leadId = remarkId || uploadId;

        // Show a little loading indicator at the top
        var $loader = $('<div id="waHistoryLoaderTop" style="text-align:center;padding:10px;color:#aaaaaa;font-size:12px;background:rgba(0,0,0,0.5);border-radius:10px;width:max-content;margin:0 auto;"><i class="fas fa-spinner fa-spin"></i> Loading older messages...</div>');
        $body.prepend($loader);

        isFetchingChat = true;
        chatOffset += chatLimit;

        $.getJSON(WA_HISTORY_PHP
            + '?limit=' + chatLimit + '&offset=' + chatOffset
            + '&lead_phone=' + encodeURIComponent(leadPhone)
            + (remarkId ? '&row_id=' + encodeURIComponent(remarkId) : '&row_id=' + encodeURIComponent(leadId))
            + (uploadId ? '&lead_id=' + encodeURIComponent(uploadId) : '')
            + '&_ts=' + Date.now())
            .done(function (res) {
                isFetchingChat = false;
                $('#waHistoryLoaderTop').remove();

                if (typeof res.has_more !== 'undefined') hasMoreChat = res.has_more;

                if (res.status === 'success' && res.messages && res.messages.length > 0) {
                    var oldScrollHeight = $body[0].scrollHeight;
                    prependEshaConversation($body, res.messages);
                    var newScrollHeight = $body[0].scrollHeight;
                    $body.scrollTop($body.scrollTop() + (newScrollHeight - oldScrollHeight));
                }
            })
            .fail(function () {
                isFetchingChat = false;
                $('#waHistoryLoaderTop').remove();
                chatOffset -= chatLimit; // revert offset
            });
    }

    $('#waHistoryChatBody').on('scroll', function () {
        if ($(this).scrollTop() <= 10) {
            loadOlderMessages();
        }
    });

    function closeWaHistorySidebar() {
        $('#waHistorySidebar').css('right', '-420px');
        $('#waHistoryOverlay').hide();
        $('body').css('overflow', '');
    }

    function createBubbleHtml(msg) {
        var isLead = msg.role === 'lead';   // INBOUND
        var timeStr = '';
        if (msg.time) {
            try {
                // DB stores time without tz — treat as IST (+05:30)
                var rawTime = String(msg.time).replace(' ', 'T').replace(/([+-]\d{2}:\d{2}|Z)$/, '') + '+05:30';
                var d = new Date(rawTime);
                var opts = { timeZone: 'Asia/Kolkata' };
                timeStr = d.toLocaleDateString('en-IN', Object.assign({ day: '2-digit', month: 'short', year: '2-digit' }, opts)) +
                    ' ' + d.toLocaleTimeString('en-IN', Object.assign({ hour: '2-digit', minute: '2-digit', hour12: true }, opts));
            } catch (e) { timeStr = msg.time; }
        }
        var originalTxt = String(msg.message || msg.text || '');
        var mUrl = msg.media_url || msg.file_url || msg.attachment_url || (msg.attachments && msg.attachments[0] ? msg.attachments[0] : null);

        var match = originalTxt.match(/(?:Attached|Attachment)\(([^\)]+)\)/i);
        if (match) {
            mUrl = match[1].trim();
        }

        var txt = originalTxt.replace(/(?:Attached|Attachment)\([^\)]+\)/ig, '').trim();
        txt = txt.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        var mediaHtml = '';
        if (mUrl) {
            var mExt = mUrl.split('.').pop().toLowerCase().split('?')[0];
            var mIcon = 'fa-file-alt';
            if (['png', 'jpg', 'jpeg', 'gif', 'webp'].indexOf(mExt) !== -1) mIcon = 'fa-image';
            else if (['pdf'].indexOf(mExt) !== -1) mIcon = 'fa-file-pdf';
            else if (['mp4', 'mov', 'avi'].indexOf(mExt) !== -1) mIcon = 'fa-video';
            else if (['mp3', 'wav', 'ogg'].indexOf(mExt) !== -1) mIcon = 'fa-headphones';

            var filename = mUrl.split('/').pop().split('?')[0] || 'attachment';
            var downloadProxyUrl = WA_HISTORY_PHP + '?action=download_attachment&url=' + encodeURIComponent(mUrl) + '&filename=' + encodeURIComponent(filename);

            mediaHtml = '<a href="' + downloadProxyUrl + '" style="display:block; margin-top:6px; margin-bottom: 4px; padding:8px 12px; background:rgba(0,0,0,0.06); border-radius:8px; text-decoration:none; color:inherit; font-weight:600; display:flex; align-items:center; gap:8px;">' +
                '<i class="fas ' + mIcon + '" style="font-size:16px;"></i> View Attachment</a>';
        }

        if (isLead) {
            // Lead reply — left bubble (INBOUND)
            return '<div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;">' +
                '<div style="width:28px;height:28px;border-radius:50%;background:#0077b6;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">' +
                '<i class="fas fa-user" style="color:#fff;font-size:12px;"></i>' +
                '</div>' +
                '<div class="wa-bubble-lead" style="background:#e8f4fd;border-radius:0 10px 10px 10px;padding:9px 13px;box-shadow:0 1px 3px rgba(0,0,0,0.09);font-size:12.5px;line-height:1.55;max-width:82%;word-break:break-word;">' +
                '<div class="wa-bubble-sender-lead" style="font-weight:700;color:#0077b6;font-size:10px;margin-bottom:4px;">Lead</div>' +
                '<div class="wa-bubble-text" style="color:#1a1a1a;">' + txt + mediaHtml + '</div>' +
                '<div class="wa-bubble-time" style="font-size:10px;color:#aaa;margin-top:5px;text-align:right;">' + timeStr + '</div>' +
                '</div>' +
                '</div>';
        } else {
            // Esha or Agent outreach — right bubble (OUTBOUND)
            return '<div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;justify-content:flex-end;">' +
                '<div class="wa-bubble-esha" style="background:#fff;border-radius:10px 0 10px 10px;padding:9px 13px;box-shadow:0 1px 3px rgba(0,0,0,0.1);font-size:12.5px;line-height:1.55;max-width:82%;word-break:break-word;">' +
                '<div class="wa-bubble-sender-esha" style="font-weight:700;color:#075E54;font-size:10px;margin-bottom:4px;">REOS AI</div>' +
                '<div class="wa-bubble-text" style="color:#1a1a1a;">' + txt + mediaHtml + '</div>' +
                '<div class="wa-bubble-time" style="font-size:10px;color:#aaa;margin-top:5px;text-align:right;">' +
                timeStr + ' <i class="fas fa-check-double" style="color:#53bdeb;"></i>' +
                '</div>' +
                '</div>' +
                '<div style="width:28px;height:28px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">' +
                '<i class="fab fa-whatsapp" style="color:#fff;font-size:14px;"></i>' +
                '</div>' +
                '</div>';
        }
    }

    // ── Render FULL Esha conversation (OUTBOUND = Esha left, INBOUND = lead right)
    function renderEshaConversation($container, messages) {
        $container.empty();
        messages.forEach(function (msg) {
            $container.append(createBubbleHtml(msg));
        });
        setTimeout(function () { $container.scrollTop($container[0].scrollHeight); }, 50);
    }

    function prependEshaConversation($container, messages) {
        var rev = messages.slice().reverse();
        rev.forEach(function (msg) {
            $container.prepend(createBubbleHtml(msg));
        });
    }

    // ── Render local DB outreach records (fallback when Esha has no conversation)
    function renderLocalChatBubbles($container, history) {
        $container.empty();
        history.forEach(function (entry) {
            var timeStr = '';
            if (entry.timestamp) {
                try {
                    var rawTs = String(entry.timestamp).replace(' ', 'T').replace(/([+-]\d{2}:\d{2}|Z)$/, '') + '+05:30';
                    var d = new Date(rawTs);
                    var opts = { timeZone: 'Asia/Kolkata' };
                    timeStr = d.toLocaleDateString('en-IN', Object.assign({ day: '2-digit', month: 'short', year: '2-digit' }, opts)) +
                        ' ' + d.toLocaleTimeString('en-IN', Object.assign({ hour: '2-digit', minute: '2-digit', hour12: true }, opts));
                } catch (e) { timeStr = entry.timestamp; }
            }
            var msgText = entry.message || 'WhatsApp outreach sent';
            var sentBy = entry.sent_by_name || entry.sent_by || 'Agent';
            var originalTxt = String(msgText || '');
            var mUrl = entry.media_url || entry.file_url || entry.attachment_url || (entry.attachments && entry.attachments[0] ? entry.attachments[0] : null);

            var match = originalTxt.match(/(?:Attached|Attachment)\(([^\)]+)\)/i);
            if (match) {
                mUrl = match[1].trim();
            }

            var escaped = originalTxt.replace(/(?:Attached|Attachment)\([^\)]+\)/ig, '').trim();
            escaped = escaped.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            var mediaLocal = '';
            if (mUrl) {
                var mExt = mUrl.split('.').pop().toLowerCase().split('?')[0];
                var mIcon = 'fa-file-alt';
                if (['png', 'jpg', 'jpeg', 'gif', 'webp'].indexOf(mExt) !== -1) mIcon = 'fa-image';
                else if (['pdf'].indexOf(mExt) !== -1) mIcon = 'fa-file-pdf';
                else if (['mp4', 'mov', 'avi'].indexOf(mExt) !== -1) mIcon = 'fa-video';
                else if (['mp3', 'wav', 'ogg'].indexOf(mExt) !== -1) mIcon = 'fa-headphones';

                var filename = mUrl.split('/').pop().split('?')[0] || 'attachment';
                var downloadProxyUrl = WA_HISTORY_PHP + '?action=download_attachment&url=' + encodeURIComponent(mUrl) + '&filename=' + encodeURIComponent(filename);

                mediaLocal = '<a href="' + downloadProxyUrl + '" style="display:block; margin-top:6px; margin-bottom: 4px; padding:8px 12px; background:rgba(0,0,0,0.06); border-radius:8px; text-decoration:none; color:inherit; font-weight:600; display:flex; align-items:center; gap:8px;">' +
                    '<i class="fas ' + mIcon + '" style="font-size:16px;"></i> View Attachment</a>';
            }

            $container.append(
                '<div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;justify-content:flex-end;">' +
                '<div class="wa-bubble-esha" style="background:#fff;border-radius:10px 0 10px 10px;padding:9px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);font-size:12.5px;line-height:1.55;max-width:82%;word-break:break-word;">' +
                '<div class="wa-bubble-sender-esha" style="font-weight:700;color:#075E54;font-size:10px;margin-bottom:4px;">Esha AI</div>' +
                '<div class="wa-bubble-text" style="color:#1a1a1a;">' + escaped + mediaLocal + '</div>' +
                '<div class="wa-bubble-time" style="font-size:10px;color:#aaa;margin-top:5px;text-align:right;">' + timeStr + ' <i class="fas fa-check-double" style="color:#53bdeb;"></i></div>' +
                '</div>' +
                '<div style="width:28px;height:28px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">' +
                '<i class="fab fa-whatsapp" style="color:#fff;font-size:14px;"></i>' +
                '</div>' +
                '</div>'
            );
        });
        setTimeout(function () { $container.scrollTop($container[0].scrollHeight); }, 50);
    }

    // ── Close listeners ───────────────────────────────────────────────────────
    $(document).on('click', '#closeWaHistorySidebar, #waHistoryOverlay', closeWaHistorySidebar);

    // ── Refresh chat ──────────────────────────────────────────────────────────
    $(document).on('click', '#refreshWaHistoryBtn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.data('loading')) return;

        var $icon = $btn.find('i');
        var originalIconClasses = $icon.attr('class') || 'fas fa-sync-alt';
        $btn.data('loading', true)
            .prop('disabled', true)
            .attr('title', 'Refreshing conversation...')
            .css({ opacity: 0.65, cursor: 'wait' });
        $icon.removeClass('fa-sync-alt').addClass('fa-spinner fa-spin');

        var $body = $('#waHistoryChatBody');
        $('#waHistoryRefreshIndicator').remove();
        $body.prepend(
            '<div id="waHistoryRefreshIndicator" style="position:sticky;top:0;z-index:3;text-align:center;padding:6px 10px;margin:0 auto 8px;background:rgba(7,94,84,0.92);color:#fff;border-radius:999px;font-size:11px;width:max-content;box-shadow:0 2px 8px rgba(0,0,0,0.15);"><i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Refreshing conversation...</div>'
        );

        var leadPhone = $('#waHistoryLeadPhone').text().trim();
        var remarkId = $('#waHistoryCurrentRowId').val();
        var uploadId = $('#waHistoryCurrentUploadId').val();
        var leadId = remarkId || uploadId;

        if (!leadPhone && !remarkId && !uploadId) {
            $('#waHistoryRefreshIndicator').remove();
            $btn.data('loading', false)
                .prop('disabled', false)
                .attr('title', 'Refresh Chat')
                .css({ opacity: '', cursor: '' });
            $icon.attr('class', originalIconClasses);
            return;
        }

        $.getJSON(WA_HISTORY_PHP
            + '?lead_phone=' + encodeURIComponent(leadPhone)
            + (remarkId ? '&row_id=' + encodeURIComponent(remarkId) : '&row_id=' + encodeURIComponent(leadId))
            + (uploadId ? '&lead_id=' + encodeURIComponent(uploadId) : '')
            + '&_ts=' + Date.now())
            .done(function (res) {
                if (typeof res.auto_reply !== 'undefined') {
                    $('#waHistoryAutoReplyToggle').prop('checked', res.auto_reply);
                }
                if (res.status === 'success' && res.messages && res.messages.length > 0) {
                    renderEshaConversation($body, res.messages);
                } else {
                    $body.html(
                        '<div class="wa-empty-state" style="text-align:center;padding:40px 16px;color:#888;font-size:13px;">' +
                        'No WhatsApp conversation yet.<br><small style="font-size:11px;"></small></div>'
                    );
                }
            })
            .fail(function () {
                isFetchingChat = false;
                $body.html('<div style="text-align:center;padding:30px;color:#e74c3c;font-size:13px;"><i class="fas fa-exclamation-circle"></i> Could not refresh conversation.</div>');
            })
            .always(function () {
                setTimeout(function () {
                    $('#waHistoryRefreshIndicator').fadeOut(140, function () { $(this).remove(); });
                    $btn.data('loading', false)
                        .prop('disabled', false)
                        .attr('title', 'Refresh Chat')
                        .css({ opacity: '', cursor: '' });
                    $icon.attr('class', originalIconClasses);
                }, 300);
            });
    });

    // ── Inject WhatsApp history icon into actions column ─────────────────────
    function injectWaHistoryIcons() {
        var isMobile = window.innerWidth <= 900;
        // On mobile/tablet, skip desktop row injection — it's handled in the expand dropdown
        if (isMobile) return;

        $('table tbody tr').not('.details-row').each(function () {
            var $row = $(this);
            if ($row.find('.action-buttons-leads:not(.mobile) .wa-history-icon-btn').length) return; // already injected

            var remarkId = $row.find('.remark-id-hidden').text().trim();
            var uploadId = $row.find('.lead-id-hidden').text().trim();
            var leadId = remarkId || uploadId;
            var leadName = ($row.find('.lead-info h4').first().text() || $row.find('.lead-name h4').first().text()).replace(/\s+/g, ' ').trim();
            var leadPhone = ($row.find('.phone-info').data('real-phone') || $row.find('.phone-info').text()).toString().replace(/\s+/g, '').trim();
            var assignToUser = ($row.find('.user-cell').text() || '').replace(/\s+/g, ' ').trim();
            var unreadMsgCount = parseInt($row.find('.unread-wa-hidden').text().trim()) || 0;

            if (!leadId) return;

            var waInterest = $row.find('.wa-interest-hidden').text().trim();
            var borderStyle = 'border:none;';
            if (waInterest === 'interested') borderStyle = 'border: 2px solid #28a745 !important;';
            else if (waInterest === 'neutral') borderStyle = 'border: 2px solid #ffc107 !important;';
            else if (waInterest === 'not_interested') borderStyle = 'border: 2px solid #dc3545 !important;';

            var badgeHtml = '';
            if (unreadMsgCount > 0) {
                var dispCount = unreadMsgCount > 99 ? '99+' : unreadMsgCount;
                badgeHtml = '<span class="wa-unread-badge" style="position:absolute; top:-6px; right:-6px; background-color:#ef4444; color:white; border-radius:50%; min-width:18px; height:18px; font-size:10px; font-weight:bold; display:flex; align-items:center; justify-content:center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); z-index:2; border: 2px solid white;">' + dispCount + '</span>';
            }

            var $btn = $(
                '<div style="position:relative; display:inline-block; margin-right:4px;">' +
                '<button class="wa-history-icon-btn icon-action-btn" ' +
                'title="WhatsApp History" ' +
                'data-lead-id="' + leadId + '" ' +
                'data-remark-id="' + remarkId + '" ' +
                'data-upload-id="' + uploadId + '" ' +
                'data-assign-user="' + assignToUser + '" ' +
                'data-lead-name="' + leadName.replace(/"/g, "'") + '" ' +
                'data-lead-phone="' + leadPhone + '" ' +
                'style="display:inline-flex;align-items:center;justify-content:center;' +
                'width:34px;height:34px;border-radius:6px;cursor:pointer;' +
                'background:rgba(37,211,102,0.12);color:#25D366;font-size:13px;' +
                'vertical-align:middle;transition:background .2s;' + borderStyle + '">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="18" height="18" fill="currentColor"><path d="M316.5 288.8C309.8 288.8 303.2 290.9 297.6 294.6C292 298.3 287.7 303.7 285.1 310C282.5 316.3 281.9 323.1 283.2 329.7C284.5 336.3 287.8 342.4 292.6 347.1C297.4 351.8 303.5 355.1 310.1 356.4C316.7 357.7 323.6 357 329.8 354.4C336 351.8 341.3 347.4 345.1 341.8C348.9 336.2 350.8 329.6 350.8 322.9C350.8 313.8 347.1 305.1 340.7 298.7C334.3 292.3 325.6 288.7 316.5 288.8zM206.1 288.8C199.4 288.8 192.8 290.9 187.2 294.6C181.6 298.3 177.3 303.7 174.7 310C172.1 316.3 171.5 323.1 172.8 329.7C174.1 336.3 177.4 342.4 182.2 347.1C187 351.8 193.1 355.1 199.7 356.4C206.3 357.7 213.2 357 219.4 354.4C225.6 351.8 230.9 347.4 234.7 341.8C238.5 336.2 240.4 329.6 240.4 322.9C240.4 313.8 236.7 305.1 230.3 298.7C223.9 292.3 215.2 288.7 206.1 288.7L206.1 288.7zM427 288.8C408.2 288.9 393 304.3 393.1 323.1C393.2 341.9 408.6 357.1 427.4 357C446.2 356.9 461.4 341.5 461.3 322.7C461.2 303.9 445.8 288.7 427 288.8zM580.8 233.5C565.3 209.3 543.5 187.9 516.1 169.9C463.2 135.1 393.7 115.9 320.4 115.9C296.2 115.9 272.1 118 248.4 122.3C233.5 108 216.9 95.7 198.9 85.7C132.1 52.4 73.3 64.8 43.6 75.5C41.3 76.3 39.3 77.6 37.7 79.4C36.1 81.2 35 83.3 34.4 85.6C33.8 87.9 33.9 90.3 34.5 92.7C35.1 95.1 36.3 97.1 38 98.8C59 120.5 93.6 163.3 85.1 202.3C52 236.2 34 277 34 319.6C34 363 52 403.8 85.1 437.7C93.6 476.7 59 519.6 38 541.2C36.3 543 35.2 545.1 34.5 547.4C33.8 549.7 33.8 552.1 34.4 554.4C35 556.7 36.1 558.9 37.7 560.6C39.3 562.3 41.3 563.7 43.6 564.5C73.3 575.2 132.1 587.6 198.9 554.3C216.9 544.3 233.6 532 248.4 517.7C272.2 522 296.3 524.1 320.4 524.1C393.7 524.1 463.2 504.9 516.1 470.1C543.5 452.1 565.2 430.7 580.8 406.5C598.1 379.6 606.9 350.6 606.9 320.4C606.9 289.4 598.1 260.4 580.8 233.5L580.8 233.5zM317.4 473.9C287.2 474 257.1 470.1 228 462.4L207.9 481.8C196.7 492.5 184.3 501.8 170.8 509.4C154.4 517.6 136.6 522.7 118.3 524.3C119.3 522.5 120.2 520.7 121.1 518.9C141.3 481.8 146.7 448.4 137.4 418.8C104.4 392.8 84.6 359.6 84.6 323.4C84.6 240.3 188.9 172.9 317.4 172.9C445.9 172.9 550.3 240.3 550.3 323.4C550.3 406.5 446 473.9 317.4 473.9z"/></svg>' +
                '</button>' +
                badgeHtml +
                '</div>'
            );

            // Append to desktop .action-buttons-leads (not the mobile one)
            var $actionDiv = $row.find('.action-buttons-leads').not('.mobile').first();
            if ($actionDiv.length) {
                $actionDiv.append($btn);
            } else {
                $row.find('td').last().append($btn);
            }
        });
    }

    // ── Click handler for the history icon ───────────────────────────────────
    $(document).on('click', '.wa-history-icon-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        var $btn = $(this);
        openWaHistorySidebar(
            $btn.data('lead-id'),
            $btn.data('lead-name'),
            $btn.data('lead-phone'),
            $btn.data('remark-id'),
            $btn.data('upload-id'),
            $btn.data('assign-user')
        );
    });

    // ── Inject on DataTable draw and on ready ────────────────────────────────
    $(document).on('draw.dt', injectWaHistoryIcons);
    $(document).ready(function () {
        setTimeout(injectWaHistoryIcons, 1800);

        // ── Mobile: inject WA button into .action-buttons-leads.mobile ──────
        // Uses MutationObserver because handleResponsiveBehavior() rebuilds HTML each time

        var WA_SVG_MOBILE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="16" height="16" fill="currentColor"><path d="M316.5 288.8C309.8 288.8 303.2 290.9 297.6 294.6C292 298.3 287.7 303.7 285.1 310C282.5 316.3 281.9 323.1 283.2 329.7C284.5 336.3 287.8 342.4 292.6 347.1C297.4 351.8 303.5 355.1 310.1 356.4C316.7 357.7 323.6 357 329.8 354.4C336 351.8 341.3 347.4 345.1 341.8C348.9 336.2 350.8 329.6 350.8 322.9C350.8 313.8 347.1 305.1 340.7 298.7C334.3 292.3 325.6 288.7 316.5 288.8zM206.1 288.8C199.4 288.8 192.8 290.9 187.2 294.6C181.6 298.3 177.3 303.7 174.7 310C172.1 316.3 171.5 323.1 172.8 329.7C174.1 336.3 177.4 342.4 182.2 347.1C187 351.8 193.1 355.1 199.7 356.4C206.3 357.7 213.2 357 219.4 354.4C225.6 351.8 230.9 347.4 234.7 341.8C238.5 336.2 240.4 329.6 240.4 322.9C240.4 313.8 236.7 305.1 230.3 298.7C223.9 292.3 215.2 288.7 206.1 288.7zM427 288.8C408.2 288.9 393 304.3 393.1 323.1C393.2 341.9 408.6 357.1 427.4 357C446.2 356.9 461.4 341.5 461.3 322.7C461.2 303.9 445.8 288.7 427 288.8zM580.8 233.5C565.3 209.3 543.5 187.9 516.1 169.9C463.2 135.1 393.7 115.9 320.4 115.9C296.2 115.9 272.1 118 248.4 122.3C233.5 108 216.9 95.7 198.9 85.7C132.1 52.4 73.3 64.8 43.6 75.5C41.3 76.3 39.3 77.6 37.7 79.4C36.1 81.2 35 83.3 34.4 85.6C33.8 87.9 33.9 90.3 34.5 92.7C35.1 95.1 36.3 97.1 38 98.8C59 120.5 93.6 163.3 85.1 202.3C52 236.2 34 277 34 319.6C34 363 52 403.8 85.1 437.7C93.6 476.7 59 519.6 38 541.2C36.3 543 35.2 545.1 34.5 547.4C33.8 549.7 33.8 552.1 34.4 554.4C35 556.7 36.1 558.9 37.7 560.6C39.3 562.3 41.3 563.7 43.6 564.5C73.3 575.2 132.1 587.6 198.9 554.3C216.9 544.3 233.6 532 248.4 517.7C272.2 522 296.3 524.1 320.4 524.1C393.7 524.1 463.2 504.9 516.1 470.1C543.5 452.1 565.2 430.7 580.8 406.5C598.1 379.6 606.9 350.6 606.9 320.4C606.9 289.4 598.1 260.4 580.8 233.5zM317.4 473.9C287.2 474 257.1 470.1 228 462.4L207.9 481.8C196.7 492.5 184.3 501.8 170.8 509.4C154.4 517.6 136.6 522.7 118.3 524.3C119.3 522.5 120.2 520.7 121.1 518.9C141.3 481.8 146.7 448.4 137.4 418.8C104.4 392.8 84.6 359.6 84.6 323.4C84.6 240.3 188.9 172.9 317.4 172.9C445.9 172.9 550.3 240.3 550.3 323.4C550.3 406.5 446 473.9 317.4 473.9z"/></svg>';

        function injectIntoMobileDropdown($mobileActions) {
            if (!$mobileActions.length || $mobileActions.find('.wa-history-icon-btn').length) return;
            var $detailsRow = $mobileActions.closest('tr');
            var $mainRow = $detailsRow.prev('tr').not('.details-row');
            if (!$mainRow.length) $mainRow = $detailsRow.prevAll('tr').not('.details-row').first();
            var remarkId = $mainRow.find('.remark-id-hidden').text().trim();
            var uploadId = $mainRow.find('.lead-id-hidden').text().trim();
            var leadId = remarkId || uploadId;
            var leadName = ($mainRow.find('.lead-info h4').first().text() || '').replace(/\s+/g, ' ').trim();
            var leadPhone = ($mainRow.find('.phone-info').data('real-phone') || $mainRow.find('.phone-info').text()).toString().replace(/\s+/g, '').trim();
            var assignToUser = ($mainRow.find('.user-cell').text() || '').replace(/\s+/g, ' ').trim();
            var unreadMsgCount = parseInt($mainRow.find('.unread-wa-hidden').text().trim()) || 0;

            if (!leadId) return;

            var badgeHtml = '';
            if (unreadMsgCount > 0) {
                var dispCount = unreadMsgCount > 99 ? '99+' : unreadMsgCount;
                badgeHtml = '<span class="wa-unread-badge" style="position:absolute; top:-4px; right:-4px; background-color:#ef4444; color:white; border-radius:50%; min-width:16px; height:16px; font-size:9px; font-weight:bold; display:flex; align-items:center; justify-content:center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); z-index:2; border: 1.5px solid white;">' + dispCount + '</span>';
            }

            var waInterest = $mobileActions.find('.wa-interest-hidden').text().trim() ||
                $detailsRow.find('.wa-interest-hidden').text().trim() ||
                $mainRow.find('.wa-interest-hidden').text().trim();
            var borderStyle = 'border:none;';
            if (waInterest === 'interested') borderStyle = 'border: 2px solid #28a745 !important; border-radius:6px;';
            else if (waInterest === 'neutral') borderStyle = 'border: 2px solid #ffc107 !important; border-radius:6px;';
            else if (waInterest === 'not_interested') borderStyle = 'border: 2px solid #dc3545 !important; border-radius:6px;';

            $mobileActions.append(
                '<button class="action-btn wa-history-icon-btn tooltip" ' +
                'title="Chat History" data-tooltip="Chat History" ' +
                'data-lead-id="' + leadId + '" ' +
                'data-remark-id="' + remarkId + '" ' +
                'data-upload-id="' + uploadId + '" ' +
                'data-assign-user="' + assignToUser + '" ' +
                'data-lead-name="' + leadName.replace(/"/g, "'") + '" ' +
                'data-lead-phone="' + leadPhone + '" ' +
                'style="position:relative;background:rgba(37,211,102,0.12);color:#25D366;' + borderStyle + '">' +
                WA_SVG_MOBILE + badgeHtml + '</button>'
            );
        }

        var tBody = document.querySelector('#leadsTable tbody') || document.querySelector('table tbody');
        if (tBody && window.MutationObserver) {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(function (node) {
                        if (node.nodeType !== 1) return;
                        var $node = $(node);
                        var $targets = ($node.hasClass('action-buttons-leads') && $node.hasClass('mobile'))
                            ? $node : $node.find('.action-buttons-leads.mobile');
                        $targets.each(function () { injectIntoMobileDropdown($(this)); });
                    });
                });
            }).observe(tBody, { childList: true, subtree: true });
        }

        // Fallback: inject into any already-rendered mobile dropdowns
        setTimeout(function () {
            $('.action-buttons-leads.mobile').each(function () { injectIntoMobileDropdown($(this)); });
        }, 2000);
    });

    // ── Save to DB after each successful Esha send ────────────────────────────
    // messageText = the actual Esha opening message sent to the lead
    window.waHistorySaveEntry = function (leadId, leadName, leadPhone, project, messageText, remarkId, uploadId) {
        var user = window.eshaCurrentUser || {};
        $.ajax({
            url: 'update_status.php?save_whatsapp_history=1',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                lead_id: leadId,
                remark_id: remarkId,
                upload_id: uploadId,
                lead_name: leadName,
                lead_phone: leadPhone,
                project: project,
                message: messageText || 'Esha AI WhatsApp outreach sent',
                sent_by: user.tablename || 'agent',
                sent_by_name: user.username || 'Agent',
                api_status: 'sent'
            }),
            dataType: 'json'
        });
    };

    // ── Chat Attachments Logic ────────────────────────────
    $('#waAttachToggleBtn').on('click', function (e) {
        e.preventDefault();
        $('#waAttachMenu').toggle();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#waAttachToggleBtn, #waAttachMenu').length) {
            $('#waAttachMenu').hide();
        }
    });

    $('.wa-attach-btn').on('click', function (e) {
        e.preventDefault();
        var type = $(this).data('type');
        var $input = $('#waHistoryAttachmentInput');

        if (type === 'image') $input.attr('accept', 'image/*');
        else if (type === 'audio') $input.attr('accept', 'audio/*');
        else if (type === 'video') $input.attr('accept', 'video/*');
        else if (type === 'document') $input.attr('accept', '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv');
        else $input.removeAttr('accept');

        $('#waHistoryAttachmentIsDoc').val(type === 'document' ? 'true' : 'false');
        $('#waAttachMenu').hide();
        $input.click();
    });

    $('#waHistoryAttachmentInput').on('change', function () {
        var file = this.files[0];
        if (file) {
            $('#waAttachmentName').text(file.name);
            $('#waAttachmentPreview').css('display', 'flex');

            var isDoc = $('#waHistoryAttachmentIsDoc').val() === 'true';
            var fileType = file.type || '';
            var fileName = file.name.toLowerCase();
            var iconClass = 'fas fa-file-alt';
            var iconBgColor = '#54656f';

            if (isDoc) {
                if (fileType === 'application/pdf' || fileName.endsWith('.pdf')) {
                    iconClass = 'fas fa-file-pdf';
                    iconBgColor = '#ef4444'; // Red for PDF
                } else if (fileName.match(/\.(doc|docx)$/)) {
                    iconClass = 'fas fa-file-word';
                    iconBgColor = '#2563eb'; // Blue for Word
                } else if (fileName.match(/\.(xls|xlsx|csv)$/)) {
                    iconClass = 'fas fa-file-excel';
                    iconBgColor = '#10b981'; // Green for Excel
                } else {
                    iconBgColor = '#5F66CD';
                }
            } else {
                if (fileType.startsWith('image/')) { iconClass = 'fas fa-image'; iconBgColor = '#1BA4ED'; }
                else if (fileType.startsWith('video/')) { iconClass = 'fas fa-video'; iconBgColor = '#EC407A'; }
                else if (fileType.startsWith('audio/')) { iconClass = 'fas fa-headphones'; iconBgColor = '#0FB2A9'; }
            }
            $('#waAttachmentPreviewIcon').attr('class', iconClass + ' fa-fw');
            $('#waAttachmentPreviewIconBg').css('background', iconBgColor);
        } else {
            $('#waAttachmentPreview').hide();
        }
    });

    $('#waRemoveAttachmentBtn').on('click', function (e) {
        e.preventDefault();
        $('#waHistoryAttachmentInput').val('');
        $('#waAttachmentPreview').hide();
    });


    // ── Chat Input and Auto-Reply logic ────────────────────────────
    $('#waHistorySendMsgBtn').on('click', function () {
        var msg = $('#waHistoryMsgInput').val().trim();
        var rowId = $('#waHistoryCurrentRowId').val();
        var fileInput = document.getElementById('waHistoryAttachmentInput');
        var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

        if (!rowId) return;
        if (!msg && !hasFile) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        var user = window.eshaCurrentUser || {};
        var leadPhoneRaw = $('#waHistoryLeadPhone').text().trim();
        var leadPhoneClean = formatPhone(leadPhoneRaw) || leadPhoneRaw;
        var spPhoneClean = formatPhone(user.phone || '') || '+919632056699';
        var autoReplyState = $('#waHistoryAutoReplyToggle').length ? $('#waHistoryAutoReplyToggle').is(':checked') : true;
        var uploadId = $('#waHistoryCurrentUploadId').val();

        var guardKey = String(rowId || uploadId || leadPhoneClean || 'default');
        var normalizedMsg = String(msg).replace(/\s+/g, ' ').trim().toLowerCase();
        var nowTs = Date.now();
        var lastManual = manualSendGuard[guardKey];

        // When auto-reply is OFF, prevent rapid duplicate manual sends of identical text.
        // Don't block if there's a file being uploaded
        if (!hasFile && !autoReplyState && lastManual && lastManual.text === normalizedMsg && (nowTs - lastManual.ts) < MANUAL_DUPLICATE_COOLDOWN_MS) {
            var waitSeconds = Math.ceil((MANUAL_DUPLICATE_COOLDOWN_MS - (nowTs - lastManual.ts)) / 1000);
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane" style="margin-left:-2px;"></i>');
            alert('Duplicate message blocked. Please wait ' + waitSeconds + ' second(s) before sending the same message again.');
            return;
        }

        if (!autoReplyState && !hasFile) {
            manualSendGuard[guardKey] = { text: normalizedMsg, ts: nowTs, pending: true };
        }

        var payloadSpName = currentChatAssignUser || user.tablename || 'agent';
        var salespersonIdentifier = 'sp_' + payloadSpName + '(' + (user.username || 'Agent') + ')';

        var payload = {
            tenantId: user.tenantId || 'tenant_omega_ba8790e7364b',
            accountId: 'omega_ba8790e7364b',
            rowId: 'row_' + rowId,
            salespersonId: salespersonIdentifier,
            leadPhone: leadPhoneClean,
            senderPhone: spPhoneClean,
            autoReplyEnabled: autoReplyState,
            text: msg
        };
        console.warn("ESHA DEBUG - Sending Sales Message payload:", payload);

        var ajaxOpts = {};
        if (hasFile) {
            var fd = new FormData();
            Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });
            fd.append('attachment', fileInput.files[0]);
            var isDoc = $('#waHistoryAttachmentIsDoc').val() === 'true';
            if (isDoc) {
                fd.append('sendAsDocument', 'true');
            }

            ajaxOpts = {
                url: WA_HISTORY_PHP + '?action=sales_send',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false
            };
        } else {
            ajaxOpts = {
                url: WA_HISTORY_PHP + '?action=sales_send',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload)
            };
        }

        $.ajax(ajaxOpts).done(function (res) {
            var parsed = null;
            try {
                parsed = (typeof res === 'string') ? JSON.parse(res) : res;
            } catch (e) {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane" style="margin-left:-2px;"></i>');
                alert('Backend returned invalid format: ' + res);
                return;
            }

            if (parsed && parsed.ok === false) {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane" style="margin-left:-2px;"></i>');
                alert('Esha Error: ' + (parsed.error || parsed.message || 'Unknown error'));
                return;
            }

            console.log("ESHA API SUCCESS (sales-send):", parsed);

            if (!autoReplyState) {
                manualSendGuard[guardKey] = { text: normalizedMsg, ts: Date.now(), pending: false };
            }

            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane" style="margin-left:-2px;"></i>');
            $('#waHistoryMsgInput').val('');
            $('#waHistoryAttachmentInput').val('');
            $('#waAttachmentPreview').hide();

            var leadPhone = $('#waHistoryLeadPhone').text();

            setTimeout(function () {
                // Fetch the newest messages up to where they've loaded so far
                var curTotal = chatOffset + chatLimit;
                $.getJSON(WA_HISTORY_PHP + '?limit=' + curTotal + '&offset=0' + '&lead_phone=' + encodeURIComponent(leadPhone) + '&row_id=' + encodeURIComponent(rowId) + '&lead_id=' + encodeURIComponent(uploadId) + '&_ts=' + Date.now())
                    .done(function (res2) {
                        if (res2.status === 'success' && res2.messages) {
                            if (typeof res2.has_more !== 'undefined') hasMoreChat = res2.has_more;
                            renderEshaConversation($('#waHistoryChatBody'), res2.messages);
                        }
                    });
            }, 600);

        }).fail(function (xhr) {
            if (!autoReplyState && manualSendGuard[guardKey] && manualSendGuard[guardKey].pending) {
                delete manualSendGuard[guardKey];
            }
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane" style="margin-left:-2px;"></i>');
            var errMsg = 'Unknown error';
            try { errMsg = JSON.parse(xhr.responseText).error || JSON.parse(xhr.responseText).message; } catch (e) { errMsg = xhr.responseText; }
            alert('Failed to send message: ' + errMsg);
        });
    });

    $('#waHistoryMsgInput').on('keypress', function (e) {
        if (e.which === 13) {
            $('#waHistorySendMsgBtn').click();
        }
    });

    $('#waHistoryAutoReplyToggle').on('change', function () {
        var isChecked = $(this).is(':checked');
        var rowId = $('#waHistoryCurrentRowId').val();
        if (!rowId) return;

        var user = window.eshaCurrentUser || {};
        var leadPhoneRaw = $('#waHistoryLeadPhone').text().trim();
        var leadPhoneClean = window.formatPhone(leadPhoneRaw) || leadPhoneRaw;
        var payloadSpName = currentChatAssignUser || user.tablename || 'agent';
        var salespersonIdentifier = 'sp_' + payloadSpName + '(' + (user.username || 'Agent') + ')';

        var spPhoneClean = window.formatPhone(user.phone || '') || '+919632056699';

        var payload = {
            rowId: 'row_' + rowId,
            salespersonId: salespersonIdentifier,
            leadPhone: leadPhoneClean,
            senderPhone: spPhoneClean,
            autoReplyEnabled: isChecked,
            tenantId: user.tenantId || 'tenant_omega_ba8790e7364b',
            accountId: 'omega_ba8790e7364b'
        };
        console.warn("ESHA DEBUG - Sending Auto Reply toggle payload:", payload);

        $.ajax({
            url: WA_HISTORY_PHP + '?action=auto_reply',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload)
        }).done(function (res) {
            var parsed = null;
            try {
                parsed = (typeof res === 'string') ? JSON.parse(res) : res;
            } catch (e) {
                alert('Backend returned invalid format: ' + res);
                $('#waHistoryAutoReplyToggle').prop('checked', !isChecked);
                return;
            }

            if (parsed && parsed.ok === false) {
                alert('Esha Error: ' + (parsed.error || parsed.message || 'Unknown error'));
                $('#waHistoryAutoReplyToggle').prop('checked', !isChecked);
            } else {
                console.log("ESHA API SUCCESS (auto-reply):", parsed);
            }
        }).fail(function (xhr) {
            var errMsg = 'Unknown error';
            try { errMsg = JSON.parse(xhr.responseText).error || JSON.parse(xhr.responseText).message; } catch (e) { errMsg = xhr.responseText; }
            alert('Failed to update auto-reply status: ' + errMsg);
            $('#waHistoryAutoReplyToggle').prop('checked', !isChecked);
        });
    });

    // ── Live Unread Count Poller (Runs every 15 seconds) ─────────────────────
    setInterval(function () {
        var remarkIds = [];
        var uploadIds = [];
        var rowsMap = {};

        // Find all active rows currently visible on the datatable page
        $('table tbody tr').not('.details-row').each(function () {
            var $row = $(this);
            var rId = $row.find('.remark-id-hidden').text().trim();
            var uId = $row.find('.lead-id-hidden').text().trim();

            if (rId) {
                remarkIds.push(rId);
                rowsMap['remark_' + rId] = $row;
            } else if (uId) {
                uploadIds.push(uId);
                rowsMap['upload_' + uId] = $row;
            }
        });

        if (remarkIds.length === 0 && uploadIds.length === 0) return;

        $.ajax({
            url: 'get_wa_unread_counts.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ remark_ids: remarkIds, upload_ids: uploadIds })
        }).done(function (res) {
            if (res && res.status === 'success' && res.counts) {
                Object.keys(res.counts).forEach(function (key) {
                    var count = res.counts[key];
                    var $row = rowsMap[key];
                    if (!$row) return;

                    // Update hidden count
                    $row.find('.unread-wa-hidden').text(count);

                    var badgeDisp = count > 99 ? '99+' : count;
                    var deskBadgeHtml = '<span class="wa-unread-badge" style="position:absolute; top:-6px; right:-6px; background-color:#ef4444; color:white; border-radius:50%; min-width:18px; height:18px; font-size:10px; font-weight:bold; display:flex; align-items:center; justify-content:center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); z-index:2; border: 2px solid white;">' + badgeDisp + '</span>';
                    var mobBadgeHtml = '<span class="wa-unread-badge" style="position:absolute; top:-4px; right:-4px; background-color:#ef4444; color:white; border-radius:50%; min-width:16px; height:16px; font-size:9px; font-weight:bold; display:flex; align-items:center; justify-content:center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); z-index:2; border: 1.5px solid white;">' + badgeDisp + '</span>';

                    // Update desktop action column
                    var $desktopBtnContainer = $row.find('.action-buttons-leads').not('.mobile').find('.wa-history-icon-btn').parent();
                    $desktopBtnContainer.find('.wa-unread-badge').remove();
                    if (count > 0) $desktopBtnContainer.append(deskBadgeHtml);

                    // Update mobile detail-row column
                    var $mobileBtnContainer = $row.next('.details-row').find('.wa-history-icon-btn').parent();
                    if ($mobileBtnContainer.length) {
                        $mobileBtnContainer.find('.wa-unread-badge').remove();
                        if (count > 0) $mobileBtnContainer.append(mobBadgeHtml);
                    } else {
                        // Fallback for smaller screens where the mobile dropdown isn't expanded yet
                        $mobileBtnContainer = $row.find('.action-buttons-leads.mobile').find('.wa-history-icon-btn').parent();
                        if ($mobileBtnContainer.length) {
                            $mobileBtnContainer.find('.wa-unread-badge').remove();
                            if (count > 0) $mobileBtnContainer.append(mobBadgeHtml);
                        }
                    }
                });
            }
        });
    }, 15000);

})();

/* ================================================================
   WhatsApp QR Link — #waQrScanBtn
   Calls ?action=pool_add → POST /api/pool/add → shows QR modal
   ================================================================ */
(function () {
    var POOL_PROXY = '/incentiveapp_integration/userlogin1/superadmin/myapicontainer/whatsapp_history/whatsapp_history.php?action=pool_add';
    var qrExpiryTimer = null;

    function showQrSpinner() {
        $('#waQrSpinner').css('display', 'flex');
        $('#waQrImage').hide();
        $('#waQrExpiry').text('');
    }

    function showQrImage(src, expiresAt) {
        $('#waQrSpinner').hide();
        $('#waQrImage').attr('src', src).show();

        // Countdown timer until expiry
        clearInterval(qrExpiryTimer);
        if (expiresAt) {
            function tick() {
                var secsLeft = Math.max(0, Math.round((new Date(expiresAt) - Date.now()) / 1000));
                if (secsLeft <= 0) {
                    $('#waQrExpiry').html('<span style="color:#dc3545;">QR expired — click Refresh to get a new one.</span>');
                    clearInterval(qrExpiryTimer);
                } else {
                    $('#waQrExpiry').text('Expires in ' + secsLeft + 's');
                }
            }
            tick();
            qrExpiryTimer = setInterval(tick, 1000);
        }
    }

    function fetchQr() {
        var user = window.eshaCurrentUser || {};
        var rawPhone = (user.phone || '').replace(/\D/g, '');
        var e164Phone = rawPhone.length === 10 ? '+91' + rawPhone : (rawPhone ? '+' + rawPhone : '+919632056699');
        var accountId = 'sender_' + (user.tablename || 'agent');
        var salespersonId = 'sp_' + (user.tablename || 'agent');

        showQrSpinner();

        $.ajax({
            url: POOL_PROXY,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                numbers: [{
                    phone: e164Phone,
                    account_id: accountId,
                    salesperson_id: salespersonId
                }]
            })
        }).done(function (res) {
            var parsed = typeof res === 'string' ? JSON.parse(res) : res;
            if (parsed && parsed.ok && parsed.results && parsed.results[0] && parsed.results[0].qr) {
                showQrImage(parsed.results[0].qr, parsed.results[0].qr_expires_at);
            } else {
                $('#waQrSpinner').hide();
                $('#waQrExpiry').html('<span style="color:#dc3545;">Could not generate QR. ' + (parsed.error || '') + '</span>');
            }
        }).fail(function (xhr) {
            $('#waQrSpinner').hide();
            var errMsg = 'Network error';
            try { errMsg = JSON.parse(xhr.responseText).error || xhr.responseText; } catch (e) {}
            $('#waQrExpiry').html('<span style="color:#dc3545;">Error: ' + errMsg + '</span>');
        });
    }

    function openQrModal() {
        $('#waQrModal').css('display', 'flex');
        fetchQr();
    }

    function closeQrModal() {
        $('#waQrModal').hide();
        clearInterval(qrExpiryTimer);
        $('#waQrImage').attr('src', '').hide();
        $('#waQrSpinner').hide();
        $('#waQrExpiry').text('');
    }

    $(document).on('click', '#waQrScanBtn', function (e) {
        e.preventDefault();
        openQrModal();
    });

    $(document).on('click', '#waQrModalClose', closeQrModal);
    $(document).on('click', '#waQrRefreshBtn', fetchQr);

    // Close on backdrop click
    $(document).on('click', '#waQrModal', function (e) {
        if ($(e.target).is('#waQrModal')) closeQrModal();
    });
})();
