import React, { useState, useEffect, useRef } from 'react';
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
  const [sigForm, setSigForm] = useState({ name: 'Shivali V Rai', font: 'Dancing Script', slot: 'cursor' });
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
      
      <div style="margin-top: 40px; display: flex; justify-content: space-between;">
        <div class="hr-sig-slot">
          <p>_______________________</p>
          <p><strong>HR Manager</strong><br>Search Homes India Pvt Ltd</p>
        </div>
        <div class="candidate-sig-slot" style="text-align: right;">
          <p>_______________________</p>
          <p><strong>Candidate Signature</strong><br>Date:</p>
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
        hrContainer = editorRef.current.querySelector('.hr-sig-slot');
        if (!hrContainer) {
          // Fallback: search DOM for elements containing HR Manager and Search Homes
          const elements = Array.from(editorRef.current.querySelectorAll('div, td, p'));
          hrContainer = elements.find(el => 
            el.textContent.includes('HR Manager') && 
            el.textContent.includes('Search Homes') && 
            el.children.length > 0 &&
            el.tagName !== 'BODY'
          );
        }
      }

      if (hrContainer) {
        hrContainer.innerHTML = `<p>${sigHtml}</p><p><strong>HR Manager</strong><br>Search Homes India Pvt Ltd</p>`;
        if (editorRef.current) {
          setEditingHtml(editorRef.current.innerHTML);
        }
      } else {
        insertSignatureAtCursor(sigHtml);
      }
    } else if (sigForm.slot === 'candidate') {
      let candContainer = null;
      if (editorRef.current) {
        candContainer = editorRef.current.querySelector('.candidate-sig-slot');
        if (!candContainer) {
          // Fallback: search DOM for elements containing Candidate Signature
          const elements = Array.from(editorRef.current.querySelectorAll('div, td, p'));
          candContainer = elements.find(el => 
            el.textContent.includes('Candidate Signature') && 
            el.children.length > 0 &&
            el.tagName !== 'BODY'
          );
        }
      }

      if (candContainer) {
        candContainer.innerHTML = `<p>${sigHtml}</p><p><strong>Candidate Signature</strong><br>Date:</p>`;
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
    <div className="space-y-6">
      {/* Page Header */}
      <div className="bg-gradient-to-r from-slate-900 via-brand-950 to-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-[10px] uppercase tracking-widest text-brand-400 font-extrabold">Operations Portal</span>
          <h1 className="text-2xl font-black text-white mt-1">Offer Letter Console</h1>
        </div>
        <button
          onClick={() => setShowOfferModal(true)}
          className="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-brand-500/10 flex items-center gap-1.5"
        >
          <Plus className="w-4 h-4" /> Create Offer Letter
        </button>
      </div>

      {/* Control Toolbar */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-4 rounded-2xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="relative w-full sm:w-64">
            <input
              type="text"
              placeholder="Search candidate name or position..."
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              onKeyDown={(e) => e.key === 'Enter' && fetchOffers()}
              className="px-4 py-2 pl-9 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-full bg-transparent dark:text-white"
            />
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          </div>

          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`p-2 border rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-all ${showFilters ? 'bg-indigo-50 dark:bg-indigo-950 border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'}`}
          >
            <Filter className="w-4 h-4" />
          </button>
          
          <button
            onClick={fetchOffers}
            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all"
          >
            Search
          </button>
        </div>
      </div>

      {/* Advanced Filters */}
      {showFilters && (
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in text-xs font-semibold">
          <div>
            <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Status</label>
            <select
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 bg-transparent text-xs dark:text-white dark:bg-slate-900"
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
          <div key={offer._id} className="p-5 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl space-y-4 hover:shadow-md transition-shadow relative">
            <div className="flex items-start justify-between">
              <div>
                <h4 className="font-extrabold text-slate-800 dark:text-white text-xs capitalize">{offer.candidateName}</h4>
                <p className="text-[10px] text-slate-400 dark:text-slate-500 mt-1 capitalize">{offer.position} | {offer.department || 'Operations'}</p>
              </div>
              <span className={`px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase ${offer.status === 'Sent' ? 'bg-indigo-50 dark:bg-indigo-950/30 text-indigo-700 dark:text-indigo-400' : offer.status === 'Accepted' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}`}>
                {offer.status}
              </span>
            </div>

            <div className="text-[11px] text-slate-500 dark:text-slate-400 space-y-1">
              <p><strong>CTC Salary:</strong> ₹{(offer.monthlySalary * 12).toLocaleString()} LPA (₹{offer.monthlySalary.toLocaleString()}/mo)</p>
              <p><strong>Joining Date:</strong> {new Date(offer.joiningDate).toLocaleDateString('en-GB')}</p>
              {offer.emailedAt && (
                <p className="text-slate-400 dark:text-slate-500 text-[10px]"><strong>Sent:</strong> {new Date(offer.emailedAt).toLocaleDateString('en-GB')} by {offer.emailedBy}</p>
              )}
            </div>

            <div className="flex gap-2 pt-2 border-t border-slate-50 dark:border-slate-800 justify-between items-center">
              <button
                onClick={() => setPreviewingOffer(offer)}
                className="text-[10px] text-brand-600 dark:text-brand-400 font-bold hover:underline flex items-center gap-1"
              >
                <Eye className="w-3.5 h-3.5" /> Preview Document
              </button>

              <div className="flex gap-2">
                {offer.status === 'Draft' && (
                  <button
                    onClick={() => handleSendOffer(offer._id)}
                    className="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 rounded-lg text-[10px] font-extrabold transition-all flex items-center gap-1"
                  >
                    <Mail className="w-3 h-3" /> Email Offer
                  </button>
                )}
                <button
                  onClick={() => handleDeleteOffer(offer._id)}
                  className="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-rose-600 dark:text-rose-400 rounded-lg transition-colors"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        ))}
        {offers.length === 0 && (
          <p className="text-center col-span-2 py-20 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl text-slate-400 dark:text-slate-500 font-semibold text-xs">
            No candidate offer letters created yet.
          </p>
        )}
      </div>

      {/* Offer Letter Document Preview & Editor Modal */}
      {previewingOffer && (
        <div className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-3xl w-full p-6 shadow-2xl overflow-hidden max-h-[95vh] flex flex-col">
            
            {/* Modal Title Block */}
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-4">
              <h3 className="font-extrabold text-slate-800 dark:text-white text-xs uppercase flex items-center gap-1.5">
                <PenTool className="w-4 h-4 text-brand-500" />
                Offer Letter Document Editor ({previewingOffer.candidateName})
              </h3>
              <button
                onClick={() => setPreviewingOffer(null)}
                className="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Document Formatting Toolbar */}
            <div className="flex flex-wrap gap-1 bg-slate-50 dark:bg-slate-800 p-2 rounded-xl mb-3 border border-slate-100 dark:border-slate-700 text-slate-700 dark:text-slate-300">
              <button type="button" onClick={() => handleToolbarCmd('bold')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Bold"><Bold className="w-4 h-4" /></button>
              <button type="button" onClick={() => handleToolbarCmd('underline')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Underline"><Underline className="w-4 h-4" /></button>
              <button type="button" onClick={() => handleToolbarCmd('removeFormat')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Clear Format"><Eraser className="w-4 h-4" /></button>
              <div className="w-[1px] bg-slate-200 dark:bg-slate-700 my-1 mx-1" />
              
              <button type="button" onClick={() => handleToolbarCmd('insertUnorderedList')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Bullet List"><List className="w-4 h-4" /></button>
              <button type="button" onClick={() => handleToolbarCmd('insertOrderedList')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Numbered List"><ListOrdered className="w-4 h-4" /></button>
              <div className="w-[1px] bg-slate-200 dark:bg-slate-700 my-1 mx-1" />
              
              <button type="button" onClick={() => handleToolbarCmd('justifyLeft')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Align Left"><AlignLeft className="w-4 h-4" /></button>
              <button type="button" onClick={() => handleToolbarCmd('justifyCenter')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Align Center"><AlignCenter className="w-4 h-4" /></button>
              <button type="button" onClick={() => handleToolbarCmd('justifyRight')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Align Right"><AlignRight className="w-4 h-4" /></button>
              <button type="button" onClick={() => handleToolbarCmd('justifyFull')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Justify"><AlignJustify className="w-4 h-4" /></button>
              <div className="w-[1px] bg-slate-200 dark:bg-slate-700 my-1 mx-1" />
              
              <button type="button" onClick={handleTablePrompt} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Insert Table"><Table className="w-4 h-4" /></button>
              <button type="button" onClick={handleLinkPrompt} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Insert Link"><Link2 className="w-4 h-4" /></button>
              <button type="button" onClick={() => handleToolbarCmd('insertHorizontalRule')} className="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-750 rounded" title="Horizontal Line"><Minus className="w-4 h-4" /></button>
              <div className="w-[1px] bg-slate-200 dark:bg-slate-700 my-1 mx-1" />
              
              <button type="button" onClick={() => setCodeMode(!codeMode)} className={`p-1.5 rounded ${codeMode ? 'bg-indigo-500 text-white' : 'hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300'}`} title="Toggle HTML View"><Code className="w-4 h-4" /></button>
            </div>
            
            {/* Editable Content Frame - Force light background style for standard letter reading */}
            <div className="flex-1 overflow-y-auto min-h-0 bg-white border border-slate-300 rounded-2xl shadow-inner relative">
              {codeMode ? (
                <textarea
                  value={editingHtml}
                  onChange={(e) => setEditingHtml(e.target.value)}
                  className="w-full h-full font-mono text-[11px] p-8 focus:outline-none bg-slate-950 text-slate-200 resize-none min-h-[45vh]"
                />
              ) : (
                <div
                  ref={editorRef}
                  contentEditable={true}
                  onBlur={() => {
                    if (editorRef.current) setEditingHtml(editorRef.current.innerHTML);
                  }}
                  dangerouslySetInnerHTML={{ __html: editingHtml }}
                  className="p-8 text-slate-900 focus:outline-none min-h-[45vh] leading-relaxed"
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
            
            {/* Modal Actions Footer */}
            <div className="flex flex-wrap gap-2 pt-4 border-t border-slate-100 dark:border-slate-800 mt-4 justify-between items-center shrink-0">
              <div className="flex gap-2">
                <button
                  onClick={handleOpenSignatureModal}
                  className="px-3.5 py-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-brand-600 dark:text-brand-400 rounded-xl text-xs font-bold transition-colors flex items-center gap-1"
                >
                  <PenTool className="w-3.5 h-3.5" /> Add Signature
                </button>
                <button
                  onClick={() => window.print()}
                  className="px-3.5 py-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-xs font-bold transition-colors flex items-center gap-1"
                >
                  <Eye className="w-3.5 h-3.5" /> Preview
                </button>
              </div>

              <div className="flex gap-2">
                <button
                  onClick={() => setPreviewingOffer(null)}
                  className="px-4 py-2 bg-slate-100 dark:bg-slate-850 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition-all"
                >
                  Close
                </button>
                <button
                  onClick={() => {
                    if (window.confirm('Reset all modifications and restore the default candidate offer letter template?')) {
                      setEditingHtml(getDefaultHtmlTemplate(previewingOffer));
                    }
                  }}
                  className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1"
                >
                  <RotateCcw className="w-3.5 h-3.5" /> Reset to Template
                </button>
                <button
                  onClick={saveEditedLetter}
                  className="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-black shadow-md shadow-brand-500/10 transition-all flex items-center gap-1"
                >
                  <Check className="w-3.5 h-3.5" /> Save Letter
                </button>
              </div>
            </div>

          </div>
        </div>
      )}

      {/* Inline Signature Creator Modal Overlay */}
      {showSignatureModal && (
        <div className="fixed inset-0 bg-slate-950/50 backdrop-blur-sm flex items-center justify-center p-6 z-[60] animate-fade-in">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h4 className="font-extrabold text-slate-800 dark:text-white text-xs uppercase flex items-center gap-1.5">
                <PenTool className="w-4 h-4 text-indigo-500" />
                Insert Signature Block
              </h4>
              <button onClick={() => setShowSignatureModal(false)} className="p-1 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg text-slate-400">
                <X className="w-4.5 h-4.5" />
              </button>
            </div>
            
            <div className="space-y-3 text-xs font-bold text-slate-600 dark:text-slate-400">
              <div>
                <label className="block text-[10px] uppercase mb-1">Name to sign</label>
                <input
                  type="text"
                  value={sigForm.name}
                  onChange={(e) => setSigForm({ ...sigForm, name: e.target.value })}
                  className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 bg-transparent dark:text-white"
                />
              </div>

              <div>
                <label className="block text-[10px] uppercase mb-1">Handwriting Cursive Style</label>
                <select
                  value={sigForm.font}
                  onChange={(e) => setSigForm({ ...sigForm, font: e.target.value })}
                  className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 focus:outline-none bg-transparent dark:text-white"
                >
                  <option value="Dancing Script">Dancing Script (Elegant)</option>
                  <option value="Great Vibes">Great Vibes (Formal)</option>
                  <option value="Pinyon Script">Pinyon Script (Classic)</option>
                </select>
              </div>

              <div>
                <label className="block text-[10px] uppercase mb-1">Placement Target</label>
                <select
                  value={sigForm.slot}
                  onChange={(e) => setSigForm({ ...sigForm, slot: e.target.value })}
                  className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 focus:outline-none bg-transparent dark:text-white"
                >
                  <option value="cursor">Active Cursor / Selection Point</option>
                  <option value="hr">HR Signature (Shivali V Rai)</option>
                  <option value="candidate">Candidate Signature</option>
                </select>
              </div>

              <div className="border border-slate-100 dark:border-slate-800 p-4 rounded-2xl bg-slate-50/50 dark:bg-slate-950/50 text-center select-none">
                <span className="text-[10px] text-slate-400 block mb-1">Signature Preview:</span>
                <span style={{ fontFamily: `'${sigForm.font}', cursive`, fontSize: '30px', color: '#1e3a8a' }} className="block py-2">
                  {sigForm.name}
                </span>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setShowSignatureModal(false)}
                className="px-4 py-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-500 rounded-xl text-xs font-bold transition-all"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleInsertSignature}
                className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black shadow-sm transition-all"
              >
                Insert Signature
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Create Offer Modal */}
      {showOfferModal && (
        <div className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-6">
              <h3 className="font-extrabold text-slate-800 dark:text-white text-sm uppercase">Generate Offer Letter</h3>
              <button onClick={() => setShowOfferModal(false)} className="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleCreateOffer} className="space-y-4 text-xs font-semibold text-slate-600 dark:text-slate-400">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Candidate Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="Candidate Name"
                    value={offerForm.candidateName}
                    onChange={(e) => setOfferForm({ ...offerForm, candidateName: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Email Address *</label>
                  <input
                    type="email"
                    required
                    placeholder="candidate@email.com"
                    value={offerForm.email}
                    onChange={(e) => setOfferForm({ ...offerForm, email: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Phone Number *</label>
                  <input
                    type="text"
                    required
                    placeholder="9876543210"
                    value={offerForm.phone}
                    onChange={(e) => setOfferForm({ ...offerForm, phone: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Job Title / Position *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Sales Executive"
                    value={offerForm.position}
                    onChange={(e) => setOfferForm({ ...offerForm, position: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Department</label>
                  <input
                    type="text"
                    placeholder="e.g. Sales, Marketing"
                    value={offerForm.department}
                    onChange={(e) => setOfferForm({ ...offerForm, department: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Monthly Salary CTC (INR) *</label>
                  <input
                    type="number"
                    required
                    placeholder="e.g. 35000"
                    value={offerForm.monthlySalary}
                    onChange={(e) => setOfferForm({ ...offerForm, monthlySalary: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Joining Date *</label>
                  <input
                    type="date"
                    required
                    value={offerForm.joiningDate}
                    onChange={(e) => setOfferForm({ ...offerForm, joiningDate: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none text-slate-500 bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Reporting Manager</label>
                  <input
                    type="text"
                    placeholder="Manager Name"
                    value={offerForm.reportingManager}
                    onChange={(e) => setOfferForm({ ...offerForm, reportingManager: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowOfferModal(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-xs font-bold transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-black shadow-md shadow-brand-500/10 transition-all"
                >
                  Generate & Save
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default OfferLetterPage;
