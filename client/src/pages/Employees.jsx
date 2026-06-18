import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Edit3, Trash2, Plus, Filter, LayoutGrid, ChevronLeft, ChevronRight, X, AlertCircle } from 'lucide-react';

const Employees = () => {
  const [employees, setEmployees] = useState([]);
  const [summary, setSummary] = useState({ activeCount: 0, inactiveCount: 0, assignedCount: 0, totalSalary: 0 });
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(10);
  const [search, setSearch] = useState('');
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading] = useState(false);

  // Edit / Add modal state
  const [showModal, setShowModal] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [assignees, setAssignees] = useState([]); // manager dropdowns
  const [currentId, setCurrentId] = useState(null);

  // Filters state
  const [showFilters, setShowFilters] = useState(false);
  const [filterRole, setFilterRole] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [filterProject, setFilterProject] = useState('');

  // Form Fields
  const [form, setForm] = useState({
    username: '',
    useremail: '',
    phonenumber: '',
    epassword: '',
    salary: 0,
    tablename: '',
    employee_id: '',
    doj: '',
    dob: '',
    one_amt: 0,
    two_amt: 0,
    thrid_amt: 0,
    forth_amt: 0,
    fifth_amt: 0,
    sixth_amt: 0,
    project_name: '',
    project_type: '',
    user_type: 'user',
    assign_user: [],
    city: '',
    is_active: true
  });

  // Column visibility state
  const [visibleCols, setVisibleCols] = useState({
    id: true,
    status: false,
    name: true,
    email: true,
    contact: false,
    salary: true,
    doj: false,
    role: true,
    project: true,
    assignee: false,
    action: true
  });
  const [showColDropdown, setShowColDropdown] = useState(false);

  useEffect(() => {
    fetchEmployees();
    fetchAssignees();
  }, [page, limit, search, filterRole, filterStatus, filterProject]);

  const fetchEmployees = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/users', {
        params: {
          page,
          limit,
          search,
          role: filterRole,
          status: filterStatus,
          project: filterProject
        }
      });
      setEmployees(res.data.data);
      setTotal(res.data.total);
      setTotalPages(res.data.totalPages);
      if (res.data.summary) {
        setSummary(res.data.summary);
      }
    } catch (err) {
      console.error('Error fetching employees', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchAssignees = async () => {
    try {
      const res = await axios.get('/api/users/assignees');
      setAssignees(res.data);
    } catch (err) {
      console.error('Error fetching assignees list', err);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this employee?')) return;
    try {
      await axios.delete(`/api/users/${id}`);
      fetchEmployees();
    } catch (err) {
      alert('Delete failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleEdit = (emp) => {
    setIsEditing(true);
    setCurrentId(emp._id);
    setForm({
      username: emp.username || '',
      useremail: emp.useremail || '',
      phonenumber: emp.phonenumber || '',
      epassword: '', // don't populate password
      salary: emp.salary || 0,
      tablename: emp.tablename || '',
      employee_id: emp.employee_id || '',
      doj: emp.doj ? emp.doj.split('T')[0] : '',
      dob: emp.dob ? emp.dob.split('T')[0] : '',
      one_amt: emp.one_amt || 0,
      two_amt: emp.two_amt || 0,
      thrid_amt: emp.thrid_amt || 0,
      forth_amt: emp.forth_amt || 0,
      fifth_amt: emp.fifth_amt || 0,
      sixth_amt: emp.sixth_amt || 0,
      project_name: emp.project_name || '',
      project_type: emp.project_type || '',
      user_type: emp.user_type || 'user',
      assign_user: emp.assign_user || [],
      city: emp.city || '',
      is_active: emp.is_active !== false
    });
    setShowModal(true);
  };

  const handleCreateNew = () => {
    setIsEditing(false);
    setCurrentId(null);
    setForm({
      username: '',
      useremail: '',
      phonenumber: '',
      epassword: '',
      salary: 0,
      tablename: '',
      employee_id: '',
      doj: '',
      dob: '',
      one_amt: 0,
      two_amt: 0,
      thrid_amt: 0,
      forth_amt: 0,
      fifth_amt: 0,
      sixth_amt: 0,
      project_name: '',
      project_type: '',
      user_type: 'user',
      assign_user: [],
      city: '',
      is_active: true
    });
    setShowModal(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      if (isEditing) {
        // Remove password if left empty during edit
        const payload = { ...form };
        if (!payload.epassword) {
          delete payload.epassword;
        }
        await axios.put(`/api/users/${currentId}`, payload);
      } else {
        await axios.post('/api/users', form);
      }
      setShowModal(false);
      fetchEmployees();
    } catch (err) {
      alert('Save failed: ' + (err.response?.data?.message || err.message));
    }
  };

  const toggleAssigneeSelect = (tablename) => {
    const exists = form.assign_user.includes(tablename);
    if (exists) {
      setForm({ ...form, assign_user: form.assign_user.filter(x => x !== tablename) });
    } else {
      setForm({ ...form, assign_user: [...form.assign_user, tablename] });
    }
  };

  return (
    <div className="space-y-6 relative text-slate-800 dark:text-slate-250">
      {/* Counters Wrap */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/50 rounded-xl p-4 text-emerald-800 dark:text-emerald-400">
          <span className="text-xs font-semibold uppercase tracking-wider block">Active Users</span>
          <span className="text-2xl font-bold mt-1 block">{summary.activeCount}</span>
        </div>
        <div className="bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-slate-700 dark:text-slate-400">
          <span className="text-xs font-semibold uppercase tracking-wider block">Inactive Users</span>
          <span className="text-2xl font-bold mt-1 block">{summary.inactiveCount}</span>
        </div>
        <div className="bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 rounded-xl p-4 text-indigo-800 dark:text-indigo-400">
          <span className="text-xs font-semibold uppercase tracking-wider block">Assigned Users</span>
          <span className="text-2xl font-bold mt-1 block">{summary.assignedCount}</span>
        </div>
        <div className="bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/50 rounded-xl p-4 text-amber-800 dark:text-amber-400">
          <span className="text-xs font-semibold uppercase tracking-wider block">Monthly Salaries</span>
          <span className="text-2xl font-bold mt-1 block">₹{summary.totalSalary.toLocaleString()}</span>
        </div>
      </div>

      {/* Control Bar */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-4 rounded-2xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <input
            type="text"
            placeholder="Search employees..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm w-full sm:w-64 bg-transparent dark:text-white"
          />
          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`p-2 border rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-all ${showFilters ? 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'}`}
          >
            <Filter className="w-5 h-5" />
          </button>
        </div>

        <div className="flex items-center gap-3 w-full sm:w-auto justify-end relative">
          <button
            onClick={() => setShowColDropdown(!showColDropdown)}
            className="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 rounded-xl flex items-center gap-2 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm"
          >
            <LayoutGrid className="w-4 h-4" /> Columns
          </button>

          {/* Columns Selector Dropdown */}
          {showColDropdown && (
            <div className="absolute right-0 top-full mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl p-4 z-30 w-48 space-y-2">
              {Object.keys(visibleCols).map((col) => (
                <label key={col} className="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400 cursor-pointer select-none capitalize">
                  <input
                    type="checkbox"
                    checked={visibleCols[col]}
                    onChange={() => setVisibleCols({ ...visibleCols, [col]: !visibleCols[col] })}
                    className="rounded text-brand-500 focus:ring-brand-500 dark:bg-slate-800"
                  />
                  {col}
                </label>
              ))}
            </div>
          )}

          <button
            onClick={handleCreateNew}
            className="px-4 py-2 bg-brand-500 text-white rounded-xl flex items-center gap-2 hover:bg-brand-600 font-bold transition-all text-sm"
          >
            <Plus className="w-4 h-4" /> Add Employee
          </button>
        </div>
      </div>

      {/* Advanced Filters Panel */}
      {showFilters && (
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Role Type</label>
            <select
              value={filterRole}
              onChange={(e) => setFilterRole(e.target.value)}
              className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:bg-slate-900"
            >
              <option value="">All Roles</option>
              <option value="promoter">Promoter</option>
              <option value="business head">Business Head</option>
              <option value="manager">Manager</option>
              <option value="team lead">Team Lead</option>
              <option value="user">User</option>
              <option value="hradmin">HR Admin</option>
              <option value="superuseradmin">Superadmin</option>
            </select>
          </div>
          <div>
            <label className="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Status</label>
            <select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:bg-slate-900"
            >
              <option value="">All Status</option>
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
          <div>
            <label className="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Project Name</label>
            <input
              type="text"
              placeholder="Filter by project..."
              value={filterProject}
              onChange={(e) => setFilterProject(e.target.value)}
              className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
            />
          </div>
        </div>
      )}

      {/* Employees Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto w-full">
          <table className="w-full border-collapse text-left">
            <thead>
              <tr className="bg-slate-50/75 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-brand-600 dark:text-brand-400 font-bold text-xs uppercase tracking-wider">
                {visibleCols.id && <th className="px-6 py-4">ID</th>}
                {visibleCols.status && <th className="px-6 py-4">Status</th>}
                {visibleCols.name && <th className="px-6 py-4">Name</th>}
                {visibleCols.email && <th className="px-6 py-4">Email</th>}
                {visibleCols.contact && <th className="px-6 py-4">Contact</th>}
                {visibleCols.salary && <th className="px-6 py-4">CTC</th>}
                {visibleCols.doj && <th className="px-6 py-4">DOJ</th>}
                {visibleCols.role && <th className="px-6 py-4">Role</th>}
                {visibleCols.project && <th className="px-6 py-4">Project</th>}
                {visibleCols.assignee && <th className="px-6 py-4">Assignee</th>}
                {visibleCols.action && <th className="px-6 py-4 text-right">Action</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-sm text-slate-700 dark:text-slate-300">
              {loading ? (
                <tr>
                  <td colSpan="11" className="text-center py-10">
                    <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-brand-500 mx-auto"></div>
                  </td>
                </tr>
              ) : employees.length === 0 ? (
                <tr>
                  <td colSpan="11" className="text-center py-10 text-slate-400 dark:text-slate-500">
                    No employees matching the filters.
                  </td>
                </tr>
              ) : (
                employees.map((emp) => (
                  <tr key={emp._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all">
                    {visibleCols.id && <td className="px-6 py-4 font-semibold text-slate-500 dark:text-slate-400">{emp.employee_id || '-'}</td>}
                    {visibleCols.status && (
                      <td className="px-6 py-4">
                        <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${emp.is_active ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}`}>
                          {emp.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                    )}
                    {visibleCols.name && <td className="px-6 py-4 font-bold text-slate-800 dark:text-white capitalize">{emp.username}</td>}
                    {visibleCols.email && <td className="px-6 py-4">{emp.useremail}</td>}
                    {visibleCols.contact && <td className="px-6 py-4 font-medium">{emp.phonenumber || '-'}</td>}
                    {visibleCols.salary && <td className="px-6 py-4 font-bold">₹{(emp.salary || 0).toLocaleString()}</td>}
                    {visibleCols.doj && <td className="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400">{emp.doj ? emp.doj.split('T')[0] : '-'}</td>}
                    {visibleCols.role && <td className="px-6 py-4 capitalize font-semibold text-brand-600 dark:text-brand-400 text-xs">{emp.user_type}</td>}
                    {visibleCols.project && <td className="px-6 py-4 text-xs font-bold text-indigo-600 dark:text-indigo-400">{emp.project_name || '-'}</td>}
                    {visibleCols.assignee && (
                      <td className="px-6 py-4 text-xs max-w-[120px] truncate">
                        {emp.assign_user && emp.assign_user.length > 0 ? emp.assign_user.join(', ') : 'None'}
                      </td>
                    )}
                    {visibleCols.action && (
                      <td className="px-6 py-4 text-right">
                        <div className="flex gap-2 justify-end">
                          <button
                            onClick={() => handleEdit(emp)}
                            className="p-1.5 hover:bg-brand-50 dark:hover:bg-brand-950/30 text-brand-600 dark:text-brand-400 rounded-lg transition-all"
                          >
                            <Edit3 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(emp._id)}
                            className="p-1.5 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-rose-600 dark:text-rose-400 rounded-lg transition-all"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Paginator */}
        <div className="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap gap-4 items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
          <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span>Show</span>
            <select
              value={limit}
              onChange={(e) => { setLimit(parseInt(e.target.value)); setPage(1); }}
              className="border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 bg-white dark:bg-slate-900 focus:outline-none focus:border-brand-500 text-slate-700 dark:text-white"
            >
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
            <span>entries (Total: {total})</span>
          </div>

          <div className="flex items-center gap-1.5">
            <button
              onClick={() => setPage(Math.max(1, page - 1))}
              disabled={page === 1}
              className="p-2 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-white dark:hover:bg-slate-800 disabled:opacity-50 text-slate-500 dark:text-slate-400"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <span className="px-4 py-1 text-sm font-bold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-950/40 rounded-lg border border-brand-100 dark:border-brand-900/55">
              Page {page} of {totalPages}
            </span>
            <button
              onClick={() => setPage(Math.min(totalPages, page + 1))}
              disabled={page === totalPages}
              className="p-2 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-white dark:hover:bg-slate-800 disabled:opacity-50 text-slate-500 dark:text-slate-400"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Add / Edit Form Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-6 z-50 animate-fade-in">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl flex flex-col">
            <div className="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
              <h3 className="text-slate-800 dark:text-white font-bold text-lg">{isEditing ? 'Edit Employee Record' : 'Add New Employee'}</h3>
              <button onClick={() => setShowModal(false)} className="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-805 rounded-lg text-slate-500 dark:text-slate-400">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-6 flex-1 text-slate-600 dark:text-slate-400">
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Employee Name *</label>
                  <input
                    type="text"
                    required
                    value={form.username}
                    onChange={(e) => setForm({ ...form, username: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Work Email *</label>
                  <input
                    type="email"
                    required
                    value={form.useremail}
                    onChange={(e) => setForm({ ...form, useremail: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Phone Number</label>
                  <input
                    type="text"
                    value={form.phonenumber}
                    onChange={(e) => setForm({ ...form, phonenumber: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Password {isEditing && '(Leave blank to keep)'}</label>
                  <input
                    type="password"
                    required={!isEditing}
                    value={form.epassword}
                    onChange={(e) => setForm({ ...form, epassword: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Monthly CTC</label>
                  <input
                    type="number"
                    value={form.salary}
                    onChange={(e) => setForm({ ...form, salary: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Unique ID (e.g. table name)</label>
                  <input
                    type="text"
                    placeholder="Auto-generated if blank"
                    value={form.tablename}
                    onChange={(e) => setForm({ ...form, tablename: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Employee ID</label>
                  <input
                    type="text"
                    value={form.employee_id}
                    onChange={(e) => setForm({ ...form, employee_id: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Date of Joining</label>
                  <input
                    type="date"
                    value={form.doj}
                    onChange={(e) => setForm({ ...form, doj: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:text-slate-400"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Date of Birth</label>
                  <input
                    type="date"
                    value={form.dob}
                    onChange={(e) => setForm({ ...form, dob: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:text-slate-400"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Project Name</label>
                  <input
                    type="text"
                    value={form.project_name}
                    onChange={(e) => setForm({ ...form, project_name: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Project Type</label>
                  <select
                    value={form.project_type}
                    onChange={(e) => setForm({ ...form, project_type: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:bg-slate-900"
                  >
                    <option value="">Select Project Type</option>
                    <option value="mandate">Mandate</option>
                    <option value="retail">Retail</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Role Type</label>
                  <select
                    value={form.user_type}
                    onChange={(e) => setForm({ ...form, user_type: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:bg-slate-900"
                  >
                    <option value="user">User</option>
                    <option value="promoter">Promoter</option>
                    <option value="business head">Business Head</option>
                    <option value="manager">Manager</option>
                    <option value="team lead">Team Lead</option>
                    <option value="hradmin">HR Admin</option>
                    <option value="superuseradmin">Superadmin</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">City</label>
                  <input
                    type="text"
                    value={form.city}
                    onChange={(e) => setForm({ ...form, city: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 text-xs font-semibold uppercase mb-1">Status</label>
                  <select
                    value={form.is_active ? '1' : '0'}
                    onChange={(e) => setForm({ ...form, is_active: e.target.value === '1' })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:bg-slate-900"
                  >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
              </div>

              {/* Incentive Slab Amounts */}
              <div className="border-t border-slate-100 dark:border-slate-800 pt-6">
                <h4 className="text-slate-800 dark:text-white font-bold text-sm mb-4">Incentive Slabs Configuration</h4>
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                  {['one_amt', 'two_amt', 'thrid_amt', 'forth_amt', 'fifth_amt', 'sixth_amt'].map((slab, index) => (
                    <div key={slab}>
                      <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-semibold uppercase mb-1">{index + 1}st Slab (₹)</label>
                      <input
                        type="number"
                        value={form[slab]}
                        onChange={(e) => setForm({ ...form, [slab]: parseFloat(e.target.value) || 0 })}
                        className="w-full px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs bg-transparent dark:text-white"
                      />
                    </div>
                  ))}
                </div>
              </div>

              {/* Multiselect assigned users */}
              <div className="border-t border-slate-100 dark:border-slate-800 pt-6">
                <label className="block text-slate-800 dark:text-white font-bold text-sm mb-2">Assign Team / Managers</label>
                <div className="border border-slate-200 dark:border-slate-700 rounded-xl p-4 max-h-40 overflow-y-auto grid grid-cols-2 md:grid-cols-3 gap-3">
                  {assignees.map((userItem) => (
                    <button
                      key={userItem.tablename}
                      type="button"
                      onClick={() => toggleAssigneeSelect(userItem.tablename)}
                      className={`p-2.5 rounded-xl border text-xs text-left transition-all font-semibold flex flex-col justify-center ${form.assign_user.includes(userItem.tablename) ? 'bg-indigo-50 dark:bg-indigo-950/30 border-indigo-200 dark:border-indigo-900/50 text-indigo-700 dark:text-indigo-400 shadow-sm shadow-indigo-500/10' : 'border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/30 text-slate-600 dark:text-slate-400'}`}
                    >
                      <span className="font-bold">{userItem.username}</span>
                      <span className="text-xxs uppercase tracking-wider text-slate-400 dark:text-slate-500 mt-0.5">{userItem.user_type}</span>
                    </button>
                  ))}
                </div>
              </div>

              <div className="border-t border-slate-100 dark:border-slate-800 pt-6 flex justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-6 py-2.5 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 text-sm font-semibold transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-6 py-2.5 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-brand-500/10 transition-all"
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

export default Employees;
