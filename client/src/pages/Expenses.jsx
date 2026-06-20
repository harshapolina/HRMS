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

  // Form Fields
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

  // Group by category for Chart visualization
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
  const COLORS = ['#477ca9', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#3b82f6'];

  return (
    <div className="space-y-6 text-slate-800 dark:text-slate-200">
      {/* Cards & Controls */}
      <div className="flex flex-col md:flex-row gap-6 items-stretch justify-between">
        
        {/* Ledger Card */}
        <div className="flex-1 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex items-center justify-between">
          <div>
            <span className="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider block">Total Ledger Expenses</span>
            <span className="text-3xl font-extrabold text-slate-800 dark:text-white mt-2 block">₹{totalAmount.toLocaleString()}</span>
          </div>
          <div className="p-4 bg-brand-500/10 rounded-xl">
            <Wallet className="w-8 h-8 text-brand-500" />
          </div>
        </div>

        <div className="flex flex-col sm:flex-row items-center gap-4 justify-end flex-wrap">
          <div className="relative w-full sm:w-60">
            <input
              type="text"
              placeholder="Search expenses..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="px-4 py-2.5 pl-9 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-full bg-transparent dark:text-white font-medium"
            />
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
          </div>

          <button
            onClick={() => setShowAddForm(true)}
            className="px-5 py-2.5 bg-brand-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-brand-500/10 hover:bg-brand-600 flex items-center gap-2 transition-all w-full sm:w-auto justify-center"
          >
            <PlusCircle className="w-4 h-4" /> Add Ledger Record
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Expense List Table */}
        <div className="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden flex flex-col">
          <div className="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/25 dark:bg-slate-800/50">
            <h3 className="text-slate-800 dark:text-white font-bold text-sm">Ledger Statements</h3>
          </div>
          <div className="flex-1 overflow-x-auto">
            <table className="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-300">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-brand-600 dark:text-brand-400 font-bold text-xs uppercase tracking-wider">
                  <th className="px-6 py-4">Category</th>
                  <th className="px-6 py-4">Payment Date</th>
                  <th className="px-6 py-4">Description</th>
                  <th className="px-6 py-4">Amount</th>
                  <th className="px-6 py-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {loading && filteredExpenses.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="text-center py-10">
                      <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
                    </td>
                  </tr>
                ) : filteredExpenses.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="text-center py-10 text-slate-400 dark:text-slate-500">
                      No matching expense statements found.
                    </td>
                  </tr>
                ) : (
                  filteredExpenses.map((exp) => (
                    <tr key={exp._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all">
                      <td className="px-6 py-4 font-bold text-slate-800 dark:text-white capitalize">{exp.category}</td>
                      <td className="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {exp.payment_date ? new Date(exp.payment_date).toLocaleDateString() : '-'}
                      </td>
                      <td className="px-6 py-4 text-slate-500 dark:text-slate-400 truncate max-w-[200px]" title={exp.description}>
                        {exp.description || '-'}
                      </td>
                      <td className="px-6 py-4 font-extrabold text-brand-600 dark:text-brand-400">₹{(exp.amount || 0).toLocaleString()}</td>
                      <td className="px-6 py-4 text-right">
                        <button
                          onClick={() => handleDelete(exp._id)}
                          className="p-1.5 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-rose-600 dark:text-rose-400 rounded-lg transition-all"
                        >
                          <Trash2 className="w-4.5 h-4.5" />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Expenses Pie Chart */}
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
          <div>
            <h3 className="text-slate-800 dark:text-white font-bold text-sm mb-4">Outflow Breakdown</h3>
          </div>
          <div className="h-64 flex items-center justify-center">
            {chartData.length === 0 ? (
              <p className="text-slate-400 dark:text-slate-500 text-xs font-semibold">No ledger data available for visualization.</p>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={chartData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={5}
                    dataKey="value"
                  >
                    {chartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value) => `₹${value.toLocaleString()}`} contentStyle={{ backgroundColor: 'rgb(15 23 42)', border: 'none', borderRadius: '8px', color: 'white' }} />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>
      </div>

      {/* Add Expense Modal */}
      {showAddForm && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-6 z-50 animate-fade-in">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4 text-slate-600 dark:text-slate-450">
            <div className="flex items-center justify-between">
              <h3 className="text-slate-800 dark:text-white font-bold text-base">Add Ledger Expense</h3>
              <button onClick={() => setShowAddForm(false)} className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-500">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Expense Category *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Server, Rent, Office Supplies"
                  value={form.category}
                  onChange={(e) => setForm({ ...form, category: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                />
              </div>
              <div>
                <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Payment Amount (₹) *</label>
                <input
                  type="number"
                  required
                  value={form.amount}
                  onChange={(e) => setForm({ ...form, amount: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                />
              </div>
              <div>
                <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Payment Date</label>
                <input
                  type="date"
                  value={form.payment_date}
                  onChange={(e) => setForm({ ...form, payment_date: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                />
              </div>
              <div>
                <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Statement Description</label>
                <textarea
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs h-20 resize-none bg-transparent dark:text-white"
                />
              </div>

              <div className="pt-2 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowAddForm(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold shadow-md"
                >
                  Save Record
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default Expenses;
