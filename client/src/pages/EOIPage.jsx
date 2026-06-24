import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { PlusCircle, ArrowRight, Trash2, X, Search, FileText, CheckCircle } from 'lucide-react';

const EOIPage = () => {
  const [eois, setEois] = useState([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [showConvertModal, setShowConvertModal] = useState(false);
  const [selectedEoi, setSelectedEoi] = useState(null);

  // EOI Form
  const [eoiForm, setEoiForm] = useState({
    booking_date: '',
    booking_month: '',
    builder: '',
    project: '',
    customer_name: '',
    contact_number: '',
    email_id: '',
    project_type: ''
  });

  // Conversion Form
  const [convertForm, setConvertForm] = useState({
    unit_no: '',
    size: '',
    agreement_value: '',
    cashback: '',
    revenue: '',
    ccashback: '',
    crevenue: '',
    recived_amt: '0',
    source_lead: '',
    remarks: '',
    city: ''
  });

  const user = JSON.parse(localStorage.getItem('user') || '{}');

  useEffect(() => {
    fetchEois();
  }, []);

  const fetchEois = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/eois');
      setEois(res.data || []);
    } catch (err) {
      console.error('Error fetching EOIs', err);
    } finally {
      setLoading(false);
    }
  };

  const handleEoiSubmit = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/eois', eoiForm);
      setShowAddModal(false);
      setEoiForm({
        booking_date: '',
        booking_month: '',
        builder: '',
        project: '',
        customer_name: '',
        contact_number: '',
        email_id: '',
        project_type: ''
      });
      fetchEois();
    } catch (err) {
      alert('Failed to save EOI: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleEoiDelete = async (id) => {
    if (!window.confirm('Are you sure you want to cancel and delete this EOI?')) return;
    try {
      await axios.delete(`/api/eois/${id}`);
      fetchEois();
    } catch (err) {
      alert('Failed to delete: ' + (err.response?.data?.message || err.message));
    }
  };

  const startConversion = (eoi) => {
    setSelectedEoi(eoi);
    // Initialize convertForm
    setConvertForm({
      unit_no: '',
      size: '',
      agreement_value: '',
      cashback: '',
      revenue: '',
      ccashback: '',
      crevenue: '',
      recived_amt: '0',
      source_lead: '',
      remarks: '',
      city: ''
    });
    setShowConvertModal(true);
  };

  const handleConvertSubmit = async (e) => {
    e.preventDefault();
    if (!selectedEoi) return;

    try {
      await axios.post(`/api/eois/${selectedEoi._id}/convert`, {
        unit_no: convertForm.unit_no,
        size: convertForm.size,
        agreement_value: Number(convertForm.agreement_value),
        cashback: Number(convertForm.cashback || 0),
        revenue: Number(convertForm.revenue || 0),
        ccashback: Number(convertForm.ccashback || 0),
        crevenue: Number(convertForm.crevenue || 0),
        recived_amt: Number(convertForm.recived_amt || 0),
        source_lead: convertForm.source_lead,
        remarks: convertForm.remarks,
        city: convertForm.city
      });
      setShowConvertModal(false);
      setSelectedEoi(null);
      fetchEois();
      alert('EOI successfully converted to a pending booking.');
    } catch (err) {
      alert('Conversion failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const filteredEois = eois.filter(eoi => {
    const term = search.toLowerCase();
    return (
      (eoi.customer_name || '').toLowerCase().includes(term) ||
      (eoi.builder || '').toLowerCase().includes(term) ||
      (eoi.project || '').toLowerCase().includes(term) ||
      (eoi.contact_number || '').includes(term)
    );
  });

  return (
    <div className="page-shell space-y-6">
      {/* Search Controls and Action Button */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div className="search-wrap">
          <Search className="w-4 h-4 text-muted absolute left-3 top-3" />
          <input
            type="text"
            placeholder="Search EOIs by customer, builder, project..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="search-input text-xs"
          />
        </div>

        <button
          onClick={() => setShowAddModal(true)}
          className="btn-primary flex items-center gap-2"
        >
          <PlusCircle className="w-4 h-4" /> Add Expression of Interest
        </button>
      </div>

      {/* Grid List of EOIs */}
      <div className="table-container">
        <div className="card-section-header">
          <h3 className="text-body font-semibold text-xs uppercase tracking-wider">Active Expressions of Interest (EOI)</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="table-shell">
            <thead>
              <tr>
                <th>Customer Info</th>
                <th>Builder & Project</th>
                <th>Project Type</th>
                <th>Booking Month</th>
                <th>Source</th>
                <th className="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading && filteredEois.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-10">
                    <div className="spinner mx-auto"></div>
                  </td>
                </tr>
              ) : filteredEois.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-10 text-muted font-medium text-xs">
                    No EOI records found.
                  </td>
                </tr>
              ) : (
                filteredEois.map((eoi) => (
                  <tr key={eoi._id} className="transition-colors">
                    <td>
                      <div className="font-semibold text-ink">{eoi.customer_name}</div>
                      <div className="text-[11px] text-muted mt-0.5">{eoi.contact_number} | {eoi.email_id}</div>
                    </td>
                    <td>
                      <div className="font-semibold text-ink">{eoi.builder}</div>
                      <div className="text-[11px] text-muted mt-0.5">{eoi.project}</div>
                    </td>
                    <td>
                      <span className="badge-neutral uppercase text-[10px]">{eoi.project_type}</span>
                    </td>
                    <td className="text-xs text-body font-medium">
                      {eoi.booking_month || '-'}
                    </td>
                    <td className="text-xs text-body capitalize">
                      {eoi.source_table || '-'}
                    </td>
                    <td>
                      <div className="flex items-center justify-end gap-2">
                        <button
                          onClick={() => startConversion(eoi)}
                          className="btn-success"
                        >
                          <CheckCircle className="w-3.5 h-3.5" />
                          <span>Convert to Booking</span>
                        </button>
                        <button
                          onClick={() => handleEoiDelete(eoi._id)}
                          className="inline-flex items-center justify-center w-8 h-8 rounded-full text-error hover:bg-error/10 transition-colors"
                          title="Delete EOI"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add EOI Modal */}
      {showAddModal && createPortal(
        <div className="modal-overlay">
          <div className="modal-panel-md max-h-[min(90vh,calc(100dvh-3rem))] flex flex-col p-0 overflow-hidden">
            <div className="px-6 py-4 border-b border-hairline bg-canvas flex items-center justify-between shrink-0">
              <h3 className="font-display text-title-sm text-ink">Add Expression of Interest</h3>
              <button onClick={() => setShowAddModal(false)} className="btn-icon w-8 h-8">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="modal-panel-body p-6">
              <form onSubmit={handleEoiSubmit} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="label-xs">Booking Date *</label>
                  <input
                    type="date"
                    required
                    value={eoiForm.booking_date}
                    onChange={(e) => {
                      const date = e.target.value;
                      const month = date ? date.substring(0, 7) : '';
                      setEoiForm({ ...eoiForm, booking_date: date, booking_month: month });
                    }}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Booking Month</label>
                  <input
                    type="text"
                    readOnly
                    placeholder="YYYY-MM"
                    value={eoiForm.booking_month}
                    className="input-field text-xs bg-surface-soft text-muted cursor-not-allowed"
                  />
                </div>
                <div>
                  <label className="label-xs">Builder Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Prestige, Sobha"
                    value={eoiForm.builder}
                    onChange={(e) => setEoiForm({ ...eoiForm, builder: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Project Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Lavender Fields"
                    value={eoiForm.project}
                    onChange={(e) => setEoiForm({ ...eoiForm, project: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Customer Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="Full Name"
                    value={eoiForm.customer_name}
                    onChange={(e) => setEoiForm({ ...eoiForm, customer_name: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Contact Number *</label>
                  <input
                    type="text"
                    required
                    placeholder="10 digit mobile"
                    value={eoiForm.contact_number}
                    onChange={(e) => setEoiForm({ ...eoiForm, contact_number: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Email ID *</label>
                  <input
                    type="email"
                    required
                    placeholder="example@mail.com"
                    value={eoiForm.email_id}
                    onChange={(e) => setEoiForm({ ...eoiForm, email_id: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Project Type *</label>
                  <select
                    required
                    value={eoiForm.project_type}
                    onChange={(e) => setEoiForm({ ...eoiForm, project_type: e.target.value })}
                    className="select-field text-xs bg-canvas"
                  >
                    <option value="">Select Type</option>
                    <option value="Apartment">Apartment</option>
                    <option value="Villa">Villa</option>
                    <option value="Plot">Plot</option>
                    <option value="Commercial">Commercial</option>
                  </select>
                </div>

                <div className="col-span-1 sm:col-span-2 pt-4 flex justify-end gap-3 border-t border-hairline mt-4">
                  <button
                    type="button"
                    onClick={() => setShowAddModal(false)}
                    className="btn-secondary btn-sm"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="btn-primary btn-sm"
                  >
                    Save EOI
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* Convert EOI to Booking Modal */}
      {showConvertModal && selectedEoi && createPortal(
        <div className="modal-overlay">
          <div className="modal-panel-lg max-h-[min(90vh,calc(100dvh-3rem))] flex flex-col p-0 overflow-hidden">
            <div className="px-6 py-4 border-b border-hairline bg-canvas flex items-center justify-between shrink-0">
              <div>
                <h3 className="font-display text-title-sm text-ink">Convert EOI to Booking</h3>
                <p className="text-[11px] text-muted mt-0.5">
                  Customer: <span className="font-semibold text-ink">{selectedEoi.customer_name}</span> | {selectedEoi.builder} - {selectedEoi.project}
                </p>
              </div>
              <button onClick={() => setShowConvertModal(false)} className="btn-icon w-8 h-8">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="modal-panel-body p-6">
              <form onSubmit={handleConvertSubmit} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="label-xs">Unit Number * (Globally Unique)</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Tower A - 104"
                    value={convertForm.unit_no}
                    onChange={(e) => setConvertForm({ ...convertForm, unit_no: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Size (SqFt/SqYd) *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. 1200 SqFt"
                    value={convertForm.size}
                    onChange={(e) => setConvertForm({ ...convertForm, size: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Agreement Value (₹) *</label>
                  <input
                    type="number"
                    required
                    placeholder="e.g. 8500000"
                    value={convertForm.agreement_value}
                    onChange={(e) => setConvertForm({ ...convertForm, agreement_value: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Cashback Amount (₹) *</label>
                  <input
                    type="number"
                    required
                    placeholder="e.g. 50000"
                    value={convertForm.cashback}
                    onChange={(e) => setConvertForm({ ...convertForm, cashback: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Company Revenue Amount (₹) *</label>
                  <input
                    type="number"
                    required
                    placeholder="e.g. 150000"
                    value={convertForm.revenue}
                    onChange={(e) => setConvertForm({ ...convertForm, revenue: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Cashback Percentage (%) *</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    placeholder="e.g. 1.2"
                    value={convertForm.ccashback}
                    onChange={(e) => setConvertForm({ ...convertForm, ccashback: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Revenue Percentage (%) *</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    placeholder="e.g. 2.5"
                    value={convertForm.crevenue}
                    onChange={(e) => setConvertForm({ ...convertForm, crevenue: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Advance Received (₹) *</label>
                  <input
                    type="number"
                    required
                    value={convertForm.recived_amt}
                    onChange={(e) => setConvertForm({ ...convertForm, recived_amt: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Lead Source Info</label>
                  <input
                    type="text"
                    placeholder="e.g. Facebook Campaign, WhatsApp Group"
                    value={convertForm.source_lead}
                    onChange={(e) => setConvertForm({ ...convertForm, source_lead: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">City</label>
                  <input
                    type="text"
                    placeholder="e.g. Bangalore, Hyderabad"
                    value={convertForm.city}
                    onChange={(e) => setConvertForm({ ...convertForm, city: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div className="col-span-1 sm:col-span-2">
                  <label className="label-xs">Remarks</label>
                  <textarea
                    placeholder="Any extra info..."
                    value={convertForm.remarks}
                    onChange={(e) => setConvertForm({ ...convertForm, remarks: e.target.value })}
                    className="input-field text-xs h-20 resize-none bg-canvas py-2"
                  />
                </div>

                <div className="col-span-1 sm:col-span-2 pt-4 flex justify-end gap-3 border-t border-hairline mt-4">
                  <button
                    type="button"
                    onClick={() => setShowConvertModal(false)}
                    className="btn-secondary btn-sm"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="btn-success"
                  >
                    Complete Conversion
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default EOIPage;
