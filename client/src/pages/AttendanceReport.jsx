import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Search, Filter, Eye, RefreshCw, ChevronLeft, ChevronRight, AlertCircle, Loader2 } from 'lucide-react';
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
  
  // Modal for coordinates history
  const [selectedLog, setSelectedLog] = useState(null);

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

  return (
    <div className="page-shell">
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Workforce Analytics</p>
          <h1 className="page-title">Attendance Report</h1>
          <p className="page-subtitle">Track punch logs, work hours, and location history across your team.</p>
        </div>
      </div>

      <div className="stat-grid">
        <div className="stat-card text-center">
          <span className="stat-card-label">Total Headcount</span>
          <span className="stat-card-value">{stats.total}</span>
        </div>
        <div className="stat-card text-center">
          <span className="stat-card-label">Present</span>
          <span className="stat-card-value">
            {stats.present + stats.late} ({stats.presentPercent || 0}%)
          </span>
        </div>
        <div className="stat-card text-center">
          <span className="stat-card-label">Absents</span>
          <span className="stat-card-value">{stats.absent}</span>
        </div>
        <div className="stat-card text-center">
          <span className="stat-card-label">Late Arrivals</span>
          <span className="stat-card-value">{stats.late}</span>
        </div>
        <div className="stat-card text-center col-span-2 md:col-span-1">
          <span className="stat-card-label">On Leave</span>
          <span className="stat-card-value">{stats.leave}</span>
        </div>
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

        <div className="flex items-center gap-2">
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
                <th className="px-6 py-4">ID</th>
                <th className="px-6 py-4">Unique ID</th>
                <th className="px-6 py-4">Name</th>
                <th className="px-6 py-4">Email</th>
                <th className="px-6 py-4">Role</th>
                <th className="px-6 py-4">Punch In</th>
                <th className="px-6 py-4">Punch Out</th>
                <th className="px-6 py-4">Work Hrs</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="10" className="text-center py-10 text-muted font-semibold">
                    <div className="flex items-center justify-center gap-2">
                      <Loader2 className="w-4 h-4 animate-spin text-ink" />
                      <span>Fetching logs from database...</span>
                    </div>
                  </td>
                </tr>
              ) : logs.map((log, index) => (
                <tr key={log._id}>
                  <td className="px-6 py-4 text-muted font-mono">#{index + 1}</td>
                  <td className="px-6 py-4 font-mono font-semibold text-muted">{log.user?.employee_id || '-'}</td>
                  <td className="px-6 py-4 font-semibold text-ink capitalize">{log.user?.username}</td>
                  <td className="px-6 py-4 font-normal text-muted">{log.user?.useremail}</td>
                  <td className="px-6 py-4 capitalize text-ink font-semibold text-[10px]">{log.user?.user_type}</td>
                  <td className="px-6 py-4">
                    {log.punchIn ? new Date(log.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-'}
                  </td>
                  <td className="px-6 py-4">
                    {log.punchOut ? new Date(log.punchOut).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-'}
                  </td>
                  <td className="px-6 py-4 font-semibold">{log.totalHours ? `${log.totalHours} hrs` : '-'}</td>
                  <td className="px-6 py-4">
                    <span className={`${log.status === 'Present' ? 'badge-success' : log.status === 'Late' ? 'badge-warning' : log.status === 'Leave' ? 'badge-neutral' : 'badge-error'} uppercase text-[10px]`}>
                      {log.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-right">
                    {log.locationHistory && log.locationHistory.length > 0 && (
                      <button
                        id={`view-route-btn-${log._id}`}
                        onClick={() => setSelectedLog(log)}
                        className="btn-secondary btn-sm"
                      >
                        View Route ({log.locationHistory.length})
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {!loading && logs.length === 0 && (
                <tr>
                  <td colSpan="10" className="text-center py-10 text-muted font-semibold">No attendance entries recorded.</td>
                </tr>
              )}
            </tbody>
          </table>
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
