import React, { useState, useEffect } from 'react';
import { Settings, Save, Clock, Calendar, Mail, CreditCard, Plus, Trash2, ShieldCheck, Check } from 'lucide-react';

const SettingsPage = () => {
  const [activeTab, setActiveTab] = useState('attendance');
  const [success, setSuccess] = useState(false);

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
    saturday_rule: 'always_paid' // always_paid, half_day, alternate_paid, unpaid
  });

  // Load from LocalStorage
  useEffect(() => {
    const savedAttendance = localStorage.getItem('hrms_attendance_rules');
    if (savedAttendance) setAttendanceRules(JSON.parse(savedAttendance));

    const savedLeaves = localStorage.getItem('hrms_leave_types');
    if (savedLeaves) setLeaveTypes(JSON.parse(savedLeaves));

    const savedSMTP = localStorage.getItem('hrms_smtp_settings');
    if (savedSMTP) setSmtpSettings(JSON.parse(savedSMTP));

    const savedPayroll = localStorage.getItem('hrms_payroll_rules');
    if (savedPayroll) setPayrollRules(JSON.parse(savedPayroll));
  }, []);

  // Save to LocalStorage
  const handleSave = (e) => {
    e.preventDefault();
    localStorage.setItem('hrms_attendance_rules', JSON.stringify(attendanceRules));
    localStorage.setItem('hrms_leave_types', JSON.stringify(leaveTypes));
    localStorage.setItem('hrms_smtp_settings', JSON.stringify(smtpSettings));
    localStorage.setItem('hrms_payroll_rules', JSON.stringify(payrollRules));

    setSuccess(true);
    setTimeout(() => setSuccess(false), 3000);
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
    const updated = [...leaveTypes, newType];
    setLeaveTypes(updated);
    localStorage.setItem('hrms_leave_types', JSON.stringify(updated));
    setNewLeaveName('');
    setNewLeaveLimit('');
    setNewLeaveIsPaid(true);
  };

  const deleteLeaveType = (id) => {
    const updated = leaveTypes.filter(x => x.id !== id);
    setLeaveTypes(updated);
    localStorage.setItem('hrms_leave_types', JSON.stringify(updated));
  };

  return (
    <div className="page-shell relative text-ink">
      
      {/* Title */}
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Administration</p>
          <h1 className="page-title">HR Control Panel</h1>
          <p className="page-subtitle">Configure attendance, leave policies, SMTP, and payroll deduction rules.</p>
        </div>
        <div className="page-header-actions">
          <span className="badge-pill">Admin Control</span>
        </div>
      </div>

      <div className="tab-bar mb-2">
        <button
          id="settings-tab-attendance"
          onClick={() => setActiveTab('attendance')}
          className={`tab-bar-item flex items-center gap-2 ${activeTab === 'attendance' ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
        >
          <Clock className="w-4 h-4" /> Attendance Rules
        </button>
        <button
          id="settings-tab-leaves"
          onClick={() => setActiveTab('leaves')}
          className={`tab-bar-item flex items-center gap-2 ${activeTab === 'leaves' ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
        >
          <Calendar className="w-4 h-4" /> Leave Policies
        </button>
        <button
          id="settings-tab-smtp"
          onClick={() => setActiveTab('smtp')}
          className={`tab-bar-item flex items-center gap-2 ${activeTab === 'smtp' ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
        >
          <Mail className="w-4 h-4" /> Email & SMTP
        </button>
        <button
          id="settings-tab-payroll"
          onClick={() => setActiveTab('payroll')}
          className={`tab-bar-item flex items-center gap-2 ${activeTab === 'payroll' ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
        >
          <CreditCard className="w-4 h-4" /> Payroll Rules
        </button>
      </div>

      <form onSubmit={handleSave} className="space-y-6 max-w-4xl">
        
        {/* Success Notice */}
        {success && (
          <div className="alert-success text-xs font-semibold">
            <ShieldCheck className="h-4.5 w-4.5 text-emerald-600" />
            <span>HR configurations updated and saved successfully!</span>
          </div>
        )}

        {/* Attendance Rules Config */}
        {activeTab === 'attendance' && (
          <div className="bg-canvas border border-hairline-soft p-6 rounded-lg shadow-sm space-y-6">
            <h3 className="text-sm font-semibold text-ink border-b border-hairline-soft pb-2 flex items-center gap-2">
              <Clock className="w-4 h-4 text-accent" /> Office Timings & Shifts
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="label-xs" htmlFor="settings-start-time">Office Start Time</label>
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
                <label className="label-xs" htmlFor="settings-end-time">Office End Time</label>
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
                <label className="block text-xs font-semibold uppercase text-muted mb-1">Late Attendance Grace Period (Minutes)</label>
                <div className="relative">
                  <input 
                    type="number" 
                    min="0"
                    max="120"
                    value={attendanceRules.grace_period_minutes}
                    onChange={e => setAttendanceRules({ ...attendanceRules, grace_period_minutes: e.target.value })}
                    className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-semibold text-muted">mins</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold uppercase text-muted mb-1">Unpaid Break Allowance (Minutes)</label>
                <div className="relative">
                  <input 
                    type="number" 
                    min="0"
                    max="180"
                    value={attendanceRules.break_time_minutes}
                    onChange={e => setAttendanceRules({ ...attendanceRules, break_time_minutes: e.target.value })}
                    className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-semibold text-muted">mins</span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Leave Policies Config */}
        {activeTab === 'leaves' && (
          <div className="space-y-6">
            <div className="bg-canvas border border-hairline-soft p-6 rounded-lg shadow-sm space-y-6">
              <h3 className="text-sm font-semibold text-ink border-b border-hairline-soft pb-2 flex items-center gap-2">
                <Calendar className="w-4 h-4 text-accent" /> Active Leave Types
              </h3>

              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="border-b border-hairline-soft text-muted font-semibold uppercase tracking-wider">
                      <th className="py-2.5">Leave Name</th>
                      <th className="py-2.5">Type</th>
                      <th className="py-2.5">Annual Allowance</th>
                      <th className="py-2.5 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-hairline-soft text-sm">
                    {leaveTypes.map((type) => (
                      <tr key={type.id} className="hover:bg-surface-soft/50">
                        <td className="py-3 font-semibold text-ink">{type.name}</td>
                        <td className="py-3">
                          <span className={type.isPaid ? 'badge-success' : 'badge-error'}>
                            {type.isPaid ? 'Paid' : 'Unpaid'}
                          </span>
                        </td>
                        <td className="py-3 font-semibold text-body">{type.limit} days/year</td>
                        <td className="py-3 text-right">
                          <button 
                            type="button" 
                            onClick={() => deleteLeaveType(type.id)}
                            className="btn-icon-danger w-8 h-8"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </td>
                      </tr>
                    ))}
                    {leaveTypes.length === 0 && (
                      <tr>
                        <td colSpan="4" className="text-center py-6 text-muted">No leave categories defined.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="bg-canvas border border-hairline-soft p-6 rounded-lg shadow-sm space-y-4">
              <h4 className="text-xs font-semibold text-ink uppercase tracking-wide">Add New Category</h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                  <label className="label-xs" htmlFor="settings-new-leave-name">Leave Name</label>
                  <input
                    id="settings-new-leave-name"
                    type="text"
                    placeholder="e.g. Maternity Leave"
                    value={newLeaveName}
                    onChange={e => setNewLeaveName(e.target.value)}
                    className="input-field text-sm"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-semibold uppercase text-muted mb-1">Annual Limit (Days)</label>
                  <input 
                    type="number" 
                    placeholder="e.g. 15"
                    value={newLeaveLimit}
                    onChange={e => setNewLeaveLimit(e.target.value)}
                    className="w-full px-3 py-1.5 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                  />
                </div>
                <div className="flex gap-3">
                  <div className="flex-1">
                    <label className="block text-[10px] font-semibold uppercase text-muted mb-1">Type</label>
                    <select 
                      value={newLeaveIsPaid ? 'paid' : 'unpaid'}
                      onChange={e => setNewLeaveIsPaid(e.target.value === 'paid')}
                      className="w-full px-3 py-1.5 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                    >
                      <option value="paid">Paid</option>
                      <option value="unpaid">Unpaid</option>
                    </select>
                  </div>
                  <button
                    id="settings-add-leave-btn"
                    type="button"
                    onClick={addLeaveType}
                    className="btn-primary flex items-center justify-center h-9"
                  >
                    <Plus className="w-4.5 h-4.5 mr-1" /> Add
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* SMTP Email Config */}
        {activeTab === 'smtp' && (
          <div className="bg-canvas border border-hairline-soft p-6 rounded-lg shadow-sm space-y-6">
            <h3 className="text-sm font-semibold text-ink border-b border-hairline-soft pb-2 flex items-center gap-2">
              <Mail className="w-4 h-4 text-accent" /> Outgoing SMTP Server Configuration
            </h3>
            <p className="text-xxs text-muted-soft italic">
              These settings are used to connect to your SMTP mail service for sending official offer letters and digital payslips.
            </p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="label-xs" htmlFor="settings-smtp-host">SMTP Hostname</label>
                <input
                  id="settings-smtp-host"
                  type="text"
                  value={smtpSettings.smtp_host}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_host: e.target.value })}
                  placeholder="e.g. smtp.gmail.com"
                  className="input-field"
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
                  className="input-field"
                  required
                />
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-smtp-user">SMTP User / Address</label>
                <input
                  id="settings-smtp-user"
                  type="email"
                  value={smtpSettings.smtp_user}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_user: e.target.value })}
                  placeholder="hr@company.com"
                  className="input-field"
                />
              </div>
              <div>
                <label className="label-xs" htmlFor="settings-smtp-pass">SMTP Password</label>
                <input
                  id="settings-smtp-pass"
                  type="password"
                  value={smtpSettings.smtp_pass}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_pass: e.target.value })}
                  placeholder="••••••••••••••"
                  className="input-field"
                />
              </div>
            </div>
          </div>
        )}

        {/* Payroll & Deductions Config */}
        {activeTab === 'payroll' && (
          <div className="bg-canvas border border-hairline-soft p-6 rounded-lg shadow-sm space-y-6">
            <h3 className="text-sm font-semibold text-ink border-b border-hairline-soft pb-2 flex items-center gap-2">
              <CreditCard className="w-4 h-4 text-accent" /> Payroll Deductions & Policy Settings
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-xs font-semibold uppercase text-muted mb-1">Provident Fund (PF) Rate - Employee / Employer</label>
                <div className="relative">
                  <input 
                    type="number" 
                    step="0.1"
                    min="0"
                    max="30"
                    value={payrollRules.pf_rate_employer}
                    onChange={e => setPayrollRules({ ...payrollRules, pf_rate_employer: e.target.value })}
                    className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-semibold text-muted">%</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold uppercase text-muted mb-1">Professional Tax Limit Threshold</label>
                <div className="relative">
                  <input 
                    type="number" 
                    value={payrollRules.professional_tax_limit}
                    onChange={e => setPayrollRules({ ...payrollRules, professional_tax_limit: e.target.value })}
                    className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-semibold text-muted">₹</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold uppercase text-muted mb-1">Professional Tax Monthly Amount</label>
                <div className="relative">
                  <input 
                    type="number" 
                    value={payrollRules.professional_tax_amount}
                    onChange={e => setPayrollRules({ ...payrollRules, professional_tax_amount: e.target.value })}
                    className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-semibold text-muted">₹</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold uppercase text-muted mb-1">Weekend (Saturday) Attendance Rule</label>
                <select 
                  value={payrollRules.saturday_rule}
                  onChange={e => setPayrollRules({ ...payrollRules, saturday_rule: e.target.value })}
                  className="w-full px-3 py-2 border border-hairline rounded-lg focus:outline-none focus:border-ink text-sm bg-transparent"
                >
                  <option value="always_paid">Always Paid (Sunday & Saturday fully paid)</option>
                  <option value="half_day">Saturday counts as Half-Day pay</option>
                  <option value="alternate_paid">Alternate Saturdays are unpaid/off</option>
                  <option value="unpaid">Saturday is always unpaid</option>
                </select>
              </div>
              <div className="md:col-span-2 pt-2">
                <label className="flex items-center gap-3 text-xs font-semibold text-body cursor-pointer">
                  <input 
                    type="checkbox"
                    checked={payrollRules.sunday_is_paid}
                    onChange={e => setPayrollRules({ ...payrollRules, sunday_is_paid: e.target.checked })}
                    className="rounded border-hairline text-accent h-4.5 w-4.5 bg-transparent"
                  />
                  Sunday Attendance is a Paid Day by default (Standard India Payroll policy)
                </label>
              </div>
            </div>
          </div>
        )}

        {/* Submit Actions */}
        <div className="flex justify-end pt-4">
          <button
            id="settings-save-btn"
            type="submit"
            className="btn-primary flex items-center gap-2"
          >
            <Save className="h-4.5 w-4.5" />
            <span>Save Settings</span>
          </button>
        </div>

      </form>
    </div>
  );
};

export default SettingsPage;
