import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Sliders, Plus, Edit2, Trash2, X, Users, ShieldAlert, Key } from 'lucide-react';

const GlobalConfig = () => {
  const [configs, setConfigs] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedConfig, setSelectedConfig] = useState(null);

  // Form State
  const [form, setForm] = useState({
    project_name: '',
    group_name: '',
    type: 'credential', // 'credential' or 'group'
    api_key: '',
    lead_source: '',
    assign_user: [], // Array of tablenames
    group_id: [] // Array of group config IDs
  });

  useEffect(() => {
    fetchConfigs();
    fetchUsers();
  }, []);

  const fetchConfigs = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/config');
      setConfigs(res.data || []);
    } catch (err) {
      console.error('Error fetching configurations', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchUsers = async () => {
    try {
      const res = await axios.get('/api/users');
      // Adjust structure if response is nested
      const usersData = res.data.data || res.data || [];
      setUsers(usersData);
    } catch (err) {
      console.error('Error fetching users', err);
    }
  };

  const handleAddSubmit = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/config', form);
      setShowAddModal(false);
      resetForm();
      fetchConfigs();
      alert('Configuration saved successfully.');
    } catch (err) {
      alert('Save failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleEditSubmit = async (e) => {
    e.preventDefault();
    if (!selectedConfig) return;

    try {
      await axios.put(`/api/config/${selectedConfig._id}`, form);
      setShowEditModal(false);
      setSelectedConfig(null);
      resetForm();
      fetchConfigs();
      alert('Configuration updated successfully.');
    } catch (err) {
      alert('Update failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this configuration? This cannot be undone.')) return;
    try {
      await axios.delete(`/api/config/${id}`);
      fetchConfigs();
    } catch (err) {
      alert('Delete failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const openEdit = (config) => {
    setSelectedConfig(config);
    setForm({
      project_name: config.project_name || '',
      group_name: config.group_name || '',
      type: config.type || 'credential',
      api_key: config.api_key || '',
      lead_source: config.lead_source || '',
      assign_user: config.assign_user || [],
      group_id: config.group_id ? config.group_id.map(g => g._id || g) : []
    });
    setShowEditModal(true);
  };

  const resetForm = () => {
    setForm({
      project_name: '',
      group_name: '',
      type: 'credential',
      api_key: '',
      lead_source: '',
      assign_user: [],
      group_id: []
    });
  };

  const handleUserSelect = (tablename) => {
    setForm(prev => {
      const alreadySelected = prev.assign_user.includes(tablename);
      const updated = alreadySelected
        ? prev.assign_user.filter(u => u !== tablename)
        : [...prev.assign_user, tablename];
      return { ...prev, assign_user: updated };
    });
  };

  const handleGroupSelect = (groupId) => {
    setForm(prev => {
      const alreadySelected = prev.group_id.includes(groupId);
      const updated = alreadySelected
        ? prev.group_id.filter(g => g !== groupId)
        : [...prev.group_id, groupId];
      return { ...prev, group_id: updated };
    });
  };

  const groupsOnly = configs.filter(c => c.type === 'group');

  return (
    <div className="space-y-6 text-slate-800 dark:text-slate-200">
      
      {/* Controls */}
      <div className="flex items-center justify-between">
        <div>
          <span className="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider block">Integrations Dashboard</span>
          <span className="text-3xl font-extrabold text-slate-800 dark:text-white mt-1 block">API & Group Configs</span>
        </div>
        <button
          onClick={() => {
            resetForm();
            setShowAddModal(true);
          }}
          className="px-5 py-2.5 bg-brand-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-brand-500/10 hover:bg-brand-600 flex items-center gap-2 transition-all"
        >
          <Plus className="w-4 h-4" /> Add Configuration
        </button>
      </div>

      {/* Configurations Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/25 dark:bg-slate-800/50">
          <h3 className="text-slate-800 dark:text-white font-bold text-sm">System Integration Configs</h3>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-300">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-brand-600 dark:text-brand-400 font-bold text-xs uppercase tracking-wider">
                <th className="px-6 py-4">Project Name</th>
                <th className="px-6 py-4">Type</th>
                <th className="px-6 py-4">Details</th>
                <th className="px-6 py-4">Assigned Users</th>
                <th className="px-6 py-4">Groups Associated</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan="6" className="text-center py-10">
                    <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
                  </td>
                </tr>
              ) : configs.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-10 text-slate-400 dark:text-slate-500">
                    No active configurations found.
                  </td>
                </tr>
              ) : (
                configs.map((config) => (
                  <tr key={config._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all">
                    <td className="px-6 py-4 font-bold text-slate-800 dark:text-white capitalize">
                      {config.project_name}
                      {config.type === 'group' && config.group_name && (
                        <span className="block text-xxs font-normal text-slate-400 dark:text-slate-500">Group: {config.group_name}</span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-xs font-semibold">
                      <span className={`px-2 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider ${config.type === 'group' ? 'bg-amber-50 dark:bg-amber-950/30 text-amber-600' : 'bg-blue-50 dark:bg-blue-950/30 text-blue-600'}`}>
                        {config.type}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-xs max-w-xs truncate">
                      {config.type === 'credential' ? (
                        <div>
                          <div className="font-semibold text-slate-700 dark:text-slate-350">Source: <span className="capitalize">{config.lead_source || 'Unknown'}</span></div>
                          <div className="text-xxs text-slate-400 dark:text-slate-500 mt-0.5">Key: {config.api_key ? config.api_key.substring(0, 15) + '...' : '-'}</div>
                        </div>
                      ) : (
                        <span className="text-slate-400">-</span>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      {config.assign_user && config.assign_user.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                          {config.assign_user.map((userTablename, idx) => (
                            <span key={idx} className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-bold rounded capitalize">{userTablename}</span>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xxs text-slate-400">None assigned</span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-xs">
                      {config.group_id && config.group_id.length > 0 ? (
                        <div className="flex flex-col gap-0.5">
                          {config.group_id.map((grp, idx) => (
                            <span key={idx} className="text-xxs text-amber-600 dark:text-amber-400 font-bold">{grp.group_name || grp.project_name}</span>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xxs text-slate-400 dark:text-slate-600">-</span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => openEdit(config)}
                          className="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 rounded-lg transition-all"
                          title="Edit Config"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(config._id)}
                          className="p-1.5 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-rose-600 dark:text-rose-400 rounded-lg transition-all"
                          title="Delete Config"
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

      {/* Modal Dialog Form */}
      {(showAddModal || showEditModal) && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-xl shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 className="text-slate-800 dark:text-white font-bold text-base">
                {showAddModal ? 'Create Configuration' : 'Edit Configuration'}
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
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Configuration Type</label>
                  <select
                    value={form.type}
                    onChange={(e) => setForm({ ...form, type: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white dark:bg-slate-900"
                  >
                    <option value="credential">API Credential Token</option>
                    <option value="group">Lead Distribution Group</option>
                  </select>
                </div>
              </div>

              {form.type === 'group' ? (
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Group Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Prestige Group Sales"
                    value={form.group_name}
                    onChange={(e) => setForm({ ...form, group_name: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                  />
                </div>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Lead Source *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Facebook, WhatsApp"
                      value={form.lead_source}
                      onChange={(e) => setForm({ ...form, lead_source: e.target.value })}
                      className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">API Key / Token *</label>
                    <input
                      type="text"
                      required
                      placeholder="API Access Key"
                      value={form.api_key}
                      onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                      className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                    />
                  </div>
                </div>
              )}

              {/* Assign Users Selection */}
              <div>
                <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Assign User Targets (Select multiple)</label>
                <div className="border border-slate-200 dark:border-slate-700 rounded-xl p-3 max-h-36 overflow-y-auto grid grid-cols-2 gap-2">
                  {users.map((u) => (
                    <label key={u._id} className="flex items-center gap-2 text-xxs font-bold cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 p-1 rounded capitalize text-slate-600 dark:text-slate-350">
                      <input
                        type="checkbox"
                        checked={form.assign_user.includes(u.tablename)}
                        onChange={() => handleUserSelect(u.tablename)}
                        className="rounded text-brand-500 focus:ring-brand-500"
                      />
                      {u.username} ({u.user_type})
                    </label>
                  ))}
                </div>
              </div>

              {/* Assign to Group Selection (Only for Credential) */}
              {form.type === 'credential' && groupsOnly.length > 0 && (
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold mb-1">Associate with Distribution Groups</label>
                  <div className="border border-slate-200 dark:border-slate-700 rounded-xl p-3 max-h-28 overflow-y-auto flex flex-col gap-1.5">
                    {groupsOnly.map((grp) => (
                      <label key={grp._id} className="flex items-center gap-2 text-xxs font-bold cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 p-1 rounded text-slate-600 dark:text-slate-350">
                        <input
                          type="checkbox"
                          checked={form.group_id.includes(grp._id)}
                          onChange={() => handleGroupSelect(grp._id)}
                          className="rounded text-brand-500 focus:ring-brand-500"
                        />
                        {grp.group_name || grp.project_name} ({grp.project_name})
                      </label>
                    ))}
                  </div>
                </div>
              )}

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
                  Save Configuration
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default GlobalConfig;
