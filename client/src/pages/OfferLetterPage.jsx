import React, { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { 
  Search, Filter, Plus, Mail, Eye, Trash2, X,
  Bold, Underline, List, ListOrdered, AlignLeft, AlignCenter, AlignRight, AlignJustify,
  Table, Link2, Code, Minus, Eraser, Check, RotateCcw, PenTool
} from 'lucide-react';

const OfferLetterPage = () => {
  const [offers, setOffers] = useState([]);
  const [filters, setFilters] = useState({ search: '', status: '' });
  const [showFilters, setShowFilters] = useState(false);
  const [showOfferModal, setShowOfferModal] = useState(false);
  const [offerForm, setOfferForm] = useState({
    candidateName: '', email: '', phone: '', position: '', department: '',
    monthlySalary: '', joiningDate: '', reportingManager: '', customHtml: ''
  });
  const [previewingOffer, setPreviewingOffer] = useState(null);
  const [loading, setLoading] = useState(false);

  const [editingHtml, setEditingHtml] = useState('');
  const [codeMode, setCodeMode] = useState(false);
  const [showSignatureModal, setShowSignatureModal] = useState(false);
  const [savedRange, setSavedRange] = useState(null);
  const [sigForm, setSigForm] = useState({ name: 'Shivali V Rai', font: 'Dancing Script', slot: 'hr' });
  const editorRef = useRef(null);

  const handleOpenSignatureModal = () => {
    // Save current selection range before modal overlays focus
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      setSavedRange(selection.getRangeAt(0));
    } else {
      setSavedRange(null);
    }
    setShowSignatureModal(true);
  };

  useEffect(() => {
    if (previewingOffer) {
      setEditingHtml(previewingOffer.customHtml || '');
    }
  }, [previewingOffer]);

  const getDefaultHtmlTemplate = (data) => {
    const today = new Date().toLocaleDateString('en-GB');
    const joining = new Date(data.joiningDate).toLocaleDateString('en-GB');
    const monthly = parseFloat(data.monthlySalary) || 0;
    const annual = monthly * 12;

    const basic = Math.round(monthly * 0.5);
    const hra = Math.round(monthly * 0.2);
    const conveyance = Math.round(monthly * 0.07);
    const pfEmployer = Math.min(1800, Math.round(basic * 0.12));
    const monthlyGross = monthly - pfEmployer;
    const special = Math.max(0, monthlyGross - (basic + hra + conveyance));
    const pfEmployee = pfEmployer;
    const pt = monthly > 15000 ? 200 : 0;
    const medical = monthly > 10000 ? 817 : 0;
    const deductions = pfEmployee + pt + medical;
    const netPay = monthlyGross - deductions;

    return `
    <div style="font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; color: #333; position: relative;">
      <!-- Document Header/Letterhead -->
      <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #115b82; padding-bottom: 15px; margin-bottom: 20px;">
        <div>
          <h1 style="margin: 0; color: #115b82; font-size: 24px; font-weight: 800; letter-spacing: 0.5px;">SEARCHHOMES</h1>
          <span style="color: #d97706; font-size: 10px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;">India</span>
        </div>
        <div style="text-align: right; font-size: 10px; color: #555; line-height: 1.4;">
          <p style="margin: 0;"><strong>Phone:</strong> +91 63600 16650</p>
          <p style="margin: 0;"><strong>Email:</strong> contact@searchhomesindia.com</p>
          <p style="margin: 0;"><strong>Web:</strong> www.searchhomesindia.com</p>
        </div>
      </div>

      <h2 style="text-align: center; color: #115b82; text-decoration: underline; margin-top: 30px;">OFFER LETTER</h2>
      <p><strong>Date:</strong> ${today}</p>
      <p><strong>To,</strong><br><strong>${data.candidateName}</strong><br>Email: ${data.email}<br>Phone: ${data.phone}</p>
      
      <p>We are pleased to offer you employment at <strong>Search Homes India Pvt Ltd</strong>. We believe your skills and background will be valuable assets to our team and contribute significantly to our success.</p>
      
      <p>As per our discussion, your position will be <strong>${data.position}</strong> with a fixed Annual Cost to Company (CTC) of <strong>INR ${annual.toLocaleString()}/- LPA</strong>.</p>
      
      <h3>Salary Breakdown (Annexure - A)</h3>
      <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;">
        <thead>
          <tr style="background-color: #115b82; color: white; text-align: left;">
            <th style="padding: 8px; border: 1px solid #ddd;">Component</th>
            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Monthly (INR)</th>
            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Annual (INR)</th>
          </tr>
        </thead>
        <tbody>
          <tr><td style="padding: 8px; border: 1px solid #ddd;">Basic Salary (50%)</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${basic.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(basic * 12).toLocaleString()}</td></tr>
          <tr><td style="padding: 8px; border: 1px solid #ddd;">HRA (20%)</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${hra.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(hra * 12).toLocaleString()}</td></tr>
          <tr><td style="padding: 8px; border: 1px solid #ddd;">Conveyance Allowance</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${conveyance.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(conveyance * 12).toLocaleString()}</td></tr>
          <tr><td style="padding: 8px; border: 1px solid #ddd;">Special Allowance</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${special.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(special * 12).toLocaleString()}</td></tr>
          <tr style="font-weight: bold; background-color: #f9f9f9;"><td style="padding: 8px; border: 1px solid #ddd;">Monthly Gross Payout</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${monthlyGross.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(monthlyGross * 12).toLocaleString()}</td></tr>
          
          <tr style="background-color: #efefef;"><td colspan="3" style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Statutory Deductions</td></tr>
          <tr><td style="padding: 8px; border: 1px solid #ddd;">PF Employee & Employer Contribution</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${pfEmployee.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(pfEmployee * 12).toLocaleString()}</td></tr>
          <tr><td style="padding: 8px; border: 1px solid #ddd;">Professional Tax (PT)</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${pt.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(pt * 12).toLocaleString()}</td></tr>
          <tr><td style="padding: 8px; border: 1px solid #ddd;">Medical Insurance Benefit</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${medical.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(medical * 12).toLocaleString()}</td></tr>
          
          <tr style="font-weight: bold; background-color: #115b82; color: white;"><td style="padding: 8px; border: 1px solid #ddd;">Net Take Home Pay</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${netPay.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(netPay * 12).toLocaleString()}</td></tr>
        </tbody>
      </table>

      <p style="margin-top: 25px;"><strong>Joining Date:</strong> ${joining}<br><strong>Reporting Manager:</strong> ${data.reportingManager || 'HR Manager'}</p>
      
      <p>Please return a signed copy of this offer letter within 3 days as acceptance of the terms outlined above.</p>
      
      <div style="margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div class="hr-sig-slot" style="min-width: 200px; text-align: center; display: inline-block;">
          <div class="hr-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: center;"></div>
          <div style="border-top: 1px solid #333; margin-top: 5px; padding-top: 5px;">
            <strong>HR Manager</strong><br>Search Homes India Pvt Ltd
          </div>
        </div>
        <div class="candidate-sig-slot" style="min-width: 200px; text-align: center; display: inline-block;">
          <div class="candidate-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: center;"></div>
          <div style="border-top: 1px solid #333; margin-top: 5px; padding-top: 5px;">
            <strong>Candidate Signature</strong><br>Date:
          </div>
        </div>
      </div>
    </div>
    `;
  };

  const handleToolbarCmd = (cmd, val = null) => {
    if (codeMode) return;
    document.execCommand(cmd, false, val);
    if (editorRef.current) {
      setEditingHtml(editorRef.current.innerHTML);
    }
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

  const insertSignatureAtCursor = (signatureHtml) => {
    let range = savedRange;
    if (!range) {
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        range = selection.getRangeAt(0);
      }
    }
    
    if (range) {
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      
      range.deleteContents();
      
      const div = document.createElement('div');
      div.innerHTML = signatureHtml;
      const frag = document.createDocumentFragment();
      let child;
      while ((child = div.firstChild)) {
        frag.appendChild(child);
      }
      range.insertNode(frag);
    } else {
      if (editorRef.current) {
        editorRef.current.innerHTML += signatureHtml;
      }
    }

    if (editorRef.current) {
      setEditingHtml(editorRef.current.innerHTML);
    }
  };

  const handleInsertSignature = () => {
    const sigHtml = `<span style="font-family: '${sigForm.font}', cursive; font-size: 28px; color: #1e3a8a; display: inline-block; padding: 5px 0; font-weight: normal;">${sigForm.name}</span>`;
    
    if (sigForm.slot === 'hr') {
      let hrContainer = null;
      if (editorRef.current) {
        hrContainer = editorRef.current.querySelector('.hr-sig-img');
        if (!hrContainer) {
          hrContainer = editorRef.current.querySelector('.hr-sig-slot');
        }
      }

      if (hrContainer) {
        if (hrContainer.classList.contains('hr-sig-img')) {
          hrContainer.innerHTML = sigHtml;
        } else {
          // Backward compatibility for old drafts
          hrContainer.innerHTML = `<p>${sigHtml}</p><p><strong>HR Manager</strong><br>Search Homes India Pvt Ltd</p>`;
        }
        if (editorRef.current) {
          setEditingHtml(editorRef.current.innerHTML);
        }
      } else {
        insertSignatureAtCursor(sigHtml);
      }
    } else if (sigForm.slot === 'candidate') {
      let candContainer = null;
      if (editorRef.current) {
        candContainer = editorRef.current.querySelector('.candidate-sig-img');
        if (!candContainer) {
          candContainer = editorRef.current.querySelector('.candidate-sig-slot');
        }
      }

      if (candContainer) {
        if (candContainer.classList.contains('candidate-sig-img')) {
          candContainer.innerHTML = sigHtml;
        } else {
          // Backward compatibility for old drafts
          candContainer.innerHTML = `<p>${sigHtml}</p><p><strong>Candidate Signature</strong><br>Date:</p>`;
        }
        if (editorRef.current) {
          setEditingHtml(editorRef.current.innerHTML);
        }
      } else {
        insertSignatureAtCursor(sigHtml);
      }
    } else {
      insertSignatureAtCursor(sigHtml);
    }
    setShowSignatureModal(false);
  };

  const saveEditedLetter = async () => {
    const htmlToSave = editorRef.current ? editorRef.current.innerHTML : editingHtml;
    try {
      await axios.put(`/api/offers/${previewingOffer._id}`, {
        customHtml: htmlToSave
      });
      alert('Offer letter saved successfully!');
      fetchOffers();
    } catch (err) {
      alert(err.response?.data?.message || 'Save failed');
    }
  };

  useEffect(() => {
    fetchOffers();
  }, [filters.status]);

  const fetchOffers = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/offers', { params: filters });
      setOffers(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateOffer = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/offers', offerForm);
      setShowOfferModal(false);
      setOfferForm({
        candidateName: '', email: '', phone: '', position: '', department: '',
        monthlySalary: '', joiningDate: '', reportingManager: '', customHtml: ''
      });
      fetchOffers();
    } catch (err) {
      alert(err.response?.data?.message || 'Save failed');
    }
  };

  const handleSendOffer = async (id) => {
    try {
      await axios.post(`/api/offers/${id}/send`);
      alert('Offer letter emailed successfully (Simulated)!');
      fetchOffers();
    } catch (err) {
      console.error(err);
    }
  };

  const handleDeleteOffer = async (id) => {
    if (!window.confirm('Delete this draft?')) return;
    try {
      await axios.delete(`/api/offers/${id}`);
      fetchOffers();
    } catch (err) {
      console.error(err);
    }
  };

  return (
    <div className="page-shell">


      {/* Control Toolbar */}
      <div className="toolbar">
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="search-wrap">
            <input
              id="offer-search-input"
              type="text"
              placeholder="Search candidate name or position..."
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              onKeyDown={(e) => e.key === 'Enter' && fetchOffers()}
              className="search-input text-xs"
            />
            <Search className="w-4 h-4 text-muted-soft absolute left-3 top-1/2 -translate-y-1/2" />
          </div>

          <button
            id="offer-filter-toggle-btn"
            onClick={() => setShowFilters(!showFilters)}
            className={`filter-btn ${showFilters ? 'filter-btn-active' : ''}`}
          >
            <Filter className="w-4 h-4" />
          </button>

          <button
            id="offer-search-submit-btn"
            onClick={fetchOffers}
            className="btn-primary btn-sm"
          >
            Search
          </button>
          <button
            id="offer-create-btn"
            onClick={() => setShowOfferModal(true)}
            className="btn-primary"
          >
            <Plus className="w-4 h-4" /> Create Offer Letter
          </button>
        </div>
      </div>

      {/* Advanced Filters */}
      {showFilters && (
        <div className="filter-panel grid-cols-1 md:grid-cols-2">
          <div>
            <label className="label-xs" htmlFor="offer-filter-status">Status</label>
            <select
              id="offer-filter-status"
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className="select-field text-xs"
            >
              <option value="">All Statuses</option>
              <option value="Draft">Draft</option>
              <option value="Sent">Sent</option>
              <option value="Accepted">Accepted</option>
            </select>
          </div>
        </div>
      )}

      {/* Candidate Offer Drafts Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {offers.map(offer => (
          <div key={offer._id} className="p-5 bg-canvas border border-hairline-soft rounded-lg space-y-4 transition-shadow relative">
            <div className="flex items-start justify-between">
              <div>
                <h4 className="font-semibold text-ink text-xs capitalize">{offer.candidateName}</h4>
                <p className="text-[10px] text-muted mt-1 capitalize">{offer.position} | {offer.department || 'Operations'}</p>
              </div>
              <span className={`${offer.status === 'Accepted' ? 'badge-success' : 'badge-neutral'} uppercase text-[9px]`}>
                {offer.status}
              </span>
            </div>

            <div className="text-[11px] text-muted space-y-1">
              <p><strong>CTC Salary:</strong> ₹{(offer.monthlySalary * 12).toLocaleString()} LPA (₹{offer.monthlySalary.toLocaleString()}/mo)</p>
              <p><strong>Joining Date:</strong> {new Date(offer.joiningDate).toLocaleDateString('en-GB')}</p>
              {offer.emailedAt && (
                <p className="text-muted text-[10px]"><strong>Sent:</strong> {new Date(offer.emailedAt).toLocaleDateString('en-GB')} by {offer.emailedBy}</p>
              )}
            </div>

            <div className="flex gap-2 pt-2 border-t border-hairline-soft justify-between items-center">
              <button
                id={`offer-preview-btn-${offer._id}`}
                onClick={() => setPreviewingOffer(offer)}
                className="text-[10px] text-ink font-semibold hover:underline flex items-center gap-1"
              >
                <Eye className="w-3.5 h-3.5" /> Preview Document
              </button>

              <div className="flex gap-2">
                {offer.status === 'Draft' && (
                  <button
                    id={`offer-send-btn-${offer._id}`}
                    onClick={() => handleSendOffer(offer._id)}
                    className="btn-secondary btn-sm"
                  >
                    <Mail className="w-3 h-3" /> Email Offer
                  </button>
                )}
                <button
                  id={`offer-delete-btn-${offer._id}`}
                  onClick={() => handleDeleteOffer(offer._id)}
                  className="btn-icon text-error hover:bg-error/10"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        ))}
        {offers.length === 0 && (
          <p className="text-center col-span-2 py-20 bg-canvas border border-hairline-soft rounded-lg text-muted font-semibold text-xs">
            No candidate offer letters created yet.
          </p>
        )}
      </div>

      {/* Offer Letter Document Preview & Editor Modal */}
      {previewingOffer && createPortal(
        <div className="modal-overlay" onClick={() => setPreviewingOffer(null)}>
          <div className="modal-popup max-w-4xl" onClick={(e) => e.stopPropagation()}>
            
            {/* Modal Title Block */}
            <div className="modal-popup-header bg-primary text-white flex justify-between items-center px-6 py-4 shrink-0 rounded-t-2xl">
              <h3 className="font-semibold text-white text-sm flex items-center gap-1.5">
                <PenTool className="w-4.5 h-4.5 text-white" />
                Offer Letter Document Editor ({previewingOffer.candidateName})
              </h3>
              <button
                onClick={() => setPreviewingOffer(null)}
                className="p-1.5 hover:bg-white/10 rounded-lg text-white/80 hover:text-white transition-colors shrink-0"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Scrollable Body */}
            <div className="modal-panel-body p-6 flex flex-col min-h-0 space-y-4">
              {/* Document Formatting Toolbar */}
              <div className="flex flex-wrap gap-1 bg-surface-soft p-2 rounded-lg border border-hairline-soft text-body shrink-0">
                <button type="button" onClick={() => handleToolbarCmd('bold')} className="p-1.5 hover:bg-surface-strong rounded" title="Bold"><Bold className="w-4 h-4" /></button>
                <button type="button" onClick={() => handleToolbarCmd('underline')} className="p-1.5 hover:bg-surface-strong rounded" title="Underline"><Underline className="w-4 h-4" /></button>
                <button type="button" onClick={() => handleToolbarCmd('removeFormat')} className="p-1.5 hover:bg-surface-strong rounded" title="Clear Format"><Eraser className="w-4 h-4" /></button>
                <div className="w-[1px] bg-surface-strong my-1 mx-1" />
                
                <button type="button" onClick={() => handleToolbarCmd('insertUnorderedList')} className="p-1.5 hover:bg-surface-strong rounded" title="Bullet List"><List className="w-4 h-4" /></button>
                <button type="button" onClick={() => handleToolbarCmd('insertOrderedList')} className="p-1.5 hover:bg-surface-strong rounded" title="Numbered List"><ListOrdered className="w-4 h-4" /></button>
                <div className="w-[1px] bg-surface-strong my-1 mx-1" />
                
                <button type="button" onClick={() => handleToolbarCmd('justifyLeft')} className="p-1.5 hover:bg-surface-strong rounded" title="Align Left"><AlignLeft className="w-4 h-4" /></button>
                <button type="button" onClick={() => handleToolbarCmd('justifyCenter')} className="p-1.5 hover:bg-surface-strong rounded" title="Align Center"><AlignCenter className="w-4 h-4" /></button>
                <button type="button" onClick={() => handleToolbarCmd('justifyRight')} className="p-1.5 hover:bg-surface-strong rounded" title="Align Right"><AlignRight className="w-4 h-4" /></button>
                <button type="button" onClick={() => handleToolbarCmd('justifyFull')} className="p-1.5 hover:bg-surface-strong rounded" title="Justify"><AlignJustify className="w-4 h-4" /></button>
                <div className="w-[1px] bg-surface-strong my-1 mx-1" />
                
                <button type="button" onClick={handleTablePrompt} className="p-1.5 hover:bg-surface-strong rounded" title="Insert Table"><Table className="w-4 h-4" /></button>
                <button type="button" onClick={handleLinkPrompt} className="p-1.5 hover:bg-surface-strong rounded" title="Insert Link"><Link2 className="w-4 h-4" /></button>
                <button type="button" onClick={() => handleToolbarCmd('insertHorizontalRule')} className="p-1.5 hover:bg-surface-strong rounded" title="Horizontal Line"><Minus className="w-4 h-4" /></button>
                <div className="w-[1px] bg-surface-strong my-1 mx-1" />
                
                <button type="button" onClick={() => setCodeMode(!codeMode)} className={`p-1.5 rounded ${codeMode ? 'bg-surface-soft0 text-white' : 'hover:bg-surface-strong text-body '}`} title="Toggle HTML View"><Code className="w-4 h-4" /></button>
              </div>
              
              {/* Editable Content Frame - Force light background style for standard letter reading */}
              <div className="flex-1 overflow-y-auto min-h-0 bg-canvas border border-hairline rounded-lg shadow-inner relative">
                {codeMode ? (
                  <textarea
                    value={editingHtml}
                    onChange={(e) => setEditingHtml(e.target.value)}
                    className="w-full h-full font-mono text-[11px] p-8 focus:outline-none bg-surface-dark text-muted-soft resize-none min-h-[45vh]"
                  />
                ) : (
                  <div
                    ref={editorRef}
                    contentEditable={true}
                    onBlur={() => {
                      if (editorRef.current) setEditingHtml(editorRef.current.innerHTML);
                    }}
                    dangerouslySetInnerHTML={{ __html: editingHtml }}
                    className="p-8 text-ink focus:outline-none min-h-[45vh] leading-relaxed"
                    style={{
                      backgroundImage: 'url("https://www.searchhomesindia.com/assets/images/logo.png")',
                      backgroundRepeat: 'no-repeat',
                      backgroundPosition: 'center',
                      backgroundSize: '25%',
                      backgroundBlendMode: 'overlay',
                      opacity: 0.99
                    }}
                  />
                )}
              </div>
            </div>
            
            {/* Modal Actions Footer */}
            <div className="flex flex-wrap gap-2 px-6 py-4 border-t border-hairline bg-surface-soft justify-between items-center shrink-0 rounded-b-2xl">
              <div className="flex gap-2">
                <button
                  id="offer-add-signature-btn"
                  onMouseDown={(e) => {
                    e.preventDefault();
                    handleOpenSignatureModal();
                  }}
                  className="btn-secondary btn-sm"
                >
                  <PenTool className="w-3.5 h-3.5" /> Add Signature
                </button>
                <button
                  id="offer-print-btn"
                  onClick={() => window.print()}
                  className="btn-secondary btn-sm"
                >
                  <Eye className="w-3.5 h-3.5" /> Preview
                </button>
              </div>

              <div className="flex gap-2">
                <button
                  id="offer-close-editor-btn"
                  onClick={() => setPreviewingOffer(null)}
                  className="btn-secondary"
                >
                  Close
                </button>
                <button
                  id="offer-reset-template-btn"
                  onClick={() => {
                    if (window.confirm('Reset all modifications and restore the default candidate offer letter template?')) {
                      setEditingHtml(getDefaultHtmlTemplate(previewingOffer));
                    }
                  }}
                  className="btn-secondary btn-sm"
                >
                  <RotateCcw className="w-3.5 h-3.5" /> Reset to Template
                </button>
                <button
                  id="offer-save-letter-btn"
                  onClick={saveEditedLetter}
                  className="btn-primary"
                >
                  <Check className="w-3.5 h-3.5" /> Save Letter
                </button>
              </div>
            </div>

          </div>
        </div>,
        document.body
      )}

      {/* Inline Signature Creator Modal Overlay */}
      {showSignatureModal && createPortal(
        <div className="modal-overlay z-[60]">
          <div className="modal-panel-md space-y-4">
            <div className="flex items-center justify-between border-b border-hairline-soft pb-3">
              <h4 className="font-semibold text-ink text-xs uppercase flex items-center gap-1.5">
                <PenTool className="w-4 h-4 text-accent" />
                Insert Signature Block
              </h4>
              <button id="close-sig-modal-btn" onClick={() => setShowSignatureModal(false)} className="btn-icon text-muted">
                <X className="w-4.5 h-4.5" />
              </button>
            </div>
            
            <div className="space-y-3 text-xs font-semibold text-body">
              <div>
                <label className="label-xs" htmlFor="sig-name-input">Name to sign</label>
                <input
                  id="sig-name-input"
                  type="text"
                  value={sigForm.name}
                  onChange={(e) => setSigForm({ ...sigForm, name: e.target.value })}
                  className="input-field"
                />
              </div>

              <div>
                <label className="label-xs" htmlFor="sig-font-select">Handwriting Cursive Style</label>
                <select
                  id="sig-font-select"
                  value={sigForm.font}
                  onChange={(e) => setSigForm({ ...sigForm, font: e.target.value })}
                  className="select-field"
                >
                  <option value="Dancing Script">Dancing Script (Elegant)</option>
                  <option value="Great Vibes">Great Vibes (Formal)</option>
                  <option value="Pinyon Script">Pinyon Script (Classic)</option>
                </select>
              </div>

              <div>
                <label className="label-xs" htmlFor="sig-slot-select">Placement Target</label>
                <select
                  id="sig-slot-select"
                  value={sigForm.slot}
                  onChange={(e) => setSigForm({ ...sigForm, slot: e.target.value })}
                  className="select-field"
                >
                  <option value="cursor">Active Cursor / Selection Point</option>
                  <option value="hr">HR Signature (Shivali V Rai)</option>
                  <option value="candidate">Candidate Signature</option>
                </select>
              </div>

              <div className="border border-hairline-soft p-4 rounded-lg bg-surface-soft/50 text-center select-none">
                <span className="text-[10px] text-muted block mb-1">Signature Preview:</span>
                <span style={{ fontFamily: `'${sigForm.font}', cursive`, fontSize: '30px', color: '#1e3a8a' }} className="block py-2">
                  {sigForm.name}
                </span>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                id="sig-cancel-btn"
                type="button"
                onClick={() => setShowSignatureModal(false)}
                className="btn-secondary"
              >
                Cancel
              </button>
              <button
                id="sig-insert-btn"
                type="button"
                onClick={handleInsertSignature}
                className="btn-primary"
              >
                Insert Signature
              </button>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* Create Offer Modal */}
      {showOfferModal && createPortal(
        <div className="modal-overlay">
          <div className="modal-panel-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-hairline-soft pb-4 mb-6">
              <h3 className="font-semibold text-ink text-sm uppercase">Generate Offer Letter</h3>
              <button id="close-offer-modal-btn" onClick={() => setShowOfferModal(false)} className="btn-icon text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleCreateOffer} className="space-y-4 text-xs font-semibold text-body">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label-xs" htmlFor="offer-candidate-name">Candidate Name *</label>
                  <input
                    id="offer-candidate-name"
                    type="text"
                    required
                    placeholder="Candidate Name"
                    value={offerForm.candidateName}
                    onChange={(e) => setOfferForm({ ...offerForm, candidateName: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="offer-email">Email Address *</label>
                  <input
                    id="offer-email"
                    type="email"
                    required
                    placeholder="candidate@email.com"
                    value={offerForm.email}
                    onChange={(e) => setOfferForm({ ...offerForm, email: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="offer-phone">Phone Number *</label>
                  <input
                    id="offer-phone"
                    type="text"
                    required
                    placeholder="9876543210"
                    value={offerForm.phone}
                    onChange={(e) => setOfferForm({ ...offerForm, phone: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="offer-position">Job Title / Position *</label>
                  <input
                    id="offer-position"
                    type="text"
                    required
                    placeholder="e.g. Sales Executive"
                    value={offerForm.position}
                    onChange={(e) => setOfferForm({ ...offerForm, position: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="offer-department">Department</label>
                  <input
                    id="offer-department"
                    type="text"
                    placeholder="e.g. Sales, Marketing"
                    value={offerForm.department}
                    onChange={(e) => setOfferForm({ ...offerForm, department: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="offer-salary">Monthly Salary CTC (INR) *</label>
                  <input
                    id="offer-salary"
                    type="number"
                    required
                    placeholder="e.g. 35000"
                    value={offerForm.monthlySalary}
                    onChange={(e) => setOfferForm({ ...offerForm, monthlySalary: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="offer-joining-date">Joining Date *</label>
                  <input
                    id="offer-joining-date"
                    type="date"
                    required
                    value={offerForm.joiningDate}
                    onChange={(e) => setOfferForm({ ...offerForm, joiningDate: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="offer-manager">Reporting Manager</label>
                  <input
                    id="offer-manager"
                    type="text"
                    placeholder="Manager Name"
                    value={offerForm.reportingManager}
                    onChange={(e) => setOfferForm({ ...offerForm, reportingManager: e.target.value })}
                    className="input-field"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-4 border-t border-hairline-soft">
                <button
                  id="offer-modal-cancel-btn"
                  type="button"
                  onClick={() => setShowOfferModal(false)}
                  className="btn-secondary"
                >
                  Cancel
                </button>
                <button
                  id="offer-modal-save-btn"
                  type="submit"
                  className="btn-primary"
                >
                  Generate & Save
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default OfferLetterPage;
