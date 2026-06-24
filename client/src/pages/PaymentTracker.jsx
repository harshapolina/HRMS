import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, Save, CheckCircle, AlertCircle, FileText, CheckSquare, Edit, Edit3 } from 'lucide-react';

const PaymentTracker = () => {
  const [payments, setPayments] = useState([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedMonth, setSelectedMonth] = useState('');

  // Editing state
  const [editingId, setEditingId] = useState(null);
  const [editValues, setEditValues] = useState({
    recived_amt: '',
    invoice_raise: 0,
    cashbackverify: false
  });

  useEffect(() => {
    fetchPayments();
  }, [selectedMonth]);

  const fetchPayments = async () => {
    setLoading(true);
    try {
      const params = {
        month: selectedMonth,
        search: search
      };
      const res = await axios.get('/api/tracking/payments', { params });
      setPayments(res.data || []);
    } catch (err) {
      console.error('Error fetching payments', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSearchKeyPress = (e) => {
    if (e.key === 'Enter') {
      fetchPayments();
    }
  };

  const startEdit = (p) => {
    setEditingId(p._id);
    setEditValues({
      recived_amt: p.recived_amt || 0,
      invoice_raise: p.invoice_raise || 0,
      cashbackverify: p.cashbackverify || false
    });
  };

  const handleSave = async (id) => {
    try {
      await axios.put(`/api/tracking/payments/${id}/advance`, {
        recived_amt: Number(editValues.recived_amt),
        invoice_raise: Number(editValues.invoice_raise),
        cashbackverify: editValues.cashbackverify
      });
      setEditingId(null);
      fetchPayments();
      alert('Payment details updated.');
    } catch (err) {
      alert('Save failed: ' + (err.response?.data?.message || err.message));
    }
  };

  return (
    <div className="space-y-6 text-slate-800 dark:text-slate-200">
      
      {/* Header and Controls */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <span className="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider block">Accounts Receivable</span>
          <span className="text-3xl font-extrabold text-slate-800 dark:text-white mt-1 block">Payment Milestone Tracker</span>
        </div>

        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
          {/* Month Filter */}
          <input
            type="month"
            value={selectedMonth}
            onChange={(e) => setSelectedMonth(e.target.value)}
            className="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white font-medium"
          />

          {/* Search Input */}
          <div className="relative w-full sm:w-60">
            <input
              type="text"
              placeholder="Search Customer/Unit (Press Enter)..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyPress={handleSearchKeyPress}
              className="px-4 py-2.5 pl-9 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-full bg-transparent dark:text-white font-medium"
            />
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
          </div>
        </div>
      </div>

      {/* Tracker List Grid */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/25 dark:bg-slate-800/50">
          <h3 className="text-slate-800 dark:text-white font-bold text-sm">Milestone Verification</h3>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-300">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-brand-600 dark:text-brand-400 font-bold text-xs uppercase tracking-wider">
                <th className="px-6 py-4">Customer & Project</th>
                <th className="px-6 py-4">Unit Detail</th>
                <th className="px-6 py-4">Agreement Value</th>
                <th className="px-6 py-4">Company Revenue</th>
                <th className="px-6 py-4">Received Advance</th>
                <th className="px-6 py-4">Invoice raised status</th>
                <th className="px-6 py-4">Cashback Verified</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan="8" className="text-center py-10">
                    <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
                  </td>
                </tr>
              ) : payments.length === 0 ? (
                <tr>
                  <td colSpan="8" className="text-center py-10 text-slate-400 dark:text-slate-500">
                    No approved booking payouts found.
                  </td>
                </tr>
              ) : (
                payments.map((p) => {
                  const isEditing = editingId === p._id;
                  return (
                    <tr key={p._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all">
                      <td className="px-6 py-4">
                        <div className="font-bold text-slate-800 dark:text-white">{p.customer_name}</div>
                        <div className="text-xxs text-slate-400 dark:text-slate-500 mt-0.5">{p.booking_month} | {p.project}</div>
                      </td>
                      <td className="px-6 py-4 text-xs font-semibold text-slate-600 dark:text-slate-350 capitalize">
                        {p.unit_no} <span className="text-[10px] text-slate-400 font-normal">({p.size})</span>
                      </td>
                      <td className="px-6 py-4 font-bold text-slate-800 dark:text-white">
                        ₹{p.agreement_value?.toLocaleString()}
                      </td>
                      <td className="px-6 py-4 text-xs font-bold text-brand-600 dark:text-brand-400">
                        ₹{p.revenue?.toLocaleString()} <span className="text-xxs text-slate-400 font-medium">({p.crevenue}%)</span>
                      </td>
                      <td className="px-6 py-4">
                        {isEditing ? (
                          <input
                            type="number"
                            value={editValues.recived_amt}
                            onChange={(e) => setEditValues({ ...editValues, recived_amt: e.target.value })}
                            className="w-24 px-2 py-1 border border-slate-300 dark:border-slate-600 rounded text-xs bg-transparent dark:text-white"
                          />
                        ) : (
                          <span className="font-extrabold text-slate-800 dark:text-white">₹{p.recived_amt?.toLocaleString()}</span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-xs">
                        {isEditing ? (
                          <select
                            value={editValues.invoice_raise}
                            onChange={(e) => setEditValues({ ...editValues, invoice_raise: Number(e.target.value) })}
                            className="px-2 py-1 border border-slate-300 dark:border-slate-600 rounded text-xs bg-transparent dark:text-white dark:bg-slate-900"
                          >
                            <option value={0}>Not Raised</option>
                            <option value={1}>Invoice Raised</option>
                            <option value={2}>Invoiced & Settled</option>
                          </select>
                        ) : (
                          <span className={`px-2 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider ${p.invoice_raise === 2 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600' : p.invoice_raise === 1 ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'}`}>
                            {p.invoice_raise === 2 ? 'Settled' : p.invoice_raise === 1 ? 'Raised' : 'Pending'}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        {isEditing ? (
                          <label className="flex items-center gap-1.5 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={editValues.cashbackverify}
                              onChange={(e) => setEditValues({ ...editValues, cashbackverify: e.target.checked })}
                              className="rounded text-brand-500 focus:ring-brand-500"
                            />
                            <span className="text-xxs font-bold text-slate-500">Verified</span>
                          </label>
                        ) : (
                          <span className={`flex items-center gap-1 text-xxs font-bold ${p.cashbackverify ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500'}`}>
                            {p.cashbackverify ? (
                              <><CheckCircle className="w-3.5 h-3.5" /> Verified (₹{p.cashback?.toLocaleString()})</>
                            ) : (
                              <><AlertCircle className="w-3.5 h-3.5" /> Pending Approval (₹{p.cashback?.toLocaleString()})</>
                            )}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-right">
                        {isEditing ? (
                          <button
                            onClick={() => handleSave(p._id)}
                            className="p-1.5 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 rounded-lg transition-all"
                            title="Save Payout Record"
                          >
                            <Save className="w-4 h-4" />
                          </button>
                        ) : (
                          <button
                            onClick={() => startEdit(p)}
                            className="p-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-500 rounded-lg transition-all"
                            title="Edit payment fields"
                          >
                            <Edit3 className="w-4 h-4" />
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default PaymentTracker;
