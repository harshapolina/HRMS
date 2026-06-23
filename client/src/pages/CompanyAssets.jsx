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
    <div className="page-shell space-y-8">
      {/* Page Header */}
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Inventory Portal</p>
          <h1 className="page-title">Company Assets &amp; FNF</h1>
          <p className="page-subtitle">Manage hardware registry, device checkouts, and full &amp; final employee settlements.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        {/* Asset Catalog */}
        <div className="bg-canvas border border-hairline-soft rounded-lg p-6 shadow-sm space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Asset Registry</h3>
            <button
              id="asset-add-btn"
              onClick={() => setShowAssetModal(true)}
              className="btn-secondary btn-sm"
            >
              + Add Hardware
            </button>
          </div>

          <div className="border border-hairline-soft rounded-lg max-h-60 overflow-y-auto divide-y divide-hairline-soft">
            {assets.map(asset => (
              <div key={asset._id} className="p-3 flex items-center justify-between text-xs hover:bg-surface-soft">
                <div>
                  <span className="font-semibold text-ink block">{asset.name}</span>
                  <span className="block text-[10px] text-muted">{asset.type} | SN: {asset.serialNumber}</span>
                </div>
                <span className={`${asset.status === 'Available' ? 'badge-success' : 'badge-neutral'} uppercase text-[9px]`}>
                  {asset.status}
                </span>
              </div>
            ))}
            {assets.length === 0 && (
              <p className="text-center py-10 text-muted text-xs font-semibold">No assets in registry.</p>
            )}
          </div>
        </div>

        {/* Assignment Form */}
        <div className="bg-canvas border border-hairline-soft rounded-lg p-6 shadow-sm space-y-4">
          <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Assign Asset</h3>
          <form onSubmit={handleAssignAsset} className="space-y-3 p-4 border border-hairline-soft rounded-lg bg-surface-soft/50">
            <div>
              <label className="label-xs" htmlFor="assign-asset-select">Select Available Hardware</label>
              <select
                id="assign-asset-select"
                value={assignForm.assetId}
                required
                onChange={(e) => setAssignForm({ ...assignForm, assetId: e.target.value })}
                className="select-field text-xs"
              >
                <option value="">Select Asset</option>
                {assets.filter(a => a.status === 'Available').map(a => (
                  <option key={a._id} value={a._id}>{a.name} ({a.serialNumber})</option>
                ))}
              </select>
            </div>
            <div>
              <label className="label-xs" htmlFor="assign-user-select">Assign To Employee</label>
              <select
                id="assign-user-select"
                value={assignForm.userId}
                required
                onChange={(e) => setAssignForm({ ...assignForm, userId: e.target.value })}
                className="select-field text-xs"
              >
                <option value="">Select User</option>
                {employees.filter(emp => emp.user_type !== 'superuseradmin').map(emp => (
                  <option key={emp._id} value={emp._id}>{emp.username} ({emp.employee_id})</option>
                ))}
              </select>
            </div>
            <div>
              <label className="label-xs" htmlFor="assign-notes-input">Notes</label>
              <input
                id="assign-notes-input"
                type="text"
                placeholder="Mouse, charger, key config..."
                value={assignForm.notes}
                onChange={(e) => setAssignForm({ ...assignForm, notes: e.target.value })}
                className="input-field text-xs"
              />
            </div>
            <button
              id="assign-asset-submit-btn"
              type="submit"
              className="btn-primary btn-sm"
            >
              Confirm Checkout
            </button>
          </form>
        </div>
      </div>

      {/* Assignments Registry */}
      <div className="bg-canvas border border-hairline-soft rounded-lg p-6 shadow-sm space-y-4">
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
                    <span className="font-semibold text-ink block capitalize">{as.user?.username}</span>
                    <span className="block text-[10px] text-muted">{as.user?.employee_id}</span>
                  </td>
                  <td className="px-6 py-4">{new Date(as.assignedDate).toLocaleDateString('en-GB')}</td>
                  <td className="px-6 py-4 max-w-[150px] truncate text-muted font-normal" title={as.notes}>{as.notes || '-'}</td>
                  <td className="px-6 py-4 text-right">
                    <button
                      id={`return-asset-btn-${as._id}`}
                      onClick={() => handleReturnAsset(as._id)}
                      className="btn-danger"
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
      <div className="bg-canvas border border-hairline-soft rounded-lg p-6 shadow-sm space-y-4">
        <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Employee Full & Final (FNF) Settlement</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <label className="text-muted text-xs font-semibold uppercase shrink-0" htmlFor="fnf-employee-select">Select Resigning Employee</label>
              <select
                id="fnf-employee-select"
                value={selectedFNFUser}
                onChange={(e) => {
                  setSelectedFNFUser(e.target.value);
                  handleFetchFNF(e.target.value);
                }}
                className="select-field text-xs flex-1"
              >
                <option value="">Select Employee</option>
                {employees.filter(emp => emp.user_type !== 'superuseradmin').map(emp => (
                  <option key={emp._id} value={emp._id}>{emp.username} ({emp.employee_id})</option>
                ))}
              </select>
            </div>

            {fnfCalculation && (
              <div className="space-y-3 p-4 bg-surface-soft border border-hairline-soft rounded-lg text-xs text-body">
                <h4 className="font-semibold text-ink uppercase text-[10px] tracking-wider mb-2">Inventory & Audit Clearance</h4>
                <p><strong>Employee:</strong> {fnfCalculation.user.username} ({fnfCalculation.user.employee_id})</p>
                <p><strong>Monthly Payout CTC:</strong> ₹{fnfCalculation.user.salary.toLocaleString()}</p>
                
                {fnfCalculation.pendingAssetsCount > 0 ? (
                  <div className="p-3 alert-warning flex items-center gap-2 mt-1">
                    <AlertCircle className="w-4 h-4 shrink-0 text-warning" />
                    Employee has {fnfCalculation.pendingAssetsCount} unreturned device(s) checked out!
                  </div>
                ) : (
                  <div className="p-3 alert-success flex items-center gap-2 mt-1">
                    <Check className="w-4 h-4 shrink-0 text-success" />
                    Zero pending hardware checked out. Inventory Clear.
                  </div>
                )}
              </div>
            )}
          </div>

          {fnfCalculation && (
            <form onSubmit={handleSaveFNF} className="space-y-4 p-5 border border-hairline-soft rounded-lg bg-canvas shadow-sm text-body">
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
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none bg-transparent"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Leave Encashment (INR)</label>
                  <input
                    type="number"
                    value={fnfForm.leaveEncashment}
                    onChange={(e) => setFnfForm({ ...fnfForm, leaveEncashment: parseFloat(e.target.value) || 0 })}
                    onBlur={calculateFNFNet}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none bg-transparent"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Bonus & Incentives (INR)</label>
                  <input
                    type="number"
                    value={fnfForm.bonusIncentives}
                    onChange={(e) => setFnfForm({ ...fnfForm, bonusIncentives: parseFloat(e.target.value) || 0 })}
                    onBlur={calculateFNFNet}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none bg-transparent"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Deductions (INR)</label>
                  <input
                    type="number"
                    value={fnfForm.deductions}
                    onChange={(e) => setFnfForm({ ...fnfForm, deductions: parseFloat(e.target.value) || 0 })}
                    onBlur={calculateFNFNet}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none bg-transparent"
                  />
                </div>
                <div>
                  <label className="block text-muted text-[10px] font-semibold uppercase mb-1">Status</label>
                  <select
                    value={fnfForm.status}
                    onChange={(e) => setFnfForm({ ...fnfForm, status: e.target.value })}
                    className="w-full border border-hairline rounded-lg px-3 py-2 text-xs bg-canvas"
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
                  <span className="block text-lg font-display font-semibold text-ink">₹{fnfForm.netSettlement.toLocaleString()}</span>
                </div>
                <button
                  id="fnf-save-btn"
                  type="submit"
                  className="btn-primary"
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
        <div className="modal-overlay">
          <div className="modal-panel-md">
            <div className="modal-header mb-0 pb-4">
              <h3 className="font-semibold text-ink text-sm">Add New Asset</h3>
              <button onClick={() => setShowAssetModal(false)} className="btn-icon text-muted">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleAddAsset} className="space-y-4 mt-4">
              <div>
                <label className="label-xs">Asset Name</label>
                <input type="text" required placeholder="e.g. MacBook Pro M3" value={assetForm.name} onChange={(e) => setAssetForm({ ...assetForm, name: e.target.value })} className="input-field text-xs" />
              </div>
              <div>
                <label className="label-xs">Asset Type</label>
                <select value={assetForm.type} onChange={(e) => setAssetForm({ ...assetForm, type: e.target.value })} className="select-field text-xs">
                  <option value="Laptop">Laptop</option>
                  <option value="Mobile">Mobile</option>
                  <option value="Accessory">Accessory</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label className="label-xs">Serial Number</label>
                <input type="text" required placeholder="e.g. C02XG123XYZ" value={assetForm.serialNumber} onChange={(e) => setAssetForm({ ...assetForm, serialNumber: e.target.value })} className="input-field text-xs" />
              </div>
              <div className="modal-footer mb-0 pt-4">
                <button type="button" onClick={() => setShowAssetModal(false)} className="btn-secondary btn-sm">Cancel</button>
                <button type="submit" className="btn-primary btn-sm">Save to Registry</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default CompanyAssets;
