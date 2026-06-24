import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Clock, Plus, Edit2, Trash2, X, Play, Pause, ListOrdered } from 'lucide-react';

const CronTracker = () => {
  const [crons, setCrons] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedCron, setSelectedCron] = useState(null);

  // Form State
  const [form, setForm] = useState({
    project_name: '',
    source_lead: '',
    interval_time: '',
    location: '',
    assigned_user: [], // Array of tablenames
    is_active: true
  });

  useEffect(() => {
    fetchCrons();
    fetchUsers();
  }, []);

  const fetchCrons = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/cron');
      setCrons(res.data || []);
    } catch (err) {
      console.error('Error fetching cron jobs', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchUsers = async () => {
    try {
      const res = await axios.get('/api/users');
      const usersData = res.data.data || res.data || [];
      setUsers(usersData);
    } catch (err) {
      console.error('Error fetching users', err);
    }
  };

  const handleAddSubmit = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/cron', {
        ...form,
        interval_time: Number(form.interval_time)
      });
      setShowAddModal(false);
      resetForm();
      fetchCrons();
      alert('Cron job successfully created.');
    } catch (err) {
      alert('Failed to save cron job: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleEditSubmit = async (e) => {
    e.preventDefault();
    if (!selectedCron) return;

    try {
      await axios.put(`/api/cron/${selectedCron._id}`, {
        ...form,
        interval_time: Number(form.interval_time)
      });
      setShowEditModal(false);
      setSelectedCron(null);
      resetForm();
      fetchCrons();
      alert('Cron job updated successfully.');
    } catch (err) {
      alert('Failed to update cron job: ' + (err.response?.data?.message || err.message));
    }
  };

  const toggleCronStatus = async (cron) => {
    try {
      await axios.put(`/api/cron/${cron._id}`, {
        is_active: !cron.is_active
      });
      fetchCrons();
    } catch (err) {
      alert('Failed to toggle cron status: ' + err.message);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this lead distribution cron job?')) return;
    try {
      await axios.delete(`/api/cron/${id}`);
      fetchCrons();
    } catch (err) {
      alert('Delete failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const openEdit = (cron) => {
    setSelectedCron(cron);
    setForm({
      project_name: cron.project_name || '',
      source_lead: cron.source_lead || '',
      interval_time: cron.interval_time || '',
      location: cron.location || '',
      assigned_user: cron.assigned_user || [],
      is_active: cron.is_active
    });
    setShowEditModal(true);
  };

  const resetForm = () => {
    setForm({
      project_name: '',
      source_lead: '',
      interval_time: '',
      location: '',
      assigned_user: [],
      is_active: true
    });
  };

  const handleUserSelect = (tablename) => {
    setForm(prev => {
      const alreadySelected = prev.assigned_user.includes(tablename);
      const updated = alreadySelected
        ? prev.assigned_user.filter(u => u !== tablename)
        : [...prev.assigned_user, tablename];
      return { ...prev, assigned_user: updated };
    });
  };

  return (
    <div className="space-y-6 text-slate-800 dark:text-slate-200">
      
      {/* Header Panel */}
      <div className="flex items-center justify-between">
        <div>
          <span className="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider block">Automation Console</span>
          <span className="text-3xl font-extrabold text-slate-800 dark:text-white mt-1 block">Lead Crons Tracker</span>
        </div>
        <button
          onClick={() => {
            resetForm();
            setShowAddModal(true);
          }}
          className="px-5 py-2.5 bg-brand-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-brand-500/10 hover:bg-brand-600 flex items-center gap-2 transition-all"
        >
          <Plus className="w-4 h-4" /> Create Cron Job
        </button>
      </div>

      {/* Crons List Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/25 dark:bg-slate-800/50">
          <h3 className="text-slate-800 dark:text-white font-bold text-sm">Lead Distribution Crons</h3>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-300">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-brand-600 dark:text-brand-400 font-bold text-xs uppercase tracking-wider">
                <th className="px-6 py-4">Project & Source</th>
                <th className="px-6 py-4">Interval Time</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4">Queued Leads</th>
                <th className="px-6 py-4">Target Promoters</th>
                <th className="px-6 py-4">Last Handled</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan="7" className="text-center py-10">
                    <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
                  </td>
                </tr>
              ) : crons.length === 0 ? (
                <tr>
                  <td colSpan="7" className="text-center py-10 text-slate-400 dark:text-slate-500">
                    No active automation crons found.
                  </td>
                </tr>
              ) : (
                crons.map((cron) => (
                  <tr key={cron._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all">
                    <td className="px-6 py-4 font-bold text-slate-800 dark:text-white capitalize">
                      {cron.project_name}
                      <span className="block text-xxs font-normal text-slate-400 dark:text-slate-500 mt-0.5">Source: {cron.source_lead || 'Any'} | Location: {cron.location || 'Any'}</span>
                    </td>
                    <td className="px-6 py-4 text-xs font-semibold text-slate-600 dark:text-slate-350">
                      <span className="flex items-center gap-1.5"><Clock className="w-3.5 h-3.5 text-slate-400" /> {cron.interval_time} mins</span>
                    </td>
                    <td className="px-6 py-4 text-xs font-semibold">
                      <button
                        onClick={() => toggleCronStatus(cron)}
                        className={`px-3 py-1 rounded-full text-xxs font-bold uppercase flex items-center gap-1 transition-all ${cron.is_active ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'}`}
                      >
                        {cron.is_active ? (
                          <><Play className="w-3 h-3 fill-emerald-600" /> Active</>
                        ) : (
                          <><Pause className="w-3 h-3 fill-slate-500" /> Paused</>
                        )}
                      </button>
                    </td>
                    <td className="px-6 py-4 text-xs font-semibold text-brand-600 dark:text-brand-400">
                      <span className="flex items-center gap-1"><ListOrdered className="w-4 h-4 text-slate-400" /> {cron.row_id?.length || 0} in queue</span>
                    </td>
                    <td className="px-6 py-4">
                      {cron.assigned_user && cron.assigned_user.length > 0 ? (
                        <div className="flex flex-wrap gap-1 max-w-[200px]">
                          {cron.assigned_user.map((userTablename, idx) => (
                            <span key={idx} className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-bold rounded capitalize">{userTablename}</span>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xxs text-slate-400">Not assigned</span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-xs text-slate-500 dark:text-slate-450 font-semibold capitalize">
                      {cron.last_assigned_user || '-'}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => openEdit(cron)}
                          className="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 rounded-lg transition-all"
                          title="Edit Cron"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(cron._id)}
                          className="p-1.5 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-rose-600 dark:text-rose-400 rounded-lg transition-all"
                          title="Delete Cron"
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

      {/* Form modal Dialog */}
      {(showAddModal || showEditModal) && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-xl shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 className="text-slate-800 dark:text-white font-bold text-base">
                {showAddModal ? 'Create Lead Distribution Cron' : 'Edit Cron Job'}
              </h3>
              <button
                onClick={() => {
                  setShowAddModal(false);
                  setShowEditModal(false);
                  resetForm();
                }}
                className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-500"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={showAddModal ? handleAddSubmit : handleEditSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Project Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Prestige Lavender"
                    value={form.project_name}
                    onChange={(e) => setForm({ ...form, project_name: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Interval Time (Minutes) *</label>
                  <input
                    type="number"
                    required
                    placeholder="e.g. 5"
                    value={form.interval_time}
                    onChange={(e) => setForm({ ...form, interval_time: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Lead Source Info</label>
                  <input
                    type="text"
                    placeholder="e.g. Facebook Campaign"
                    value={form.source_lead}
                    onChange={(e) => setForm({ ...form, source_lead: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Location Filter</label>
                  <input
                    type="text"
                    placeholder="e.g. East Bangalore"
                    value={form.location}
                    onChange={(e) => setForm({ ...form, location: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                  />
                </div>
              </div>

              {/* Target Promoters Multi-select */}
              <div>
                <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Target Promoters to Distribute (Select multiple)</label>
                <div className="border border-slate-200 dark:border-slate-700 rounded-xl p-3 max-h-36 overflow-y-auto grid grid-cols-2 gap-2">
                  {users.map((u) => (
                    <label key={u._id} className="flex items-center gap-2 text-xxs font-bold cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 p-1 rounded capitalize text-slate-600 dark:text-slate-350">
                      <input
                        type="checkbox"
                        checked={form.assigned_user.includes(u.tablename)}
                        onChange={() => handleUserSelect(u.tablename)}
                        className="rounded text-brand-500 focus:ring-brand-500"
                      />
                      {u.username} ({u.user_type})
                    </label>
                  ))}
                </div>
              </div>

              <div className="pt-2 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800 pt-3">
                <button
                  type="button"
                  onClick={() => {
                    setShowAddModal(false);
                    setShowEditModal(false);
                    resetForm();
                  }}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold shadow-md"
                >
                  Save Cron
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default CronTracker;
