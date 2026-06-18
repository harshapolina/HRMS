import React, { useState } from 'react';
import axios from 'axios';
import { RefreshCw, FileText, X } from 'lucide-react';

const Payroll = () => {
  const [payrollMonth, setPayrollMonth] = useState('2026-06');
  const [calculatedPayroll, setCalculatedPayroll] = useState(null);
  const [payrollProcessing, setPayrollProcessing] = useState(false);
  const [payrollCalculatedList, setPayrollCalculatedList] = useState([]);
  const [viewingDraftPayslip, setViewingDraftPayslip] = useState(null);
  const [loading, setLoading] = useState(false);

  const formatToMonthYearString = (yyyyMm) => {
    if (!yyyyMm) return '';
    const [year, month] = yyyyMm.split('-');
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const monthIndex = parseInt(month, 10) - 1;
    return `${monthNames[monthIndex]} ${year}`;
  };

  const calculatePayroll = async () => {
    setLoading(true);
    try {
      const formattedMonth = formatToMonthYearString(payrollMonth);
      const res = await axios.get(`/api/payroll/calculate?monthYear=${formattedMonth}`);
      setCalculatedPayroll(res.data);
      setPayrollCalculatedList(res.data.calculations);
    } catch (err) {
      alert(err.response?.data?.message || 'Calculation failed');
    } finally {
      setLoading(false);
    }
  };

  const processPayroll = async () => {
    if (!calculatedPayroll) return;
    setPayrollProcessing(true);
    try {
      const formattedMonth = formatToMonthYearString(payrollMonth);
      await axios.post('/api/payroll/process', {
        monthYear: formattedMonth,
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

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="bg-gradient-to-r from-slate-900 via-brand-950 to-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-[10px] uppercase tracking-widest text-brand-400 font-extrabold">Superadmin Panel</span>
          <h1 className="text-2xl font-black text-white mt-1">Payroll Processing</h1>
        </div>
      </div>

      {/* Control Toolbar */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-4 rounded-2xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
        <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
          <label className="text-slate-500 dark:text-slate-400 font-extrabold text-xs uppercase">Run Payroll Month</label>
          <input
            type="month"
            value={payrollMonth}
            onChange={(e) => setPayrollMonth(e.target.value)}
            className="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-40 text-center font-bold bg-transparent dark:text-white"
          />
          <button
            onClick={calculatePayroll}
            disabled={loading}
            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/10 flex items-center gap-1.5"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Calculate Sheet
          </button>
        </div>

        {payrollCalculatedList.length > 0 && (
          <button
            onClick={processPayroll}
            disabled={payrollProcessing}
            className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-black transition-all shadow-md shadow-emerald-600/15"
          >
            {payrollProcessing ? 'Finalizing...' : 'Finalize & Save Payroll'}
          </button>
        )}
      </div>

      {/* Calculations Output */}
      {payrollCalculatedList.length > 0 ? (
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
          <div className="overflow-x-auto w-full">
            <table className="w-full border-collapse text-left text-xs">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                  <th className="px-6 py-4">Employee ID</th>
                  <th className="px-6 py-4">Name</th>
                  <th className="px-6 py-4">Base Salary</th>
                  <th className="px-6 py-4">Punches</th>
                  <th className="px-6 py-4">LOP / Paid Days</th>
                  <th className="px-6 py-4">Net Payout</th>
                  <th className="px-6 py-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300 font-medium">
                {payrollCalculatedList.map((rec, i) => (
                  <tr key={i} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                    <td className="px-6 py-4 font-mono font-bold text-slate-500 dark:text-slate-400">{rec.employee_id || '-'}</td>
                    <td className="px-6 py-4">
                      <span className="font-extrabold text-slate-800 dark:text-white block capitalize">{rec.username}</span>
                      <span className="block text-[10px] text-slate-400 dark:text-slate-500 capitalize">{rec.user_type}</span>
                    </td>
                    <td className="px-6 py-4 font-bold">₹{rec.baseSalary.toLocaleString()}</td>
                    <td className="px-6 py-4">{rec.presentDays} days</td>
                    <td className="px-6 py-4">
                      <span className="text-rose-600 dark:text-rose-400 font-bold">{rec.lopDays} LOP</span> / <span className="text-emerald-600 dark:text-emerald-400 font-bold">{rec.paidDays} Paid</span>
                    </td>
                    <td className="px-6 py-4 font-black text-indigo-600 dark:text-indigo-400">₹{rec.netSalary.toLocaleString()}</td>
                    <td className="px-6 py-4 text-right">
                      <button
                        onClick={() => setViewingDraftPayslip(rec)}
                        className="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1 justify-end ml-auto"
                      >
                        <FileText className="w-3.5 h-3.5" /> View Statement
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-20 shadow-sm text-center text-slate-400 dark:text-slate-500 font-semibold text-xs">
          Select month-year above and click Calculate Sheet to generate payroll drafts.
        </div>
      )}

      {/* Draft Calculation Statement Modal */}
      {viewingDraftPayslip && (
        <div className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 shadow-2xl overflow-y-auto max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-6">
              <h3 className="font-extrabold text-slate-800 dark:text-white text-xs uppercase">Draft Salary Statement ({formatToMonthYearString(payrollMonth)})</h3>
              <button
                onClick={() => setViewingDraftPayslip(null)}
                className="px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 rounded-lg text-xs font-bold transition-all"
              >
                Close
              </button>
            </div>

            <div className="border border-slate-300 dark:border-slate-700 p-6 rounded-2xl text-xs space-y-6 text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-950/40">
              <div className="text-center border-b border-slate-200 dark:border-slate-700 pb-4">
                <h2 className="font-extrabold text-slate-800 dark:text-white text-sm uppercase">Draft Payroll Sheet</h2>
                <p className="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{viewingDraftPayslip.username} ({viewingDraftPayslip.employee_id})</p>
              </div>

              <div className="grid grid-cols-2 gap-4 border-b border-slate-200 dark:border-slate-700 pb-4 font-semibold text-[11px]">
                <div>
                  <p className="text-slate-400 dark:text-slate-500">Basic Payout CTC</p>
                  <p className="text-slate-800 dark:text-white font-bold mt-0.5">₹{viewingDraftPayslip.baseSalary.toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-slate-400 dark:text-slate-500">Attendance Days</p>
                  <p className="text-slate-800 dark:text-white font-bold mt-0.5">{viewingDraftPayslip.presentDays} Present / {viewingDraftPayslip.lopDays} LOP Days</p>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 className="font-extrabold text-indigo-700 dark:text-indigo-400 uppercase tracking-wider text-[10px] border-b border-slate-200 dark:border-slate-700 pb-1.5 mb-3">Earnings Breakdown</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>Basic Salary (50%)</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.earnings.basic.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>House Rent Allowance (HRA)</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.earnings.hra.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Conveyance Allowance</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.earnings.conveyance.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Special Allowance</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.earnings.special.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>PF Employer Part</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.earnings.pfEmployer.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2 font-bold text-slate-800 dark:text-white">
                      <span>Gross Payout</span>
                      <span>₹{viewingDraftPayslip.payslipData.earnings.gross.toLocaleString()}</span>
                    </div>
                  </div>
                </div>

                <div>
                  <h4 className="font-extrabold text-rose-700 dark:text-rose-400 uppercase tracking-wider text-[10px] border-b border-slate-200 dark:border-slate-700 pb-1.5 mb-3">Deductions Breakdown</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>PF (Employee Part)</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Professional Tax (PT)</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.deductions.professionalTax.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Medical Benefit</span>
                      <span className="font-bold">₹{viewingDraftPayslip.payslipData.deductions.medical.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2 font-bold text-slate-800 dark:text-white">
                      <span>Total Deductions</span>
                      <span>₹{viewingDraftPayslip.payslipData.deductions.total.toLocaleString()}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div className="border-t border-slate-300 dark:border-slate-700 pt-4 flex items-center justify-between bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 p-4 rounded-xl">
                <div>
                  <span className="text-[10px] font-bold text-indigo-400 dark:text-indigo-500 uppercase tracking-widest block">Net Salary Payout</span>
                  <span className="text-xl font-black text-indigo-700 dark:text-indigo-400 mt-1 block">₹{viewingDraftPayslip.netSalary.toLocaleString()}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Payroll;
