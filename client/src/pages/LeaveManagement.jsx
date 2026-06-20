import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Search, Filter, Check, X, RefreshCw } from 'lucide-react';
import io from 'socket.io-client';

const LeaveManagement = () => {
  const [leaveRequests, setLeaveRequests] = useState([]);
  const [filters, setFilters] = useState({ search: '', status: '' });
  const [showFilters, setShowFilters] = useState(false);
  const [actionRemarks, setActionRemarks] = useState({});
  const [loading, setLoading] = useState(false);

  const fetchLeaveRequestsRef = useRef(null);

  useEffect(() => {
    fetchLeaveRequestsRef.current = fetchLeaveRequests;
  });

  useEffect(() => {
    const socket = io();
    socket.on('leave_update', () => {
      console.log('Real-time leave update received. Reloading leave requests...');
      if (fetchLeaveRequestsRef.current) {
        fetchLeaveRequestsRef.current();
      }
    });

    return () => {
      socket.disconnect();
    };
  }, []);

  useEffect(() => {
    fetchLeaveRequests();
  }, [filters.status]);

  const fetchLeaveRequests = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/leaves', { params: filters });
      setLeaveRequests(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleLeaveDecision = async (id, status) => {
    const remarks = actionRemarks[id] || '';
    try {
      await axios.put(`/api/leaves/${id}/status`, { status, adminRemarks: remarks });
      fetchLeaveRequests();
      alert(`Leave request ${status.toLowerCase()} successfully!`);
    } catch (err) {
      alert(err.response?.data?.message || 'Error updating status');
    }
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="bg-gradient-to-r from-slate-900 via-brand-950 to-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-[10px] uppercase tracking-widest text-brand-400 font-extrabold">Operations Portal</span>
          <h1 className="text-2xl font-black text-white mt-1">Leave Management</h1>
        </div>
      </div>

      {/* Control Toolbar */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-4 rounded-2xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="relative w-full sm:w-64">
            <input
              type="text"
              placeholder="Search employee name..."
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              onKeyDown={(e) => e.key === 'Enter' && fetchLeaveRequests()}
              className="px-4 py-2 pl-9 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-full bg-transparent dark:text-white"
            />
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          </div>

          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`p-2 border rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-all ${showFilters ? 'bg-indigo-50 dark:bg-indigo-950 border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'}`}
          >
            <Filter className="w-4 h-4" />
          </button>
          
          <button
            onClick={fetchLeaveRequests}
            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/10 flex items-center gap-1.5"
          >
            Search
          </button>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={fetchLeaveRequests}
            className="p-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl text-slate-500 dark:text-slate-400 transition-all"
            title="Reload Data"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {/* Advanced Filters */}
      {showFilters && (
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in text-xs font-semibold">
          <div>
            <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Status</label>
            <select
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 bg-transparent text-xs dark:text-white dark:bg-slate-900"
            >
              <option value="">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
        </div>
      )}

      {/* Main Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto w-full">
          <table className="w-full border-collapse text-left text-xs">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                <th className="px-6 py-4">Employee</th>
                <th className="px-6 py-4">Leave Type</th>
                <th className="px-6 py-4">Timeline</th>
                <th className="px-6 py-4">Reason</th>
                <th className="px-6 py-4 text-center">Status / Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300 font-medium">
              {leaveRequests.map((reqItem) => (
                <tr key={reqItem._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                  <td className="px-6 py-4">
                    <span className="font-extrabold text-slate-800 dark:text-white block capitalize">{reqItem.user?.username || 'Unknown'}</span>
                    <span className="block text-[10px] text-slate-400 dark:text-slate-500 capitalize">{reqItem.user?.user_type}</span>
                  </td>
                  <td className="px-6 py-4 font-bold text-indigo-600 dark:text-indigo-400">{reqItem.leaveType}</td>
                  <td className="px-6 py-4">
                    <span className="block font-bold dark:text-white">
                      {new Date(reqItem.startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - {new Date(reqItem.endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </span>
                    <span className="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{reqItem.leaveDays} days</span>
                  </td>
                  <td className="px-6 py-4 max-w-[200px] truncate text-slate-500 dark:text-slate-400 font-normal" title={reqItem.reason}>
                    {reqItem.reason}
                  </td>
                  <td className="px-6 py-4 text-center">
                    {reqItem.status === 'Pending' ? (
                      <div className="flex flex-col gap-2 items-center justify-center">
                        <input
                          type="text"
                          placeholder="Approve/reject remarks..."
                          value={actionRemarks[reqItem._id] || ''}
                          onChange={(e) => setActionRemarks({ ...actionRemarks, [reqItem._id]: e.target.value })}
                          className="px-2 py-1 border border-slate-200 dark:border-slate-700 rounded-lg text-[10px] focus:outline-none w-44 bg-transparent dark:text-white"
                        />
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleLeaveDecision(reqItem._id, 'Approved')}
                            className="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] shadow-sm flex items-center gap-1 transition-all"
                          >
                            <Check className="w-3 h-3" /> Approve
                          </button>
                          <button
                            onClick={() => handleLeaveDecision(reqItem._id, 'Rejected')}
                            className="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-[10px] shadow-sm flex items-center gap-1 transition-all"
                          >
                            <X className="w-3 h-3" /> Reject
                          </button>
                        </div>
                      </div>
                    ) : (
                      <div className="flex flex-col items-center">
                        <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase ${reqItem.status === 'Approved' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400 border border-rose-100 dark:border-rose-900/50'}`}>
                          {reqItem.status}
                        </span>
                        {reqItem.adminRemarks && (
                          <span className="block text-[9px] text-slate-400 dark:text-slate-500 mt-1 max-w-[150px] truncate font-normal" title={reqItem.adminRemarks}>
                            Note: {reqItem.adminRemarks}
                          </span>
                        )}
                      </div>
                    )}
                  </td>
                </tr>
              ))}
              {leaveRequests.length === 0 && (
                <tr>
                  <td colSpan="5" className="text-center py-10 text-slate-400 dark:text-slate-500 font-semibold">No leave request entries found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default LeaveManagement;
