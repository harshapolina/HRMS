import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { 
  Search, Filter, Eye, RefreshCw, ChevronLeft, ChevronRight, AlertCircle, Loader2,
  ChevronDown, ChevronUp, CheckCircle2, Clock, XCircle, CalendarDays, MapPin, LayoutGrid 
} from 'lucide-react';
import io from 'socket.io-client';

const AttendanceReport = () => {
  const getTodayStr = () => {
    const options = { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' };
    const formatter = new Intl.DateTimeFormat('en-CA', options); // YYYY-MM-DD
    return formatter.format(new Date());
  };

  const todayDate = getTodayStr();

  const [logs, setLogs] = useState([]);
  const [stats, setStats] = useState({ total: 0, present: 0, absent: 0, late: 0, leave: 0 });
  const [filters, setFilters] = useState({ search: '', status: '', from: todayDate, to: todayDate });
  const [showFilters, setShowFilters] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  
  // Expanded calendar analysis state
  const [expandedUser, setExpandedUser] = useState(null);
  const [calMonth, setCalMonth] = useState(new Date().getMonth() + 1);
  const [calYear, setCalYear] = useState(new Date().getFullYear());
  const [userMonthLogs, setUserMonthLogs] = useState([]);
  const [calLoading, setCalLoading] = useState(false);
  const [selectedCalDate, setSelectedCalDate] = useState(null);
  
  // Modal for coordinates history
  const [selectedLog, setSelectedLog] = useState(null);

  // Column visibility state
  const [visibleCols, setVisibleCols] = useState({
    id: false,
    uniqueId: true,
    name: true,
    email: false,
    role: false,
    punchIn: true,
    punchOut: true,
    workHrs: true,
    status: true,
    tracking: true,
    actions: true
  });
  const [showColDropdown, setShowColDropdown] = useState(false);
  const colDropdownRef = useRef(null);
  const activeColCount = Object.values(visibleCols).filter(Boolean).length;

  // Pagination state
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(10);
  const totalPages = Math.ceil(logs.length / limit);
  const paginatedLogs = logs.slice((page - 1) * limit, page * limit);

  const fetchLogsRef = useRef(null);

  useEffect(() => {
    fetchLogsRef.current = fetchLogs;
  });

  useEffect(() => {
    const socket = io();
    socket.on('attendance_update', () => {
      console.log('Real-time attendance update received. Reloading logs...');
      if (fetchLogsRef.current) {
        fetchLogsRef.current();
      }
    });

    return () => {
      socket.disconnect();
    };
  }, []);

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

  useEffect(() => {
    setPage(1);
  }, [filters.status, filters.from, filters.to, filters.search]);

  useEffect(() => {
    fetchLogs();
  }, [filters.status, filters.from, filters.to, filters.search]); // added filters.search to auto-update on search

  const fetchLogs = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get('/api/attendance', { params: filters });
      setLogs(res.data);
      
      // Calculate inline counts for the status cards
      const total = res.data.length;
      const present = res.data.filter(l => l.status === 'Present').length;
      const late = res.data.filter(l => l.status === 'Late').length;
      const absent = res.data.filter(l => l.status === 'Absent').length;
      const leave = res.data.filter(l => l.status === 'Leave').length;

      setStats({
        total,
        present,
        absent,
        late,
        leave,
        presentPercent: total > 0 ? Math.round(((present + late) / total) * 100) : 0
      });
    } catch (err) {
      console.error(err);
      setError(err.response?.data?.message || err.message || 'Failed to connect to the backend server.');
    } finally {
      setLoading(false);
    }
  };

  const fetchUserMonthLogs = async (userId, year, month) => {
    setCalLoading(true);
    try {
      const fromDate = `${year}-${String(month).padStart(2, '0')}-01`;
      const lastDay = new Date(year, month, 0).getDate();
      const toDate = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
      
      const res = await axios.get('/api/attendance', {
        params: {
          from: fromDate,
          to: toDate,
          userId: userId
        }
      });
      setUserMonthLogs(res.data);
    } catch (err) {
      console.error('Failed to fetch monthly logs', err);
    } finally {
      setCalLoading(false);
    }
  };

  const toggleRowExpansion = (userId) => {
    if (expandedUser === userId) {
      setExpandedUser(null);
      setUserMonthLogs([]);
      setSelectedCalDate(null);
    } else {
      setExpandedUser(userId);
      const today = new Date();
      const initialMonth = today.getMonth() + 1;
      const initialYear = today.getFullYear();
      setCalMonth(initialMonth);
      setCalYear(initialYear);
      setSelectedCalDate(null);
      fetchUserMonthLogs(userId, initialYear, initialMonth);
    }
  };

  const handlePrevMonth = (userId) => {
    let newMonth = calMonth - 1;
    let newYear = calYear;
    if (newMonth < 1) {
      newMonth = 12;
      newYear = calYear - 1;
    }
    setCalMonth(newMonth);
    setCalYear(newYear);
    setSelectedCalDate(null);
    fetchUserMonthLogs(userId, newYear, newMonth);
  };

  const handleNextMonth = (userId) => {
    let newMonth = calMonth + 1;
    let newYear = calYear;
    if (newMonth > 12) {
      newMonth = 1;
      newYear = calYear + 1;
    }
    setCalMonth(newMonth);
    setCalYear(newYear);
    setSelectedCalDate(null);
    fetchUserMonthLogs(userId, newYear, newMonth);
  };

  const monthNames = [
    "January", "February", "March", "April", "May", "June", 
    "July", "August", "September", "October", "November", "December"
  ];

  const getMonthlyStats = () => {
    let totalPresent = 0;
    let totalLate = 0;
    let totalAbsent = 0;
    let totalLeavesOrOffs = 0;

    const lastDay = new Date(calYear, calMonth, 0).getDate();

    for (let d = 1; d <= lastDay; d++) {
      const dateObj = new Date(calYear, calMonth - 1, d);
      const dateStr = `${calYear}-${String(calMonth).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const isFuture = dateObj > new Date();
      const isSunday = dateObj.getDay() === 0;

      const log = userMonthLogs.find(l => l.date === dateStr);
      
      if (log) {
        if (log.status === 'Present') totalPresent++;
        else if (log.status === 'Late') totalLate++;
        else if (log.status === 'Leave') totalLeavesOrOffs++;
        else if (log.status === 'Absent') totalAbsent++;
      } else if (!isFuture) {
        if (isSunday) {
          totalLeavesOrOffs++;
        } else {
          totalAbsent++;
        }
      } else {
        if (isSunday) {
          totalLeavesOrOffs++;
        }
      }
    }

    return { totalPresent, totalLate, totalAbsent, totalLeavesOrOffs };
  };

  const renderCalendarCell = (day, userId) => {
    const dateObj = new Date(calYear, calMonth - 1, day);
    const dateStr = `${calYear}-${String(calMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const isFuture = dateObj > new Date();
    const isSunday = dateObj.getDay() === 0;

    const log = userMonthLogs.find(l => l.date === dateStr);

    let dotColor = '';
    if (log) {
      if (log.status === 'Present') dotColor = 'bg-emerald-500';
      else if (log.status === 'Late') dotColor = 'bg-amber-500';
      else if (log.status === 'Leave') dotColor = 'bg-gray-400';
      else if (log.status === 'Absent') dotColor = 'bg-rose-500';
    } else if (!isFuture) {
      if (isSunday) dotColor = 'bg-blue-500';
      else dotColor = 'bg-rose-500';
    }

    const isSelected = selectedCalDate === dateStr;

    return (
      <button 
        key={`day-${day}`} 
        type="button"
        onClick={() => setSelectedCalDate(dateStr)}
        className={`w-full aspect-square border rounded-lg bg-canvas p-1.5 flex flex-col justify-between items-center min-h-[38px] cursor-pointer transition-all shadow-sm ${
          isSelected 
            ? 'border-indigo-600 ring-2 ring-indigo-500/20 bg-indigo-50/5 scale-[1.03] font-bold' 
            : 'border-hairline-soft hover:border-hairline'
        }`}
      >
        <span className="text-[9px] text-muted-soft font-semibold self-start">{day}</span>
        {dotColor && (
          <span className={`w-2 h-2 rounded-full ${dotColor} mb-0.5`} />
        )}
      </button>
    );
  };

  return (
    <div className="page-shell">
      <div className="stat-grid lg:grid-cols-5">
        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: '' }))}
          className={`stat-card-btn ${filters.status === '' ? 'stat-card-btn-active' : ''} text-center`}
        >
          <span className="stat-card-label">Total Headcount</span>
          <span className="stat-card-value">{stats.total}</span>
        </button>
        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: 'Present' }))}
          className={`stat-card-btn ${filters.status === 'Present' ? 'stat-card-btn-active' : ''} text-center`}
        >
          <span className="stat-card-label">Present</span>
          <span className="stat-card-value">
            {stats.present + stats.late} ({stats.presentPercent || 0}%)
          </span>
        </button>
        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: 'Absent' }))}
          className={`stat-card-btn ${filters.status === 'Absent' ? 'stat-card-btn-active' : ''} text-center`}
        >
          <span className="stat-card-label">Absents</span>
          <span className="stat-card-value">{stats.absent}</span>
        </button>
        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: 'Late' }))}
          className={`stat-card-btn ${filters.status === 'Late' ? 'stat-card-btn-active' : ''} text-center`}
        >
          <span className="stat-card-label">Late Arrivals</span>
          <span className="stat-card-value">{stats.late}</span>
        </button>
        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: 'Leave' }))}
          className={`stat-card-btn ${filters.status === 'Leave' ? 'stat-card-btn-active' : ''} text-center col-span-2 lg:col-span-1`}
        >
          <span className="stat-card-label">On Leave</span>
          <span className="stat-card-value">{stats.leave}</span>
        </button>
      </div>

      <div className="toolbar">
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="search-wrap">
            <input
              id="attendance-search-input"
              type="text"
              placeholder="Search attendance..."
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              className="search-input text-xs"
            />
            <Search className="w-4 h-4 text-muted-soft absolute left-3 top-1/2 -translate-y-1/2" />
          </div>

          <button
            id="attendance-filter-toggle-btn"
            onClick={() => setShowFilters(!showFilters)}
            className={`filter-btn ${showFilters ? 'filter-btn-active' : ''}`}
          >
            <Filter className="w-4 h-4" />
          </button>
        </div>

        <div className="flex items-center gap-2 relative" ref={colDropdownRef}>
          <button
            id="attendance-column-toggle-btn"
            onClick={() => setShowColDropdown(!showColDropdown)}
            className="filter-btn text-muted flex items-center gap-1.5 text-xs font-semibold h-8"
            title="Toggle Columns"
          >
            <LayoutGrid className="w-4 h-4" /> Columns
          </button>

          {showColDropdown && (
            <div className="absolute right-0 top-full mt-2 card p-4 z-30 w-48 space-y-2 text-left bg-canvas border border-hairline rounded-lg shadow-card">
              {Object.keys(visibleCols).map((col) => (
                <label key={col} className="flex items-center gap-2 text-xs font-semibold text-body cursor-pointer select-none capitalize font-sans">
                  <input
                    type="checkbox"
                    checked={visibleCols[col]}
                    onChange={() => setVisibleCols({ ...visibleCols, [col]: !visibleCols[col] })}
                    className="rounded text-indigo-600 focus:ring-indigo-500"
                  />
                  {col === 'uniqueId' ? 'Unique ID' : col === 'workHrs' ? 'Work Hrs' : col}
                </label>
              ))}
            </div>
          )}

          <button
            id="attendance-reload-btn"
            onClick={fetchLogs}
            className="filter-btn text-muted"
            title="Reload Data"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {showFilters && (
        <div className="filter-panel grid-cols-1 md:grid-cols-3 text-xs">
          <div>
            <label className="label-xs">Status</label>
            <select
              id="attendance-filter-status"
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className="select-field text-xs"
            >
              <option value="">All Statuses</option>
              <option value="Present">Present</option>
              <option value="Late">Late</option>
              <option value="Absent">Absent</option>
              <option value="Leave">Leave</option>
            </select>
          </div>
          <div>
            <label className="label-xs">From Date</label>
            <input
              id="attendance-filter-from"
              type="date"
              value={filters.from}
              onChange={(e) => setFilters({ ...filters, from: e.target.value })}
              className="input-field text-xs"
            />
          </div>
          <div>
            <label className="label-xs">To Date</label>
            <input
              id="attendance-filter-to"
              type="date"
              value={filters.to}
              onChange={(e) => setFilters({ ...filters, to: e.target.value })}
              className="input-field text-xs"
            />
          </div>
        </div>
      )}

      {error && (
        <div className="alert-error">
          <AlertCircle className="w-5 h-5 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div className="table-container">
        <div className="overflow-x-auto w-full">
          <table className="table-shell text-xs">
            <thead>
              <tr>
                {visibleCols.id && <th className="px-6 py-4">ID</th>}
                {visibleCols.uniqueId && <th className="px-6 py-4">Unique ID</th>}
                {visibleCols.name && <th className="px-6 py-4">Name</th>}
                {visibleCols.email && <th className="px-6 py-4">Email</th>}
                {visibleCols.role && <th className="px-6 py-4">Role</th>}
                {visibleCols.punchIn && <th className="px-6 py-4">Punch In</th>}
                {visibleCols.punchOut && <th className="px-6 py-4">Punch Out</th>}
                {visibleCols.workHrs && <th className="px-6 py-4">Work Hrs</th>}
                {visibleCols.status && <th className="px-6 py-4">Status</th>}
                {visibleCols.tracking && <th className="px-6 py-4 text-center">Tracking</th>}
                {visibleCols.actions && <th className="px-6 py-4 text-right">Actions</th>}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={activeColCount} className="text-center py-10 text-muted font-semibold">
                    <div className="flex items-center justify-center gap-2">
                      <Loader2 className="w-4 h-4 animate-spin text-ink" />
                      <span>Fetching logs from database...</span>
                    </div>
                  </td>
                </tr>
              ) : paginatedLogs.map((log, index) => {
                const actualIndex = (page - 1) * limit + index;
                const isExpanded = expandedUser === log.user?._id;
                
                // Calculate monthly stats if expanded
                let monthlyStats = { totalPresent: 0, totalLate: 0, totalAbsent: 0, totalLeavesOrOffs: 0 };
                let calendarCells = [];
                
                if (isExpanded && log.user?._id) {
                  monthlyStats = getMonthlyStats();
                  
                  const firstDay = new Date(calYear, calMonth - 1, 1).getDay();
                  const lastDay = new Date(calYear, calMonth, 0).getDate();
                  
                  for (let i = 0; i < firstDay; i++) {
                    calendarCells.push(<div key={`pad-${i}`} className="aspect-square border border-transparent" />);
                  }
                  
                  for (let d = 1; d <= lastDay; d++) {
                    calendarCells.push(renderCalendarCell(d, log.user._id));
                  }
                }
                
                return (
                  <React.Fragment key={log._id}>
                    <tr className={isExpanded ? 'bg-surface-soft border-l-2 border-indigo-600' : ''}>
                      {visibleCols.id && <td className="px-6 py-4 text-muted font-mono">#{actualIndex + 1}</td>}
                      {visibleCols.uniqueId && <td className="px-6 py-4 font-mono font-semibold text-muted">{log.user?.employee_id || '-'}</td>}
                      {visibleCols.name && <td className="px-6 py-4 font-semibold text-ink capitalize">{log.user?.username}</td>}
                      {visibleCols.email && <td className="px-6 py-4 font-normal text-muted">{log.user?.useremail}</td>}
                      {visibleCols.role && <td className="px-6 py-4 capitalize text-ink font-semibold text-[10px]">{log.user?.user_type}</td>}
                      {visibleCols.punchIn && (
                        <td className="px-6 py-4">
                          {log.punchIn ? new Date(log.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-'}
                        </td>
                      )}
                      {visibleCols.punchOut && (
                        <td className="px-6 py-4">
                          {log.punchOut ? new Date(log.punchOut).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-'}
                        </td>
                      )}
                      {visibleCols.workHrs && <td className="px-6 py-4 font-semibold">{log.totalHours ? `${log.totalHours} hrs` : '-'}</td>}
                      {visibleCols.status && (
                        <td className="px-6 py-4">
                          <span className={`${log.status === 'Present' ? 'badge-success' : log.status === 'Late' ? 'badge-warning' : log.status === 'Leave' ? 'badge-neutral' : 'badge-error'} uppercase text-[10px]`}>
                            {log.status}
                          </span>
                        </td>
                      )}
                      {visibleCols.tracking && (
                        <td className="px-6 py-4 text-center">
                          {log.locationHistory && log.locationHistory.length > 0 ? (
                            <button
                              id={`view-route-btn-${log._id}`}
                              onClick={() => setSelectedLog(log)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-600 hover:bg-indigo-100 border border-indigo-200 transition-all shadow-sm whitespace-nowrap"
                            >
                              <MapPin className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                              <span>Route ({log.locationHistory.length})</span>
                            </button>
                          ) : (
                            <span className="text-muted-soft text-xs">-</span>
                          )}
                        </td>
                      )}
                      {visibleCols.actions && (
                        <td className="px-6 py-4 text-right">
                          <div className="flex items-center justify-end">
                            {log.user?._id && (
                              <button
                                type="button"
                                onClick={() => toggleRowExpansion(log.user._id)}
                                className="p-1 hover:bg-surface-soft rounded-lg text-muted transition-colors"
                                title="Monthly Analytics"
                              >
                                {isExpanded ? (
                                  <ChevronUp className="w-4 h-4 text-indigo-600 animate-pulse" />
                                ) : (
                                  <ChevronDown className="w-4 h-4 text-muted-soft" />
                                )}
                              </button>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                    {isExpanded && log.user?._id && (
                      <tr className="bg-surface-soft/40 border-b border-hairline">
                        <td colSpan={activeColCount} className="p-5">
                          <div className="bg-canvas border border-hairline rounded-2xl p-5 shadow-sm flex flex-col lg:flex-row gap-6 max-w-fit mx-auto items-stretch">
                            {/* Calendar Grid */}
                            <div className="w-[290px] sm:w-[320px] space-y-4 shrink-0">
                              <div className="flex items-center justify-between">
                                <button
                                  type="button"
                                  onClick={() => handlePrevMonth(log.user._id)}
                                  className="p-1.5 hover:bg-surface-soft hover:text-ink text-muted border border-hairline rounded-lg transition-all"
                                  title="Previous Month"
                                >
                                  <ChevronLeft className="w-4 h-4" />
                                </button>
                                <span className="font-display font-semibold text-xs text-ink uppercase tracking-wider">
                                  {monthNames[calMonth - 1]} {calYear}
                                </span>
                                <button
                                  type="button"
                                  onClick={() => handleNextMonth(log.user._id)}
                                  className="p-1.5 hover:bg-surface-soft hover:text-ink text-muted border border-hairline rounded-lg transition-all"
                                  title="Next Month"
                                >
                                  <ChevronRight className="w-4 h-4" />
                                </button>
                              </div>
                              
                              {calLoading ? (
                                <div className="py-12 text-center text-xs text-muted flex items-center justify-center gap-2">
                                  <Loader2 className="w-4 h-4 animate-spin text-ink" />
                                  <span>Analyzing records...</span>
                                </div>
                              ) : (
                                <div className="space-y-2">
                                  <div className="grid grid-cols-7 gap-1.5 text-center text-[9px] font-bold text-muted-soft tracking-wider border-b border-hairline pb-1.5">
                                    {['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'].map(day => (
                                      <span key={day} className="py-0.5">{day}</span>
                                    ))}
                                  </div>
                                  <div className="grid grid-cols-7 gap-1.5">
                                    {calendarCells}
                                  </div>
                                </div>
                              )}
                            </div>
                            
                            {/* Vertical Separator */}
                            <div className="hidden lg:block w-px bg-hairline-soft self-stretch my-2" />

                            {/* Stats Sidebar */}
                            <div className="w-[260px] sm:w-[280px] bg-surface-soft border border-hairline-soft rounded-xl p-4 flex flex-col justify-between shrink-0 space-y-4">
                              <div>
                                <h4 className="font-display font-bold text-[9px] uppercase tracking-wider text-muted mb-3 text-center">Monthly Summary</h4>
                                <div className="space-y-2">
                                  <div className="bg-canvas p-2.5 border border-hairline-soft rounded-lg flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                      <span className="p-1 bg-emerald-50 rounded-md text-emerald-600"><CheckCircle2 className="w-3.5 h-3.5" /></span>
                                      <span className="font-semibold text-[11px] text-body">Total Present</span>
                                    </div>
                                    <span className="font-display font-bold text-xs text-ink">{monthlyStats.totalPresent}</span>
                                  </div>
                                  <div className="bg-canvas p-2.5 border border-hairline-soft rounded-lg flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                      <span className="p-1 bg-amber-50 rounded-md text-amber-600"><Clock className="w-3.5 h-3.5" /></span>
                                      <span className="font-semibold text-[11px] text-body">Total Late</span>
                                    </div>
                                    <span className="font-display font-bold text-xs text-ink">{monthlyStats.totalLate}</span>
                                  </div>
                                  <div className="bg-canvas p-2.5 border border-hairline-soft rounded-lg flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                      <span className="p-1 bg-rose-50 rounded-md text-rose-600"><XCircle className="w-3.5 h-3.5" /></span>
                                      <span className="font-semibold text-[11px] text-body">Total Absents</span>
                                    </div>
                                    <span className="font-display font-bold text-xs text-ink">{monthlyStats.totalAbsent}</span>
                                  </div>
                                  <div className="bg-canvas p-2.5 border border-hairline-soft rounded-lg flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                      <span className="p-1 bg-blue-50 rounded-md text-blue-600"><CalendarDays className="w-3.5 h-3.5" /></span>
                                      <span className="font-semibold text-[11px] text-body">Leaves / Offs</span>
                                    </div>
                                    <span className="font-display font-bold text-xs text-ink">{monthlyStats.totalLeavesOrOffs}</span>
                                  </div>
                                </div>
                              </div>

                              {/* Selected Day Details */}
                              <div className="border-t border-hairline-soft pt-4">
                                <h4 className="font-display font-bold text-[9px] uppercase tracking-wider text-muted mb-3 text-center">Day Details</h4>
                                {selectedCalDate ? (() => {
                                  const dateObj = new Date(selectedCalDate);
                                  const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                  const log = userMonthLogs.find(l => l.date === selectedCalDate);
                                  const isSunday = dateObj.getDay() === 0;

                                  let statusText = 'Absent';
                                  let statusBadge = 'badge-error';
                                  let punchInText = '-';
                                  let punchOutText = '-';
                                  let hoursText = '-';

                                  if (log) {
                                    statusText = log.status;
                                    statusBadge = log.status === 'Present' ? 'badge-success' : log.status === 'Late' ? 'badge-warning' : log.status === 'Leave' ? 'badge-neutral' : 'badge-error';
                                    punchInText = log.punchIn ? new Date(log.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-';
                                    punchOutText = log.punchOut ? new Date(log.punchOut).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-';
                                    hoursText = log.totalHours ? `${log.totalHours} hrs` : '-';
                                  } else {
                                    if (isSunday) {
                                      statusText = 'Weekly Off';
                                      statusBadge = 'badge-neutral';
                                    } else if (dateObj > new Date()) {
                                      statusText = 'Future';
                                      statusBadge = 'badge-neutral';
                                    }
                                  }

                                  return (
                                    <div className="bg-canvas border border-hairline-soft rounded-lg p-2.5 space-y-2">
                                      <div className="flex justify-between items-center text-[10px] border-b border-hairline-soft pb-1.5 font-bold text-ink">
                                        <span>{formattedDate}</span>
                                        <span className={`${statusBadge} text-[8px] uppercase font-bold py-0.5 px-1.5`}>{statusText}</span>
                                      </div>
                                      <div className="grid grid-cols-2 gap-2 text-[10px] pt-1">
                                        <div>
                                          <span className="text-muted block text-[9px]">Punch In</span>
                                          <span className="font-semibold text-ink">{punchInText}</span>
                                        </div>
                                        <div>
                                          <span className="text-muted block text-[9px]">Punch Out</span>
                                          <span className="font-semibold text-ink">{punchOutText}</span>
                                        </div>
                                        <div className="col-span-2 pt-1.5 border-t border-hairline-soft/50">
                                          <span className="text-muted block text-[9px]">Hours Worked</span>
                                          <span className="font-semibold text-ink">{hoursText}</span>
                                        </div>
                                      </div>
                                    </div>
                                  );
                                })() : (
                                  <div className="text-center py-4 bg-canvas/40 border border-dashed border-hairline-soft rounded-lg text-muted text-[9px] font-medium leading-relaxed">
                                    Click a calendar date<br />to view timings
                                  </div>
                                )}
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                );
              })}
              {!loading && logs.length === 0 && (
                <tr>
                  <td colSpan={activeColCount} className="text-center py-10 text-muted font-semibold">No attendance entries recorded.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="paginator">
          <div className="flex items-center gap-2 text-sm text-muted font-sans">
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
            <span>entries (Total: {logs.length})</span>
          </div>

          <div className="flex items-center gap-1.5">
            <button
              onClick={() => setPage(Math.max(1, page - 1))}
              disabled={page === 1}
              className="p-2 border border-hairline rounded-lg hover:bg-canvas disabled:opacity-50 text-muted transition-colors"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <span className="px-4 py-1 text-sm font-semibold text-ink bg-surface-soft rounded-lg border border-hairline font-sans">
              Page {page} of {totalPages || 1}
            </span>
            <button
              onClick={() => setPage(Math.min(totalPages, page + 1))}
              disabled={page === totalPages || totalPages === 0}
              className="p-2 border border-hairline rounded-lg hover:bg-canvas disabled:opacity-50 text-muted transition-colors"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {selectedLog && (
        <div className="modal-overlay">
          <div className="modal-panel-md">
            <div className="flex items-center justify-between border-b border-hairline pb-4 mb-4">
              <h3 className="font-semibold text-ink text-xs uppercase">Location Tracking Log ({selectedLog.date})</h3>
              <button id="close-log-modal-btn" onClick={() => setSelectedLog(null)} className="btn-secondary btn-sm">
                Close
              </button>
            </div>
            <div className="flex-1 overflow-y-auto space-y-2 pr-1 text-xs">
              <p className="font-semibold mb-3 text-muted">Employee: {selectedLog.user?.username}</p>
              {selectedLog.locationHistory.map((loc, idx) => (
                <div key={idx} className="p-2 border border-hairline-soft rounded-md flex items-center justify-between text-xs font-mono">
                  <span>Coordinates: {loc.latitude.toFixed(6)}, {loc.longitude.toFixed(6)}</span>
                  <span className="text-muted">{new Date(loc.capturedAt).toLocaleTimeString('en-US')}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AttendanceReport;
