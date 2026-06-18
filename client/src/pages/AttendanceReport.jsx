import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, Filter, Eye, RefreshCw, ChevronLeft, ChevronRight, AlertCircle, Loader2 } from 'lucide-react';

const AttendanceReport = () => {
  const [logs, setLogs] = useState([]);
  const [stats, setStats] = useState({ total: 0, present: 0, absent: 0, late: 0, leave: 0 });
  const [filters, setFilters] = useState({ search: '', status: '', from: '', to: '' });
  const [showFilters, setShowFilters] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  
  // Modal for coordinates history
  const [selectedLog, setSelectedLog] = useState(null);

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
    <div className="space-y-6">
      {/* Dynamic Summary Cards - respects premium legacy look */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div className="bg-white border border-sky-200 rounded-full px-5 py-3 shadow-sm text-center">
          <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Total Headcount</span>
          <span className="text-sm font-black text-sky-700 block mt-0.5">{stats.total}</span>
        </div>
        <div className="bg-white border border-emerald-200 rounded-full px-5 py-3 shadow-sm text-center">
          <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Present</span>
          <span className="text-sm font-black text-emerald-700 block mt-0.5">
            {stats.present + stats.late} ({stats.presentPercent || 0}%)
          </span>
        </div>
        <div className="bg-white border border-rose-200 rounded-full px-5 py-3 shadow-sm text-center">
          <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Absents</span>
          <span className="text-sm font-black text-rose-700 block mt-0.5">{stats.absent}</span>
        </div>
        <div className="bg-white border border-amber-200 rounded-full px-5 py-3 shadow-sm text-center">
          <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Late Arrivals</span>
          <span className="text-sm font-black text-amber-700 block mt-0.5">{stats.late}</span>
        </div>
        <div className="bg-white border border-indigo-200 rounded-full px-5 py-3 shadow-sm text-center col-span-2 md:col-span-1">
          <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">On Leave</span>
          <span className="text-sm font-black text-indigo-700 block mt-0.5">{stats.leave}</span>
        </div>
      </div>

      {/* Control Toolbar */}
      <div className="bg-white border border-slate-100 p-4 rounded-2xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="relative w-full sm:w-64">
            <input
              type="text"
              placeholder="Search attendance..."
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              className="px-4 py-2 pl-9 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-full bg-transparent"
            />
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          </div>

          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`p-2 border rounded-xl hover:bg-slate-50 transition-all ${showFilters ? 'bg-indigo-50 border-indigo-200 text-indigo-600' : 'border-slate-200 text-slate-600'}`}
          >
            <Filter className="w-4 h-4" />
          </button>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={fetchLogs}
            className="p-2 border border-slate-200 hover:bg-slate-50 rounded-xl text-slate-500 transition-all"
            title="Reload Data"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Advanced Filters */}
      {showFilters && (
        <div className="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-4 animate-fade-in text-xs font-semibold">
          <div>
            <label className="block text-slate-500 text-xxs font-bold uppercase mb-1">Status</label>
            <select
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 bg-transparent text-xs"
            >
              <option value="">All Statuses</option>
              <option value="Present">Present</option>
              <option value="Late">Late</option>
              <option value="Absent">Absent</option>
              <option value="Leave">Leave</option>
            </select>
          </div>
          <div>
            <label className="block text-slate-500 text-xxs font-bold uppercase mb-1">From Date</label>
            <input
              type="date"
              value={filters.from}
              onChange={(e) => setFilters({ ...filters, from: e.target.value })}
              className="w-full px-3 py-1.5 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs text-slate-500"
            />
          </div>
          <div>
            <label className="block text-slate-500 text-xxs font-bold uppercase mb-1">To Date</label>
            <input
              type="date"
              value={filters.to}
              onChange={(e) => setFilters({ ...filters, to: e.target.value })}
              className="w-full px-3 py-1.5 border border-slate-200 rounded-xl focus:outline-none focus:border-brand-500 text-xs text-slate-500"
            />
          </div>
        </div>
      )}

      {error && (
        <div className="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl flex items-center gap-2 text-xs font-semibold">
          <AlertCircle className="w-4.5 h-4.5 shrink-0 text-rose-500" />
          <span>{error}</span>
        </div>
      )}

      {/* Main Table */}
      <div className="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto w-full">
          <table className="w-full border-collapse text-left text-xs">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider">
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
            <tbody className="divide-y divide-slate-100 text-slate-700 font-medium">
              {loading ? (
                <tr>
                  <td colSpan="10" className="text-center py-10 text-slate-400 font-semibold">
                    <div className="flex items-center justify-center gap-2">
                      <Loader2 className="w-4 h-4 animate-spin text-brand-500" />
                      <span>Fetching logs from database...</span>
                    </div>
                  </td>
                </tr>
              ) : logs.map((log, index) => (
                <tr key={log._id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="px-6 py-4 text-slate-400 font-mono">#{index + 1}</td>
                  <td className="px-6 py-4 font-mono font-bold text-slate-500">{log.user?.employee_id || '-'}</td>
                  <td className="px-6 py-4 font-extrabold text-slate-800 capitalize">{log.user?.username}</td>
                  <td className="px-6 py-4 font-normal text-slate-500">{log.user?.useremail}</td>
                  <td className="px-6 py-4 capitalize text-brand-600 font-bold text-[10px]">{log.user?.user_type}</td>
                  <td className="px-6 py-4">
                    {log.punchIn ? new Date(log.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-'}
                  </td>
                  <td className="px-6 py-4">
                    {log.punchOut ? new Date(log.punchOut).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-'}
                  </td>
                  <td className="px-6 py-4 font-bold">{log.totalHours ? `${log.totalHours} hrs` : '-'}</td>
                  <td className="px-6 py-4">
                    <span className={`px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase ${log.status === 'Present' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : log.status === 'Late' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}`}>
                      {log.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-right">
                    {log.locationHistory && log.locationHistory.length > 0 && (
                      <button
                        onClick={() => setSelectedLog(log)}
                        className="px-2.5 py-1 hover:bg-brand-50 text-brand-600 rounded-lg font-bold text-[10px] border border-brand-100 transition-colors"
                      >
                        View Route ({log.locationHistory.length})
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {!loading && logs.length === 0 && (
                <tr>
                  <td colSpan="10" className="text-center py-10 text-slate-400 font-semibold">No attendance entries recorded.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Coordinate History Modal */}
      {selectedLog && (
        <div className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 shadow-2xl max-h-[80vh] flex flex-col">
            <div className="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
              <h3 className="font-extrabold text-slate-800 text-xs uppercase">Location Tracking Log ({selectedLog.date})</h3>
              <button
                onClick={() => setSelectedLog(null)}
                className="px-3 py-1.5 hover:bg-slate-50 border border-slate-200 text-slate-500 rounded-lg text-xs font-bold transition-all"
              >
                Close
              </button>
            </div>
            <div className="flex-1 overflow-y-auto space-y-2 pr-1 text-xs">
              <p className="font-extrabold mb-3 text-slate-500">Employee: {selectedLog.user?.username}</p>
              {selectedLog.locationHistory.map((loc, idx) => (
                <div key={idx} className="p-2 border border-slate-50 rounded-xl hover:bg-slate-50 flex items-center justify-between text-xxs font-mono">
                  <span>Coordinates: {loc.latitude.toFixed(6)}, {loc.longitude.toFixed(6)}</span>
                  <span className="text-slate-400">{new Date(loc.capturedAt).toLocaleTimeString('en-US')}</span>
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
