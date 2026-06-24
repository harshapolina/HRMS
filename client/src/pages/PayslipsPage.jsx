import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, FileText, RefreshCw, X, Download } from 'lucide-react';

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

  const getSundaysCountForMonth = (yyyyMm) => {
    if (!yyyyMm) return 4;
    const [year, month] = yyyyMm.split('-');
    const yearInt = parseInt(year, 10);
    const monthInt = parseInt(month, 10) - 1;
    if (isNaN(yearInt) || isNaN(monthInt)) return 4;
    
    let count = 0;
    const date = new Date(yearInt, monthInt, 1);
    while (date.getMonth() === monthInt) {
      if (date.getDay() === 0) count++;
      date.setDate(date.getDate() + 1);
    }
    return count;
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
    const username = rec.user?.username || rec.employeeName || '';
    const empId = rec.user?.employee_id || rec.employeeId || '';
    return (
      username.toLowerCase().includes(term) ||
      empId.toLowerCase().includes(term)
    );
  });

  return (
    <div className="page-shell">


      <div className="toolbar">
        <div className="toolbar-left">
          <div className="flex items-center gap-2">
            <label className="label-xs mb-0 whitespace-nowrap" htmlFor="payslip-month-input">Payroll Month</label>
            <input
              id="payslip-month-input"
              type="month"
              value={monthYear}
              onChange={(e) => setMonthYear(e.target.value)}
              className="input-field text-xs w-40 text-center font-semibold"
            />
          </div>
          <div className="search-wrap">
            <input
              id="payslip-search-input"
              type="text"
              placeholder="Filter by name/ID..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="search-input text-xs"
            />
            <Search className="w-4 h-4 text-muted-soft absolute left-3 top-1/2 -translate-y-1/2" />
          </div>
        </div>
        <div className="toolbar-right">
          <button id="payslip-reload-btn" onClick={fetchFinalizedPayroll} className="filter-btn text-muted" title="Refresh">
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {filteredRecords.length > 0 ? (
        <div className="table-container">
          <div className="overflow-x-auto w-full">
            <table className="table-shell text-xs">
              <thead>
                <tr>
                  <th>Employee ID</th>
                  <th>Name</th>
                  <th>Base Salary</th>
                  <th>Punches</th>
                  <th>LOP / Paid Days</th>
                  <th>Net Payout</th>
                  <th className="text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                {filteredRecords.map((rec) => (
                  <tr key={rec._id}>
                    <td className="font-mono font-semibold text-muted">{rec.user?.employee_id || rec.employeeId || '-'}</td>
                    <td>
                      <span className="font-semibold text-ink block capitalize">{rec.user?.username || rec.employeeName || 'Deleted Employee'}</span>
                      <span className="block text-[10px] text-muted capitalize">{rec.user?.user_type || rec.userType || ''}</span>
                    </td>
                    <td className="font-semibold">₹{rec.baseSalary.toLocaleString()}</td>
                    <td>{rec.presentDays} days</td>
                    <td>
                      <span className="text-error font-semibold">{rec.payslipData?.lopDays || 0} LOP</span>
                      <span className="text-muted"> / </span>
                      <span className="text-success font-semibold">{rec.payslipData?.paidDays || 0} Paid</span>
                    </td>
                    <td className="font-semibold text-ink">₹{rec.netSalary.toLocaleString()}</td>
                    <td className="text-right">
                      <button id={`payslip-view-btn-${rec._id}`} onClick={() => setViewingPayslip(rec)} className="btn-secondary btn-sm ml-auto">
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
        <div className="empty-state">
          No finalized payroll statements found for &ldquo;{formatToMonthYearString(monthYear)}&rdquo;.
        </div>
      )}

      {viewingPayslip && (() => {
        const sundaysVal = viewingPayslip.payslipData?.sundaysCount ?? getSundaysCountForMonth(monthYear);
        const workingDaysVal = viewingPayslip.payslipData?.workingDays ?? (viewingPayslip.totalDays - sundaysVal);
        const customDeductionsSum = (viewingPayslip.payslipData?.deductions?.custom || []).reduce((acc, d) => acc + (d.amount || 0), 0);
        const lopDeductionVal = viewingPayslip.payslipData?.deductions?.lopDeduction ?? (
          (viewingPayslip.payslipData?.deductions?.total ?? 0) -
          (viewingPayslip.payslipData?.deductions?.pfEmployee ?? 0) -
          (viewingPayslip.payslipData?.deductions?.professionalTax ?? 0) -
          (viewingPayslip.payslipData?.deductions?.medical ?? 0) -
          customDeductionsSum
        );

        return (
          <div className="modal-overlay" onClick={() => setViewingPayslip(null)}>
            <div className="modal-popup max-w-2xl" onClick={(e) => e.stopPropagation()}>
              {/* Header */}
              <div className="modal-popup-header bg-primary text-white flex justify-between items-center px-6 py-4 shrink-0 rounded-t-2xl">
                <div className="flex items-center gap-2">
                  <FileText className="w-5 h-5 text-white" />
                  <h3 className="font-semibold text-white text-sm">Payslip Preview</h3>
                </div>
                <button
                  onClick={() => setViewingPayslip(null)}
                  className="p-1.5 hover:bg-white/10 rounded-lg text-white/80 hover:text-white transition-colors shrink-0"
                  aria-label="Close Preview"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              {/* Body */}
              <div className="modal-panel-body p-6 space-y-6 text-body">
                {/* Employee Details Grid */}
                <div className="border border-hairline rounded-lg overflow-hidden bg-canvas">
                  <table className="w-full text-xs text-left border-collapse border-spacing-0">
                    <tbody>
                      <tr className="border-b border-hairline">
                        <td className="px-4 py-3 border-r border-hairline w-1/2">
                          <span className="font-semibold text-muted">Employee Name:</span> <span className="font-bold text-ink capitalize ml-1">{viewingPayslip.user?.username || viewingPayslip.employeeName || 'Deleted Employee'}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Designation:</span> <span className="font-bold text-ink capitalize ml-1">{viewingPayslip.user?.user_type || viewingPayslip.userType || 'User'}</span>
                        </td>
                      </tr>
                      <tr className="border-b border-hairline">
                        <td className="px-4 py-3 border-r border-hairline">
                          <span className="font-semibold text-muted">Working Days:</span> <span className="font-bold text-ink ml-1">{workingDaysVal}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Loss of Pay (Days):</span> <span className="font-bold text-ink text-error ml-1">{viewingPayslip.payslipData?.lopDays ?? 0}</span>
                        </td>
                      </tr>
                      <tr>
                        <td colSpan="2" className="px-4 py-3">
                          <span className="font-semibold text-muted">Calendar:</span> <span className="font-bold text-ink ml-1">{viewingPayslip.totalDays} days</span>
                          <span className="text-muted mx-2">|</span>
                          <span className="font-semibold text-muted">Sundays (excluded):</span> <span className="font-bold text-ink ml-1">{sundaysVal}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                {/* Earnings & Deductions Tables */}
                {viewingPayslip.payslipData && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Earnings */}
                    <div>
                      <h4 className="text-xs font-bold text-primary tracking-wider uppercase mb-3">Earnings</h4>
                      <div className="border border-hairline rounded-lg overflow-hidden">
                        <table className="w-full text-xs text-left">
                          <tbody className="divide-y divide-hairline">
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Basic</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.basic.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">HRA</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.hra.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Conveyance</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.conveyance.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Special Allowance</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingPayslip.payslipData.earnings.special.toLocaleString()}</td>
                            </tr>
                            <tr className="bg-surface-soft font-bold text-ink border-t border-hairline">
                              <td className="px-4 py-3">Total Earnings:</td>
                              <td className="px-4 py-3 text-right">₹{viewingPayslip.payslipData.earnings.gross.toLocaleString()}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    {/* Deductions */}
                    <div>
                      <h4 className="text-xs font-bold text-primary tracking-wider uppercase mb-3">Deductions</h4>
                      <div className="border border-hairline rounded-lg overflow-hidden">
                        <table className="w-full text-xs text-left">
                          <tbody className="divide-y divide-hairline">
                            <tr>
                              <td className="px-4 py-2.5 text-muted">PF</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Professional Tax</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingPayslip.payslipData.deductions.professionalTax.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Medical Benefit</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingPayslip.payslipData.deductions.medical.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">LOP Deduction</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{lopDeductionVal.toLocaleString()}</td>
                            </tr>
                            {viewingPayslip.payslipData.deductions.custom && viewingPayslip.payslipData.deductions.custom.map((cust, idx) => (
                              <tr key={idx}>
                                <td className="px-4 py-2.5 text-muted">{cust.name}</td>
                                <td className="px-4 py-2.5 text-right font-semibold text-error">₹{cust.amount.toLocaleString()}</td>
                              </tr>
                            ))}
                            <tr className="bg-surface-soft font-bold text-error border-t border-hairline">
                              <td className="px-4 py-3">Total Deductions:</td>
                              <td className="px-4 py-3 text-right">₹{viewingPayslip.payslipData.deductions.total.toLocaleString()}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                )}

                {/* Net Payout Row & Close / Download PDF Buttons */}
                <div className="flex justify-between items-center border-t border-hairline pt-6 mt-4">
                  <div>
                    <span className="text-[10px] font-semibold text-muted uppercase tracking-wider block">Net Take Home Pay</span>
                    <span className={`text-xl font-bold block mt-0.5 ${viewingPayslip.netSalary >= 0 ? 'text-success' : 'text-error'}`}>
                      {viewingPayslip.netSalary < 0 ? '-' : ''}₹{Math.abs(viewingPayslip.netSalary).toLocaleString()}
                    </span>
                  </div>
                  <div className="flex gap-3">
                    <button
                      onClick={() => setViewingPayslip(null)}
                      className="btn-secondary h-9 px-5 text-xs bg-gray-500 hover:bg-gray-600 active:bg-gray-700 text-white border-none"
                    >
                      Close
                    </button>
                    <button
                      onClick={() => window.print()}
                      className="btn-primary h-9 px-5 text-xs flex items-center gap-1.5"
                    >
                      <Download className="w-3.5 h-3.5" /> Download PDF
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        );
      })()}
    </div>
  );
};

export default PayslipsPage;
