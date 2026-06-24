import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { 
  Settings, Save, Clock, Calendar, Mail, CreditCard, 
  Plus, Trash2, ShieldCheck, Check, PlusCircle, Info, Sparkles 
} from 'lucide-react';

const SettingsPage = () => {
  const [activeTab, setActiveTab] = useState('attendance');
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  // Default State Values
  const [attendanceRules, setAttendanceRules] = useState({
    office_start_time: '09:00',
    office_end_time: '18:00',
    grace_period_minutes: '15',
    break_time_minutes: '0'
  });

  const [leaveTypes, setLeaveTypes] = useState([
    { id: 1, name: 'Sick Leave', limit: 12, isPaid: true },
    { id: 2, name: 'Casual Leave', limit: 12, isPaid: true },
    { id: 3, name: 'Vacation Leave', limit: 15, isPaid: true },
    { id: 4, name: 'Leave Without Pay (LWP)', limit: 30, isPaid: false }
  ]);

  const [newLeaveName, setNewLeaveName] = useState('');
  const [newLeaveLimit, setNewLeaveLimit] = useState('');
  const [newLeaveIsPaid, setNewLeaveIsPaid] = useState(true);

  const [smtpSettings, setSmtpSettings] = useState({
    smtp_host: 'smtp.gmail.com',
    smtp_port: '587',
    smtp_user: '',
    smtp_pass: ''
  });

  const [payrollRules, setPayrollRules] = useState({
    pf_rate_employer: '12',
    professional_tax_limit: '15000',
    professional_tax_amount: '200',
    sunday_is_paid: true,
    saturday_rule: 'always_paid'
  });

  const [customDeductions, setCustomDeductions] = useState([]);
  const [newDeduction, setNewDeduction] = useState({ name: '', type: 'flat', value: '', status: 'Active' });

  // Load settings from MongoDB
  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/settings');
      const data = res.data;
      if (data.attendance_rules) setAttendanceRules(data.attendance_rules);
      if (data.leave_types) setLeaveTypes(data.leave_types);
      if (data.smtp_settings) setSmtpSettings(data.smtp_settings);
      if (data.payroll_rules) setPayrollRules(data.payroll_rules);
      if (data.custom_deductions) setCustomDeductions(data.custom_deductions);
    } catch (err) {
      console.error('Error fetching settings:', err);
    } finally {
      setLoading(false);
    }
  };

  // Save settings to MongoDB
  const handleSave = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/settings', { key: 'attendance_rules', value: attendanceRules });
      await axios.post('/api/settings', { key: 'leave_types', value: leaveTypes });
      await axios.post('/api/settings', { key: 'smtp_settings', value: smtpSettings });
      await axios.post('/api/settings', { key: 'payroll_rules', value: payrollRules });
      await axios.post('/api/settings', { key: 'custom_deductions', value: customDeductions });

      setSuccess(true);
      setTimeout(() => setSuccess(false), 4000);
    } catch (err) {
      alert('Save failed: ' + (err.response?.data?.message || err.message));
    }
  };

  // Leave Type Handlers
  const addLeaveType = () => {
    if (!newLeaveName || !newLeaveLimit) return;
    const newType = {
      id: Date.now(),
      name: newLeaveName,
      limit: parseInt(newLeaveLimit) || 0,
      isPaid: newLeaveIsPaid
    };
    setLeaveTypes([...leaveTypes, newType]);
    setNewLeaveName('');
    setNewLeaveLimit('');
    setNewLeaveIsPaid(true);
  };

  const deleteLeaveType = (id) => {
    setLeaveTypes(leaveTypes.filter(x => x.id !== id));
  };

  // Custom Deduction Handlers
  const addCustomDeduction = () => {
    if (!newDeduction.name || !newDeduction.value) return;
    const newDeds = [...customDeductions, {
      id: Date.now(),
      name: newDeduction.name,
      type: newDeduction.type,
      value: parseFloat(newDeduction.value) || 0,
      status: newDeduction.status
    }];
    setCustomDeductions(newDeds);
    setNewDeduction({ name: '', type: 'flat', value: '', status: 'Active' });
  };

  const deleteCustomDeduction = (id) => {
    setCustomDeductions(customDeductions.filter(x => x.id !== id));
  };

  const toggleDeductionStatus = (id) => {
    setCustomDeductions(customDeductions.map(x => {
      if (x.id === id) {
        return { ...x, status: x.status === 'Active' ? 'Inactive' : 'Active' };
      }
      return x;
    }));
  };

  if (loading) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="spinner" />
      </div>
    );
  }

  return (
    <div className="page-shell relative text-ink">
      
      {/* Sub-Header Tab Selector */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-6 shadow-sm border border-hairline bg-canvas p-1 rounded-xl">
        <button
          id="settings-tab-attendance"
          onClick={() => setActiveTab('attendance')}
          className={`flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-200 ${
            activeTab === 'attendance'
              ? 'bg-primary text-white shadow-md border border-primary'
              : 'border border-hairline text-muted hover:text-ink hover:bg-surface-soft'
          }`}
        >
          <Clock className="w-4 h-4" /> Attendance Timings
        </button>
        <button
          id="settings-tab-leaves"
          onClick={() => setActiveTab('leaves')}
          className={`flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-200 ${
            activeTab === 'leaves'
              ? 'bg-primary text-white shadow-md border border-primary'
              : 'border border-hairline text-muted hover:text-ink hover:bg-surface-soft'
          }`}
        >
          <Calendar className="w-4 h-4" /> Leave Policies
        </button>
        <button
          id="settings-tab-smtp"
          onClick={() => setActiveTab('smtp')}
          className={`flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-200 ${
            activeTab === 'smtp'
              ? 'bg-primary text-white shadow-md border border-primary'
              : 'border border-hairline text-muted hover:text-ink hover:bg-surface-soft'
          }`}
        >
          <Mail className="w-4 h-4" /> Email &amp; SMTP
        </button>
        <button
          id="settings-tab-payroll"
          onClick={() => setActiveTab('payroll')}
          className={`flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-200 ${
            activeTab === 'payroll'
              ? 'bg-primary text-white shadow-md border border-primary'
              : 'border border-hairline text-muted hover:text-ink hover:bg-surface-soft'
          }`}
        >
          <CreditCard className="w-4 h-4" /> Payroll &amp; Cuttings
        </button>
      </div>

      <form onSubmit={handleSave} className="space-y-6">
        
        {/* Success Notice */}
        {success && (
          <div className="bg-success/10 border border-success/30 text-success px-4 py-3 rounded-lg text-xs font-bold flex items-center gap-2.5 animate-fade-in shadow-sm">
            <ShieldCheck className="h-5 w-5 text-success" />
            <span>HR System configurations saved and circulated successfully!</span>
          </div>
        )}

        {/* Attendance Rules Config */}
        {activeTab === 'attendance' && (
          <div className="card p-8 shadow-sm border border-hairline space-y-6 animate-scale-in">
            <div className="flex items-center gap-3 border-b border-hairline pb-4">
              <div className="w-10 h-10 rounded-lg bg-surface-soft border border-hairline flex items-center justify-center text-accent">
                <Clock className="w-5 h-5" />
              </div>
              <div>
                <h3 className="font-display font-bold text-sm tracking-tight text-ink">Office Shift Timings</h3>
                <p className="text-[11px] text-muted">Set target work shift hours and late grace limits.</p>
              </div>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="label-xs" htmlFor="settings-start-time">Office Shift Start *</label>
                <input
                  id="settings-start-time"
                  type="time"
                  value={attendanceRules.office_start_time}
                  onChange={e => setAttendanceRules({ ...attendanceRules, office_start_time: e.target.value })}
                  className="input-field"
                  required
                />
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-end-time">Office Shift End *</label>
                <input
                  id="settings-end-time"
                  type="time"
                  value={attendanceRules.office_end_time}
                  onChange={e => setAttendanceRules({ ...attendanceRules, office_end_time: e.target.value })}
                  className="input-field"
                  required
                />
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-grace">Late Grace Period</label>
                <div className="relative">
                  <input 
                    id="settings-grace"
                    type="number" 
                    min="0"
                    max="120"
                    value={attendanceRules.grace_period_minutes}
                    onChange={e => setAttendanceRules({ ...attendanceRules, grace_period_minutes: e.target.value })}
                    className="input-field pr-12 font-medium"
                    required
                  />
                  <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xxs font-bold text-muted uppercase">mins</span>
                </div>
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-break">Unpaid Break Allowance</label>
                <div className="relative">
                  <input 
                    id="settings-break"
                    type="number" 
                    min="0"
                    max="180"
                    value={attendanceRules.break_time_minutes}
                    onChange={e => setAttendanceRules({ ...attendanceRules, break_time_minutes: e.target.value })}
                    className="input-field pr-12 font-medium"
                    required
                  />
                  <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xxs font-bold text-muted uppercase">mins</span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Leave Policies Config */}
        {activeTab === 'leaves' && (
          <div className="space-y-6 animate-scale-in">
            <div className="card p-8 shadow-sm border border-hairline space-y-6">
              <div className="flex items-center gap-3 border-b border-hairline pb-4">
                <div className="w-10 h-10 rounded-lg bg-surface-soft border border-hairline flex items-center justify-center text-accent">
                  <Calendar className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-display font-bold text-sm tracking-tight text-ink">Active Leave Categories</h3>
                  <p className="text-[11px] text-muted">Manage corporate leaves and annual calendar allowances.</p>
                </div>
              </div>

              <div className="overflow-hidden border border-hairline rounded-xl">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="bg-surface-soft border-b border-hairline text-muted font-bold uppercase tracking-wider">
                      <th className="px-6 py-3.5">Leave Category</th>
                      <th className="px-6 py-3.5">Type</th>
                      <th className="px-6 py-3.5">Annual Cap</th>
                      <th className="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-hairline-soft font-semibold">
                    {leaveTypes.map((type) => (
                      <tr key={type.id} className="hover:bg-surface-soft/50 transition-colors">
                        <td className="px-6 py-4 text-ink font-bold">{type.name}</td>
                        <td className="px-6 py-4">
                          <span className={type.isPaid ? 'badge-success uppercase text-[9px]' : 'badge-error uppercase text-[9px]'}>
                            {type.isPaid ? 'Paid' : 'Unpaid'}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-body font-mono">{type.limit} days/year</td>
                        <td className="px-6 py-4 text-right">
                          <button 
                            type="button" 
                            onClick={() => deleteLeaveType(type.id)}
                            className="text-error hover:bg-error/10 p-2 rounded-full transition-colors inline-flex items-center justify-center"
                            title="Delete policy"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </td>
                      </tr>
                    ))}
                    {leaveTypes.length === 0 && (
                      <tr>
                        <td colSpan="4" className="text-center py-10 text-muted font-semibold">No leave policies defined.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="card p-6 shadow-sm border border-hairline space-y-4">
              <h4 className="text-xs font-bold text-ink uppercase tracking-wide flex items-center gap-1.5">
                <PlusCircle className="w-4 h-4 text-accent" /> Define New Leave Policy
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                  <label className="label-xs" htmlFor="settings-new-leave-name">Leave Name</label>
                  <input
                    id="settings-new-leave-name"
                    type="text"
                    placeholder="e.g. Maternity Leave"
                    value={newLeaveName}
                    onChange={e => setNewLeaveName(e.target.value)}
                    className="input-field text-xs font-semibold"
                  />
                </div>
                <div>
                  <label className="label-xs" htmlFor="settings-new-leave-limit">Annual Limit (Days)</label>
                  <input 
                    id="settings-new-leave-limit"
                    type="number" 
                    placeholder="e.g. 15"
                    value={newLeaveLimit}
                    onChange={e => setNewLeaveLimit(e.target.value)}
                    className="input-field text-xs font-semibold font-mono"
                  />
                </div>
                <div className="flex gap-3">
                  <div className="flex-1">
                    <label className="label-xs" htmlFor="settings-new-leave-pay">Type</label>
                    <select 
                      id="settings-new-leave-pay"
                      value={newLeaveIsPaid ? 'paid' : 'unpaid'}
                      onChange={e => setNewLeaveIsPaid(e.target.value === 'paid')}
                      className="select-field text-xs font-semibold"
                    >
                      <option value="paid">Paid Leave</option>
                      <option value="unpaid">Unpaid (LOP)</option>
                    </select>
                  </div>
                  <button
                    id="settings-add-leave-btn"
                    type="button"
                    onClick={addLeaveType}
                    className="btn-primary h-10 px-4 text-xs shrink-0 font-bold uppercase tracking-wider"
                  >
                    Add Category
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* SMTP Email Config */}
        {activeTab === 'smtp' && (
          <div className="card p-8 shadow-sm border border-hairline space-y-6 animate-scale-in">
            <div className="flex items-center gap-3 border-b border-hairline pb-4">
              <div className="w-10 h-10 rounded-lg bg-surface-soft border border-hairline flex items-center justify-center text-accent">
                <Mail className="w-5 h-5" />
              </div>
              <div>
                <h3 className="font-display font-bold text-sm tracking-tight text-ink">SMTP Dispatch Configuration</h3>
                <p className="text-[11px] text-muted">Connect your corporate email gateway to automate dispatch of offer letters and payslips.</p>
              </div>
            </div>

            <div className="bg-surface-soft border border-hairline p-4 rounded-xl flex items-start gap-3">
              <Info className="w-5 h-5 text-accent shrink-0 mt-0.5" />
              <div className="text-xs text-body leading-relaxed">
                <strong className="block text-ink mb-0.5">Email Delivery Note:</strong>
                Saving SMTP settings here dynamically registers them in the backend database. To dispatch emails via Gmail, enter <strong>smtp.gmail.com</strong> (Port <strong>587</strong>), keep your Gmail Address as username, and generate a 16-character <strong>App Password</strong> in your Google Account settings to use as your SMTP password.
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="label-xs" htmlFor="settings-smtp-host">SMTP Hostname</label>
                <input
                  id="settings-smtp-host"
                  type="text"
                  value={smtpSettings.smtp_host}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_host: e.target.value })}
                  placeholder="e.g. smtp.gmail.com"
                  className="input-field text-xs font-semibold"
                  required
                />
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-smtp-port">SMTP Port</label>
                <input
                  id="settings-smtp-port"
                  type="text"
                  value={smtpSettings.smtp_port}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_port: e.target.value })}
                  placeholder="e.g. 587 or 465"
                  className="input-field text-xs font-semibold font-mono"
                  required
                />
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-smtp-user">SMTP Email Address</label>
                <input
                  id="settings-smtp-user"
                  type="email"
                  value={smtpSettings.smtp_user}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_user: e.target.value })}
                  placeholder="hr@company.com"
                  className="input-field text-xs font-semibold"
                  required
                />
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-smtp-pass">SMTP App Password</label>
                <input
                  id="settings-smtp-pass"
                  type="password"
                  value={smtpSettings.smtp_pass}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_pass: e.target.value })}
                  placeholder="••••••••••••••••"
                  className="input-field text-xs font-semibold font-mono"
                  required
                />
              </div>
            </div>
          </div>
        )}

        {/* Payroll & Deductions Config */}
        {activeTab === 'payroll' && (
          <div className="grid grid-cols-1 lg:grid-cols-5 gap-8 animate-scale-in">
            {/* Standard Policies */}
            <div className="lg:col-span-2 card p-8 shadow-sm border border-hairline space-y-6">
              <div className="flex items-center gap-3 border-b border-hairline pb-4">
                <div className="w-10 h-10 rounded-lg bg-surface-soft border border-hairline flex items-center justify-center text-accent">
                  <CreditCard className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-display font-bold text-sm tracking-tight text-ink">Payroll Parameters</h3>
                  <p className="text-[11px] text-muted">Configure PF and Professional Tax policies.</p>
                </div>
              </div>

              <div className="space-y-5">
                <div>
                  <label className="label-xs">Provident Fund (PF) Rate</label>
                  <div className="relative">
                    <input 
                      type="number" 
                      step="0.1"
                      min="0"
                      max="30"
                      value={payrollRules.pf_rate_employer}
                      onChange={e => setPayrollRules({ ...payrollRules, pf_rate_employer: e.target.value })}
                      className="input-field pr-10 text-xs font-semibold font-mono"
                      required
                    />
                    <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xxs font-bold text-muted">%</span>
                  </div>
                </div>

                <div>
                  <label className="label-xs">Professional Tax Gross Threshold</label>
                  <div className="relative">
                    <input 
                      type="number" 
                      value={payrollRules.professional_tax_limit}
                      onChange={e => setPayrollRules({ ...payrollRules, professional_tax_limit: e.target.value })}
                      className="input-field pr-10 text-xs font-semibold font-mono"
                      required
                    />
                    <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xxs font-bold text-muted">₹</span>
                  </div>
                </div>

                <div>
                  <label className="label-xs">Professional Tax Monthly Amount</label>
                  <div className="relative">
                    <input 
                      type="number" 
                      value={payrollRules.professional_tax_amount}
                      onChange={e => setPayrollRules({ ...payrollRules, professional_tax_amount: e.target.value })}
                      className="input-field pr-10 text-xs font-semibold font-mono"
                      required
                    />
                    <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xxs font-bold text-muted">₹</span>
                  </div>
                </div>

                <div>
                  <label className="label-xs">Saturday Overtime/Attendance Rule</label>
                  <select 
                    value={payrollRules.saturday_rule}
                    onChange={e => setPayrollRules({ ...payrollRules, saturday_rule: e.target.value })}
                    className="select-field text-xs font-semibold"
                  >
                    <option value="always_paid">Always Paid (Sunday &amp; Saturday fully paid)</option>
                    <option value="half_day">Saturday counts as Half-Day pay</option>
                    <option value="alternate_paid">Alternate Saturdays are unpaid/off</option>
                    <option value="unpaid">Saturday is always unpaid</option>
                  </select>
                </div>

                <div className="pt-2">
                  <label className="flex items-center gap-3 text-xs font-semibold text-body cursor-pointer">
                    <input 
                      type="checkbox"
                      checked={payrollRules.sunday_is_paid}
                      onChange={e => setPayrollRules({ ...payrollRules, sunday_is_paid: e.target.checked })}
                      className="rounded border-hairline text-accent h-4.5 w-4.5 bg-transparent"
                    />
                    Sunday is a Paid day by default
                  </label>
                </div>
              </div>
            </div>

            {/* Custom Deductions (Cuttings) Management */}
            <div className="lg:col-span-3 card p-8 shadow-sm border border-hairline flex flex-col space-y-6">
              <div className="flex items-center gap-3 border-b border-hairline pb-4 justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-surface-soft border border-hairline flex items-center justify-center text-error">
                    <Trash2 className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-display font-bold text-sm tracking-tight text-ink">Custom Deductions ("Cuttings")</h3>
                    <p className="text-[11px] text-muted">Create custom monthly deductions applied to all employees.</p>
                  </div>
                </div>
                <span className="badge-neutral text-[9px] uppercase tracking-wider">{customDeductions.length} Active</span>
              </div>

              {/* Deductions registry */}
              <div className="overflow-hidden border border-hairline rounded-xl flex-1 min-h-[200px]">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="bg-surface-soft border-b border-hairline text-muted font-bold uppercase tracking-wider">
                      <th className="px-4 py-3">Cutting Name</th>
                      <th className="px-4 py-3">Type</th>
                      <th className="px-4 py-3">Value</th>
                      <th className="px-4 py-3 text-center">Status</th>
                      <th className="px-4 py-3 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-hairline-soft font-semibold">
                    {customDeductions.map((ded) => (
                      <tr key={ded.id} className="hover:bg-surface-soft/50 transition-colors">
                        <td className="px-4 py-3.5 text-ink font-bold">{ded.name}</td>
                        <td className="px-4 py-3.5 capitalize text-muted">{ded.type}</td>
                        <td className="px-4 py-3.5 font-mono text-body">
                          {ded.type === 'percentage' ? `${ded.value}%` : `₹${ded.value.toLocaleString()}`}
                        </td>
                        <td className="px-4 py-3.5 text-center">
                          <button
                            type="button"
                            onClick={() => toggleDeductionStatus(ded.id)}
                            className={`px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase transition-colors ${ded.status === 'Active' ? 'bg-success/15 text-success hover:bg-success/25' : 'bg-muted/15 text-muted hover:bg-muted/25'}`}
                          >
                            {ded.status}
                          </button>
                        </td>
                        <td className="px-4 py-3.5 text-right">
                          <button 
                            type="button" 
                            onClick={() => deleteCustomDeduction(ded.id)}
                            className="text-error hover:bg-error/10 p-1.5 rounded-full transition-colors inline-flex items-center justify-center"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </td>
                      </tr>
                    ))}
                    {customDeductions.length === 0 && (
                      <tr>
                        <td colSpan="5" className="text-center py-10 text-muted font-semibold">No custom deductions configured.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              {/* Add Deduction Form */}
              <div className="p-4 border border-hairline rounded-xl bg-surface-soft/50 space-y-3">
                <h4 className="text-[10px] font-bold text-ink uppercase tracking-wider flex items-center gap-1.5">
                  <Sparkles className="w-3.5 h-3.5 text-warning" /> Add Dynamic Custom Cutting
                </h4>
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                  <div className="sm:col-span-2">
                    <label className="label-xs">Deduction Name</label>
                    <input 
                      type="text" 
                      placeholder="e.g. Welfare Fund, Advance"
                      value={newDeduction.name}
                      onChange={e => setNewDeduction({ ...newDeduction, name: e.target.value })}
                      className="input-field text-xs font-semibold"
                    />
                  </div>
                  <div>
                    <label className="label-xs">Type</label>
                    <select 
                      value={newDeduction.type}
                      onChange={e => setNewDeduction({ ...newDeduction, type: e.target.value })}
                      className="select-field text-xs font-semibold"
                    >
                      <option value="flat">Flat Amount (₹)</option>
                      <option value="percentage">Percentage (%)</option>
                    </select>
                  </div>
                  <div>
                    <label className="label-xs">Value</label>
                    <div className="flex gap-2">
                      <input 
                        type="number" 
                        placeholder={newDeduction.type === 'percentage' ? 'e.g. 2' : 'e.g. 500'}
                        value={newDeduction.value}
                        onChange={e => setNewDeduction({ ...newDeduction, value: e.target.value })}
                        className="input-field text-xs font-semibold font-mono"
                      />
                      <button
                        type="button"
                        onClick={addCustomDeduction}
                        className="btn-primary px-3 text-xs shrink-0 font-bold"
                        title="Add Custom Deduction"
                      >
                        <Plus className="w-4.5 h-4.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Submit Actions */}
        <div className="flex justify-end pt-4 border-t border-hairline-soft mt-6">
          <button
            id="settings-save-btn"
            type="submit"
            className="btn-primary flex items-center gap-2 h-11 px-6 shadow-md shadow-primary/10 text-sm font-bold uppercase tracking-wider hover:-translate-y-[1px] active:translate-y-0 transition-all duration-150"
          >
            <Save className="h-4.5 w-4.5 text-white" />
            <span>Apply &amp; Circulate Settings</span>
          </button>
        </div>

      </form>
    </div>
  );
};

export default SettingsPage;
