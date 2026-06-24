import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { PlusCircle, Search, Filter, Check, X, FileText, CheckCircle, AlertCircle, Trash2, Edit2, Calendar } from 'lucide-react';

const PropertyBookings = () => {
  const [bookings, setBookings] = useState([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [tab, setTab] = useState('approved'); // 'approved' or 'pending' or 'rejected'
  const [showAddForm, setShowAddForm] = useState(false);
  const [showFilterDrawer, setShowFilterDrawer] = useState(false);
  const [selectedBooking, setSelectedBooking] = useState(null);
  const [showEditModal, setShowEditModal] = useState(false);

  // Filters state
  const [filters, setFilters] = useState({
    city: '',
    year: '',
    status: ''
  });

  // Booking Form State
  const [form, setForm] = useState({
    booking_date: '',
    booking_month: '',
    builder: '',
    project: '',
    customer_name: '',
    contact_number: '',
    email_id: '',
    project_type: '',
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
    city: '',
    document_path: ''
  });

  // Edit Form State
  const [editForm, setEditForm] = useState({});

  const user = JSON.parse(localStorage.getItem('user') || '{}');
  const isAdmin = ['superuseradmin', 'hradmin'].includes(user.user_type);

  useEffect(() => {
    fetchBookings();
  }, [tab, filters]);

  const fetchBookings = async () => {
    setLoading(true);
    try {
      const params = {
        approvalStatus: tab,
        city: filters.city,
        year: filters.year,
        status: filters.status,
        search: search
      };
      const res = await axios.get('/api/bookings', { params });
      setBookings(res.data || []);
    } catch (err) {
      console.error('Error fetching bookings', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSearchKeyPress = (e) => {
    if (e.key === 'Enter') {
      fetchBookings();
    }
  };

  const handleBookingSubmit = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/bookings', form);
      setShowAddForm(false);
      resetForm();
      fetchBookings();
      alert('Booking request submitted successfully to the pending approvals queue.');
    } catch (err) {
      alert('Failed to submit booking: ' + (err.response?.data?.message || err.message));
    }
  };

  const resetForm = () => {
    setForm({
      booking_date: '',
      booking_month: '',
      builder: '',
      project: '',
      customer_name: '',
      contact_number: '',
      email_id: '',
      project_type: '',
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
      city: '',
      document_path: ''
    });
  };

  const handleApprove = async (id) => {
    if (!window.confirm('Approve this property booking? This will finalize it.')) return;
    try {
      await axios.put(`/api/bookings/${id}/approve`);
      fetchBookings();
    } catch (err) {
      alert('Approval failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleReject = async (id) => {
    if (!window.confirm('Reject/Cancel this property booking?')) return;
    try {
      await axios.put(`/api/bookings/${id}/reject`);
      fetchBookings();
    } catch (err) {
      alert('Rejection failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Permanently delete this booking record?')) return;
    try {
      await axios.delete(`/api/bookings/${id}`);
      fetchBookings();
    } catch (err) {
      alert('Delete failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const openEditModal = (booking) => {
    setSelectedBooking(booking);
    setEditForm({ ...booking });
    setShowEditModal(true);
  };

  const handleEditSubmit = async (e) => {
    e.preventDefault();
    try {
      await axios.put(`/api/bookings/${selectedBooking._id}`, editForm);
      setShowEditModal(false);
      fetchBookings();
      alert('Booking details updated successfully.');
    } catch (err) {
      alert('Update failed: ' + (err.response?.data?.message || err.message));
    }
  };

  return (
    <div className="page-shell space-y-6">
      
      {/* Controls & Search Banner */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4">
        
        {/* Search */}
        <div className="search-wrap">
          <Search className="w-4 h-4 text-muted absolute left-3 top-3" />
          <input
            type="text"
            placeholder="Search booking list (Press Enter)..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyPress={handleSearchKeyPress}
            className="search-input text-xs"
          />
        </div>

        {/* Buttons */}
        <div className="flex items-center gap-3 w-full md:w-auto justify-end">
          <button
            onClick={() => setShowFilterDrawer(true)}
            className="btn-secondary flex items-center gap-2"
          >
            <Filter className="w-4 h-4" /> Filters
          </button>
          <button
            onClick={() => setShowAddForm(true)}
            className="btn-primary flex items-center gap-2"
          >
            <PlusCircle className="w-4 h-4" /> Submit Booking
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="tab-bar">
        <button
          onClick={() => setTab('approved')}
          className={`tab-bar-item ${tab === 'approved' ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
        >
          Approved Bookings
        </button>
        <button
          onClick={() => setTab('pending')}
          className={`tab-bar-item ${tab === 'pending' ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
        >
          Pending Approvals
        </button>
        <button
          onClick={() => setTab('rejected')}
          className={`tab-bar-item ${tab === 'rejected' ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
        >
          Rejected/Canceled
        </button>
      </div>

      {/* List Table */}
      <div className="table-container">
        <div className="overflow-x-auto">
          <table className="table-shell">
            <thead>
              <tr>
                <th>Customer Info</th>
                <th>Property Info</th>
                <th>Financial Agreement</th>
                <th>Cashback / Rev Pct</th>
                <th>Status & Creator</th>
                <th className="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="6" className="text-center py-10">
                    <div className="spinner mx-auto"></div>
                  </td>
                </tr>
              ) : bookings.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-10 text-muted font-medium text-xs">
                    No bookings found in this category.
                  </td>
                </tr>
              ) : (
                bookings.map((booking) => (
                  <tr key={booking._id} className="transition-colors">
                    <td>
                      <div className="font-semibold text-ink">{booking.customer_name}</div>
                      <div className="text-[11px] text-muted mt-0.5">{booking.contact_number} | {booking.email_id}</div>
                      {booking.city && <span className="mt-1 inline-block px-1.5 py-0.5 bg-surface-soft border border-hairline text-muted text-[10px] font-semibold rounded capitalize">{booking.city}</span>}
                    </td>
                    <td>
                      <div className="font-semibold text-ink">{booking.builder} - {booking.project}</div>
                      <div className="text-[11px] text-muted mt-0.5">Unit: {booking.unit_no} | Size: {booking.size}</div>
                    </td>
                    <td>
                      <div className="font-semibold text-ink">₹{booking.agreement_value?.toLocaleString()}</div>
                      <div className="text-[10px] text-success font-semibold mt-0.5">Deduct Agreement: ₹{booking.deduct_agreement?.toLocaleString()}</div>
                    </td>
                    <td className="text-xs text-body font-medium">
                      <div>Cashback: {booking.ccashback}% (₹{booking.cashback?.toLocaleString()})</div>
                      <div className="mt-0.5 text-accent font-semibold">Revenue: {booking.crevenue}% (₹{booking.revenue?.toLocaleString()})</div>
                    </td>
                    <td>
                      <div>
                        <span className={`badge-pill uppercase text-[10px] font-bold tracking-wide ${booking.astatus === 'approved' ? 'badge-success' : booking.astatus === 'pending' ? 'badge-neutral' : 'badge-error'}`}>
                          {booking.astatus}
                        </span>
                      </div>
                      <div className="text-[10px] text-muted mt-1.5 capitalize">By: {booking.source_table}</div>
                    </td>
                    <td>
                      <div className="flex items-center justify-end gap-1.5">
                        {/* Manager Approval Buttons */}
                        {tab === 'pending' && (isAdmin || booking.assign_user.includes(user.tablename)) && (
                          <>
                            <button
                              onClick={() => handleApprove(booking._id)}
                              className="inline-flex items-center justify-center w-8 h-8 rounded-full text-success hover:bg-success/10 transition-colors"
                              title="Approve Booking"
                            >
                              <Check className="w-4.5 h-4.5" />
                            </button>
                            <button
                              onClick={() => handleReject(booking._id)}
                              className="inline-flex items-center justify-center w-8 h-8 rounded-full text-error hover:bg-error/10 transition-colors"
                              title="Reject Booking"
                            >
                              <X className="w-4.5 h-4.5" />
                            </button>
                          </>
                        )}
                        {/* Document Link */}
                        {booking.document_path && (
                          <a
                            href={booking.document_path}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center justify-center w-8 h-8 rounded-full text-muted hover:bg-surface-soft hover:text-ink transition-colors"
                            title="Download Contract"
                          >
                            <FileText className="w-4.5 h-4.5" />
                          </a>
                        )}
                        {/* Edit and Delete */}
                        <button
                          onClick={() => openEditModal(booking)}
                          className="inline-flex items-center justify-center w-8 h-8 rounded-full text-muted hover:bg-surface-soft hover:text-ink transition-colors"
                          title="Edit Details"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        {isAdmin && (
                          <button
                            onClick={() => handleDelete(booking._id)}
                            className="inline-flex items-center justify-center w-8 h-8 rounded-full text-error hover:bg-error/10 transition-colors"
                            title="Delete Permanently"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Filter Drawer Slider Panel */}
      {showFilterDrawer && createPortal(
        <div className="fixed inset-0 z-[100] flex justify-end">
          <div className="fixed inset-0 bg-surface-dark/40 backdrop-blur-sm transition-opacity" onClick={() => setShowFilterDrawer(false)} />
          <div className="relative w-80 bg-canvas border-l border-hairline h-full p-6 shadow-elevated space-y-6 flex flex-col justify-between z-10 animate-slide-in-right">
            <div className="space-y-6">
              <div className="flex items-center justify-between border-b border-hairline pb-3">
                <h3 className="font-display text-title-sm text-ink">Filter Bookings</h3>
                <button onClick={() => setShowFilterDrawer(false)} className="btn-icon w-8 h-8">
                  <X className="w-5 h-5" />
                </button>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="label-xs">City</label>
                  <input
                    type="text"
                    placeholder="e.g. Bangalore"
                    value={filters.city}
                    onChange={(e) => setFilters({ ...filters, city: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Financial Year (April - March)</label>
                  <input
                    type="text"
                    placeholder="e.g. 2026-2027"
                    value={filters.year}
                    onChange={(e) => setFilters({ ...filters, year: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Status</label>
                  <select
                    value={filters.status}
                    onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                    className="select-field text-xs bg-canvas"
                  >
                    <option value="">All Statuses</option>
                    <option value="Processing">Processing</option>
                    <option value="Completed">Completed</option>
                    <option value="Received">Received</option>
                    <option value="Cancelled">Cancelled</option>
                  </select>
                </div>
              </div>
            </div>

            <div className="flex gap-3 border-t border-hairline pt-4">
              <button
                onClick={() => {
                  setFilters({ city: '', year: '', status: '' });
                  setShowFilterDrawer(false);
                }}
                className="w-1/2 btn-secondary btn-sm"
              >
                Reset
              </button>
              <button
                onClick={() => setShowFilterDrawer(false)}
                className="w-1/2 btn-primary btn-sm"
              >
                Apply
              </button>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* Add Booking Modal */}
      {showAddForm && createPortal(
        <div className="modal-overlay">
          <div className="modal-panel-lg max-h-[min(90vh,calc(100dvh-3rem))] flex flex-col p-0 overflow-hidden">
            <div className="px-6 py-4 border-b border-hairline bg-canvas flex items-center justify-between shrink-0">
              <h3 className="font-display text-title-sm text-ink">Submit Property Booking</h3>
              <button onClick={() => setShowAddForm(false)} className="btn-icon w-8 h-8">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="modal-panel-body p-6">
              <form onSubmit={handleBookingSubmit} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="label-xs">Booking Date *</label>
                  <input
                    type="date"
                    required
                    value={form.booking_date}
                    onChange={(e) => {
                      const d = e.target.value;
                      const m = d ? d.substring(0, 7) : '';
                      setForm({ ...form, booking_date: d, booking_month: m });
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
                    value={form.booking_month}
                    className="input-field text-xs bg-surface-soft text-muted cursor-not-allowed"
                  />
                </div>
                <div>
                  <label className="label-xs">Builder Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="Prestige Group"
                    value={form.builder}
                    onChange={(e) => setForm({ ...form, builder: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Project Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="Lavender"
                    value={form.project}
                    onChange={(e) => setForm({ ...form, project: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Customer Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="Customer Full Name"
                    value={form.customer_name}
                    onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Contact Number *</label>
                  <input
                    type="text"
                    required
                    placeholder="Mobile"
                    value={form.contact_number}
                    onChange={(e) => setForm({ ...form, contact_number: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Email ID *</label>
                  <input
                    type="email"
                    required
                    placeholder="customer@domain.com"
                    value={form.email_id}
                    onChange={(e) => setForm({ ...form, email_id: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Project Type *</label>
                  <select
                    required
                    value={form.project_type}
                    onChange={(e) => setForm({ ...form, project_type: e.target.value })}
                    className="select-field text-xs bg-canvas"
                  >
                    <option value="">Select Type</option>
                    <option value="Apartment">Apartment</option>
                    <option value="Villa">Villa</option>
                    <option value="Plot">Plot</option>
                    <option value="Commercial">Commercial</option>
                  </select>
                </div>
                <div>
                  <label className="label-xs">Unit Number * (Globally Unique)</label>
                  <input
                    type="text"
                    required
                    placeholder="Tower 2 - 402"
                    value={form.unit_no}
                    onChange={(e) => setForm({ ...form, unit_no: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Size (SqFt/SqYd) *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. 1500"
                    value={form.size}
                    onChange={(e) => setForm({ ...form, size: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Agreement Value (₹) *</label>
                  <input
                    type="number"
                    required
                    placeholder="e.g. 9500000"
                    value={form.agreement_value}
                    onChange={(e) => setForm({ ...form, agreement_value: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Cashback Amount (₹) *</label>
                  <input
                    type="number"
                    required
                    placeholder="Cashback"
                    value={form.cashback}
                    onChange={(e) => setForm({ ...form, cashback: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Company Revenue (₹) *</label>
                  <input
                    type="number"
                    required
                    placeholder="Revenue"
                    value={form.revenue}
                    onChange={(e) => setForm({ ...form, revenue: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Cashback Percentage (%) *</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    placeholder="e.g. 1.0"
                    value={form.ccashback}
                    onChange={(e) => setForm({ ...form, ccashback: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Revenue Percentage (%) *</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    placeholder="e.g. 2.0"
                    value={form.crevenue}
                    onChange={(e) => setForm({ ...form, crevenue: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Advance Payment Received (₹) *</label>
                  <input
                    type="number"
                    required
                    value={form.recived_amt}
                    onChange={(e) => setForm({ ...form, recived_amt: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Lead Source Info</label>
                  <input
                    type="text"
                    placeholder="e.g. Website"
                    value={form.source_lead}
                    onChange={(e) => setForm({ ...form, source_lead: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">City</label>
                  <input
                    type="text"
                    placeholder="e.g. Bangalore"
                    value={form.city}
                    onChange={(e) => setForm({ ...form, city: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div className="col-span-1 sm:col-span-2">
                  <label className="label-xs">Document Link / Path</label>
                  <input
                    type="text"
                    placeholder="e.g. https://drive.google.com/..."
                    value={form.document_path}
                    onChange={(e) => setForm({ ...form, document_path: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div className="col-span-1 sm:col-span-2">
                  <label className="label-xs">Remarks</label>
                  <textarea
                    value={form.remarks}
                    onChange={(e) => setForm({ ...form, remarks: e.target.value })}
                    className="input-field text-xs h-16 resize-none bg-canvas py-2"
                  />
                </div>

                <div className="col-span-1 sm:col-span-2 pt-4 flex justify-end gap-3 border-t border-hairline mt-4">
                  <button
                    type="button"
                    onClick={() => setShowAddForm(false)}
                    className="btn-secondary btn-sm"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="btn-primary btn-sm"
                  >
                    Submit Booking
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/* Edit Booking Modal */}
      {showEditModal && selectedBooking && createPortal(
        <div className="modal-overlay">
          <div className="modal-panel-lg max-h-[min(90vh,calc(100dvh-3rem))] flex flex-col p-0 overflow-hidden">
            <div className="px-6 py-4 border-b border-hairline bg-canvas flex items-center justify-between shrink-0">
              <h3 className="font-display text-title-sm text-ink">Edit Booking Details</h3>
              <button onClick={() => setShowEditModal(false)} className="btn-icon w-8 h-8">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="modal-panel-body p-6">
              <form onSubmit={handleEditSubmit} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="label-xs">Builder Name *</label>
                  <input
                    type="text"
                    required
                    value={editForm.builder || ''}
                    onChange={(e) => setEditForm({ ...editForm, builder: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Project Name *</label>
                  <input
                    type="text"
                    required
                    value={editForm.project || ''}
                    onChange={(e) => setEditForm({ ...editForm, project: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Customer Name *</label>
                  <input
                    type="text"
                    required
                    value={editForm.customer_name || ''}
                    onChange={(e) => setEditForm({ ...editForm, customer_name: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Contact Number *</label>
                  <input
                    type="text"
                    required
                    value={editForm.contact_number || ''}
                    onChange={(e) => setEditForm({ ...editForm, contact_number: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Unit Number * (Globally Unique)</label>
                  <input
                    type="text"
                    required
                    value={editForm.unit_no || ''}
                    onChange={(e) => setEditForm({ ...editForm, unit_no: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Size (SqFt/SqYd) *</label>
                  <input
                    type="text"
                    required
                    value={editForm.size || ''}
                    onChange={(e) => setEditForm({ ...editForm, size: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Agreement Value (₹) *</label>
                  <input
                    type="number"
                    required
                    value={editForm.agreement_value || ''}
                    onChange={(e) => setEditForm({ ...editForm, agreement_value: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Cashback Amount (₹) *</label>
                  <input
                    type="number"
                    required
                    value={editForm.cashback || ''}
                    onChange={(e) => setEditForm({ ...editForm, cashback: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Company Revenue (₹) *</label>
                  <input
                    type="number"
                    required
                    value={editForm.revenue || ''}
                    onChange={(e) => setEditForm({ ...editForm, revenue: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Cashback Percentage (%) *</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={editForm.ccashback || ''}
                    onChange={(e) => setEditForm({ ...editForm, ccashback: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Revenue Percentage (%) *</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={editForm.crevenue || ''}
                    onChange={(e) => setEditForm({ ...editForm, crevenue: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Advance Payment Received (₹) *</label>
                  <input
                    type="number"
                    required
                    value={editForm.recived_amt || 0}
                    onChange={(e) => setEditForm({ ...editForm, recived_amt: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">City</label>
                  <input
                    type="text"
                    value={editForm.city || ''}
                    onChange={(e) => setEditForm({ ...editForm, city: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Document Path</label>
                  <input
                    type="text"
                    value={editForm.document_path || ''}
                    onChange={(e) => setEditForm({ ...editForm, document_path: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>

                <div className="col-span-1 sm:col-span-2 pt-4 flex justify-end gap-3 border-t border-hairline mt-4">
                  <button
                    type="button"
                    onClick={() => setShowEditModal(false)}
                    className="btn-secondary btn-sm"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="btn-primary btn-sm"
                  >
                    Save Changes
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

export default PropertyBookings;
