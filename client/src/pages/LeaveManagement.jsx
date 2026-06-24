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
  }, []);

  const fetchLeaveRequests = async () => {
    setLoading(true);
    try {
      // Don't pass status to the backend to get all requests for counts counting
      const res = await axios.get('/api/leaves', { params: { search: filters.search } });
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

  const total = leaveRequests.length;
  const pending = leaveRequests.filter(r => r.status === 'Pending').length;
  const approved = leaveRequests.filter(r => r.status === 'Approved').length;
  const rejected = leaveRequests.filter(r => r.status === 'Rejected').length;
  const pendingPercent = total > 0 ? Math.round((pending / total) * 100) : 0;

  const displayedRequests = leaveRequests.filter(req => {
    if (filters.status && req.status !== filters.status) return false;
    return true;
  });

  return (
    <div className="page-shell">

      <div className="flex flex-wrap gap-4 mb-6">
        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: prev.status === 'Pending' ? '' : 'Pending' }))}
          className={`px-6 py-2.5 rounded-full border text-xs font-semibold tracking-wide transition-all shadow-sm cursor-pointer whitespace-nowrap ${
            filters.status === 'Pending'
              ? 'bg-amber-500 text-white border-amber-600 shadow-md scale-105'
              : 'border-amber-500 text-amber-600 bg-canvas hover:bg-amber-50/20'
          }`}
        >
          Pending : {pending} ({pendingPercent}%)
        </button>

        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: prev.status === 'Approved' ? '' : 'Approved' }))}
          className={`px-6 py-2.5 rounded-full border text-xs font-semibold tracking-wide transition-all shadow-sm cursor-pointer whitespace-nowrap ${
            filters.status === 'Approved'
              ? 'bg-emerald-500 text-white border-emerald-600 shadow-md scale-105'
              : 'border-emerald-500 text-emerald-600 bg-canvas hover:bg-emerald-50/20'
          }`}
        >
          Approved : {approved}
        </button>

        <button
          type="button"
          onClick={() => setFilters(prev => ({ ...prev, status: prev.status === 'Rejected' ? '' : 'Rejected' }))}
          className={`px-6 py-2.5 rounded-full border text-xs font-semibold tracking-wide transition-all shadow-sm cursor-pointer whitespace-nowrap ${
            filters.status === 'Rejected'
              ? 'bg-rose-500 text-white border-rose-600 shadow-md scale-105'
              : 'border-rose-500 text-rose-600 bg-canvas hover:bg-rose-50/20'
          }`}
        >
          Rejected : {rejected}
        </button>
      </div>

      <div className="toolbar">
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="search-wrap">
            <input
              id="leave-search-input"
              type="text"
              placeholder="Search employee name..."
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              onKeyDown={(e) => e.key === 'Enter' && fetchLeaveRequests()}
              className="search-input text-xs"
            />
            <Search className="w-4 h-4 text-muted-soft absolute left-3 top-1/2 -translate-y-1/2" />
          </div>

          <button
            id="leave-filter-toggle-btn"
            onClick={() => setShowFilters(!showFilters)}
            className={`filter-btn ${showFilters ? 'filter-btn-active' : ''}`}
          >
            <Filter className="w-4 h-4" />
          </button>

          <button id="leave-search-submit-btn" onClick={fetchLeaveRequests} className="btn-primary btn-sm">
            Search
          </button>
        </div>

        <button
          id="leave-reload-btn"
          onClick={fetchLeaveRequests}
          className="filter-btn text-muted"
          title="Reload Data"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {showFilters && (
        <div className="filter-panel grid-cols-1 md:grid-cols-2 text-xs">
          <div>
            <label className="label-xs" htmlFor="leave-filter-status">Status</label>
            <select
              id="leave-filter-status"
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className="select-field text-xs"
            >
              <option value="">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
        </div>
      )}

      <div className="table-container">
        <div className="overflow-x-auto w-full">
          <table className="table-shell text-xs">
            <thead>
              <tr>
                <th className="px-6 py-4">Employee</th>
                <th className="px-6 py-4">Leave Type</th>
                <th className="px-6 py-4">Timeline</th>
                <th className="px-6 py-4">Reason</th>
                <th className="px-6 py-4 text-center">Status / Actions</th>
              </tr>
            </thead>
            <tbody>
              {displayedRequests.map((reqItem) => (
                <tr key={reqItem._id}>
                  <td className="px-6 py-4">
                    <span className="font-semibold text-ink block capitalize">{reqItem.user?.username || 'Unknown'}</span>
                    <span className="block text-[10px] text-muted capitalize">{reqItem.user?.user_type}</span>
                  </td>
                  <td className="px-6 py-4 font-semibold text-ink">{reqItem.leaveType}</td>
                  <td className="px-6 py-4">
                    <span className="block font-semibold">
                      {new Date(reqItem.startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - {new Date(reqItem.endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </span>
                    <span className="block text-[10px] text-muted mt-0.5">{reqItem.leaveDays} days</span>
                  </td>
                  <td className="px-6 py-4 max-w-[200px] truncate text-muted font-normal" title={reqItem.reason}>
                    {reqItem.reason}
                  </td>
                  <td className="px-6 py-4 text-center">
                    {reqItem.status === 'Pending' ? (
                      <div className="flex flex-col gap-2 items-center justify-center">
                        <input
                          id={`leave-remarks-input-${reqItem._id}`}
                          type="text"
                          placeholder="Approve/reject remarks..."
                          value={actionRemarks[reqItem._id] || ''}
                          onChange={(e) => setActionRemarks({ ...actionRemarks, [reqItem._id]: e.target.value })}
                          className="input-field text-[10px] w-44 h-8"
                        />
                        <div className="flex gap-2">
                          <button
                            id={`leave-approve-btn-${reqItem._id}`}
                            onClick={() => handleLeaveDecision(reqItem._id, 'Approved')}
                            className="btn-success"
                          >
                            <Check className="w-3 h-3" /> Approve
                          </button>
                          <button
                            id={`leave-reject-btn-${reqItem._id}`}
                            onClick={() => handleLeaveDecision(reqItem._id, 'Rejected')}
                            className="btn-danger"
                          >
                            <X className="w-3 h-3" /> Reject
                          </button>
                        </div>
                      </div>
                    ) : (
                      <div className="flex flex-col items-center">
                        <span className={`${reqItem.status === 'Approved' ? 'badge-success' : 'badge-error'} uppercase text-[10px]`}>
                          {reqItem.status}
                        </span>
                        {reqItem.adminRemarks && (
                          <span className="block text-[9px] text-muted mt-1 max-w-[150px] truncate font-normal" title={reqItem.adminRemarks}>
                            Note: {reqItem.adminRemarks}
                          </span>
                        )}
                      </div>
                    )}
                  </td>
                </tr>
              ))}
              {displayedRequests.length === 0 && (
                <tr>
                  <td colSpan="5" className="text-center py-10 text-muted font-semibold">No leave request entries found.</td>
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
