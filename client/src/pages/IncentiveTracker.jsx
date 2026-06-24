import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip, Legend, CartesianGrid } from 'recharts';
import { Award, Plus, Calendar, DollarSign, ArrowUpRight, TrendingUp, Users, Percent, X } from 'lucide-react';

const IncentiveTracker = () => {
  const [incentiveData, setIncentiveData] = useState({ bookings: [], trackings: [] });
  const [manualTrackings, setManualTrackings] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showAddForm, setShowAddForm] = useState(false);

  // Form State
  const [form, setForm] = useState({
    month: '',
    send_amt: '',
    user_name: '',
    user_type: 'salary', // 'salary', 'manager', 'teamlead', 'ceo'
    bookin_number: '',
    gen_revenue: '',
    recent_pay: '',
    remaning_amt: ''
  });

  useEffect(() => {
    fetchIncentiveData();
    fetchManualTrackings();
  }, []);

  const fetchIncentiveData = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/tracking/incentives');
      setIncentiveData(res.data || { bookings: [], trackings: [] });
    } catch (err) {
      console.error('Error fetching incentive data', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchManualTrackings = async () => {
    try {
      const res = await axios.get('/api/tracking');
      setManualTrackings(res.data || []);
    } catch (err) {
      console.error('Error fetching manual trackings', err);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/tracking', {
        ...form,
        send_amt: Number(form.send_amt || 0),
        bookin_number: Number(form.bookin_number || 0),
        gen_revenue: Number(form.gen_revenue || 0),
        recent_pay: Number(form.recent_pay || 0),
        remaning_amt: Number(form.remaning_amt || 0)
      });
      setShowAddForm(false);
      resetForm();
      fetchIncentiveData();
      fetchManualTrackings();
      alert('Manual tracking/salary entry saved.');
    } catch (err) {
      alert('Save failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const resetForm = () => {
    setForm({
      month: '',
      send_amt: '',
      user_name: '',
      user_type: 'salary',
      bookin_number: '',
      gen_revenue: '',
      recent_pay: '',
      remaning_amt: ''
    });
  };

  // Process data for Recharts (Combine monthly revenues vs payouts)
  const getChartData = () => {
    const months = {};
    
    // Process bookings aggregated stats
    incentiveData.bookings.forEach((b) => {
      const m = b._id; // YYYY-MM
      months[m] = {
        month: m,
        revenue: b.totalRevenue || 0,
        cashback: b.totalCashback || 0,
        payout: 0
      };
    });

    // Merge manual trackings aggregated stats
    incentiveData.trackings.forEach((t) => {
      const m = t._id;
      if (!months[m]) {
        months[m] = {
          month: m,
          revenue: t.totalGenRevenue || 0,
          cashback: 0,
          payout: t.totalPayout || 0
        };
      } else {
        months[m].payout = t.totalPayout || 0;
        if (t.totalGenRevenue) months[m].revenue += t.totalGenRevenue;
      }
    });

    return Object.values(months).sort((a, b) => a.month.localeCompare(b.month));
  };

  const chartData = getChartData();

  // Summary Metrics calculations
  const totalRevenues = incentiveData.bookings.reduce((sum, b) => sum + (b.totalRevenue || 0), 0);
  const totalCashbacks = incentiveData.bookings.reduce((sum, b) => sum + (b.totalCashback || 0), 0);
  const totalPayouts = incentiveData.trackings.reduce((sum, t) => sum + (t.totalPayout || 0), 0);

  return (
    <div className="page-shell space-y-6">
      
      {/* Metrics Row */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        {/* Metric 1 */}
        <div className="card p-6 flex items-center justify-between shadow-card">
          <div>
            <span className="text-muted text-xs font-semibold uppercase tracking-wider block">Total Generated Revenue</span>
            <span className="text-3xl font-extrabold text-ink mt-2 block">₹{totalRevenues.toLocaleString()}</span>
          </div>
          <div className="p-4 bg-success/10 rounded-xl">
            <TrendingUp className="w-8 h-8 text-success" />
          </div>
        </div>

        {/* Metric 2 */}
        <div className="card p-6 flex items-center justify-between shadow-card">
          <div>
            <span className="text-muted text-xs font-semibold uppercase tracking-wider block">Promoter Payouts</span>
            <span className="text-3xl font-extrabold text-ink mt-2 block">₹{totalPayouts.toLocaleString()}</span>
          </div>
          <div className="p-4 bg-primary/10 rounded-xl">
            <Award className="w-8 h-8 text-primary" />
          </div>
        </div>

        {/* Metric 3 */}
        <div className="card p-6 flex items-center justify-between shadow-card">
          <div>
            <span className="text-muted text-xs font-semibold uppercase tracking-wider block">Customer Cashbacks</span>
            <span className="text-3xl font-extrabold text-ink mt-2 block">₹{totalCashbacks.toLocaleString()}</span>
          </div>
          <div className="p-4 bg-warning/10 rounded-xl">
            <Percent className="w-8 h-8 text-warning" />
          </div>
        </div>
      </div>

      {/* Recharts Chart Section */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div className="lg:col-span-2 card p-6 flex flex-col justify-between shadow-card">
          <div>
            <h3 className="text-ink font-semibold text-sm mb-4">Revenue vs. Payout Performance</h3>
          </div>
          <div className="h-72 w-full">
            {chartData.length === 0 ? (
              <div className="h-full flex items-center justify-center text-muted">No chart data available.</div>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} margin={{ top: 10, right: 10, left: 10, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="rgba(148, 163, 184, 0.1)" />
                  <XAxis dataKey="month" stroke="rgb(156, 163, 175)" fontSize={10} tickLine={false} />
                  <YAxis stroke="rgb(156, 163, 175)" fontSize={10} tickLine={false} tickFormatter={(val) => `₹${val/1000}k`} />
                  <Tooltip formatter={(value) => `₹${value.toLocaleString()}`} contentStyle={{ backgroundColor: 'rgb(15 23 42)', border: 'none', borderRadius: '8px', color: 'white', fontSize: '11px' }} />
                  <Legend wrapperStyle={{ fontSize: '11px' }} />
                  <Bar name="Company Revenue" dataKey="revenue" fill="#10b981" radius={[4, 4, 0, 0]} />
                  <Bar name="Payouts / Salaries" dataKey="payout" fill="#3b82f6" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>

        {/* Aggregated Months Summary List */}
        <div className="card p-6 flex flex-col justify-between overflow-hidden shadow-card">
          <h3 className="text-ink font-semibold text-sm mb-4">Monthly Statements</h3>
          <div className="flex-1 overflow-y-auto space-y-4 max-h-72 scrollbar-none pr-1">
            {incentiveData.bookings.map((bookingStat, idx) => (
              <div key={idx} className="flex justify-between items-center border-b border-hairline pb-3">
                <div>
                  <span className="font-bold text-xs text-ink block">{bookingStat._id}</span>
                  <span className="text-[10px] text-muted mt-0.5 block">{bookingStat.totalBookings} Completed Bookings</span>
                </div>
                <div className="text-right">
                  <span className="text-xs font-semibold text-success block">₹{bookingStat.totalRevenue?.toLocaleString()}</span>
                  <span className="text-[9px] text-muted block">CB: ₹{bookingStat.totalCashback?.toLocaleString()}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Manual Trackings Log & Form Trigger */}
      <div className="table-container shadow-card">
        <div className="card-section-header flex items-center justify-between">
          <h3 className="text-body font-semibold text-xs uppercase tracking-wider">Commission & Salary Ledger</h3>
          <button
            onClick={() => setShowAddForm(true)}
            className="btn-primary btn-sm flex items-center gap-1.5"
          >
            <Plus className="w-3.5 h-3.5" /> Log Manual Payout
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="table-shell">
            <thead>
              <tr>
                <th>Month</th>
                <th>Employee / Partner Name</th>
                <th>Role Category</th>
                <th>Gen Revenue</th>
                <th>Sent Amount</th>
                <th>Recent Pay / Rem</th>
              </tr>
            </thead>
            <tbody>
              {manualTrackings.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-6 text-muted">No manual ledger items logged.</td>
                </tr>
              ) : (
                manualTrackings.map((track) => (
                  <tr key={track._id} className="transition-colors">
                    <td className="font-bold text-ink">{track.month}</td>
                    <td className="font-semibold text-ink capitalize">{track.user_name}</td>
                    <td className="text-xs font-semibold capitalize">
                      <span className="badge-neutral uppercase text-[10px]">{track.user_type}</span>
                    </td>
                    <td className="text-xs font-medium text-body">₹{track.gen_revenue?.toLocaleString()}</td>
                    <td className="text-xs font-semibold text-ink">₹{track.send_amt?.toLocaleString()}</td>
                    <td className="text-xs text-body">
                      <div>Paid: ₹{track.recent_pay?.toLocaleString()}</div>
                      <div className="text-[10px] text-error font-semibold mt-0.5">Remaining: ₹{track.remaning_amt?.toLocaleString()}</div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Log Manual Payout Modal */}
      {showAddForm && createPortal(
        <div className="modal-overlay">
          <div className="modal-panel-md max-h-[min(90vh,calc(100dvh-3rem))] flex flex-col p-0 overflow-hidden">
            <div className="px-6 py-4 border-b border-hairline bg-canvas flex items-center justify-between shrink-0">
              <h3 className="font-display text-title-sm text-ink">Log Payout / Salary Entry</h3>
              <button onClick={() => { setShowAddForm(false); resetForm(); }} className="btn-icon w-8 h-8">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="modal-panel-body p-6">
              <form onSubmit={handleSubmit} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="label-xs">Month *</label>
                  <input
                    type="month"
                    required
                    value={form.month}
                    onChange={(e) => setForm({ ...form, month: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Employee / Partner Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Search Homes Partner"
                    value={form.user_name}
                    onChange={(e) => setForm({ ...form, user_name: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Category Type *</label>
                  <select
                    value={form.user_type}
                    onChange={(e) => setForm({ ...form, user_type: e.target.value })}
                    className="select-field text-xs bg-canvas"
                  >
                    <option value="salary">Basic Salary Payout</option>
                    <option value="manager">Manager Commission</option>
                    <option value="teamlead">Team Lead Commission</option>
                    <option value="ceo">CEO target Payout</option>
                  </select>
                </div>
                <div>
                  <label className="label-xs">Generated Revenue (₹)</label>
                  <input
                    type="number"
                    placeholder="Revenue amount"
                    value={form.gen_revenue}
                    onChange={(e) => setForm({ ...form, gen_revenue: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Total Payout Amount (₹) *</label>
                  <input
                    type="number"
                    required
                    placeholder="Payout amount"
                    value={form.send_amt}
                    onChange={(e) => setForm({ ...form, send_amt: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Booking Count</label>
                  <input
                    type="number"
                    placeholder="Bookings count"
                    value={form.bookin_number}
                    onChange={(e) => setForm({ ...form, bookin_number: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Recent Paid Amount (₹)</label>
                  <input
                    type="number"
                    placeholder="Paid amount"
                    value={form.recent_pay}
                    onChange={(e) => setForm({ ...form, recent_pay: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>
                <div>
                  <label className="label-xs">Remaining Due Balance (₹)</label>
                  <input
                    type="number"
                    placeholder="Due amount"
                    value={form.remaning_amt}
                    onChange={(e) => setForm({ ...form, remaning_amt: e.target.value })}
                    className="input-field text-xs bg-canvas"
                  />
                </div>

                <div className="col-span-1 sm:col-span-2 pt-4 flex justify-end gap-3 border-t border-hairline mt-4">
                  <button
                    type="button"
                    onClick={() => {
                      setShowAddForm(false);
                      resetForm();
                    }}
                    className="btn-secondary btn-sm"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="btn-primary btn-sm"
                  >
                    Log Payout
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

export default IncentiveTracker;
