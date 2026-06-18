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
    <div className="space-y-6 relative text-slate-800 dark:text-slate-200">
      
      {/* Title */}
      <div className="flex flex-col md:flex-row justify-between align-start md:align-center gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <Settings className="w-6 h-6 text-brand-500" /> HR Control Panel
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">
            Configure system parameters, attendance guidelines, leave categories, SMTP configurations, and payroll.
          </p>
        </div>
        <span className="self-start px-3 py-1 bg-brand-50 dark:bg-brand-950/45 border border-brand-100 dark:border-brand-900 text-brand-600 dark:text-brand-400 font-bold text-xs rounded-full shadow-sm">
          Admin Control
        </span>
      </div>

      <div className="border-b border-slate-200 dark:border-slate-850 flex flex-wrap gap-4 text-sm font-semibold text-slate-500 dark:text-slate-400">
        <button 
          onClick={() => setActiveTab('attendance')}
          className={`pb-2 border-b-2 transition-all flex items-center gap-2 ${activeTab === 'attendance' ? 'border-brand-500 text-brand-600 dark:text-brand-450 font-bold' : 'border-transparent hover:text-slate-700 dark:hover:text-slate-200'}`}
        >
          <Clock className="w-4 h-4" /> Attendance Rules
        </button>
        <button 
          onClick={() => setActiveTab('leaves')}
          className={`pb-2 border-b-2 transition-all flex items-center gap-2 ${activeTab === 'leaves' ? 'border-brand-500 text-brand-600 dark:text-brand-450 font-bold' : 'border-transparent hover:text-slate-700 dark:hover:text-slate-200'}`}
        >
          <Calendar className="w-4 h-4" /> Leave Policies
        </button>
        <button 
          onClick={() => setActiveTab('smtp')}
          className={`pb-2 border-b-2 transition-all flex items-center gap-2 ${activeTab === 'smtp' ? 'border-brand-500 text-brand-600 dark:text-brand-450 font-bold' : 'border-transparent hover:text-slate-700 dark:hover:text-slate-200'}`}
        >
          <Mail className="w-4 h-4" /> Email & SMTP
        </button>
        <button 
          onClick={() => setActiveTab('payroll')}
          className={`pb-2 border-b-2 transition-all flex items-center gap-2 ${activeTab === 'payroll' ? 'border-brand-500 text-brand-600 dark:text-brand-450 font-bold' : 'border-transparent hover:text-slate-700 dark:hover:text-slate-200'}`}
        >
          <CreditCard className="w-4 h-4" /> Payroll Rules
        </button>
      </div>

      <form onSubmit={handleSave} className="space-y-6 max-w-4xl">
        
        {/* Success Notice */}
        {success && (
          <div className="rounded-xl border border-emerald-150 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950/40 p-4 text-xs font-semibold text-emerald-800 dark:text-emerald-450 flex items-center gap-2 animate-fade-in shadow-sm">
            <ShieldCheck className="h-4.5 w-4.5 text-emerald-600 dark:text-emerald-400" />
            <span>HR configurations updated and saved successfully!</span>
          </div>
        )}

        {/* Attendance Rules Config */}
        {activeTab === 'attendance' && (
          <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-6">
            <h3 className="text-sm font-bold text-slate-800 dark:text-white border-b border-slate-50 dark:border-slate-800 pb-2 flex items-center gap-2">
              <Clock className="w-4 h-4 text-brand-500" /> Office Timings & Shifts
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Office Start Time</label>
                <input 
                  type="time" 
                  value={attendanceRules.office_start_time}
                  onChange={e => setAttendanceRules({ ...attendanceRules, office_start_time: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Office End Time</label>
                <input 
                  type="time" 
                  value={attendanceRules.office_end_time}
                  onChange={e => setAttendanceRules({ ...attendanceRules, office_end_time: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Late Attendance Grace Period (Minutes)</label>
                <div className="relative">
                  <input 
                    type="number" 
                    min="0"
                    max="120"
                    value={attendanceRules.grace_period_minutes}
                    onChange={e => setAttendanceRules({ ...attendanceRules, grace_period_minutes: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-bold text-slate-400">mins</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Unpaid Break Allowance (Minutes)</label>
                <div className="relative">
                  <input 
                    type="number" 
                    min="0"
                    max="180"
                    value={attendanceRules.break_time_minutes}
                    onChange={e => setAttendanceRules({ ...attendanceRules, break_time_minutes: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-bold text-slate-400">mins</span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Leave Policies Config */}
        {activeTab === 'leaves' && (
          <div className="space-y-6">
            <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-6">
              <h3 className="text-sm font-bold text-slate-800 dark:text-white border-b border-slate-50 dark:border-slate-800 pb-2 flex items-center gap-2">
                <Calendar className="w-4 h-4 text-brand-500" /> Active Leave Types
              </h3>

              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                      <th className="py-2.5">Leave Name</th>
                      <th className="py-2.5">Type</th>
                      <th className="py-2.5">Annual Allowance</th>
                      <th className="py-2.5 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                    {leaveTypes.map((type) => (
                      <tr key={type.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                        <td className="py-3 font-semibold text-slate-800 dark:text-slate-200">{type.name}</td>
                        <td className="py-3">
                          <span className={`px-2 py-0.5 rounded-full text-xxs font-bold ${type.isPaid ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-450'}`}>
                            {type.isPaid ? 'Paid' : 'Unpaid'}
                          </span>
                        </td>
                        <td className="py-3 font-bold text-slate-600 dark:text-slate-400">{type.limit} days/year</td>
                        <td className="py-3 text-right">
                          <button 
                            type="button" 
                            onClick={() => deleteLeaveType(type.id)}
                            className="text-rose-600 hover:text-rose-700 dark:text-rose-400 p-1"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </td>
                      </tr>
                    ))}
                    {leaveTypes.length === 0 && (
                      <tr>
                        <td colSpan="4" className="text-center py-6 text-slate-400">No leave categories defined.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
              <h4 className="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wide">Add New Category</h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                  <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Leave Name</label>
                  <input 
                    type="text" 
                    placeholder="e.g. Maternity Leave"
                    value={newLeaveName}
                    onChange={e => setNewLeaveName(e.target.value)}
                    className="w-full px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Annual Limit (Days)</label>
                  <input 
                    type="number" 
                    placeholder="e.g. 15"
                    value={newLeaveLimit}
                    onChange={e => setNewLeaveLimit(e.target.value)}
                    className="w-full px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  />
                </div>
                <div className="flex gap-3">
                  <div className="flex-1">
                    <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Type</label>
                    <select 
                      value={newLeaveIsPaid ? 'paid' : 'unpaid'}
                      onChange={e => setNewLeaveIsPaid(e.target.value === 'paid')}
                      className="w-full px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:bg-slate-900"
                    >
                      <option value="paid">Paid</option>
                      <option value="unpaid">Unpaid</option>
                    </select>
                  </div>
                  <button 
                    type="button"
                    onClick={addLeaveType}
                    className="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-xl flex items-center justify-center font-bold text-xs h-9"
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
          <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-6">
            <h3 className="text-sm font-bold text-slate-800 dark:text-white border-b border-slate-50 dark:border-slate-800 pb-2 flex items-center gap-2">
              <Mail className="w-4 h-4 text-brand-500" /> Outgoing SMTP Server Configuration
            </h3>
            <p className="text-xxs text-slate-450 dark:text-slate-450 italic">
              These settings are used to connect to your SMTP mail service for sending official offer letters and digital payslips.
            </p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">SMTP Hostname</label>
                <input 
                  type="text" 
                  value={smtpSettings.smtp_host}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_host: e.target.value })}
                  placeholder="e.g. smtp.gmail.com"
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">SMTP Port</label>
                <input 
                  type="text" 
                  value={smtpSettings.smtp_port}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_port: e.target.value })}
                  placeholder="e.g. 587 or 465"
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">SMTP User / Address</label>
                <input 
                  type="email" 
                  value={smtpSettings.smtp_user}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_user: e.target.value })}
                  placeholder="hr@company.com"
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">SMTP Password</label>
                <input 
                  type="password" 
                  value={smtpSettings.smtp_pass}
                  onChange={e => setSmtpSettings({ ...smtpSettings, smtp_pass: e.target.value })}
                  placeholder="••••••••••••••"
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                />
              </div>
            </div>
          </div>
        )}

        {/* Payroll & Deductions Config */}
        {activeTab === 'payroll' && (
          <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-6">
            <h3 className="text-sm font-bold text-slate-800 dark:text-white border-b border-slate-50 dark:border-slate-800 pb-2 flex items-center gap-2">
              <CreditCard className="w-4 h-4 text-brand-500" /> Payroll Deductions & Policy Settings
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Provident Fund (PF) Rate - Employee / Employer</label>
                <div className="relative">
                  <input 
                    type="number" 
                    step="0.1"
                    min="0"
                    max="30"
                    value={payrollRules.pf_rate_employer}
                    onChange={e => setPayrollRules({ ...payrollRules, pf_rate_employer: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-bold text-slate-400">%</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Professional Tax Limit Threshold</label>
                <div className="relative">
                  <input 
                    type="number" 
                    value={payrollRules.professional_tax_limit}
                    onChange={e => setPayrollRules({ ...payrollRules, professional_tax_limit: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-bold text-slate-400">₹</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Professional Tax Monthly Amount</label>
                <div className="relative">
                  <input 
                    type="number" 
                    value={payrollRules.professional_tax_amount}
                    onChange={e => setPayrollRules({ ...payrollRules, professional_tax_amount: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white"
                    required
                  />
                  <span className="absolute right-3 top-2 text-xxs font-bold text-slate-400">₹</span>
                </div>
              </div>
              <div>
                <label className="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Weekend (Saturday) Attendance Rule</label>
                <select 
                  value={payrollRules.saturday_rule}
                  onChange={e => setPayrollRules({ ...payrollRules, saturday_rule: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-sm bg-transparent dark:text-white dark:bg-slate-900"
                >
                  <option value="always_paid">Always Paid (Sunday & Saturday fully paid)</option>
                  <option value="half_day">Saturday counts as Half-Day pay</option>
                  <option value="alternate_paid">Alternate Saturdays are unpaid/off</option>
                  <option value="unpaid">Saturday is always unpaid</option>
                </select>
              </div>
              <div className="md:col-span-2 pt-2">
                <label className="flex items-center gap-3 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                  <input 
                    type="checkbox"
                    checked={payrollRules.sunday_is_paid}
                    onChange={e => setPayrollRules({ ...payrollRules, sunday_is_paid: e.target.checked })}
                    className="rounded border-slate-350 text-brand-500 h-4.5 w-4.5 bg-transparent dark:bg-slate-800"
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
            type="submit"
            className="flex items-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-500/10 transition-all cursor-pointer"
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
