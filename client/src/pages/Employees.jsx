import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Edit3, Trash2, Plus, Filter, LayoutGrid, ChevronLeft, ChevronRight, X, AlertCircle, Eye, EyeOff, FileText, Download } from 'lucide-react';
import io from 'socket.io-client';
import FnfSettlementModals from '../components/FnfSettlementModals';

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

  // User 360 Overview Modal State
  const [showOverview, setShowOverview] = useState(false);
  const [selectedEmp, setSelectedEmp] = useState(null);
  const [showPassword, setShowPassword] = useState(false);
  const [activeTab, setActiveTab] = useState('overview');
  const [attendanceLogs, setAttendanceLogs] = useState([]);
  const [payslips, setPayslips] = useState([]);
  const [assignedAssets, setAssignedAssets] = useState([]);
  const [offerLetters, setOfferLetters] = useState([]);
  const [viewingLetter, setViewingLetter] = useState(null);
  const [viewingPayslip, setViewingPayslip] = useState(null);
  const [fnfEmployee, setFnfEmployee] = useState(null);
  const [currentMonth, setCurrentMonth] = useState(new Date().getMonth() + 1);
  const [currentYear, setCurrentYear] = useState(new Date().getFullYear());
  const overviewBodyRef = useRef(null);
  const colDropdownRef = useRef(null);

  const openOverview = (emp) => {
    setSelectedEmp(emp);
    setShowPassword(false);
    setActiveTab('overview');
    setShowOverview(true);

    fetchAttendanceLogs(emp._id);
    fetchUserPayslips(emp._id);
    fetchUserAssets(emp._id);
    fetchUserLetters(emp.useremail);

    requestAnimationFrame(() => {
      overviewBodyRef.current?.scrollTo(0, 0);
    });
  };

  const openOverviewByTablename = (tablename) => {
    const found = employees.find(e => e.tablename === tablename);
    if (found) openOverview(found);
  };

  const fetchAttendanceLogs = async (userId) => {
    try {
      const res = await axios.get('/api/attendance');
      const filtered = res.data.filter(log => (log.user?._id || log.user) === userId);
      setAttendanceLogs(filtered);
    } catch (err) {
      console.error("Error fetching user attendance", err);
    }
  };

  const fetchUserPayslips = async (userId) => {
    try {
      const res = await axios.get(`/api/payroll/user/${userId}`);
      setPayslips(res.data);
    } catch (err) {
      console.error("Error fetching user payslips", err);
    }
  };

  const fetchUserAssets = async (userId) => {
    try {
      const res = await axios.get('/api/assets/assignments');
      const filtered = res.data.filter(as => (as.user?._id || as.user) === userId);
      setAssignedAssets(filtered);
    } catch (err) {
      console.error("Error fetching user assets", err);
    }
  };

  const fetchUserLetters = async (email) => {
    if (!email) return;
    try {
      const res = await axios.get('/api/offers');
      const filtered = res.data.filter(o => o.email?.toLowerCase() === email.toLowerCase());
      setOfferLetters(filtered);
    } catch (err) {
      console.error("Error fetching user letters", err);
    }
  };

  const handleReturnAssetFromDrawer = async (assignmentId) => {
    if (!window.confirm("Are you sure you want to mark this asset as returned?")) return;
    try {
      await axios.post(`/api/assets/return/${assignmentId}`);
      alert("Asset returned successfully!");
      if (selectedEmp) {
        fetchUserAssets(selectedEmp._id);
      }
    } catch (err) {
      alert("Failed to return asset: " + (err.response?.data?.message || err.message));
    }
  };

  const toggleUserActivation = async (emp, currentStatus) => {
    try {
      const newStatus = !currentStatus;
      await axios.put(`/api/users/${emp._id}`, { is_active: newStatus });
      setSelectedEmp({ ...emp, is_active: newStatus });
      fetchEmployees();
    } catch (err) {
      alert("Failed to toggle status: " + (err.response?.data?.message || err.message));
    }
  };

  const handleEditFromDrawer = () => {
    setShowOverview(false);
    handleEdit(selectedEmp);
  };

  const getSundaysCountForMonth = (monthYearStr) => {
    if (!monthYearStr) return 4;
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const parts = monthYearStr.split(' ');
    if (parts.length !== 2) return 4;
    const monthIndex = monthNames.indexOf(parts[0]);
    const year = parseInt(parts[1], 10);
    if (monthIndex === -1 || isNaN(year)) return 4;
    
    let count = 0;
    const date = new Date(year, monthIndex, 1);
    while (date.getMonth() === monthIndex) {
      if (date.getDay() === 0) count++;
      date.setDate(date.getDate() + 1);
    }
    return count;
  };

  const getAttendanceStats = () => {
    const monthStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}`;
    const monthlyLogs = attendanceLogs.filter(log => log.date && log.date.startsWith(monthStr));
    
    const present = monthlyLogs.filter(log => log.status === 'Present').length;
    const late = monthlyLogs.filter(log => log.status === 'Late').length;
    const absent = monthlyLogs.filter(log => log.status === 'Absent').length;
    const leaves = monthlyLogs.filter(log => log.status === 'Leave').length; 
    
    return { present, late, absent, leaves, monthlyLogs };
  };

  const renderAttendanceCalendar = () => {
    const { monthlyLogs } = getAttendanceStats();
    const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
    const firstDayIndex = new Date(currentYear, currentMonth - 1, 1).getDay();
    
    const days = [];
    
    for (let i = 0; i < firstDayIndex; i++) {
      days.push(<div key={`pad-${i}`} className="aspect-square border border-hairline-soft/30 bg-surface-soft/20 rounded-lg" />);
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
      const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const log = monthlyLogs.find(l => l.date === dateStr);
      
      let statusClass = 'bg-canvas text-ink border border-hairline hover:bg-surface-soft';
      let indicator = null;
      const isSunday = new Date(currentYear, currentMonth - 1, day).getDay() === 0;
      
      if (log) {
        if (log.status === 'Present') {
          statusClass = 'bg-success/10 text-success border border-success/20 font-bold';
          indicator = <span className="w-1.5 h-1.5 rounded-full bg-success mt-1" />;
        } else if (log.status === 'Late') {
          statusClass = 'bg-warning/10 text-warning border border-warning/20 font-bold';
          indicator = <span className="w-1.5 h-1.5 rounded-full bg-warning mt-1" />;
        } else if (log.status === 'Absent') {
          statusClass = 'bg-error/10 text-error border border-error/20 font-bold';
          indicator = <span className="w-1.5 h-1.5 rounded-full bg-error mt-1" />;
        }
      } else if (isSunday) {
        statusClass = 'bg-primary/5 text-primary/75 border border-primary/10 font-bold';
      }
      
      days.push(
        <div 
          key={`day-${day}`} 
          className={`aspect-square flex flex-col items-center justify-center p-1 rounded-lg text-xxs font-semibold cursor-default transition-all ${statusClass}`}
          title={log ? `${log.status} (In: ${log.punchIn ? new Date(log.punchIn).toLocaleTimeString() : '-'}, Out: ${log.punchOut ? new Date(log.punchOut).toLocaleTimeString() : '-'})` : (isSunday ? 'Weekly Off (Sunday)' : 'No Record')}
        >
          <span>{day}</span>
          {indicator}
        </div>
      );
    }
    
    return (
      <div className="space-y-3">
        <div className="flex justify-between items-center bg-surface-soft p-2.5 rounded-lg border border-hairline">
          <button 
            type="button"
            onClick={() => {
              if (currentMonth === 1) {
                setCurrentMonth(12);
                setCurrentYear(currentYear - 1);
              } else {
                setCurrentMonth(currentMonth - 1);
              }
            }}
            className="btn-secondary btn-sm h-7 px-2"
          >
            Prev
          </button>
          <span className="font-display font-bold text-xs text-ink">
            {new Date(currentYear, currentMonth - 1).toLocaleString('default', { month: 'long', year: 'numeric' })}
          </span>
          <button 
            type="button"
            onClick={() => {
              if (currentMonth === 12) {
                setCurrentMonth(1);
                setCurrentYear(currentYear + 1);
              } else {
                setCurrentMonth(currentMonth + 1);
              }
            }}
            className="btn-secondary btn-sm h-7 px-2"
          >
            Next
          </button>
        </div>
        
        <div className="grid grid-cols-7 gap-1 text-center font-bold text-[9px] text-muted uppercase tracking-wider mb-1">
          <div>Sun</div>
          <div>Mon</div>
          <div>Tue</div>
          <div>Wed</div>
          <div>Thu</div>
          <div>Fri</div>
          <div>Sat</div>
        </div>
        
        <div className="grid grid-cols-7 gap-1">
          {days}
        </div>
      </div>
    );
  };

  const renderSalaryStructure = (emp) => {
    if (!emp) return null;
    
    const monthlyCTC = parseFloat(emp.salary) || 0;
    const yearlyCTC = monthlyCTC * 12;
    
    const hasManual = (
      (emp.one_amt && parseFloat(emp.one_amt) > 0) ||
      (emp.two_amt && parseFloat(emp.two_amt) > 0) ||
      (emp.thrid_amt && parseFloat(emp.thrid_amt) > 0) ||
      (emp.forth_amt && parseFloat(emp.forth_amt) > 0) ||
      (emp.fifth_amt && parseFloat(emp.fifth_amt) > 0) ||
      (emp.sixth_amt && parseFloat(emp.sixth_amt) > 0)
    );
    
    const basic = hasManual ? (parseFloat(emp.one_amt) || 0) : Math.round(monthlyCTC * 0.5);
    const hra = hasManual ? (parseFloat(emp.two_amt) || 0) : Math.round(monthlyCTC * 0.2);
    const conveyance = hasManual ? (parseFloat(emp.thrid_amt) || 0) : Math.round(monthlyCTC * 0.07);
    const pfEmployer = hasManual ? (parseFloat(emp.fifth_amt) || 0) : Math.min(1800, Math.round(basic * 0.12));
    const monthlyGross = monthlyCTC - pfEmployer;
    const specialAllowance = hasManual ? (parseFloat(emp.forth_amt) || 0) : (monthlyGross - (basic + hra + conveyance));
    const totalDeds = hasManual ? (parseFloat(emp.sixth_amt) || 0) : (pfEmployer + 200 + 817);
    const netPay = monthlyGross - totalDeds;
    
    const fmt = (n) => '₹' + Number(n).toLocaleString('en-IN');
    
    return (
      <div className="overflow-x-auto">
        <table className="w-full text-xs text-left border-collapse text-body">
          <thead>
            <tr className="border-b border-hairline bg-surface-soft text-muted text-[10px] font-semibold uppercase">
              <th className="px-4 py-2">Earnings & Benefits</th>
              <th className="px-4 py-2 text-right">Monthly</th>
              <th className="px-4 py-2 text-right">Yearly</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-hairline-soft font-semibold text-ink">
            <tr className="bg-surface-soft/50 font-bold">
              <td className="px-4 py-2.5">Total CTC</td>
              <td className="px-4 py-2.5 text-right">{fmt(monthlyCTC)}</td>
              <td className="px-4 py-2.5 text-right">{fmt(yearlyCTC)}</td>
            </tr>
            <tr>
              <td className="px-4 py-2 font-normal text-body">Basic Salary</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(basic)}</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(basic * 12)}</td>
            </tr>
            <tr>
              <td className="px-4 py-2 font-normal text-body">HRA</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(hra)}</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(hra * 12)}</td>
            </tr>
            <tr>
              <td className="px-4 py-2 font-normal text-body">Conveyance</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(conveyance)}</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(conveyance * 12)}</td>
            </tr>
            <tr>
              <td className="px-4 py-2 font-normal text-body">Special Allowance</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(specialAllowance)}</td>
              <td className="px-4 py-2 text-right font-normal">{fmt(specialAllowance * 12)}</td>
            </tr>
            <tr className="bg-primary/5 text-primary">
              <td className="px-4 py-2">PF (Employer)</td>
              <td className="px-4 py-2 text-right">{fmt(pfEmployer)}</td>
              <td className="px-4 py-2 text-right">{fmt(pfEmployer * 12)}</td>
            </tr>
            <tr className="font-bold border-t border-hairline">
              <td className="px-4 py-2.5">Monthly Gross</td>
              <td className="px-4 py-2.5 text-right">{fmt(monthlyGross)}</td>
              <td className="px-4 py-2.5 text-right">{fmt(monthlyGross * 12)}</td>
            </tr>
            <tr className="text-error bg-error/5">
              <td className="px-4 py-2">Total Deductions</td>
              <td className="px-4 py-2 text-right">{fmt(totalDeds)}</td>
              <td className="px-4 py-2 text-right">{fmt(totalDeds * 12)}</td>
            </tr>
            <tr className="bg-success/5 text-success font-bold text-sm border-t-2 border-success/30">
              <td className="px-4 py-2.5">Net Pay</td>
              <td className="px-4 py-2.5 text-right">{fmt(netPay)}</td>
              <td className="px-4 py-2.5 text-right">{fmt(netPay * 12)}</td>
            </tr>
          </tbody>
        </table>
      </div>
    );
  };

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
  const [statCardFilter, setStatCardFilter] = useState('');

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
  }, [page, limit, search, filterRole, filterStatus, filterProject, statCardFilter]);

  useEffect(() => {
    const isModalOpen = showOverview || showModal || Boolean(viewingLetter) || Boolean(viewingPayslip) || Boolean(fnfEmployee);
    const mainEl = document.querySelector('main');

    if (isModalOpen) {
      document.body.classList.add('modal-scroll-lock');
      mainEl?.classList.add('modal-scroll-lock');
    } else {
      document.body.classList.remove('modal-scroll-lock');
      mainEl?.classList.remove('modal-scroll-lock');
    }

    return () => {
      document.body.classList.remove('modal-scroll-lock');
      mainEl?.classList.remove('modal-scroll-lock');
    };
  }, [showOverview, showModal, viewingLetter, viewingPayslip, fnfEmployee]);

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (colDropdownRef.current && !colDropdownRef.current.contains(e.target)) {
        setShowColDropdown(false);
      }
    };
    if (showColDropdown) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [showColDropdown]);

  const fetchEmployees = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/users', {
        params: {
          page,
          limit,
          search,
          role: filterRole,
          status: statCardFilter === 'assigned' || statCardFilter === 'salary' ? '' : filterStatus,
          project: filterProject,
          assigned: statCardFilter === 'assigned' ? '1' : '',
          hasSalary: statCardFilter === 'salary' ? '1' : ''
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

  const handleStatCardClick = (type) => {
    setPage(1);
    if (statCardFilter === type) {
      setStatCardFilter('');
      setFilterStatus('');
      return;
    }

    setStatCardFilter(type);
    if (type === 'active') setFilterStatus('1');
    else if (type === 'inactive') setFilterStatus('0');
    else setFilterStatus('');
  };

  return (
    <>
    <div className="page-shell relative text-ink">
      <div className="stat-grid lg:grid-cols-4">
        <button
          type="button"
          onClick={() => handleStatCardClick('active')}
          className={`stat-card-btn ${statCardFilter === 'active' ? 'stat-card-btn-active' : ''}`}
        >
          <span className="stat-card-label">Active Users</span>
          <span className="stat-card-value">{summary.activeCount}</span>
        </button>
        <button
          type="button"
          onClick={() => handleStatCardClick('inactive')}
          className={`stat-card-btn ${statCardFilter === 'inactive' ? 'stat-card-btn-active' : ''}`}
        >
          <span className="stat-card-label">Inactive Users</span>
          <span className="stat-card-value">{summary.inactiveCount}</span>
        </button>
        <button
          type="button"
          onClick={() => handleStatCardClick('assigned')}
          className={`stat-card-btn ${statCardFilter === 'assigned' ? 'stat-card-btn-active' : ''}`}
        >
          <span className="stat-card-label">Assigned Users</span>
          <span className="stat-card-value">{summary.assignedCount}</span>
        </button>
        <button
          type="button"
          onClick={() => handleStatCardClick('salary')}
          className={`stat-card-btn ${statCardFilter === 'salary' ? 'stat-card-btn-active' : ''}`}
        >
          <span className="stat-card-label">Monthly Salaries</span>
          <span className="stat-card-value">₹{summary.totalSalary.toLocaleString()}</span>
        </button>
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

        <div className="toolbar-right relative" ref={colDropdownRef}>
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
              onChange={(e) => { setFilterStatus(e.target.value); setStatCardFilter(''); setPage(1); }}
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
                    {visibleCols.name && (
                      <td 
                        onClick={() => openOverview(emp)}
                        className="font-semibold text-primary capitalize cursor-pointer hover:underline"
                      >
                        {emp.username}
                      </td>
                    )}
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
                          {!emp.is_active && (
                            <button
                              onClick={() => setFnfEmployee(emp)}
                              className="btn-icon w-8 h-8"
                              title="FNF Settlement"
                            >
                              <FileText className="w-4 h-4" />
                            </button>
                          )}
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
    </div>

    {createPortal(
      <>
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
      {/* User 360 Overview Popup */}
      {showOverview && selectedEmp && (
        <div className="modal-overlay" onClick={() => setShowOverview(false)}>
          <div className="modal-popup" onClick={(e) => e.stopPropagation()}>
            {/* Header */}
            <div className="modal-popup-header">
              <div className="flex items-center gap-4 min-w-0">
                <div className="w-12 h-12 rounded-full bg-surface-soft flex items-center justify-center border-2 border-ink text-xl font-bold text-ink shrink-0">
                  {selectedEmp.username ? selectedEmp.username.charAt(0).toUpperCase() : '?'}
                </div>
                <div className="min-w-0">
                  <h3 className="font-display font-semibold text-base leading-tight capitalize text-ink truncate">{selectedEmp.username}</h3>
                  <div className="flex items-center gap-2.5 mt-1.5 h-6">
                    <span className="text-[11px] text-muted font-semibold uppercase tracking-wider shrink-0">Status</span>
                    <label className="inline-flex shrink-0">
                      <input
                        type="checkbox"
                        checked={selectedEmp.is_active}
                        onChange={() => toggleUserActivation(selectedEmp, selectedEmp.is_active)}
                        className="toggle-switch-input sr-only"
                      />
                      <span className="toggle-switch" aria-hidden="true">
                        <span className="toggle-switch-knob" />
                      </span>
                    </label>
                    <span className={`status-badge-fixed ${selectedEmp.is_active ? 'badge-success' : 'badge-neutral'}`}>
                      {selectedEmp.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </div>
                </div>
              </div>
              <button
                id="close-overview-btn"
                onClick={() => setShowOverview(false)}
                className="p-1.5 hover:bg-surface-soft rounded-lg text-muted transition-colors shrink-0 ml-4"
                aria-label="Close Overview"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Tabs */}
            <div className="modal-popup-tabs">
              {['overview', 'attendance', 'payroll', 'assets'].map((tab) => (
                <button
                  key={tab}
                  type="button"
                  onClick={() => setActiveTab(tab)}
                  className={`modal-popup-tab ${activeTab === tab ? 'modal-popup-tab-active' : 'modal-popup-tab-inactive'}`}
                >
                  {tab === 'assets' ? 'Assets & Docs' : tab}
                </button>
              ))}
            </div>

            {/* Body */}
            <div ref={overviewBodyRef} className="modal-panel-body p-6 space-y-6 text-body">
              {activeTab === 'overview' && (
                <div className="space-y-6">
                  {/* Basic Information */}
                  <div>
                    <h4 className="modal-section-title">Basic Information</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Email</span>
                        <span className="font-semibold text-ink break-all text-xs">{selectedEmp.useremail || '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Contact No</span>
                        <span className="font-semibold text-ink text-xs">{selectedEmp.phonenumber || '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Date of Birth</span>
                        <span className="font-semibold text-ink text-xs">{selectedEmp.dob ? selectedEmp.dob.split('T')[0] : '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Unique ID</span>
                        <span className="font-semibold text-ink text-xs">{selectedEmp.tablename || '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Employee ID</span>
                        <span className="font-semibold text-ink text-xs">{selectedEmp.employee_id || '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft flex flex-col justify-between">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Password</span>
                        <div className="flex items-center justify-between gap-2">
                          <span className="font-semibold text-ink text-xs select-all truncate">
                            {showPassword ? (selectedEmp.epassword || '---') : '••••••••'}
                          </span>
                          <button 
                            id="toggle-pwd-btn"
                            type="button" 
                            onClick={() => setShowPassword(!showPassword)}
                            className="text-muted hover:text-ink transition-colors focus:outline-none"
                          >
                            {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                          </button>
                        </div>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Date of Joining</span>
                        <span className="font-semibold text-ink text-xs">{selectedEmp.doj ? selectedEmp.doj.split('T')[0] : '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Monthly CTC</span>
                        <span className="font-semibold text-ink text-xs">₹{(selectedEmp.salary || 0).toLocaleString()}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">
                          {selectedEmp.is_active ? 'Created At' : 'Inactive At'}
                        </span>
                        <span className="font-semibold text-ink text-xs">
                          {selectedEmp.is_active 
                            ? (selectedEmp.createdAt ? selectedEmp.createdAt.split('T')[0] : '---')
                            : (selectedEmp.deactivated_at ? selectedEmp.deactivated_at.split('T')[0] : '---')
                          }
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Project Details */}
                  <div>
                    <h4 className="modal-section-title">Project Details</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Project Name</span>
                        <span className="font-semibold text-ink text-xs">{selectedEmp.project_name || 'Unassigned'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Project Type</span>
                        <span className="font-semibold text-ink text-xs capitalize">{selectedEmp.project_type || '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Role Type</span>
                        <span className="font-semibold text-ink text-xs capitalize">{selectedEmp.user_type || '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">City</span>
                        <span className="font-semibold text-ink text-xs">{selectedEmp.city || '---'}</span>
                      </div>
                      <div className="bg-surface-soft/40 p-3 rounded-lg border border-hairline-soft sm:col-span-2">
                        <span className="block text-muted text-[10px] font-semibold uppercase tracking-wider mb-1">Assigned Users</span>
                        <div className="flex flex-wrap gap-1.5 mt-1">
                          {selectedEmp.assign_user && selectedEmp.assign_user.length > 0 ? (
                            selectedEmp.assign_user.map(uname => (
                              <button
                                key={uname}
                                type="button"
                                onClick={() => openOverviewByTablename(uname)}
                                className="text-ink hover:underline text-[10px] font-semibold bg-surface-soft px-2 py-0.5 rounded border border-hairline hover:border-ink transition-all text-left"
                              >
                                {uname}
                              </button>
                            ))
                          ) : (
                            <span className="font-semibold text-ink text-xs">---</span>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Actions & Insights */}
                  <div className="pt-4 border-t border-hairline flex gap-3">
                    <button 
                      type="button"
                      onClick={handleEditFromDrawer}
                      className="btn-primary flex-1 justify-center h-9 text-xs"
                    >
                      Edit Employee Profile
                    </button>
                  </div>

                  <div className="pt-4 border-t border-hairline-soft border-dashed">
                    <h5 className="text-ink font-semibold text-[10px] uppercase tracking-wider mb-2">HR Suggestions</h5>
                    <ul className="list-disc pl-4 space-y-1.5 text-xs text-muted font-medium">
                      <li><strong className="text-body">Activity Timeline:</strong> Track when this user logged in, completed tasks, or applied for leave.</li>
                      <li><strong className="text-body">Performance Notes:</strong> Add a private section here to drop notes about performance or warnings.</li>
                    </ul>
                  </div>
                </div>
              )}

              {activeTab === 'attendance' && (
                <div className="space-y-6">
                  <h4 className="modal-section-title">Monthly Attendance Summary</h4>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div className="border-l-4 border-success bg-success/5 p-3 rounded-r-lg border border-hairline border-l-0">
                      <span className="block text-[9px] font-semibold text-muted uppercase tracking-wider">Days Present</span>
                      <span className="block text-base font-bold text-success mt-1">{getAttendanceStats().present}</span>
                    </div>
                    <div className="border-l-4 border-error bg-error/5 p-3 rounded-r-lg border border-hairline border-l-0">
                      <span className="block text-[9px] font-semibold text-muted uppercase tracking-wider">Days Absent</span>
                      <span className="block text-base font-bold text-error mt-1">{getAttendanceStats().absent}</span>
                    </div>
                    <div className="border-l-4 border-warning bg-warning/5 p-3 rounded-r-lg border border-hairline border-l-0">
                      <span className="block text-[9px] font-semibold text-muted uppercase tracking-wider">Days Late</span>
                      <span className="block text-base font-bold text-warning mt-1">{getAttendanceStats().late}</span>
                    </div>
                    <div className="border-l-4 border-primary bg-primary/5 p-3 rounded-r-lg border border-hairline border-l-0">
                      <span className="block text-[9px] font-semibold text-muted uppercase tracking-wider">Leaves Taken</span>
                      <span className="block text-base font-bold text-primary mt-1">{getAttendanceStats().leaves}</span>
                    </div>
                  </div>

                  <div className="border border-hairline p-4 rounded-lg bg-canvas mt-4">
                    <h5 className="font-semibold text-ink text-xs uppercase tracking-wider mb-3">Attendance History Grid</h5>
                    {renderAttendanceCalendar()}
                  </div>
                </div>
              )}

              {activeTab === 'payroll' && (
                <div className="space-y-6">
                  {/* Salary Structure (CTC) */}
                  <div>
                    <h4 className="modal-section-title">Salary Structure (CTC)</h4>
                    <div className="border border-hairline rounded-lg overflow-hidden bg-canvas shadow-sm">
                      {renderSalaryStructure(selectedEmp)}
                    </div>
                  </div>

                  {/* Processed Payslips History */}
                  <div>
                    <div className="flex justify-between items-center mb-3">
                      <h4 className="modal-section-title mb-0">Recent Processed Payslips</h4>
                    </div>
                    <div className="border border-hairline rounded-lg overflow-hidden bg-canvas">
                      <table className="w-full text-xs text-left border-collapse text-body">
                        <thead>
                          <tr className="border-b border-hairline bg-surface-soft text-muted text-[10px] font-semibold uppercase">
                            <th className="px-4 py-2">Month/Year</th>
                            <th className="px-4 py-2 text-right">Net Pay</th>
                            <th className="px-4 py-2 text-right">Actions</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-hairline-soft font-semibold text-ink">
                          {payslips.map(ps => (
                            <tr key={ps._id} className="hover:bg-surface-soft/50">
                              <td className="px-4 py-3">{ps.monthYear}</td>
                              <td className="px-4 py-3 text-right">₹{ps.netSalary.toLocaleString()}</td>
                              <td className="px-4 py-3 text-right">
                                <button 
                                  type="button"
                                  onClick={() => setViewingPayslip(ps)}
                                  className="text-ink hover:underline text-xs font-semibold"
                                >
                                  View Payslip
                                </button>
                              </td>
                            </tr>
                          ))}
                          {payslips.length === 0 && (
                            <tr>
                              <td colSpan="3" className="px-4 py-6 text-center text-muted">No processed payslips found.</td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'assets' && (
                <div className="space-y-6">
                  {/* Company Checked Out Devices */}
                  <div>
                    <h4 className="modal-section-title">Company Checked Out Hardware</h4>
                    <div className="border border-hairline rounded-lg overflow-hidden bg-canvas">
                      <table className="w-full text-xs text-left border-collapse text-body">
                        <thead>
                          <tr className="border-b border-hairline bg-surface-soft text-muted text-[10px] font-semibold uppercase">
                            <th className="px-4 py-2">Hardware Details</th>
                            <th className="px-4 py-2">Serial Number</th>
                            <th className="px-4 py-2 text-right">Action</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-hairline-soft font-semibold text-ink">
                          {assignedAssets.map(as => (
                            <tr key={as._id} className="hover:bg-surface-soft/50">
                              <td className="px-4 py-3">
                                <span className="block font-semibold text-ink">{as.asset?.name}</span>
                                <span className="block text-[10px] text-muted font-normal">{as.asset?.type}</span>
                              </td>
                              <td className="px-4 py-3 font-mono font-medium">{as.asset?.serialNumber}</td>
                              <td className="px-4 py-3 text-right">
                                <button 
                                  type="button"
                                  onClick={() => handleReturnAssetFromDrawer(as._id)}
                                  className="text-error hover:underline text-xs font-semibold"
                                >
                                  Return Device
                                </button>
                              </td>
                            </tr>
                          ))}
                          {assignedAssets.length === 0 && (
                            <tr>
                              <td colSpan="3" className="px-4 py-6 text-center text-muted">No devices checked out.</td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  {/* Documents Management */}
                  <div>
                    <h4 className="modal-section-title">Official Documents</h4>
                    <div className="border border-hairline rounded-lg overflow-hidden bg-canvas">
                      <div className="p-4 space-y-4">
                        <div className="flex justify-between items-center">
                          <div>
                            <span className="block font-semibold text-ink text-xs">Employment Offer Letter</span>
                            <span className="text-[10px] text-muted">
                              Status: {offerLetters.length > 0 ? (
                                <span className="text-success font-semibold">Generated</span>
                              ) : (
                                <span className="text-muted">Not Generated</span>
                              )}
                            </span>
                          </div>
                          <div className="flex gap-2">
                            {offerLetters.length > 0 ? (
                              <>
                                <button 
                                  type="button"
                                  onClick={() => setViewingLetter(offerLetters[0])}
                                  className="btn-secondary btn-sm h-8"
                                >
                                  View
                                </button>
                                <button 
                                  type="button"
                                  onClick={() => {
                                    if (window.confirm(`Email this document to ${selectedEmp.useremail}?`)) {
                                      axios.post(`/api/offers/${offerLetters[0]._id}/send`)
                                        .then(() => alert("Email queued successfully!"))
                                        .catch(err => alert("Failed to send: " + err.message));
                                    }
                                  }}
                                  className="btn-secondary btn-sm h-8"
                                >
                                  Mail
                                </button>
                              </>
                            ) : (
                              <button 
                                type="button"
                                onClick={async () => {
                                  try {
                                    const newLetter = {
                                      candidateName: selectedEmp.username,
                                      email: selectedEmp.useremail,
                                      phone: selectedEmp.phonenumber || '0000000000',
                                      position: selectedEmp.user_type,
                                      monthlySalary: selectedEmp.salary || 0,
                                      joiningDate: selectedEmp.doj || new Date(),
                                      reportingManager: 'HR Manager'
                                    };
                                    await axios.post('/api/offers', newLetter);
                                    alert("Offer Letter template generated!");
                                    fetchUserLetters(selectedEmp.useremail);
                                  } catch (err) {
                                    alert("Generation failed: " + err.message);
                                  }
                                }}
                                className="btn-primary btn-sm h-8"
                              >
                                Generate Template
                              </button>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {fnfEmployee && (
        <FnfSettlementModals
          employee={fnfEmployee}
          onClose={() => setFnfEmployee(null)}
          onRefresh={fetchEmployees}
          setViewingPayslip={setViewingPayslip}
        />
      )}

      {/* Viewing Letter Modal */}
      {viewingLetter && (
        <div className="modal-overlay modal-overlay-nested">
          <div className="modal-panel max-w-4xl w-full">
            <div className="modal-header mb-0 pb-4 sticky top-0 bg-canvas z-10">
              <h3 className="text-ink font-semibold text-sm">Document Preview</h3>
              <div className="flex gap-2">
                <button 
                  onClick={() => {
                    const win = window.open("", "_blank");
                    win.document.write(viewingLetter.customHtml);
                    win.document.close();
                    win.print();
                  }}
                  className="btn-primary btn-sm"
                >
                  Print PDF
                </button>
                <button onClick={() => setViewingLetter(null)} className="p-1.5 hover:bg-surface-soft rounded-lg text-muted">
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>
            <div className="p-6 bg-white border border-hairline rounded-lg overflow-y-auto max-h-[70vh] mt-4">
              <div dangerouslySetInnerHTML={{ __html: viewingLetter.customHtml }} />
            </div>
          </div>
        </div>
      )}

      {/* Viewing Payslip Modal */}
      {viewingPayslip && (() => {
        const sundaysVal = viewingPayslip.payslipData?.sundaysCount ?? getSundaysCountForMonth(viewingPayslip.monthYear);
        const workingDaysVal = viewingPayslip.payslipData?.workingDays ?? (viewingPayslip.totalDays - sundaysVal);
        const customDeductionsSum = (viewingPayslip.payslipData?.deductions?.custom || []).reduce((acc, d) => acc + (d.amount || 0), 0);
        const lopDeductionVal = viewingPayslip.payslipData?.deductions?.lopDeduction ?? (
          (viewingPayslip.payslipData?.deductions?.total ?? 0) -
          (viewingPayslip.payslipData?.deductions?.pfEmployee ?? 0) -
          (viewingPayslip.payslipData?.deductions?.professionalTax ?? 0) -
          (viewingPayslip.payslipData?.deductions?.medical ?? 0) -
          customDeductionsSum
        );

        return (
          <div className="modal-overlay modal-overlay-nested" onClick={() => setViewingPayslip(null)}>
            <div className="modal-popup max-w-2xl" onClick={(e) => e.stopPropagation()}>
              {/* Header */}
              <div className="modal-popup-header bg-primary text-white flex justify-between items-center px-6 py-4 shrink-0 rounded-t-2xl">
                <div className="flex items-center gap-2">
                  <FileText className="w-5 h-5 text-white" />
                  <h3 className="font-semibold text-white text-sm">Payslip Preview</h3>
                </div>
                <button
                  onClick={() => setViewingPayslip(null)}
                  className="p-1.5 hover:bg-white/10 rounded-lg text-white/80 hover:text-white transition-colors shrink-0"
                  aria-label="Close Preview"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              {/* Body */}
              <div className="modal-panel-body p-6 space-y-6 text-body">
                {/* Employee Details Grid */}
                <div className="border border-hairline rounded-lg overflow-hidden bg-canvas">
                  <table className="w-full text-xs text-left border-collapse border-spacing-0">
                    <tbody>
                      <tr className="border-b border-hairline">
                        <td className="px-4 py-3 border-r border-hairline w-1/2">
                          <span className="font-semibold text-muted">Employee Name:</span> <span className="font-bold text-ink capitalize ml-1">{viewingPayslip.user?.username || viewingPayslip.employeeName || 'Deleted Employee'}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Designation:</span> <span className="font-bold text-ink capitalize ml-1">{viewingPayslip.user?.user_type || viewingPayslip.userType || 'User'}</span>
                        </td>
                      </tr>
                      <tr className="border-b border-hairline">
                        <td className="px-4 py-3 border-r border-hairline">
                          <span className="font-semibold text-muted">Working Days:</span> <span className="font-bold text-ink ml-1">{workingDaysVal}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Loss of Pay (Days):</span> <span className="font-bold text-ink text-error ml-1">{viewingPayslip.payslipData?.lopDays ?? 0}</span>
                        </td>
                      </tr>
                      <tr>
                        <td colSpan="2" className="px-4 py-3">
                          <span className="font-semibold text-muted">Calendar:</span> <span className="font-bold text-ink ml-1">{viewingPayslip.totalDays} days</span>
                          <span className="text-muted mx-2">|</span>
                          <span className="font-semibold text-muted">Sundays (excluded):</span> <span className="font-bold text-ink ml-1">{sundaysVal}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                {/* Earnings & Deductions Tables */}
                {viewingPayslip.payslipData && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Earnings */}
                    <div>
                      <h4 className="text-xs font-bold text-primary tracking-wider uppercase mb-3">Earnings</h4>
                      <div className="border border-hairline rounded-lg overflow-hidden">
                        <table className="w-full text-xs text-left">
                          <tbody className="divide-y divide-hairline">
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Basic</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.basic.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">HRA</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.hra.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Conveyance</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.conveyance.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Special Allowance</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.special.toLocaleString()}</td>
                            </tr>
                            <tr className="bg-surface-soft font-bold text-ink border-t border-hairline">
                              <td className="px-4 py-3">Total Earnings:</td>
                              <td className="px-4 py-3 text-right">₹{viewingPayslip.payslipData.earnings.gross.toLocaleString()}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    {/* Deductions */}
                    <div>
                      <h4 className="text-xs font-bold text-primary tracking-wider uppercase mb-3">Deductions</h4>
                      <div className="border border-hairline rounded-lg overflow-hidden">
                        <table className="w-full text-xs text-left">
                          <tbody className="divide-y divide-hairline">
                            <tr>
                              <td className="px-4 py-2.5 text-muted">PF</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Professional Tax</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingPayslip.payslipData.deductions.professionalTax.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Medical Benefit</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingPayslip.payslipData.deductions.medical.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">LOP Deduction</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{lopDeductionVal.toLocaleString()}</td>
                            </tr>
                            {viewingPayslip.payslipData.deductions.custom && viewingPayslip.payslipData.deductions.custom.map((cust, idx) => (
                              <tr key={idx}>
                                <td className="px-4 py-2.5 text-muted">{cust.name}</td>
                                <td className="px-4 py-2.5 text-right font-semibold text-error">₹{cust.amount.toLocaleString()}</td>
                              </tr>
                            ))}
                            <tr className="bg-surface-soft font-bold text-error border-t border-hairline">
                              <td className="px-4 py-3">Total Deductions:</td>
                              <td className="px-4 py-3 text-right">₹{viewingPayslip.payslipData.deductions.total.toLocaleString()}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                )}

                {/* Net Payout Row & Close / Download PDF Buttons */}
                <div className="flex justify-between items-center border-t border-hairline pt-6 mt-4">
                  <div>
                    <span className="text-[10px] font-semibold text-muted uppercase tracking-wider block">Net Take Home Pay</span>
                    <span className={`text-xl font-bold block mt-0.5 ${viewingPayslip.netSalary >= 0 ? 'text-success' : 'text-error'}`}>
                      {viewingPayslip.netSalary < 0 ? '-' : ''}₹{Math.abs(viewingPayslip.netSalary).toLocaleString()}
                    </span>
                  </div>
                  <div className="flex gap-3">
                    <button
                      onClick={() => setViewingPayslip(null)}
                      className="btn-secondary h-9 px-5 text-xs bg-gray-500 hover:bg-gray-600 active:bg-gray-700 text-white border-none"
                    >
                      Close
                    </button>
                    <button
                      onClick={() => window.print()}
                      className="btn-primary h-9 px-5 text-xs flex items-center gap-1.5"
                    >
                      <Download className="w-3.5 h-3.5" /> Download PDF
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        );
      })()}
      </>,
      document.body
    )}
    </>
  );
};

export default Employees;
