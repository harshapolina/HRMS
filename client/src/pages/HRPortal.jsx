import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, Filter, Check, X, Plus, Calendar, FileText, Mail, HardDrive, RefreshCw, Eye, Edit3, Trash2 } from 'lucide-react';

const HRPortal = () => {
  const [activeTab, setActiveTab] = useState('attendance');
  
  // Shared States
  const [employees, setEmployees] = useState([]);
  
  // Attendance States
  const [attendanceLogs, setAttendanceLogs] = useState([]);
  const [attFilters, setAttFilters] = useState({ search: '', status: '', from: '', to: '' });
  const [selectedLogsLocation, setSelectedLogsLocation] = useState(null);

  // Leaves States
  const [leaveRequests, setLeaveRequests] = useState([]);
  const [leaveFilters, setLeaveFilters] = useState({ search: '', status: '' });
  const [actionRemarks, setActionRemarks] = useState({});

  // Payroll States
  const [payrollMonth, setPayrollMonth] = useState('Jun 2026');
  const [calculatedPayroll, setCalculatedPayroll] = useState(null);
  const [payrollProcessing, setPayrollProcessing] = useState(false);
  const [payrollCalculatedList, setPayrollCalculatedList] = useState([]);
  const [viewingDraftPayslip, setViewingDraftPayslip] = useState(null);

  // Offer Letter States
  const [offers, setOffers] = useState([]);
  const [showOfferModal, setShowOfferModal] = useState(false);
  const [offerForm, setOfferForm] = useState({
    candidateName: '', email: '', phone: '', position: '', department: '',
    monthlySalary: '', joiningDate: '', reportingManager: '', customHtml: ''
  });
  const [previewingOffer, setPreviewingOffer] = useState(null);

  // Assets & FNF States
  const [assets, setAssets] = useState([]);
  const [assignments, setAssignments] = useState([]);
  const [showAssetModal, setShowAssetModal] = useState(false);
  const [assetForm, setAssetForm] = useState({ name: '', type: 'Laptop', serialNumber: '' });
  
  const [assignForm, setAssignForm] = useState({ assetId: '', userId: '', notes: '' });
  const [selectedFNFUser, setSelectedFNFUser] = useState('');
  const [fnfCalculation, setFnfCalculation] = useState(null);
  const [fnfForm, setFnfForm] = useState({
    lastWorkingDay: '', unpaidSalary: 0, leaveEncashment: 0, bonusIncentives: 0, deductions: 0, netSettlement: 0, status: 'Pending', assetsReturned: false
  });

  useEffect(() => {
    fetchEmployees();
    if (activeTab === 'attendance') fetchAttendanceLogs();
    if (activeTab === 'leaves') fetchLeaveRequests();
    if (activeTab === 'offers') fetchOffers();
    if (activeTab === 'assets') {
      fetchAssets();
      fetchAssignments();
    }
  }, [activeTab, attFilters.status, attFilters.from, attFilters.to, leaveFilters.status]);

  const fetchEmployees = async () => {
    try {
      const res = await axios.get('/api/users?limit=1000');
      setEmployees(res.data.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchAttendanceLogs = async () => {
    try {
      const res = await axios.get('/api/attendance', { params: attFilters });
      setAttendanceLogs(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchLeaveRequests = async () => {
    try {
      const res = await axios.get('/api/leaves', { params: leaveFilters });
      setLeaveRequests(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchOffers = async () => {
    try {
      const res = await axios.get('/api/offers');
      setOffers(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchAssets = async () => {
    try {
      const res = await axios.get('/api/assets');
      setAssets(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchAssignments = async () => {
    try {
      const res = await axios.get('/api/assets/assignments');
      setAssignments(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  // Leaves decision
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

  // Run Calculations
  const calculatePayroll = async () => {
    try {
      const res = await axios.get(`/api/payroll/calculate?monthYear=${payrollMonth}`);
      setCalculatedPayroll(res.data);
      setPayrollCalculatedList(res.data.calculations);
    } catch (err) {
      alert(err.response?.data?.message || 'Calculation failed');
    }
  };

  // Save/Process Payroll
  const processPayroll = async () => {
    if (!calculatedPayroll) return;
    setPayrollProcessing(true);
    try {
      await axios.post('/api/payroll/process', {
        monthYear: payrollMonth,
        records: payrollCalculatedList
      });
      alert('Payroll finalized and saved successfully!');
      setCalculatedPayroll(null);
      setPayrollCalculatedList([]);
    } catch (err) {
      alert(err.response?.data?.message || 'Payroll processing failed');
    } finally {
      setPayrollProcessing(false);
    }
  };

  // Offers
  const handleCreateOffer = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/offers', offerForm);
      setShowOfferModal(false);
      setOfferForm({
        candidateName: '', email: '', phone: '', position: '', department: '',
        monthlySalary: '', joiningDate: '', reportingManager: '', customHtml: ''
      });
      fetchOffers();
    } catch (err) {
      alert(err.response?.data?.message || 'Save failed');
    }
  };

  const handleSendOffer = async (id) => {
    try {
      await axios.post(`/api/offers/${id}/send`);
      alert('Offer letter emailed successfully (Simulated)!');
      fetchOffers();
    } catch (err) {
      console.error(err);
    }
  };

  const handleDeleteOffer = async (id) => {
    if (!window.confirm('Delete this draft?')) return;
    try {
      await axios.delete(`/api/offers/${id}`);
      fetchOffers();
    } catch (err) {
      console.error(err);
    }
  };

  // Assets
  const handleAddAsset = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/assets', assetForm);
      setShowAssetModal(false);
      setAssetForm({ name: '', type: 'Laptop', serialNumber: '' });
      fetchAssets();
    } catch (err) {
      alert(err.response?.data?.message || 'Adding failed');
    }
  };

  const handleAssignAsset = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/assets/assign', assignForm);
      setAssignForm({ assetId: '', userId: '', notes: '' });
      fetchAssets();
      fetchAssignments();
      alert('Asset assigned successfully!');
    } catch (err) {
      alert(err.response?.data?.message || 'Assigning failed');
    }
  };

  const handleReturnAsset = async (assignmentId) => {
    try {
      await axios.post(`/api/assets/return/${assignmentId}`);
      fetchAssets();
      fetchAssignments();
      alert('Asset returned successfully!');
    } catch (err) {
      console.error(err);
    }
  };

  // FNF Calculation & Settle
  const handleFetchFNF = async (userId) => {
    if (!userId) return;
    try {
      const res = await axios.get(`/api/fnf/${userId}`);
      setFnfCalculation(res.data);
      if (res.data.existing) {
        setFnfForm({
          lastWorkingDay: res.data.existing.lastWorkingDay.split('T')[0],
          unpaidSalary: res.data.existing.unpaidSalary,
          leaveEncashment: res.data.existing.leaveEncashment,
          bonusIncentives: res.data.existing.bonusIncentives,
          deductions: res.data.existing.deductions,
          netSettlement: res.data.existing.netSettlement,
          status: res.data.existing.status,
          assetsReturned: res.data.existing.assetsReturned
        });
      } else {
        setFnfForm({
          lastWorkingDay: '', unpaidSalary: 0, leaveEncashment: 0, bonusIncentives: 0, deductions: 0, netSettlement: 0, status: 'Pending',
          assetsReturned: res.data.pendingAssetsCount === 0
        });
      }
    } catch (err) {
      console.error(err);
    }
  };

  const calculateFNFNet = () => {
    const net = (parseFloat(fnfForm.unpaidSalary) || 0) +
                (parseFloat(fnfForm.leaveEncashment) || 0) +
                (parseFloat(fnfForm.bonusIncentives) || 0) -
                (parseFloat(fnfForm.deductions) || 0);
    setFnfForm(prev => ({ ...prev, netSettlement: Math.max(0, net) }));
  };

  const handleSaveFNF = async (e) => {
    e.preventDefault();
    if (!selectedFNFUser) return;
    try {
      await axios.post('/api/fnf', {
        userId: selectedFNFUser,
        ...fnfForm
      });
      alert('FNF Settlement saved successfully!');
      setFnfCalculation(null);
      setSelectedFNFUser('');
      fetchEmployees(); // User might be deactivated
    } catch (err) {
      alert(err.response?.data?.message || 'FNF saving failed');
    }
  };

  return (
    <div className="space-y-6">
      {/* Workspace Navigation Header */}
      <div className="bg-surface-card border border-hairline p-6 rounded-3xl shadow-elevated flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-xxs uppercase tracking-widest text-accent font-semibold">Superadmin Panel</span>
          <h1 className="text-2xl font-semibold text-white mt-1">HR & Operations Console</h1>
        </div>
      </div>

      {/* Primary Tab Navigation */}
      <div className="flex border-b border-hairline overflow-x-auto gap-4 scrollbar-none">
        {['attendance', 'leaves', 'payroll', 'offers', 'assets'].map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`pb-4 px-2 text-sm font-semibold capitalize transition-all border-b-2 -mb-[2px] whitespace-nowrap ${activeTab === tab ? 'border-ink text-ink' : 'border-transparent text-muted hover:text-body'}`}
          >
            {tab === 'offers' ? 'Offer Letters' : tab === 'assets' ? 'Assets & FNF' : tab}
          </button>
        ))}
      </div>

      {/* Main Workspace Card */}
      <div className="bg-canvas border border-hairline-soft rounded-3xl p-6 shadow-sm min-h-[60vh]">
        
        {/* ATTENDANCE TAB */}
        {activeTab === 'attendance' && (
          <div className="space-y-6">
            <div className="flex flex-wrap gap-4 items-center justify-between">
              <div className="flex flex-wrap gap-3 items-center">
                <input
                  type="text"
                  placeholder="Search name..."
                  value={attFilters.search}
                  onChange={(e) => setAttFilters({ ...attFilters, search: e.target.value })}
                  className="px-4 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs w-48 bg-transparent"
                />
                <select
                  value={attFilters.status}
                  onChange={(e) => setAttFilters({ ...attFilters, status: e.target.value })}
                  className="px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs bg-transparent"
                >
                  <option value="">All Statuses</option>
                  <option value="Present">Present</option>
                  <option value="Late">Late</option>
                  <option value="Absent">Absent</option>
                </select>
                <input
                  type="date"
                  value={attFilters.from}
                  onChange={(e) => setAttFilters({ ...attFilters, from: e.target.value })}
                  className="px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs text-muted"
                />
                <input
                  type="date"
                  value={attFilters.to}
                  onChange={(e) => setAttFilters({ ...attFilters, to: e.target.value })}
                  className="px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs text-muted"
                />
              </div>
              <button
                onClick={fetchAttendanceLogs}
                className="p-2 hover:bg-surface-soft border border-hairline rounded-lg text-body transition-all"
                title="Refresh Logs"
              >
                <RefreshCw className="w-4 h-4" />
              </button>
            </div>

            <div className="overflow-x-auto border border-hairline-soft rounded-lg">
              <table className="w-full border-collapse text-left text-xs">
                <thead>
                  <tr className="bg-surface-soft border-b border-hairline-soft text-muted font-semibold uppercase tracking-wider">
                    <th className="px-6 py-4">Date</th>
                    <th className="px-6 py-4">Employee</th>
                    <th className="px-6 py-4">Status</th>
                    <th className="px-6 py-4">Punch In</th>
                    <th className="px-6 py-4">Punch Out</th>
                    <th className="px-6 py-4">Total Hours</th>
                    <th className="px-6 py-4">IP / Loc</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-hairline-soft text-body">
                  {attendanceLogs.map((log) => (
                    <tr key={log._id} className="hover:bg-surface-soft/50 transition-colors">
                      <td className="px-6 py-4 font-semibold text-muted">{log.date}</td>
                      <td className="px-6 py-4">
                        <span className="font-semibold text-ink">{log.user?.username || 'Unknown'}</span>
                        <span className="block text-[10px] text-muted capitalize">{log.user?.user_type}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase ${log.status === 'Present' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : log.status === 'Late' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}`}>
                          {log.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 font-medium">
                        {log.punchIn ? new Date(log.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '-'}
                      </td>
                      <td className="px-6 py-4 font-medium">
                        {log.punchOut ? new Date(log.punchOut).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '-'}
                      </td>
                      <td className="px-6 py-4 font-semibold">{log.totalHours || '-'} hrs</td>
                      <td className="px-6 py-4">
                        <span className="block font-mono text-[9px] text-muted">{log.ip}</span>
                        {log.locationHistory && log.locationHistory.length > 0 && (
                          <button
                            onClick={() => setSelectedLogsLocation(log)}
                            className="text-[10px] text-accent font-semibold hover:underline flex items-center gap-1 mt-0.5"
                          >
                            <Eye className="w-3.5 h-3.5" /> Coordinates History ({log.locationHistory.length})
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                  {attendanceLogs.length === 0 && (
                    <tr>
                      <td colSpan="7" className="text-center py-10 text-muted font-semibold">No attendance entries matching filters.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* LEAVES TAB */}
        {activeTab === 'leaves' && (
          <div className="space-y-6">
            <div className="flex gap-4 items-center">
              <input
                type="text"
                placeholder="Search name..."
                value={leaveFilters.search}
                onChange={(e) => setLeaveFilters({ ...leaveFilters, search: e.target.value })}
                className="px-4 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs w-48 bg-transparent"
              />
              <select
                value={leaveFilters.status}
                onChange={(e) => setLeaveFilters({ ...leaveFilters, status: e.target.value })}
                className="px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs bg-transparent"
              >
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>

            <div className="overflow-x-auto border border-hairline-soft rounded-lg">
              <table className="w-full border-collapse text-left text-xs">
                <thead>
                  <tr className="bg-surface-soft border-b border-hairline-soft text-muted font-semibold uppercase tracking-wider">
                    <th className="px-6 py-4">Employee</th>
                    <th className="px-6 py-4">Leave Type</th>
                    <th className="px-6 py-4">Timeline</th>
                    <th className="px-6 py-4">Reason</th>
                    <th className="px-6 py-4 text-center">Status / Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-hairline-soft text-body">
                  {leaveRequests.map((reqItem) => (
                    <tr key={reqItem._id} className="hover:bg-surface-soft/50 transition-colors">
                      <td className="px-6 py-4">
                        <span className="font-semibold text-ink block">{reqItem.user?.username || 'Unknown'}</span>
                        <span className="block text-[10px] text-muted capitalize">{reqItem.user?.user_type}</span>
                      </td>
                      <td className="px-6 py-4 font-semibold text-accent">{reqItem.leaveType}</td>
                      <td className="px-6 py-4">
                        <span className="block font-semibold">
                          {new Date(reqItem.startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - {new Date(reqItem.endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </span>
                        <span className="block text-[10px] text-muted mt-0.5">{reqItem.leaveDays} days</span>
                      </td>
                      <td className="px-6 py-4 max-w-[200px] truncate text-muted" title={reqItem.reason}>
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
                              className="px-2 py-1 border border-hairline rounded-lg text-[10px] focus:outline-none w-44"
                            />
                            <div className="flex gap-2">
                              <button
                                onClick={() => handleLeaveDecision(reqItem._id, 'Approved')}
                                className="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold text-[10px] shadow-sm flex items-center gap-1 transition-all"
                              >
                                <Check className="w-3 h-3" /> Approve
                              </button>
                              <button
                                onClick={() => handleLeaveDecision(reqItem._id, 'Rejected')}
                                className="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-semibold text-[10px] shadow-sm flex items-center gap-1 transition-all"
                              >
                                <X className="w-3 h-3" /> Reject
                              </button>
                            </div>
                          </div>
                        ) : (
                          <div className="flex flex-col items-center">
                            <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase ${reqItem.status === 'Approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}`}>
                              {reqItem.status}
                            </span>
                            {reqItem.adminRemarks && (
                              <span className="block text-[9px] text-muted mt-1 max-w-[150px] truncate" title={reqItem.adminRemarks}>
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
                      <td colSpan="5" className="text-center py-10 text-muted font-semibold">No leave requests entries matching filters.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* PAYROLL TAB */}
        {activeTab === 'payroll' && (
          <div className="space-y-6">
            <div className="flex flex-wrap gap-4 items-center justify-between border-b border-hairline-soft pb-4">
              <div className="flex items-center gap-3">
                <label className="text-muted font-semibold text-xs uppercase">Run Payroll Month</label>
                <input
                  type="text"
                  placeholder="e.g. Jun 2026"
                  value={payrollMonth}
                  onChange={(e) => setPayrollMonth(e.target.value)}
                  className="px-3 py-1.5 border border-hairline rounded-lg focus:outline-none focus:border-ink text-xs w-36 text-center font-semibold"
                />
                <button
                  onClick={calculatePayroll}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-all shadow-md shadow-indigo-600/10 flex items-center gap-1.5"
                >
                  <RefreshCw className="w-3.5 h-3.5" /> Calculate Sheet
                </button>
              </div>

              {payrollCalculatedList.length > 0 && (
                <button
                  onClick={processPayroll}
                  disabled={payrollProcessing}
                  className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg text-xs font-semibold transition-all shadow-md shadow-emerald-600/15"
                >
                  {payrollProcessing ? 'Finalizing...' : 'Finalize & Save Payroll'}
                </button>
              )}
            </div>

            {/* Calculations Output */}
            {payrollCalculatedList.length > 0 ? (
              <div className="space-y-6">
                <div className="overflow-x-auto border border-hairline-soft rounded-lg">
                  <table className="w-full border-collapse text-left text-xs">
                    <thead>
                      <tr className="bg-surface-soft border-b border-hairline-soft text-muted font-semibold uppercase tracking-wider">
                        <th className="px-6 py-4">Employee ID</th>
                        <th className="px-6 py-4">Name</th>
                        <th className="px-6 py-4">Base Salary</th>
                        <th className="px-6 py-4">Punches</th>
                        <th className="px-6 py-4">LOP / Paid Days</th>
                        <th className="px-6 py-4">Net Payout</th>
                        <th className="px-6 py-4 text-right">Action</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-hairline-soft text-body font-medium">
                      {payrollCalculatedList.map((rec, i) => (
                        <tr key={i} className="hover:bg-surface-soft/50 transition-colors">
                          <td className="px-6 py-4 font-semibold text-muted">{rec.employee_id || '-'}</td>
                          <td className="px-6 py-4">
                            <span className="font-semibold text-ink block">{rec.username}</span>
                            <span className="block text-[10px] text-muted capitalize">{rec.user_type}</span>
                          </td>
                          <td className="px-6 py-4 font-semibold">₹{rec.baseSalary.toLocaleString()}</td>
                          <td className="px-6 py-4">{rec.presentDays} days</td>
                          <td className="px-6 py-4">
                            <span className="text-rose-600 font-semibold">{rec.lopDays} LOP</span> / <span className="text-emerald-600 font-semibold">{rec.paidDays} Paid</span>
                          </td>
                          <td className="px-6 py-4 font-semibold text-accent">₹{rec.netSalary.toLocaleString()}</td>
                          <td className="px-6 py-4 text-right">
                            <button
                              onClick={() => setViewingDraftPayslip(rec)}
                              className="px-3 py-1.5 hover:bg-surface-soft border border-hairline text-body rounded-lg text-[10px] font-semibold transition-all flex items-center gap-1 justify-end ml-auto"
                            >
                              <FileText className="w-3 h-3" /> View Statement
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            ) : (
              <div className="text-center py-20 text-muted font-semibold text-xs">
                Select month-year above and click Calculate Sheet to generate payroll drafts.
              </div>
            )}
          </div>
        )}

        {/* OFFER LETTERS TAB */}
        {activeTab === 'offers' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between pb-4 border-b border-hairline-soft">
              <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Candidate Offer Drafts</h3>
              <button
                onClick={() => setShowOfferModal(true)}
                className="px-4 py-2 bg-primary hover:bg-primary-active text-white rounded-lg text-xs font-semibold transition-all shadow-md shadow-card flex items-center gap-1.5"
              >
                <Plus className="w-4 h-4" /> Create Offer Letter
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {offers.map(offer => (
                <div key={offer._id} className="p-5 border border-hairline-soft rounded-lg space-y-4 transition-shadow relative">
                  <div className="flex items-start justify-between">
                    <div>
                      <h4 className="font-semibold text-ink text-xs">{offer.candidateName}</h4>
                      <p className="text-[10px] text-muted mt-1 capitalize">{offer.position} | {offer.department || 'Operations'}</p>
                    </div>
                    <span className={`px-2 py-0.5 rounded-full text-[9px] font-semibold uppercase ${offer.status === 'Sent' ? 'bg-surface-soft text-indigo-700' : offer.status === 'Accepted' ? 'bg-emerald-50 text-emerald-700' : 'bg-surface-soft text-body'}`}>
                      {offer.status}
                    </span>
                  </div>

                  <div className="text-xxs text-muted space-y-1">
                    <p><strong>CTC Salary:</strong> ₹{(offer.monthlySalary * 12).toLocaleString()} LPA (₹{offer.monthlySalary.toLocaleString()}/mo)</p>
                    <p><strong>Joining Date:</strong> {new Date(offer.joiningDate).toLocaleDateString('en-GB')}</p>
                    {offer.emailedAt && (
                      <p className="text-muted"><strong>Sent:</strong> {new Date(offer.emailedAt).toLocaleDateString('en-GB')} by {offer.emailedBy}</p>
                    )}
                  </div>

                  <div className="flex gap-2 pt-2 border-t border-hairline-soft justify-between items-center">
                    <button
                      onClick={() => setPreviewingOffer(offer)}
                      className="text-[10px] text-ink font-semibold hover:underline flex items-center gap-1"
                    >
                      <Eye className="w-3.5 h-3.5" /> Preview Document
                    </button>

                    <div className="flex gap-2">
                      {offer.status === 'Draft' && (
                        <button
                          onClick={() => handleSendOffer(offer._id)}
                          className="px-2.5 py-1 bg-surface-soft hover:bg-indigo-100 text-indigo-700 rounded-lg text-[10px] font-semibold transition-all flex items-center gap-1"
                        >
                          <Mail className="w-3 h-3" /> Email Offer
                        </button>
                      )}
                      <button
                        onClick={() => handleDeleteOffer(offer._id)}
                        className="p-1 hover:bg-rose-50 text-rose-600 rounded-lg transition-colors"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
              {offers.length === 0 && (
                <p className="text-center col-span-2 py-20 text-muted font-semibold text-xs">No candidate offer letters created yet.</p>
              )}
            </div>
          </div>
        )}

        {/* ASSETS & FNF TAB */}
        {activeTab === 'assets' && (
          <div className="space-y-8 animate-fade-in">
            {/* Top widgets for assets catalog */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {/* Asset Catalog */}
              <div className="space-y-4">
                <div className="flex justify-between items-center">
                  <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Asset Registry</h3>
                  <button
                    onClick={() => setShowAssetModal(true)}
                    className="px-3 py-1.5 bg-surface-dark-elevated hover:bg-surface-strong text-white rounded-lg text-xs font-semibold transition-colors"
                  >
                    + Add Hardware
                  </button>
                </div>

                <div className="border border-hairline-soft rounded-lg max-h-60 overflow-y-auto divide-y divide-hairline-soft">
                  {assets.map(asset => (
                    <div key={asset._id} className="p-3 flex items-center justify-between text-xs hover:bg-surface-soft">
                      <div>
                        <span className="font-semibold text-ink">{asset.name}</span>
                        <span className="block text-[10px] text-muted">{asset.type} | SN: {asset.serialNumber}</span>
                      </div>
                      <span className={`px-2 py-0.5 rounded-full text-[9px] font-semibold uppercase ${asset.status === 'Available' ? 'bg-emerald-50 text-emerald-700' : 'bg-surface-soft text-body'}`}>
                        {asset.status}
                      </span>
                    </div>
                  ))}
                  {assets.length === 0 && (
                    <p className="text-center py-10 text-muted text-xs">No assets in registry.</p>
                  )}
                </div>
              </div>

              {/* Assignment Form */}
              <div className="space-y-4">
                <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Assign Asset</h3>
                <form onSubmit={handleAssignAsset} className="space-y-3 p-4 border border-hairline-soft rounded-lg bg-surface-soft/50">
                  <div>
                    <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Select Available Hardware</label>
                    <select
                      value={assignForm.assetId}
                      required
                      onChange={(e) => setAssignForm({ ...assignForm, assetId: e.target.value })}
                      className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink bg-canvas"
                    >
                      <option value="">Select Asset</option>
                      {assets.filter(a => a.status === 'Available').map(a => (
                        <option key={a._id} value={a._id}>{a.name} ({a.serialNumber})</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Assign To Employee</label>
                    <select
                      value={assignForm.userId}
                      required
                      onChange={(e) => setAssignForm({ ...assignForm, userId: e.target.value })}
                      className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink bg-canvas"
                    >
                      <option value="">Select User</option>
                      {employees.filter(emp => emp.user_type !== 'superuseradmin').map(emp => (
                        <option key={emp._id} value={emp._id}>{emp.username} ({emp.employee_id})</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Notes</label>
                    <input
                      type="text"
                      placeholder="Mouse, charger, key config..."
                      value={assignForm.notes}
                      onChange={(e) => setAssignForm({ ...assignForm, notes: e.target.value })}
                      className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink bg-canvas"
                    />
                  </div>
                  <button
                    type="submit"
                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-colors shadow-sm"
                  >
                    Confirm Checkout
                  </button>
                </form>
              </div>
            </div>

            {/* Assignments Registry */}
            <div className="space-y-4">
              <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Active Device Assignments</h3>
              <div className="overflow-x-auto border border-hairline-soft rounded-lg">
                <table className="w-full border-collapse text-left text-xs">
                  <thead>
                    <tr className="bg-surface-soft border-b border-hairline-soft text-muted font-semibold uppercase tracking-wider">
                      <th className="px-6 py-4">Hardware Details</th>
                      <th className="px-6 py-4">Employee</th>
                      <th className="px-6 py-4">Checkout Date</th>
                      <th className="px-6 py-4">Notes</th>
                      <th className="px-6 py-4 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-hairline-soft text-body font-medium">
                    {assignments.map(as => (
                      <tr key={as._id} className="hover:bg-surface-soft/50 transition-colors">
                        <td className="px-6 py-4">
                          <span className="font-semibold text-ink block">{as.asset?.name}</span>
                          <span className="block text-[10px] text-muted">{as.asset?.type} | SN: {as.asset?.serialNumber}</span>
                        </td>
                        <td className="px-6 py-4">
                          <span className="font-semibold text-ink block">{as.user?.username}</span>
                          <span className="block text-[10px] text-muted">{as.user?.employee_id}</span>
                        </td>
                        <td className="px-6 py-4">{new Date(as.assignedDate).toLocaleDateString('en-GB')}</td>
                        <td className="px-6 py-4 max-w-[150px] truncate text-muted" title={as.notes}>{as.notes || '-'}</td>
                        <td className="px-6 py-4 text-right">
                          <button
                            onClick={() => handleReturnAsset(as._id)}
                            className="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-100 rounded-lg text-[10px] font-semibold transition-all"
                          >
                            Mark Returned
                          </button>
                        </td>
                      </tr>
                    ))}
                    {assignments.length === 0 && (
                      <tr>
                        <td colSpan="5" className="text-center py-10 text-muted font-semibold">No active hardware checkouts found.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* FNF SETTLEMENT CONTAINER */}
            <div className="space-y-4 pt-6 border-t border-hairline-soft">
              <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Employee Full & Final (FNF) Settlement</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <label className="text-muted font-semibold text-xs uppercase shrink-0">Select Resigning Employee</label>
                    <select
                      value={selectedFNFUser}
                      onChange={(e) => {
                        setSelectedFNFUser(e.target.value);
                        handleFetchFNF(e.target.value);
                      }}
                      className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink bg-canvas"
                    >
                      <option value="">Select Employee</option>
                      {employees.filter(emp => emp.user_type !== 'superuseradmin').map(emp => (
                        <option key={emp._id} value={emp._id}>{emp.username} ({emp.employee_id})</option>
                      ))}
                    </select>
                  </div>

                  {fnfCalculation && (
                    <div className="space-y-3 p-4 bg-surface-soft border border-hairline-soft rounded-lg text-xs">
                      <h4 className="font-semibold text-ink uppercase text-[10px] tracking-wider mb-2">Inventory & Audit Clearance</h4>
                      <p><strong>Employee:</strong> {fnfCalculation.user.username} ({fnfCalculation.user.employee_id})</p>
                      <p><strong>Monthly Payout CTC:</strong> ₹{fnfCalculation.user.salary.toLocaleString()}</p>
                      
                      {fnfCalculation.pendingAssetsCount > 0 ? (
                        <div className="p-3 bg-amber-50 border border-amber-100 rounded-lg text-amber-800 font-semibold flex items-center gap-2 mt-1 animate-shake">
                          <AlertCircle className="w-4 h-4 shrink-0 text-warning" />
                          Employee has {fnfCalculation.pendingAssetsCount} unreturned device(s) checked out!
                        </div>
                      ) : (
                        <div className="p-3 bg-emerald-50 border border-emerald-100 rounded-lg text-emerald-800 font-semibold flex items-center gap-2 mt-1">
                          <Check className="w-4 h-4 shrink-0 text-success" />
                          Zero pending hardware checked out. Inventory Clear.
                        </div>
                      )}
                    </div>
                  )}
                </div>

                {fnfCalculation && (
                  <form onSubmit={handleSaveFNF} className="space-y-4 p-5 border border-hairline-soft rounded-lg bg-canvas shadow-sm">
                    <h4 className="font-semibold text-ink text-xs uppercase mb-2">Final Settlement Statements</h4>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Last Working Day</label>
                        <input
                          type="date"
                          required
                          value={fnfForm.lastWorkingDay}
                          onChange={(e) => setFnfForm({ ...fnfForm, lastWorkingDay: e.target.value })}
                          className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink bg-canvas"
                        />
                      </div>
                      <div>
                        <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Unpaid Salary (INR)</label>
                        <input
                          type="number"
                          value={fnfForm.unpaidSalary}
                          onChange={(e) => setFnfForm({ ...fnfForm, unpaidSalary: parseFloat(e.target.value) || 0 })}
                          onBlur={calculateFNFNet}
                          className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Leave Encashment (INR)</label>
                        <input
                          type="number"
                          value={fnfForm.leaveEncashment}
                          onChange={(e) => setFnfForm({ ...fnfForm, leaveEncashment: parseFloat(e.target.value) || 0 })}
                          onBlur={calculateFNFNet}
                          className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Bonus & Incentives (INR)</label>
                        <input
                          type="number"
                          value={fnfForm.bonusIncentives}
                          onChange={(e) => setFnfForm({ ...fnfForm, bonusIncentives: parseFloat(e.target.value) || 0 })}
                          onBlur={calculateFNFNet}
                          className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Deductions (INR)</label>
                        <input
                          type="number"
                          value={fnfForm.deductions}
                          onChange={(e) => setFnfForm({ ...fnfForm, deductions: parseFloat(e.target.value) || 0 })}
                          onBlur={calculateFNFNet}
                          className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Status</label>
                        <select
                          value={fnfForm.status}
                          onChange={(e) => setFnfForm({ ...fnfForm, status: e.target.value })}
                          className="w-full border border-hairline rounded-lg px-3 py-2 text-xs bg-transparent"
                        >
                          <option value="Pending">Pending</option>
                          <option value="Settled">Settled</option>
                        </select>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 mt-2">
                      <input
                        type="checkbox"
                        id="assetsReturned"
                        checked={fnfForm.assetsReturned}
                        onChange={(e) => setFnfForm({ ...fnfForm, assetsReturned: e.target.checked })}
                        className="rounded border-hairline text-accent focus:ring-ink"
                      />
                      <label htmlFor="assetsReturned" className="text-[10px] font-semibold text-body cursor-pointer select-none">
                        Audit Clear: All checked out devices returned
                      </label>
                    </div>

                    <div className="flex items-center justify-between pt-4 border-t border-hairline-soft mt-4">
                      <div>
                        <span className="block text-[9px] font-semibold text-muted uppercase">Net Settlement Amount</span>
                        <span className="block text-lg font-semibold text-accent">₹{fnfForm.netSettlement.toLocaleString()}</span>
                      </div>
                      <button
                        type="submit"
                        className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm"
                      >
                        Save Settlement
                      </button>
                    </div>
                  </form>
                )}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Draft Calculation Statement Modal */}
      {viewingDraftPayslip && (
        <div className="fixed inset-0 bg-surface-dark/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-canvas border border-hairline rounded-3xl max-w-2xl w-full p-6 shadow-elevated overflow-y-auto max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between border-b border-hairline-soft pb-4 mb-6">
              <h3 className="font-semibold text-ink text-xs uppercase">Draft Salary Statement ({payrollMonth})</h3>
              <button
                onClick={() => setViewingDraftPayslip(null)}
                className="px-3 py-1.5 hover:bg-surface-soft border border-hairline text-muted rounded-lg text-xs font-semibold transition-all"
              >
                Close
              </button>
            </div>

            <div className="border border-hairline p-6 rounded-lg text-xs space-y-6 text-body bg-surface-soft/50">
              <div className="text-center border-b border-hairline pb-4">
                <h2 className="font-semibold text-ink text-sm uppercase">Draft Payroll Sheet</h2>
                <p className="text-[10px] text-muted mt-0.5">{viewingDraftPayslip.username} ({viewingDraftPayslip.employee_id})</p>
              </div>

              <div className="grid grid-cols-2 gap-4 border-b border-hairline pb-4 font-semibold text-[11px]">
                <div>
                  <p className="text-muted">Basic Payout CTC</p>
                  <p className="text-ink font-semibold mt-0.5">₹{viewingDraftPayslip.baseSalary.toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-muted">Attendance Days</p>
                  <p className="text-ink font-semibold mt-0.5">{viewingDraftPayslip.presentDays} Present / {viewingDraftPayslip.lopDays} LOP Days</p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-6">
                <div>
                  <h4 className="font-semibold text-indigo-700 uppercase tracking-wider text-[10px] border-b border-hairline pb-1.5 mb-3">Earnings Breakdown</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>Basic Salary (50%)</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.basic.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>House Rent Allowance (HRA)</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.hra.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Conveyance Allowance</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.conveyance.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Special Allowance</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.special.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>PF Employer Part</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.pfEmployer.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between border-t border-hairline pt-2 font-semibold text-ink">
                      <span>Gross Payout</span>
                      <span>₹{viewingDraftPayslip.payslipData.earnings.gross.toLocaleString()}</span>
                    </div>
                  </div>
                </div>

                <div>
                  <h4 className="font-semibold text-rose-700 uppercase tracking-wider text-[10px] border-b border-hairline pb-1.5 mb-3">Deductions Breakdown</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>PF (Employee Part)</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Professional Tax (PT)</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.deductions.professionalTax.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Medical Benefit</span>
                      <span className="font-semibold">₹{viewingDraftPayslip.payslipData.deductions.medical.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between border-t border-hairline pt-2 font-semibold text-ink">
                      <span>Total Deductions</span>
                      <span>₹{viewingDraftPayslip.payslipData.deductions.total.toLocaleString()}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div className="border-t border-hairline pt-4 flex items-center justify-between bg-surface-soft border border-hairline-soft p-4 rounded-lg">
                <div>
                  <span className="text-[10px] font-semibold text-indigo-400 uppercase tracking-widest block">Net Salary Payout</span>
                  <span className="text-xl font-semibold text-indigo-700 mt-1 block">₹{viewingDraftPayslip.netSalary.toLocaleString()}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Offer Letter Document Preview Modal */}
      {previewingOffer && (
        <div className="fixed inset-0 bg-surface-dark/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-canvas border border-hairline rounded-3xl max-w-3xl w-full p-6 shadow-elevated overflow-y-auto max-h-[95vh] flex flex-col">
            <div className="flex items-center justify-between border-b border-hairline-soft pb-4 mb-4">
              <h3 className="font-semibold text-ink text-xs uppercase">Offer Letter Preview</h3>
              <button
                onClick={() => setPreviewingOffer(null)}
                className="px-3 py-1.5 hover:bg-surface-soft border border-hairline text-muted rounded-lg text-xs font-semibold transition-all"
              >
                Close
              </button>
            </div>
            
            {/* Offer content frame */}
            <div className="border border-hairline p-8 rounded-lg bg-canvas shadow-inner overflow-y-auto max-h-[65vh]">
              <div dangerouslySetInnerHTML={{ __html: previewingOffer.customHtml }} />
            </div>
            
            <div className="flex justify-end gap-3 pt-4 border-t border-hairline-soft mt-4">
              <button
                onClick={() => window.print()}
                className="px-4 py-2 border border-hairline hover:bg-surface-soft text-body rounded-lg text-xs font-semibold transition-colors"
              >
                Print PDF
              </button>
              {previewingOffer.status === 'Draft' && (
                <button
                  onClick={() => {
                    handleSendOffer(previewingOffer._id);
                    setPreviewingOffer(null);
                  }}
                  className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors"
                >
                  Send & Mark Emailed
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Add Asset Modal */}
      {showAssetModal && (
        <div className="fixed inset-0 bg-surface-dark/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-canvas border border-hairline rounded-3xl max-w-md w-full p-6 shadow-elevated">
            <div className="flex items-center justify-between border-b border-hairline-soft pb-4 mb-6">
              <h3 className="font-semibold text-ink text-sm uppercase">Add New Asset</h3>
              <button onClick={() => setShowAssetModal(false)} className="p-1.5 hover:bg-surface-soft rounded-lg text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleAddAsset} className="space-y-4">
              <div>
                <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Asset Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. MacBook Pro M3"
                  value={assetForm.name}
                  onChange={(e) => setAssetForm({ ...assetForm, name: e.target.value })}
                  className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink"
                />
              </div>
              <div>
                <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Asset Type</label>
                <select
                  value={assetForm.type}
                  onChange={(e) => setAssetForm({ ...assetForm, type: e.target.value })}
                  className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink bg-transparent"
                >
                  <option value="Laptop">Laptop</option>
                  <option value="Mobile">Mobile</option>
                  <option value="Accessory">Accessory</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Serial Number</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. C02XG123XYZ"
                  value={assetForm.serialNumber}
                  onChange={(e) => setAssetForm({ ...assetForm, serialNumber: e.target.value })}
                  className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink"
                />
              </div>
              <div className="flex justify-end gap-3 pt-4 border-t border-hairline-soft">
                <button
                  type="button"
                  onClick={() => setShowAssetModal(false)}
                  className="px-4 py-2 border border-hairline hover:bg-surface-soft text-body rounded-lg text-xs font-semibold transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-surface-dark-elevated hover:bg-surface-strong text-white rounded-lg text-xs font-semibold transition-all"
                >
                  Save to Registry
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Create Offer Modal */}
      {showOfferModal && (
        <div className="fixed inset-0 bg-surface-dark/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-canvas border border-hairline rounded-3xl max-w-2xl w-full p-6 shadow-elevated max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-hairline-soft pb-4 mb-6">
              <h3 className="font-semibold text-ink text-sm uppercase">Generate Offer Letter</h3>
              <button onClick={() => setShowOfferModal(false)} className="p-1.5 hover:bg-surface-soft rounded-lg text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleCreateOffer} className="space-y-4 text-xs font-semibold text-body">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Candidate Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="Candidate Name"
                    value={offerForm.candidateName}
                    onChange={(e) => setOfferForm({ ...offerForm, candidateName: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Email Address *</label>
                  <input
                    type="email"
                    required
                    placeholder="candidate@email.com"
                    value={offerForm.email}
                    onChange={(e) => setOfferForm({ ...offerForm, email: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Phone Number *</label>
                  <input
                    type="text"
                    required
                    placeholder="9876543210"
                    value={offerForm.phone}
                    onChange={(e) => setOfferForm({ ...offerForm, phone: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Job Title / Position *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Sales Executive"
                    value={offerForm.position}
                    onChange={(e) => setOfferForm({ ...offerForm, position: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Department</label>
                  <input
                    type="text"
                    placeholder="e.g. Sales, Marketing"
                    value={offerForm.department}
                    onChange={(e) => setOfferForm({ ...offerForm, department: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Monthly Salary CTC (INR) *</label>
                  <input
                    type="number"
                    required
                    placeholder="e.g. 35000"
                    value={offerForm.monthlySalary}
                    onChange={(e) => setOfferForm({ ...offerForm, monthlySalary: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Joining Date *</label>
                  <input
                    type="date"
                    required
                    value={offerForm.joiningDate}
                    onChange={(e) => setOfferForm({ ...offerForm, joiningDate: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none text-muted"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Reporting Manager</label>
                  <input
                    type="text"
                    placeholder="Manager Name"
                    value={offerForm.reportingManager}
                    onChange={(e) => setOfferForm({ ...offerForm, reportingManager: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-4 border-t border-hairline-soft">
                <button
                  type="button"
                  onClick={() => setShowOfferModal(false)}
                  className="px-4 py-2 border border-hairline hover:bg-surface-soft text-body rounded-lg text-xs font-semibold transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-primary hover:bg-primary-active text-white rounded-lg text-xs font-semibold shadow-md shadow-card transition-all"
                >
                  Generate & Save
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* View logs details location modal */}
      {selectedLogsLocation && (
        <div className="fixed inset-0 bg-surface-dark/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-canvas border border-hairline rounded-3xl max-w-lg w-full p-6 shadow-elevated max-h-[80vh] flex flex-col">
            <div className="flex items-center justify-between border-b border-hairline-soft pb-4 mb-4">
              <h3 className="font-semibold text-ink text-xs uppercase">GPS Coordinates Log ({selectedLogsLocation.date})</h3>
              <button
                onClick={() => setSelectedLogsLocation(null)}
                className="px-3 py-1.5 hover:bg-surface-soft border border-hairline text-muted rounded-lg text-xs font-semibold transition-all"
              >
                Close
              </button>
            </div>
            <div className="flex-1 overflow-y-auto space-y-2 pr-1 text-xs">
              <p className="font-semibold mb-3 text-muted">Employee: {selectedLogsLocation.user?.username}</p>
              {selectedLogsLocation.locationHistory.map((loc, idx) => (
                <div key={idx} className="p-2 border border-hairline-soft rounded-lg hover:bg-surface-soft flex items-center justify-between text-xxs font-mono">
                  <span>GPS: {loc.latitude.toFixed(6)}, {loc.longitude.toFixed(6)}</span>
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

export default HRPortal;
