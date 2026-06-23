import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import { ResponsiveContainer, PieChart, Pie, Cell, Tooltip, Legend } from 'recharts';
import { Plus, Trash2, Wallet, PlusCircle, X, Search } from 'lucide-react';
import io from 'socket.io-client';

const Expenses = () => {
  const [expenses, setExpenses] = useState([]);
  const [totalAmount, setTotalAmount] = useState(0);
  const [showAddForm, setShowAddForm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');

  const [form, setForm] = useState({
    category: '',
    amount: '',
    description: '',
    payment_date: ''
  });

  const user = JSON.parse(localStorage.getItem('user') || '{}');

  const fetchExpensesRef = useRef(null);

  useEffect(() => {
    fetchExpensesRef.current = fetchExpenses;
  });

  useEffect(() => {
    const socket = io();
    socket.on('expense_update', () => {
      console.log('Real-time expense update received. Reloading expenses...');
      if (fetchExpensesRef.current) {
        fetchExpensesRef.current();
      }
    });

    return () => {
      socket.disconnect();
    };
  }, []);

  useEffect(() => {
    fetchExpenses();
  }, []);

  const fetchExpenses = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/expenses');
      setExpenses(res.data.data || []);
      setTotalAmount(res.data.totalAmount || 0);
    } catch (err) {
      console.error('Error fetching expenses', err);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this expense ledger item?')) return;
    try {
      await axios.delete(`/api/expenses/${id}`);
      fetchExpenses();
    } catch (err) {
      alert('Delete failed: ' + err.message);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.category || !form.amount) return;

    try {
      await axios.post('/api/expenses', {
        ...form,
        amount: parseFloat(form.amount),
        created_by: user.tablename || 'system'
      });
      setShowAddForm(false);
      setForm({ category: '', amount: '', description: '', payment_date: '' });
      fetchExpenses();
    } catch (err) {
      alert('Save failed: ' + err.message);
    }
  };

  const filteredExpenses = expenses.filter(exp => {
    const term = search.toLowerCase();
    return (
      (exp.category || '').toLowerCase().includes(term) ||
      (exp.description || '').toLowerCase().includes(term)
    );
  });

  const getChartData = () => {
    const categories = {};
    filteredExpenses.forEach((e) => {
      categories[e.category] = (categories[e.category] || 0) + e.amount;
    });
    return Object.keys(categories).map((cat) => ({
      name: cat,
      value: categories[cat]
    }));
  };

  const chartData = getChartData();
  const COLORS = ['#111111', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6b7280'];

  return (
    <div className="page-shell">
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Finance Ledger</p>
          <h1 className="page-title">Ledger Expenses</h1>
          <p className="page-subtitle">Track company outflows and category-wise spending.</p>
        </div>
        <div className="page-header-actions">
          <span className="badge-pill">₹{totalAmount.toLocaleString()} total</span>
        </div>
      </div>

      <div className="toolbar">
        <div className="toolbar-left">
          <div className="search-wrap">
            <input
              type="text"
              placeholder="Search expenses..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="search-input text-xs"
            />
            <Search className="w-4 h-4 text-muted-soft absolute left-3 top-1/2 -translate-y-1/2" />
          </div>
        </div>
        <div className="toolbar-right">
          <button onClick={() => setShowAddForm(true)} className="btn-primary btn-sm">
            <PlusCircle className="w-4 h-4" /> Add Ledger Record
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 table-container flex flex-col">
          <div className="card-section-header">
            <h3 className="section-title text-sm">Ledger Statements</h3>
          </div>
          <div className="flex-1 overflow-x-auto">
            <table className="table-shell text-sm">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Payment Date</th>
                  <th>Description</th>
                  <th>Amount</th>
                  <th className="text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                {loading && filteredExpenses.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="text-center py-10">
                      <div className="spinner mx-auto h-8 w-8" />
                    </td>
                  </tr>
                ) : filteredExpenses.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="text-center py-10 text-muted">No matching expense statements found.</td>
                  </tr>
                ) : (
                  filteredExpenses.map((exp) => (
                    <tr key={exp._id}>
                      <td className="font-semibold text-ink capitalize">{exp.category}</td>
                      <td className="text-xs font-medium text-muted">
                        {exp.payment_date ? new Date(exp.payment_date).toLocaleDateString() : '-'}
                      </td>
                      <td className="text-muted truncate max-w-[200px]" title={exp.description}>
                        {exp.description || '-'}
                      </td>
                      <td className="font-semibold text-ink">₹{(exp.amount || 0).toLocaleString()}</td>
                      <td className="text-right">
                        <button onClick={() => handleDelete(exp._id)} className="btn-icon-danger w-8 h-8">
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card p-6 flex flex-col">
          <h3 className="section-title text-sm mb-4">Outflow Breakdown</h3>
          <div className="h-64 flex items-center justify-center flex-1">
            {chartData.length === 0 ? (
              <p className="text-muted text-xs font-medium">No ledger data available for visualization.</p>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie data={chartData} cx="50%" cy="50%" innerRadius={60} outerRadius={80} paddingAngle={5} dataKey="value">
                    {chartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value) => `₹${value.toLocaleString()}`} contentStyle={{ backgroundColor: '#111111', border: 'none', borderRadius: '8px', color: 'white', fontSize: 12 }} />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>
      </div>

      {showAddForm && (
        <div className="modal-overlay">
          <div className="modal-panel-md space-y-4">
            <div className="modal-header mb-0 pb-4">
              <h3 className="font-semibold text-ink text-base">Add Ledger Expense</h3>
              <button onClick={() => setShowAddForm(false)} className="btn-icon text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleSubmit} className="space-y-4 mt-4">
              <div>
                <label className="label-xs">Expense Category *</label>
                <input type="text" required placeholder="e.g. Server, Rent, Office Supplies" value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="input-field text-xs" />
              </div>
              <div>
                <label className="label-xs">Payment Amount (₹) *</label>
                <input type="number" required value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} className="input-field text-xs" />
              </div>
              <div>
                <label className="label-xs">Payment Date</label>
                <input type="date" value={form.payment_date} onChange={(e) => setForm({ ...form, payment_date: e.target.value })} className="input-field text-xs" />
              </div>
              <div>
                <label className="label-xs">Statement Description</label>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="input-field text-xs h-20 resize-none py-2" />
              </div>
              <div className="modal-footer mb-0 pt-4">
                <button type="button" onClick={() => setShowAddForm(false)} className="btn-secondary btn-sm">Cancel</button>
                <button type="submit" className="btn-primary btn-sm">Save Record</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default Expenses;
