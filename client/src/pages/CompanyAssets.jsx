import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, Plus, Trash2, X, Check, AlertCircle } from 'lucide-react';

const CompanyAssets = () => {
  const [employees, setEmployees] = useState([]);
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
    fetchAssets();
    fetchAssignments();
  }, []);

  const fetchEmployees = async () => {
    try {
      const res = await axios.get('/api/users?limit=1000');
      setEmployees(res.data.data);
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
    <div className="space-y-8">
      {/* Page Header */}
      <div className="bg-gradient-to-r from-slate-900 via-brand-950 to-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-[10px] uppercase tracking-widest text-brand-400 font-extrabold">Inventory Portal</span>
          <h1 className="text-2xl font-black text-white mt-1">Company Assets & FNF</h1>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        {/* Asset Catalog */}
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="text-slate-700 dark:text-white font-extrabold text-sm uppercase tracking-wider">Asset Registry</h3>
            <button
              onClick={() => setShowAssetModal(true)}
              className="px-3 py-1.5 bg-slate-800 dark:bg-slate-700 hover:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-xl text-xs font-bold transition-colors"
            >
              + Add Hardware
            </button>
          </div>

          <div className="border border-slate-100 dark:border-slate-800 rounded-2xl max-h-60 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
            {assets.map(asset => (
              <div key={asset._id} className="p-3 flex items-center justify-between text-xs hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <div>
                  <span className="font-extrabold text-slate-800 dark:text-white block">{asset.name}</span>
                  <span className="block text-[10px] text-slate-400 dark:text-slate-500">{asset.type} | SN: {asset.serialNumber}</span>
                </div>
                <span className={`px-2 py-0.5 rounded-full text-[9px] font-bold uppercase ${asset.status === 'Available' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}`}>
                  {asset.status}
                </span>
              </div>
            ))}
            {assets.length === 0 && (
              <p className="text-center py-10 text-slate-400 dark:text-slate-500 text-xs font-semibold">No assets in registry.</p>
            )}
          </div>
        </div>

        {/* Assignment Form */}
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
          <h3 className="text-slate-700 dark:text-white font-extrabold text-sm uppercase tracking-wider">Assign Asset</h3>
          <form onSubmit={handleAssignAsset} className="space-y-3 p-4 border border-slate-100 dark:border-slate-800 rounded-2xl bg-slate-50/50 dark:bg-slate-950/40">
            <div>
              <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Select Available Hardware</label>
              <select
                value={assignForm.assetId}
                required
                onChange={(e) => setAssignForm({ ...assignForm, assetId: e.target.value })}
                className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-white dark:bg-slate-900 dark:text-white"
              >
                <option value="">Select Asset</option>
                {assets.filter(a => a.status === 'Available').map(a => (
                  <option key={a._id} value={a._id}>{a.name} ({a.serialNumber})</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Assign To Employee</label>
              <select
                value={assignForm.userId}
                required
                onChange={(e) => setAssignForm({ ...assignForm, userId: e.target.value })}
                className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-white dark:bg-slate-900 dark:text-white"
              >
                <option value="">Select User</option>
                {employees.filter(emp => emp.user_type !== 'superuseradmin').map(emp => (
                  <option key={emp._id} value={emp._id}>{emp.username} ({emp.employee_id})</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Notes</label>
              <input
                type="text"
                placeholder="Mouse, charger, key config..."
                value={assignForm.notes}
                onChange={(e) => setAssignForm({ ...assignForm, notes: e.target.value })}
                className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-white dark:bg-slate-900 dark:text-white"
              />
            </div>
            <button
              type="submit"
              className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-colors shadow-sm"
            >
              Confirm Checkout
            </button>
          </form>
        </div>
      </div>

      {/* Assignments Registry */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="text-slate-700 dark:text-white font-extrabold text-sm uppercase tracking-wider">Active Device Assignments</h3>
        <div className="overflow-x-auto border border-slate-100 dark:border-slate-800 rounded-2xl">
          <table className="w-full border-collapse text-left text-xs">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                <th className="px-6 py-4">Hardware Details</th>
                <th className="px-6 py-4">Employee</th>
                <th className="px-6 py-4">Checkout Date</th>
                <th className="px-6 py-4">Notes</th>
                <th className="px-6 py-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300 font-medium">
              {assignments.map(as => (
                <tr key={as._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                  <td className="px-6 py-4">
                    <span className="font-extrabold text-slate-800 dark:text-white block">{as.asset?.name}</span>
                    <span className="block text-[10px] text-slate-400 dark:text-slate-500">{as.asset?.type} | SN: {as.asset?.serialNumber}</span>
                  </td>
                  <td className="px-6 py-4">
                    <span className="font-extrabold text-slate-800 dark:text-white block capitalize">{as.user?.username}</span>
                    <span className="block text-[10px] text-slate-400 dark:text-slate-500">{as.user?.employee_id}</span>
                  </td>
                  <td className="px-6 py-4">{new Date(as.assignedDate).toLocaleDateString('en-GB')}</td>
                  <td className="px-6 py-4 max-w-[150px] truncate text-slate-500 dark:text-slate-400 font-normal" title={as.notes}>{as.notes || '-'}</td>
                  <td className="px-6 py-4 text-right">
                    <button
                      onClick={() => handleReturnAsset(as._id)}
                      className="px-2.5 py-1.5 bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-400 border border-rose-100 dark:border-rose-900/50 rounded-lg text-[10px] font-bold transition-all"
                    >
                      Mark Returned
                    </button>
                  </td>
                </tr>
              ))}
              {assignments.length === 0 && (
                <tr>
                  <td colSpan="5" className="text-center py-10 text-slate-400 dark:text-slate-500 font-semibold">No active hardware checkouts found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* FNF SETTLEMENT CONTAINER */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="text-slate-700 dark:text-white font-extrabold text-sm uppercase tracking-wider">Employee Full & Final (FNF) Settlement</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <label className="text-slate-500 dark:text-slate-400 text-xs font-bold uppercase shrink-0">Select Resigning Employee</label>
              <select
                value={selectedFNFUser}
                onChange={(e) => {
                  setSelectedFNFUser(e.target.value);
                  handleFetchFNF(e.target.value);
                }}
                className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-white dark:bg-slate-900 dark:text-white"
              >
                <option value="">Select Employee</option>
                {employees.filter(emp => emp.user_type !== 'superuseradmin').map(emp => (
                  <option key={emp._id} value={emp._id}>{emp.username} ({emp.employee_id})</option>
                ))}
              </select>
            </div>

            {fnfCalculation && (
              <div className="space-y-3 p-4 bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800 rounded-2xl text-xs text-slate-700 dark:text-slate-300">
                <h4 className="font-extrabold text-slate-800 dark:text-white uppercase text-[10px] tracking-wider mb-2">Inventory & Audit Clearance</h4>
                <p><strong>Employee:</strong> {fnfCalculation.user.username} ({fnfCalculation.user.employee_id})</p>
                <p><strong>Monthly Payout CTC:</strong> ₹{fnfCalculation.user.salary.toLocaleString()}</p>
                
                {fnfCalculation.pendingAssetsCount > 0 ? (
                  <div className="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/50 rounded-xl text-amber-800 dark:text-amber-400 font-bold flex items-center gap-2 mt-1">
                    <AlertCircle className="w-4 h-4 shrink-0 text-amber-500" />
                    Employee has {fnfCalculation.pendingAssetsCount} unreturned device(s) checked out!
                  </div>
                ) : (
                  <div className="p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/50 rounded-xl text-emerald-800 dark:text-emerald-400 font-bold flex items-center gap-2 mt-1">
                    <Check className="w-4 h-4 shrink-0 text-emerald-500" />
                    Zero pending hardware checked out. Inventory Clear.
                  </div>
                )}
              </div>
            )}
          </div>

          {fnfCalculation && (
            <form onSubmit={handleSaveFNF} className="space-y-4 p-5 border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-950/20 shadow-sm text-slate-600 dark:text-slate-400">
              <h4 className="font-extrabold text-slate-800 dark:text-white text-xs uppercase mb-2">Final Settlement Statements</h4>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Last Working Day</label>
                  <input
                    type="date"
                    required
                    value={fnfForm.lastWorkingDay}
                    onChange={(e) => setFnfForm({ ...fnfForm, lastWorkingDay: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-white dark:bg-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Unpaid Salary (INR)</label>
                  <input
                    type="number"
                    value={fnfForm.unpaidSalary}
                    onChange={(e) => setFnfForm({ ...fnfForm, unpaidSalary: parseFloat(e.target.value) || 0 })}
                    onBlur={calculateFNFNet}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Leave Encashment (INR)</label>
                  <input
                    type="number"
                    value={fnfForm.leaveEncashment}
                    onChange={(e) => setFnfForm({ ...fnfForm, leaveEncashment: parseFloat(e.target.value) || 0 })}
                    onBlur={calculateFNFNet}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Bonus & Incentives (INR)</label>
                  <input
                    type="number"
                    value={fnfForm.bonusIncentives}
                    onChange={(e) => setFnfForm({ ...fnfForm, bonusIncentives: parseFloat(e.target.value) || 0 })}
                    onBlur={calculateFNFNet}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Deductions (INR)</label>
                  <input
                    type="number"
                    value={fnfForm.deductions}
                    onChange={(e) => setFnfForm({ ...fnfForm, deductions: parseFloat(e.target.value) || 0 })}
                    onBlur={calculateFNFNet}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Status</label>
                  <select
                    value={fnfForm.status}
                    onChange={(e) => setFnfForm({ ...fnfForm, status: e.target.value })}
                    className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs bg-white dark:bg-slate-900 dark:text-white"
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
                  className="rounded border-slate-300 text-brand-500 focus:ring-brand-500 dark:bg-slate-800"
                />
                <label htmlFor="assetsReturned" className="text-[10px] font-bold text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                  Audit Clear: All checked out devices returned
                </label>
              </div>

              <div className="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800 mt-4">
                <div>
                  <span className="block text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase">Net Settlement Amount</span>
                  <span className="block text-lg font-black text-indigo-600 dark:text-indigo-400">₹{fnfForm.netSettlement.toLocaleString()}</span>
                </div>
                <button
                  type="submit"
                  className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black shadow-sm"
                >
                  Save Settlement
                </button>
              </div>
            </form>
          )}
        </div>
      </div>

      {/* Add Asset Modal */}
      {showAssetModal && (
        <div className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-6">
              <h3 className="font-extrabold text-slate-800 dark:text-white text-sm uppercase">Add New Asset</h3>
              <button onClick={() => setShowAssetModal(false)} className="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-400">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleAddAsset} className="space-y-4">
              <div>
                <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Asset Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. MacBook Pro M3"
                  value={assetForm.name}
                  onChange={(e) => setAssetForm({ ...assetForm, name: e.target.value })}
                  className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-transparent dark:text-white"
                />
              </div>
              <div>
                <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Asset Type</label>
                <select
                  value={assetForm.type}
                  onChange={(e) => setAssetForm({ ...assetForm, type: e.target.value })}
                  className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-transparent dark:text-white"
                >
                  <option value="Laptop">Laptop</option>
                  <option value="Mobile">Mobile</option>
                  <option value="Accessory">Accessory</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label className="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase mb-1">Serial Number</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. C02XG123XYZ"
                  value={assetForm.serialNumber}
                  onChange={(e) => setAssetForm({ ...assetForm, serialNumber: e.target.value })}
                  className="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500 bg-transparent dark:text-white"
                />
              </div>
              <div className="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAssetModal(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-xs font-bold transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-slate-800 dark:bg-slate-700 hover:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-xl text-xs font-bold transition-all"
                >
                  Save to Registry
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default CompanyAssets;
