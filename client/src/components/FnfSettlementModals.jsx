import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import {
  X, FileText, Edit3, Mail, Eye, Printer, Send,
  Bold, Underline, List, ListOrdered, AlignLeft, AlignCenter, AlignRight, AlignJustify,
  Table, Link2, Code, Minus, Eraser, RotateCcw, PenTool
} from 'lucide-react';

const LETTER_LABELS = {
  no_dues_certificate: 'No Dues Certificate',
  relieving_letter: 'Relieving Letter'
};

const FnfSettlementModals = ({ employee, onClose, onRefresh, setViewingPayslip }) => {
  const [fnfData, setFnfData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [letterEditor, setLetterEditor] = useState(null);
  const [editingHtml, setEditingHtml] = useState('');
  const [codeMode, setCodeMode] = useState(false);
  const [saving, setSaving] = useState(false);
  
  // Signature States
  const [showSignatureModal, setShowSignatureModal] = useState(false);
  const [savedRange, setSavedRange] = useState(null);
  const [sigForm, setSigForm] = useState({ name: 'Shivali V Rai', font: 'Dancing Script', slot: 'hr' });
  
  const editorRef = useRef(null);

  const handleOpenSignatureModal = () => {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      
      // Make sure the selection is actually inside our editor
      if (editorRef.current && editorRef.current.contains(range.commonAncestorContainer)) {
        // Create a temporary placeholder span
        const span = document.createElement('span');
        span.id = 'temp-sig-placeholder';
        span.style.display = 'inline-block';
        span.style.width = '0px';
        span.style.height = '0px';
        
        range.deleteContents();
        range.insertNode(span);
        
        // Update the editing HTML state with the new content
        setEditingHtml(editorRef.current.innerHTML);
      }
    }
    setShowSignatureModal(true);
  };

  const handleCancelSignature = () => {
    if (editorRef.current) {
      const placeholder = editorRef.current.querySelector('#temp-sig-placeholder');
      if (placeholder) {
        placeholder.remove();
        setEditingHtml(editorRef.current.innerHTML);
      }
    }
    setShowSignatureModal(false);
  };

  const handleSlotChange = (slot) => {
    let name = sigForm.name;
    if (slot === 'hr') {
      name = 'Shivali V Rai';
    } else if (slot === 'employee') {
      name = employee?.username || '';
    }
    setSigForm({ ...sigForm, slot, name });
  };

  const replacePlaceholderOrAppend = (sigHtml) => {
    if (editorRef.current) {
      const placeholder = editorRef.current.querySelector('#temp-sig-placeholder');
      if (placeholder) {
        const div = document.createElement('div');
        div.innerHTML = sigHtml;
        const frag = document.createDocumentFragment();
        let child;
        while ((child = div.firstChild)) {
          frag.appendChild(child);
        }
        placeholder.parentNode.replaceChild(frag, placeholder);
      } else {
        editorRef.current.innerHTML += sigHtml;
      }
    }
  };

  const handleInsertSignature = () => {
    const sigHtml = `<span style="font-family: '${sigForm.font}', cursive; font-size: 28px; color: #1e3a8a; display: inline-block; padding: 5px 0; font-weight: normal;">${sigForm.name}</span>`;
    
    if (sigForm.slot === 'hr') {
      let hrContainer = null;
      if (editorRef.current) {
        hrContainer = editorRef.current.querySelector('.hr-sig-img');
      }

      if (hrContainer) {
        hrContainer.innerHTML = sigHtml;
      } else {
        replacePlaceholderOrAppend(sigHtml);
      }
    } else if (sigForm.slot === 'employee') {
      let empContainer = null;
      if (editorRef.current) {
        empContainer = editorRef.current.querySelector('.employee-sig-img');
      }

      if (empContainer) {
        empContainer.innerHTML = sigHtml;
      } else {
        replacePlaceholderOrAppend(sigHtml);
      }
    } else {
      replacePlaceholderOrAppend(sigHtml);
    }

    // Clean up placeholder if it still exists
    if (editorRef.current) {
      const placeholder = editorRef.current.querySelector('#temp-sig-placeholder');
      if (placeholder) {
        placeholder.remove();
      }
      setEditingHtml(editorRef.current.innerHTML);
    }
    setShowSignatureModal(false);
  };

  useEffect(() => {
    if (!employee?._id) return;
    setLoading(true);
    axios.get(`/api/fnf/${employee._id}`)
      .then((res) => setFnfData(res.data))
      .catch((err) => alert(err.response?.data?.message || 'Failed to load FNF data'))
      .finally(() => setLoading(false));
  }, [employee?._id]);

  const refreshFnf = async () => {
    const res = await axios.get(`/api/fnf/${employee._id}`);
    setFnfData(res.data);
    onRefresh?.();
  };

  const cleanUnderscores = (html) => {
    if (!html) return html;
    
    const pattern = /Employee Signature:\s*_+/g;
    if (pattern.test(html)) {
      return html.replace(pattern, `
        <div style="display: inline-block; text-align: left; vertical-align: bottom;">
          <div class="employee-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: center; border-bottom: 1px solid #111; min-width: 200px; margin-bottom: 5px;"></div>
          <p style="margin: 0; font-weight: bold; text-align: center;">Employee Signature</p>
        </div>
      `.trim());
    }
    return html;
  };

  const openLetterEditor = async (type) => {
    try {
      const res = await axios.get(`/api/fnf/${employee._id}/letters/${type}`);
      const cleanedHtml = cleanUnderscores(res.data.customHtml || '');
      setEditingHtml(cleanedHtml);
      setCodeMode(false);
      setLetterEditor({ type, title: res.data.title });
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to load letter');
    }
  };

  const handleToolbarCmd = (cmd, value = null) => {
    document.execCommand(cmd, false, value);
    if (editorRef.current) setEditingHtml(editorRef.current.innerHTML);
  };

  const handleLinkPrompt = () => {
    const url = prompt('Enter the link URL:');
    if (url) {
      handleToolbarCmd('createLink', url);
    }
  };

  const handleTablePrompt = () => {
    const rows = prompt('Enter number of rows:', '3');
    const cols = prompt('Enter number of columns:', '3');
    if (rows && cols) {
      let tableHtml = `<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">`;
      for (let r = 0; r < parseInt(rows); r++) {
        tableHtml += `<tr>`;
        for (let c = 0; c < parseInt(cols); c++) {
          tableHtml += `<td style="border: 1px solid #ddd; padding: 8px;">Cell</td>`;
        }
        tableHtml += `</tr>`;
      }
      tableHtml += `</table>`;
      handleToolbarCmd('insertHTML', tableHtml);
    }
  };

  const handleSaveLetter = async () => {
    if (!letterEditor) return;
    setSaving(true);
    try {
      const html = codeMode ? editingHtml : (editorRef.current?.innerHTML || editingHtml);
      await axios.put(`/api/fnf/${employee._id}/letters/${letterEditor.type}`, { customHtml: html });
      alert('Letter saved successfully!');
      setLetterEditor(null);
      await refreshFnf();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to save letter');
    } finally {
      setSaving(false);
    }
  };

  const handleResetTemplate = async () => {
    if (!letterEditor || !window.confirm('Reset to default template? Unsaved changes will be lost.')) return;
    try {
      const res = await axios.get(`/api/fnf/${employee._id}/letters/${letterEditor.type}`, { params: { fresh: '1' } });
      const cleanedHtml = cleanUnderscores(res.data.customHtml || '');
      setEditingHtml(cleanedHtml);
      if (editorRef.current) editorRef.current.innerHTML = cleanedHtml;
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to reset template');
    }
  };

  const handleMailLetter = async (type) => {
    if (!window.confirm(`Email ${LETTER_LABELS[type]} to ${employee.useremail}?`)) return;
    try {
      await axios.post(`/api/fnf/${employee._id}/letters/${type}/send`);
      alert('Letter emailed successfully!');
      await refreshFnf();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to send letter');
    }
  };

  const handleSendFinalMail = async () => {
    if (!window.confirm(`Send final FNF mail to ${employee.useremail}?`)) return;
    try {
      await axios.post(`/api/fnf/${employee._id}/send-final-mail`);
      alert('Final settlement mail sent successfully!');
      await refreshFnf();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to send final mail');
    }
  };

  const handleSaveFnf = async () => {
    try {
      const lastWorkingDay = employee.deactivated_at
        ? employee.deactivated_at.split('T')[0]
        : new Date().toISOString().split('T')[0];
      await axios.post('/api/fnf', {
        userId: employee._id,
        lastWorkingDay,
        unpaidSalary: fnfData?.existing?.unpaidSalary || 0,
        leaveEncashment: fnfData?.existing?.leaveEncashment || 0,
        bonusIncentives: fnfData?.existing?.bonusIncentives || 0,
        deductions: fnfData?.existing?.deductions || 0,
        netSettlement: fnfData?.existing?.netSettlement || 0,
        status: fnfData?.existing?.status || 'Pending',
        assetsReturned: fnfData?.pendingAssetsCount === 0
      });
      alert('FNF details saved successfully!');
      await refreshFnf();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to save FNF');
    }
  };

  const printHtml = (html) => {
    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    win.print();
  };

  const previewHtml = (html) => {
    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
  };

  if (!employee) return null;

  return (
    <>
      <div className="modal-overlay" onClick={onClose}>
        <div className="modal-popup max-w-3xl" onClick={(e) => e.stopPropagation()}>
          <div className="modal-popup-header !rounded-t-2xl !bg-canvas !text-ink !border-b !border-hairline">
            <div className="flex items-center gap-3">
              <FileText className="w-5 h-5" />
              <h3 className="font-display font-semibold text-base">FNF Settlement</h3>
            </div>
            <button onClick={onClose} className="p-1.5 hover:bg-surface-soft rounded-lg text-muted">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="modal-panel-body p-6 space-y-6">
            {loading ? (
              <div className="py-12 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-ink mx-auto" />
              </div>
            ) : (
              <>
                <div>
                  <p className="text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Employee Name</p>
                  <h4 className="font-display font-semibold text-xl capitalize text-ink">{employee.username}</h4>
                  <p className="text-xs text-muted mt-1">{employee.useremail} · {employee.employee_id || '—'}</p>
                </div>

                {fnfData?.pendingAssetsCount > 0 && (
                  <div className="border border-hairline bg-surface-soft/50 rounded-lg p-3 text-xs text-body">
                    Employee has {fnfData.pendingAssetsCount} unreturned device(s). Please ensure assets are returned before final settlement.
                  </div>
                )}

                <div>
                  <p className="modal-section-title mb-3">Exit Documents</p>
                  <div className="space-y-3">
                    {['no_dues_certificate', 'relieving_letter'].map((type) => {
                      const generated = Boolean(fnfData?.letters?.[type]);
                      return (
                        <div key={type} className="border border-hairline rounded-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                          <div>
                            <p className="font-semibold text-sm text-ink">{LETTER_LABELS[type]}</p>
                            <p className="text-xs text-muted mt-0.5">
                              Status: <span className={generated ? 'text-ink font-semibold' : 'text-muted'}>{generated ? 'Generated' : 'Not Generated'}</span>
                            </p>
                          </div>
                          <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => openLetterEditor(type)} className="btn-secondary btn-sm">
                              <Edit3 className="w-3.5 h-3.5" /> Generate / Edit
                            </button>
                            {generated && (
                              <>
                                <button type="button" onClick={() => previewHtml(fnfData.letters[type].customHtml)} className="btn-secondary btn-sm">
                                  <Eye className="w-3.5 h-3.5" />
                                </button>
                                <button type="button" onClick={() => printHtml(fnfData.letters[type].customHtml)} className="btn-secondary btn-sm">
                                  <Printer className="w-3.5 h-3.5" />
                                </button>
                                <button type="button" onClick={() => handleMailLetter(type)} className="btn-secondary btn-sm">
                                  <Mail className="w-3.5 h-3.5" />
                                </button>
                              </>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>

                <div>
                  <p className="modal-section-title mb-3">Past Payslips</p>
                  <div className="border border-hairline rounded-lg overflow-hidden">
                    <table className="w-full text-xs text-left">
                      <thead>
                        <tr className="border-b border-hairline bg-surface-soft text-muted text-[10px] font-semibold uppercase">
                          <th className="px-4 py-2">Month</th>
                          <th className="px-4 py-2 text-right">Net Pay</th>
                          <th className="px-4 py-2 text-right">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-hairline-soft">
                        {(fnfData?.payslips || []).map((ps) => (
                          <tr key={ps._id}>
                            <td className="px-4 py-3 font-semibold text-ink">{ps.monthYear}</td>
                            <td className="px-4 py-3 text-right">₹{(ps.netSalary || 0).toLocaleString()}</td>
                            <td className="px-4 py-3 text-right">
                              <div className="flex gap-1 justify-end">
                                <button type="button" onClick={() => setViewingPayslip?.(ps)} className="btn-icon w-7 h-7">
                                  <Eye className="w-3.5 h-3.5" />
                                </button>
                                <button type="button" onClick={() => printHtml(document.querySelector('.payslip-print-area')?.innerHTML || '')} className="btn-icon w-7 h-7 hidden">
                                  <Printer className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                        {(fnfData?.payslips || []).length === 0 && (
                          <tr>
                            <td colSpan="3" className="px-4 py-6 text-center text-muted">No processed payslips found.</td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="flex flex-col sm:flex-row gap-3 pt-2 border-t border-hairline">
                  <button type="button" onClick={handleSaveFnf} className="btn-secondary flex-1 justify-center">
                    Save Details
                  </button>
                  <button type="button" onClick={handleSendFinalMail} className="btn-primary flex-1 justify-center">
                    <Send className="w-4 h-4" /> Send Final Mail
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      </div>

      {letterEditor && (
        <div className="modal-overlay modal-overlay-nested" onClick={() => setLetterEditor(null)}>
          <div className="modal-panel max-w-4xl w-full overflow-hidden max-h-[95vh]" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header mb-0 pb-4">
              <h3 className="text-ink font-semibold text-sm uppercase tracking-wider">{letterEditor.title}</h3>
              <button onClick={() => setLetterEditor(null)} className="p-1.5 hover:bg-surface-soft rounded-lg text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex flex-wrap gap-1 bg-surface-soft p-2 rounded-lg mb-3 border border-hairline text-body">
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('bold'); }} className="p-1.5 hover:bg-canvas rounded" title="Bold"><Bold className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('underline'); }} className="p-1.5 hover:bg-canvas rounded" title="Underline"><Underline className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('removeFormat'); }} className="p-1.5 hover:bg-canvas rounded" title="Clear Format"><Eraser className="w-4 h-4" /></button>
              <div className="w-px bg-hairline my-1 mx-1" />
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('insertUnorderedList'); }} className="p-1.5 hover:bg-canvas rounded" title="Bullet List"><List className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('insertOrderedList'); }} className="p-1.5 hover:bg-canvas rounded" title="Numbered List"><ListOrdered className="w-4 h-4" /></button>
              <div className="w-px bg-hairline my-1 mx-1" />
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('justifyLeft'); }} className="p-1.5 hover:bg-canvas rounded" title="Align Left"><AlignLeft className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('justifyCenter'); }} className="p-1.5 hover:bg-canvas rounded" title="Align Center"><AlignCenter className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('justifyRight'); }} className="p-1.5 hover:bg-canvas rounded" title="Align Right"><AlignRight className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('justifyFull'); }} className="p-1.5 hover:bg-canvas rounded" title="Justify"><AlignJustify className="w-4 h-4" /></button>
              <div className="w-px bg-hairline my-1 mx-1" />
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleTablePrompt(); }} className="p-1.5 hover:bg-canvas rounded" title="Insert Table"><Table className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleLinkPrompt(); }} className="p-1.5 hover:bg-canvas rounded" title="Insert Link"><Link2 className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleToolbarCmd('insertHorizontalRule'); }} className="p-1.5 hover:bg-canvas rounded" title="Horizontal Line"><Minus className="w-4 h-4" /></button>
              <button type="button" onMouseDown={(e) => { e.preventDefault(); handleOpenSignatureModal(); }} className="p-1.5 hover:bg-canvas rounded" title="Add Signature"><PenTool className="w-4 h-4" /></button>
              <button type="button" onClick={() => setCodeMode(!codeMode)} className={`p-1.5 rounded ${codeMode ? 'bg-ink text-canvas' : 'hover:bg-canvas'}`} title="Toggle HTML View"><Code className="w-4 h-4" /></button>
            </div>

            <div className="flex-1 overflow-y-auto min-h-0 bg-canvas border border-hairline rounded-lg">
              {codeMode ? (
                <textarea
                  value={editingHtml}
                  onChange={(e) => setEditingHtml(e.target.value)}
                  className="w-full h-full font-mono text-[11px] p-6 focus:outline-none bg-canvas text-ink resize-none min-h-[50vh]"
                />
              ) : (
                <div
                  ref={editorRef}
                  contentEditable
                  suppressContentEditableWarning
                  onBlur={() => { if (editorRef.current) setEditingHtml(editorRef.current.innerHTML); }}
                  dangerouslySetInnerHTML={{ __html: editingHtml }}
                  className="p-6 text-ink focus:outline-none min-h-[50vh] leading-relaxed"
                />
              )}
            </div>

            <div className="modal-footer mb-0 pt-4 mt-4">
              <button type="button" onClick={handleResetTemplate} className="btn-secondary btn-sm">
                <RotateCcw className="w-3.5 h-3.5" /> Reset to Template
              </button>
              <button type="button" onClick={() => setLetterEditor(null)} className="btn-secondary btn-sm">Close</button>
              <button type="button" onClick={handleSaveLetter} disabled={saving} className="btn-primary btn-sm">
                {saving ? 'Saving...' : 'Save Letter'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Signature Creator Modal Overlay */}
      {showSignatureModal && (
        <div className="modal-overlay modal-overlay-nested" style={{ zIndex: 130 }} onClick={handleCancelSignature}>
          <div className="modal-popup max-w-md p-6 space-y-4" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between border-b border-hairline-soft pb-3">
              <h4 className="font-semibold text-ink text-xs uppercase flex items-center gap-1.5">
                <PenTool className="w-4 h-4 text-indigo-600" />
                Insert Signature Block
              </h4>
              <button onClick={handleCancelSignature} className="p-1 hover:bg-surface-soft rounded text-muted">
                <X className="w-4.5 h-4.5" />
              </button>
            </div>
            
            <div className="space-y-3 text-xs font-semibold text-body">
              <div>
                <label className="block text-muted text-[10px] uppercase font-semibold mb-1" htmlFor="sig-name-input">Name to sign</label>
                <input
                  id="sig-name-input"
                  type="text"
                  value={sigForm.name}
                  onChange={(e) => setSigForm({ ...sigForm, name: e.target.value })}
                  className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink bg-canvas"
                />
              </div>

              <div>
                <label className="block text-muted text-[10px] uppercase font-semibold mb-1" htmlFor="sig-font-select">Handwriting Cursive Style</label>
                <select
                  id="sig-font-select"
                  value={sigForm.font}
                  onChange={(e) => setSigForm({ ...sigForm, font: e.target.value })}
                  className="w-full border border-hairline rounded-lg px-3 py-2 text-xs bg-canvas focus:outline-none focus:border-ink"
                >
                  <option value="Dancing Script">Dancing Script (Elegant)</option>
                  <option value="Great Vibes">Great Vibes (Formal)</option>
                  <option value="Pinyon Script">Pinyon Script (Classic)</option>
                </select>
              </div>

              <div>
                <label className="block text-muted text-[10px] uppercase font-semibold mb-1" htmlFor="sig-slot-select">Placement Target</label>
                <select
                  id="sig-slot-select"
                  value={sigForm.slot}
                  onChange={(e) => handleSlotChange(e.target.value)}
                  className="w-full border border-hairline rounded-lg px-3 py-2 text-xs bg-canvas focus:outline-none focus:border-ink"
                >
                  <option value="cursor">Active Cursor / Selection Point</option>
                  <option value="hr">HR Signature (Shivali V Rai)</option>
                  <option value="employee">Employee Signature</option>
                </select>
              </div>

              <div className="border border-hairline-soft p-4 rounded-lg bg-surface-soft/50 text-center select-none">
                <span className="text-[10px] text-muted block mb-1">Signature Preview:</span>
                <span style={{ fontFamily: `'${sigForm.font}', cursive`, fontSize: '30px', color: '#1e3a8a' }} className="block py-2 animate-pulse">
                  {sigForm.name}
                </span>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={handleCancelSignature}
                className="px-4 py-2 hover:bg-surface-soft border border-hairline text-muted rounded-lg text-xs font-semibold transition-all"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleInsertSignature}
                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm"
              >
                Insert Signature
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default FnfSettlementModals;
