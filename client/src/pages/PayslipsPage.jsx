import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, FileText, RefreshCw } from 'lucide-react';

const PayslipsPage = () => {
  const [monthYear, setMonthYear] = useState('2026-06');
  const [records, setRecords] = useState([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);
  const [viewingPayslip, setViewingPayslip] = useState(null);

  const formatToMonthYearString = (yyyyMm) => {
    if (!yyyyMm) return '';
    const [year, month] = yyyyMm.split('-');
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const monthIndex = parseInt(month, 10) - 1;
    return `${monthNames[monthIndex]} ${year}`;
  };

  useEffect(() => {
    fetchFinalizedPayroll();
  }, [monthYear]);

  const fetchFinalizedPayroll = async () => {
    if (!monthYear) return;
    setLoading(true);
    try {
      const formattedMonth = formatToMonthYearString(monthYear);
      const res = await axios.get('/api/payroll', { params: { monthYear: formattedMonth } });
      setRecords(res.data);
    } catch (err) {
      console.error(err);
      setRecords([]);
    } finally {
      setLoading(false);
    }
  };

  const filteredRecords = records.filter(rec => {
    const term = search.toLowerCase();
    return (
      (rec.user?.username || '').toLowerCase().includes(term) ||
      (rec.user?.employee_id || '').toLowerCase().includes(term)
    );
  });

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="bg-gradient-to-r from-slate-900 via-brand-950 to-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-[10px] uppercase tracking-widest text-brand-400 font-extrabold">Finance Portal</span>
          <h1 className="text-2xl font-black text-white mt-1">Processed Payslips</h1>
        </div>
      </div>

      {/* Control Toolbar */}
      <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-4 rounded-2xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
        <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
          <div className="flex items-center gap-2">
            <label className="text-slate-500 dark:text-slate-400 font-extrabold text-xs uppercase whitespace-nowrap">Payroll Month</label>
            <input
              type="month"
              value={monthYear}
              onChange={(e) => setMonthYear(e.target.value)}
              className="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-40 text-center font-bold bg-transparent dark:text-white"
            />
          </div>
          
          <div className="relative w-full sm:w-64">
            <input
              type="text"
              placeholder="Filter by name/ID..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="px-4 py-2 pl-9 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-brand-500 text-xs w-full bg-transparent dark:text-white"
            />
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={fetchFinalizedPayroll}
            className="p-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl text-slate-500 dark:text-slate-400 transition-all"
            title="Refresh"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {/* Main Grid */}
      {filteredRecords.length > 0 ? (
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
                {filteredRecords.map((rec) => (
                  <tr key={rec._id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                    <td className="px-6 py-4 font-mono font-bold text-slate-500 dark:text-slate-400">{rec.user?.employee_id || '-'}</td>
                    <td className="px-6 py-4">
                      <span className="font-extrabold text-slate-800 dark:text-white block capitalize">{rec.user?.username}</span>
                      <span className="block text-[10px] text-slate-400 dark:text-slate-500 capitalize">{rec.user?.user_type}</span>
                    </td>
                    <td className="px-6 py-4 font-bold">₹{rec.baseSalary.toLocaleString()}</td>
                    <td className="px-6 py-4">{rec.presentDays} days</td>
                    <td className="px-6 py-4">
                      <span className="text-rose-600 dark:text-rose-400 font-bold">{rec.payslipData?.lopDays || 0} LOP</span> / <span className="text-emerald-600 dark:text-emerald-400 font-bold">{rec.payslipData?.paidDays || 0} Paid</span>
                    </td>
                    <td className="px-6 py-4 font-black text-indigo-600 dark:text-indigo-400">₹{rec.netSalary.toLocaleString()}</td>
                    <td className="px-6 py-4 text-right">
                      <button
                        onClick={() => setViewingPayslip(rec)}
                        className="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1 justify-end ml-auto"
                      >
                        <FileText className="w-3.5 h-3.5" /> View Payslip
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
          No finalized payroll statements found for "{formatToMonthYearString(monthYear)}".
        </div>
      )}

      {/* Payslip View Modal */}
      {viewingPayslip && (
        <div className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm flex items-center justify-center p-6 z-50">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 shadow-2xl overflow-y-auto max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-6">
              <h3 className="font-extrabold text-slate-800 dark:text-white text-xs uppercase">Official Payslip Statement ({formatToMonthYearString(monthYear)})</h3>
              <button
                onClick={() => setViewingPayslip(null)}
                className="px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 rounded-lg text-xs font-bold transition-all"
              >
                Close
              </button>
            </div>

            <div className="border border-slate-300 dark:border-slate-700 p-6 rounded-2xl text-xs space-y-6 text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-950/40">
              <div className="text-center border-b border-slate-200 dark:border-slate-700 pb-4">
                <h2 className="font-extrabold text-slate-800 dark:text-white text-sm uppercase">Search Homes India Pvt Ltd</h2>
                <p className="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Pay Slip for the month of {formatToMonthYearString(monthYear)}</p>
              </div>

              <div className="grid grid-cols-2 gap-4 border-b border-slate-200 dark:border-slate-700 pb-4 font-semibold text-[11px]">
                <div>
                  <p className="text-slate-400 dark:text-slate-500">Employee Name: <span className="text-slate-800 dark:text-white font-bold ml-1 capitalize">{viewingPayslip.user?.username}</span></p>
                  <p className="text-slate-400 dark:text-slate-500 mt-1">Employee ID: <span className="text-slate-800 dark:text-white font-bold ml-1">{viewingPayslip.user?.employee_id || '-'}</span></p>
                </div>
                <div>
                  <p className="text-slate-400 dark:text-slate-500">Basic Payout CTC: <span className="text-slate-800 dark:text-white font-bold ml-1">₹{viewingPayslip.baseSalary.toLocaleString()}</span></p>
                  <p className="text-slate-400 dark:text-slate-500 mt-1">Paid Days: <span className="text-slate-800 dark:text-white font-bold ml-1">{viewingPayslip.payslipData?.paidDays} / {viewingPayslip.totalDays} days</span></p>
                </div>
              </div>

              {viewingPayslip.payslipData && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <h4 className="font-extrabold text-indigo-700 dark:text-indigo-400 uppercase tracking-wider text-[10px] border-b border-slate-200 dark:border-slate-700 pb-1.5 mb-3">Earnings Breakdown</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between">
                        <span>Basic Salary</span>
                        <span className="font-bold">₹{viewingPayslip.payslipData.earnings.basic.toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between">
                        <span>HRA</span>
                        <span className="font-bold">₹{viewingPayslip.payslipData.earnings.hra.toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between">
                        <span>Conveyance</span>
                        <span className="font-bold">₹{viewingPayslip.payslipData.earnings.conveyance.toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between">
                        <span>Special Allowance</span>
                        <span className="font-bold">₹{viewingPayslip.payslipData.earnings.special.toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2 font-bold text-slate-800 dark:text-white">
                        <span>Gross Salary</span>
                        <span>₹{viewingPayslip.payslipData.earnings.gross.toLocaleString()}</span>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 className="font-extrabold text-rose-700 dark:text-rose-400 uppercase tracking-wider text-[10px] border-b border-slate-200 dark:border-slate-700 pb-1.5 mb-3">Deductions Breakdown</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between">
                        <span>Provident Fund (PF)</span>
                        <span className="font-bold">₹{viewingPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between">
                        <span>Professional Tax (PT)</span>
                        <span className="font-bold">₹{viewingPayslip.payslipData.deductions.professionalTax.toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between">
                        <span>Medical Insurance Benefit</span>
                        <span className="font-bold">₹{viewingPayslip.payslipData.deductions.medical.toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2 font-bold text-slate-800 dark:text-white">
                        <span>Total Deductions</span>
                        <span>₹{viewingPayslip.payslipData.deductions.total.toLocaleString()}</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              <div className="border-t border-slate-300 dark:border-slate-700 pt-4 flex items-center justify-between bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 p-4 rounded-xl">
                <div>
                  <span className="text-[10px] font-bold text-indigo-400 dark:text-indigo-500 uppercase tracking-widest block">Net Payout Amount</span>
                  <span className="text-xl font-black text-indigo-700 dark:text-indigo-400 mt-1 block">₹{viewingPayslip.netSalary.toLocaleString()}</span>
                </div>
                <button
                  onClick={() => window.print()}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 text-slate-700 dark:text-white rounded-xl text-[10px] font-bold transition-all shadow-sm"
                >
                  Print PDF
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default PayslipsPage;
