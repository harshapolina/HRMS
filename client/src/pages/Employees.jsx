import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import { Edit3, Trash2, Plus, Filter, LayoutGrid, ChevronLeft, ChevronRight, X, AlertCircle } from 'lucide-react';
import io from 'socket.io-client';

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

  const fetchEmployeesRef = useRef(null);

  useEffect(() => {
    fetchEmployeesRef.current = fetchEmployees;
  });

  useEffect(() => {
    const socket = io();
    socket.on('user_update', () => {
      console.log('Real-time user update received. Reloading employees...');
      if (fetchEmployeesRef.current) {
        fetchEmployeesRef.current();
      }
    });

    return () => {
      socket.disconnect();
    };
  }, []);

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
    <div className="page-shell relative text-ink">
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Workforce Directory</p>
          <h1 className="page-title">Employee Registry</h1>
          <p className="page-subtitle">Manage employee profiles, CTC parameters, role assignments, and project details.</p>
        </div>
      </div>

      <div className="stat-grid lg:grid-cols-4">
        <div className="stat-card">
          <span className="stat-card-label">Active Users</span>
          <span className="stat-card-value">{summary.activeCount}</span>
        </div>
        <div className="stat-card">
          <span className="stat-card-label">Inactive Users</span>
          <span className="stat-card-value">{summary.inactiveCount}</span>
        </div>
        <div className="stat-card">
          <span className="stat-card-label">Assigned Users</span>
          <span className="stat-card-value">{summary.assignedCount}</span>
        </div>
        <div className="stat-card">
          <span className="stat-card-label">Monthly Salaries</span>
          <span className="stat-card-value">₹{summary.totalSalary.toLocaleString()}</span>
        </div>
      </div>

      <div className="toolbar">
        <div className="toolbar-left">
          <input
            id="employee-search-input"
            type="text"
            placeholder="Search employees..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="input-field text-sm sm:max-w-xs"
          />
          <button
            id="employee-filter-toggle-btn"
            onClick={() => setShowFilters(!showFilters)}
            className={`filter-btn ${showFilters ? 'filter-btn-active' : ''}`}
          >
            <Filter className="w-5 h-5" />
          </button>
        </div>

        <div className="toolbar-right relative">
          <button
            id="employee-column-toggle-btn"
            onClick={() => setShowColDropdown(!showColDropdown)}
            className="btn-secondary btn-sm"
          >
            <LayoutGrid className="w-4 h-4" /> Columns
          </button>

          {showColDropdown && (
            <div className="absolute right-0 top-full mt-2 card p-4 z-30 w-48 space-y-2">
              {Object.keys(visibleCols).map((col) => (
                <label key={col} className="flex items-center gap-2 text-xs font-semibold text-body cursor-pointer select-none capitalize">
                  <input
                    type="checkbox"
                    checked={visibleCols[col]}
                    onChange={() => setVisibleCols({ ...visibleCols, [col]: !visibleCols[col] })}
                    className="rounded text-ink focus:ring-ink"
                  />
                  {col}
                </label>
              ))}
            </div>
          )}

          <button id="employee-add-btn" onClick={handleCreateNew} className="btn-primary btn-sm">
            <Plus className="w-4 h-4" /> Add Employee
          </button>
        </div>
      </div>

      {showFilters && (
        <div className="filter-panel grid-cols-1 md:grid-cols-3">
          <div>
            <label className="block text-muted text-xs font-semibold uppercase mb-1">Role Type</label>
            <select
              id="filter-role-select"
              value={filterRole}
              onChange={(e) => setFilterRole(e.target.value)}
              className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
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
            <label className="block text-muted text-xs font-semibold uppercase mb-1">Status</label>
            <select
              id="filter-status-select"
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
            >
              <option value="">All Status</option>
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
          <div>
            <label className="block text-muted text-xs font-semibold uppercase mb-1">Project Name</label>
            <input
              id="filter-project-input"
              type="text"
              placeholder="Filter by project..."
              value={filterProject}
              onChange={(e) => setFilterProject(e.target.value)}
              className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
            />
          </div>
        </div>
      )}

      <div className="table-container">
        <div className="overflow-x-auto w-full">
          <table className="table-shell">
            <thead>
              <tr>
                {visibleCols.id && <th>ID</th>}
                {visibleCols.status && <th>Status</th>}
                {visibleCols.name && <th>Name</th>}
                {visibleCols.email && <th>Email</th>}
                {visibleCols.contact && <th>Contact</th>}
                {visibleCols.salary && <th>CTC</th>}
                {visibleCols.doj && <th>DOJ</th>}
                {visibleCols.role && <th>Role</th>}
                {visibleCols.project && <th>Project</th>}
                {visibleCols.assignee && <th>Assignee</th>}
                {visibleCols.action && <th className="text-right">Action</th>}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="11" className="text-center py-10">
                    <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-ink mx-auto"></div>
                  </td>
                </tr>
              ) : employees.length === 0 ? (
                <tr>
                  <td colSpan="11" className="text-center py-10 text-muted">
                    No employees matching the filters.
                  </td>
                </tr>
              ) : (
                employees.map((emp) => (
                  <tr key={emp._id}>
                    {visibleCols.id && <td className="font-semibold text-muted">{emp.employee_id || '-'}</td>}
                    {visibleCols.status && (
                      <td>
                        <span className={emp.is_active ? 'badge-success' : 'badge-neutral'}>
                          {emp.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                    )}
                    {visibleCols.name && <td className="font-semibold text-ink capitalize">{emp.username}</td>}
                    {visibleCols.email && <td>{emp.useremail}</td>}
                    {visibleCols.contact && <td className="font-medium">{emp.phonenumber || '-'}</td>}
                    {visibleCols.salary && <td className="font-semibold">₹{(emp.salary || 0).toLocaleString()}</td>}
                    {visibleCols.doj && <td className="text-xs font-semibold text-muted">{emp.doj ? emp.doj.split('T')[0] : '-'}</td>}
                    {visibleCols.role && <td className="capitalize font-semibold text-ink text-xs">{emp.user_type}</td>}
                    {visibleCols.project && <td className="text-xs font-semibold text-body">{emp.project_name || '-'}</td>}
                    {visibleCols.assignee && (
                      <td className="text-xs max-w-[120px] truncate">
                        {emp.assign_user && emp.assign_user.length > 0 ? emp.assign_user.join(', ') : 'None'}
                      </td>
                    )}
                    {visibleCols.action && (
                      <td className="text-right">
                        <div className="flex gap-2 justify-end">
                          <button onClick={() => handleEdit(emp)} className="btn-icon w-8 h-8">
                            <Edit3 className="w-4 h-4" />
                          </button>
                          <button onClick={() => handleDelete(emp._id)} className="btn-icon-danger w-8 h-8">
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

        <div className="paginator">
          <div className="flex items-center gap-2 text-sm text-muted">
            <span>Show</span>
            <select
              value={limit}
              onChange={(e) => { setLimit(parseInt(e.target.value)); setPage(1); }}
              className="border border-hairline rounded-lg px-2 py-1 bg-canvas focus:outline-none focus:border-ink text-body"
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
              className="p-2 border border-hairline rounded-lg hover:bg-canvas disabled:opacity-50 text-muted"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <span className="px-4 py-1 text-sm font-semibold text-ink bg-surface-soft rounded-lg border border-hairline">
              Page {page} of {totalPages}
            </span>
            <button
              onClick={() => setPage(Math.min(totalPages, page + 1))}
              disabled={page === totalPages}
              className="p-2 border border-hairline rounded-lg hover:bg-canvas disabled:opacity-50 text-muted"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {showModal && (
        <div className="modal-overlay">
          <div className="modal-panel max-w-4xl w-full">
            <div className="modal-header mb-0 pb-4 sticky top-0 bg-canvas z-10">
              <h3 className="text-ink font-semibold text-lg">{isEditing ? 'Edit Employee Record' : 'Add New Employee'}</h3>
              <button onClick={() => setShowModal(false)} className="p-1.5 hover:bg-surface-soft rounded-lg text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-6 flex-1 text-body">
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-username">Employee Name *</label>
                  <input
                    id="form-employee-username"
                    type="text"
                    required
                    value={form.username}
                    onChange={(e) => setForm({ ...form, username: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-email">Work Email *</label>
                  <input
                    id="form-employee-email"
                    type="email"
                    required
                    value={form.useremail}
                    onChange={(e) => setForm({ ...form, useremail: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-phone">Phone Number</label>
                  <input
                    id="form-employee-phone"
                    type="text"
                    value={form.phonenumber}
                    onChange={(e) => setForm({ ...form, phonenumber: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-password">Password {isEditing && '(Leave blank to keep)'}</label>
                  <input
                    id="form-employee-password"
                    type="password"
                    required={!isEditing}
                    value={form.epassword}
                    onChange={(e) => setForm({ ...form, epassword: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-salary">Monthly CTC</label>
                  <input
                    id="form-employee-salary"
                    type="number"
                    value={form.salary}
                    onChange={(e) => setForm({ ...form, salary: parseFloat(e.target.value) || 0 })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-tablename">Unique ID (e.g. table name)</label>
                  <input
                    id="form-employee-tablename"
                    type="text"
                    placeholder="Auto-generated if blank"
                    value={form.tablename}
                    onChange={(e) => setForm({ ...form, tablename: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-id">Employee ID</label>
                  <input
                    id="form-employee-id"
                    type="text"
                    value={form.employee_id}
                    onChange={(e) => setForm({ ...form, employee_id: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-doj">Date of Joining</label>
                  <input
                    id="form-employee-doj"
                    type="date"
                    value={form.doj}
                    onChange={(e) => setForm({ ...form, doj: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-dob">Date of Birth</label>
                  <input
                    id="form-employee-dob"
                    type="date"
                    value={form.dob}
                    onChange={(e) => setForm({ ...form, dob: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-project-name">Project Name</label>
                  <input
                    id="form-employee-project-name"
                    type="text"
                    value={form.project_name}
                    onChange={(e) => setForm({ ...form, project_name: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-project-type">Project Type</label>
                  <select
                    id="form-employee-project-type"
                    value={form.project_type}
                    onChange={(e) => setForm({ ...form, project_type: e.target.value })}
                    className="select-field"
                  >
                    <option value="">Select Project Type</option>
                    <option value="mandate">Mandate</option>
                    <option value="retail">Retail</option>
                  </select>
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-role">Role Type</label>
                  <select
                    id="form-employee-role"
                    value={form.user_type}
                    onChange={(e) => setForm({ ...form, user_type: e.target.value })}
                    className="select-field"
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
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-city">City</label>
                  <input
                    id="form-employee-city"
                    type="text"
                    value={form.city}
                    onChange={(e) => setForm({ ...form, city: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-body text-xs font-semibold uppercase mb-1" htmlFor="form-employee-status">Status</label>
                  <select
                    id="form-employee-status"
                    value={form.is_active ? '1' : '0'}
                    onChange={(e) => setForm({ ...form, is_active: e.target.value === '1' })}
                    className="select-field"
                  >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
              </div>

              {/* Incentive Slab Amounts */}
              <div className="border-t border-hairline-soft pt-6">
                <h4 className="text-ink font-semibold text-sm mb-4">Incentive Slabs Configuration</h4>
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                  {['one_amt', 'two_amt', 'thrid_amt', 'forth_amt', 'fifth_amt', 'sixth_amt'].map((slab, index) => (
                    <div key={slab}>
                      <label className="block text-muted text-[10px] font-semibold uppercase mb-1" htmlFor={`form-employee-slab-${slab}`}>{index + 1}st Slab (₹)</label>
                      <input
                        id={`form-employee-slab-${slab}`}
                        type="number"
                        value={form[slab]}
                        onChange={(e) => setForm({ ...form, [slab]: parseFloat(e.target.value) || 0 })}
                        className="input-field h-8 text-xs px-2"
                      />
                    </div>
                  ))}
                </div>
              </div>

              {/* Multiselect assigned users */}
              <div className="border-t border-hairline-soft pt-6">
                <label className="block text-ink font-semibold text-sm mb-2" id="assign-team-label">Assign Team / Managers</label>
                <div className="border border-hairline rounded-lg p-4 max-h-40 overflow-y-auto grid grid-cols-2 md:grid-cols-3 gap-3">
                  {assignees.map((userItem) => (
                    <button
                      id={`assign-user-btn-${userItem.tablename}`}
                      key={userItem.tablename}
                      type="button"
                      onClick={() => toggleAssigneeSelect(userItem.tablename)}
                      className={`p-2.5 rounded-md border text-xs text-left transition-all font-semibold flex flex-col justify-center ${form.assign_user.includes(userItem.tablename) ? 'bg-canvas text-ink border-ink shadow-card' : 'border-hairline hover:bg-surface-soft text-body'}`}
                    >
                      <span className="font-semibold">{userItem.username}</span>
                      <span className="text-xxs uppercase tracking-wider text-muted mt-0.5">{userItem.user_type}</span>
                    </button>
                  ))}
                </div>
              </div>

              <div className="border-t border-hairline-soft pt-6 flex justify-end gap-3">
                <button
                  id="form-employee-cancel"
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="btn-secondary"
                >
                  Cancel
                </button>
                <button
                  id="form-employee-save"
                  type="submit"
                  className="btn-primary"
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
