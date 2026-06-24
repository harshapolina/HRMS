import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { RefreshCw, FileText, X } from 'lucide-react';

const Payroll = () => {
  const [payrollMonth, setPayrollMonth] = useState('2026-06');
  const [calculatedPayroll, setCalculatedPayroll] = useState(null);
  const [payrollProcessing, setPayrollProcessing] = useState(false);
  const [payrollCalculatedList, setPayrollCalculatedList] = useState([]);
  const [viewingDraftPayslip, setViewingDraftPayslip] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    calculatePayroll();
  }, [payrollMonth]);

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
    <div className="page-shell">


      <div className="toolbar">
        <div className="toolbar-left">
          <label className="label-xs mb-0 whitespace-nowrap" htmlFor="payroll-month-input">Run Payroll Month</label>
          <input
            id="payroll-month-input"
            type="month"
            value={payrollMonth}
            onChange={(e) => setPayrollMonth(e.target.value)}
            className="input-field text-xs w-40 text-center font-semibold"
          />
          <button id="payroll-calculate-btn" onClick={calculatePayroll} disabled={loading} className="btn-primary btn-sm">
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Calculate Sheet
          </button>
        </div>

        {payrollCalculatedList.length > 0 && (
          <div className="toolbar-right">
            <button id="payroll-finalize-btn" onClick={processPayroll} disabled={payrollProcessing} className="btn-primary btn-sm">
              {payrollProcessing ? 'Finalizing...' : 'Finalize & Save Payroll'}
            </button>
          </div>
        )}
      </div>

      {payrollCalculatedList.length > 0 ? (
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
                {payrollCalculatedList.map((rec, i) => (
                  <tr key={i}>
                    <td className="font-mono font-semibold text-muted">{rec.employee_id || '-'}</td>
                    <td>
                      <span className="font-semibold text-ink block capitalize">{rec.username}</span>
                      <span className="block text-[10px] text-muted capitalize">{rec.user_type}</span>
                    </td>
                    <td className="font-semibold">₹{rec.baseSalary.toLocaleString()}</td>
                    <td>{rec.presentDays} days</td>
                    <td>
                      <span className="text-error font-semibold">{rec.lopDays} LOP</span>
                      <span className="text-muted"> / </span>
                      <span className="text-success font-semibold">{rec.paidDays} Paid</span>
                    </td>
                    <td className="font-semibold text-ink">₹{rec.netSalary.toLocaleString()}</td>
                    <td className="text-right">
                      <button id={`payroll-statement-btn-${rec.employee_id}`} onClick={() => setViewingDraftPayslip(rec)} className="btn-secondary btn-sm ml-auto">
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
        <div className="empty-state">
          Select month-year above and click Calculate Sheet to generate payroll drafts.
        </div>
      )}

      {viewingDraftPayslip && (() => {
        const sundaysVal = viewingDraftPayslip.payslipData.sundaysCount ?? getSundaysCountForMonth(payrollMonth);
        const workingDaysVal = viewingDraftPayslip.payslipData.workingDays ?? (viewingDraftPayslip.totalDays - sundaysVal);
        const customDeductionsSum = (viewingDraftPayslip.payslipData.deductions.custom || []).reduce((acc, d) => acc + (d.amount || 0), 0);
        const lopDeductionVal = viewingDraftPayslip.payslipData.deductions.lopDeduction ?? (
          viewingDraftPayslip.payslipData.deductions.total -
          (viewingDraftPayslip.payslipData.deductions.pfEmployee ?? 0) -
          (viewingDraftPayslip.payslipData.deductions.professionalTax ?? 0) -
          (viewingDraftPayslip.payslipData.deductions.medical ?? 0) -
          customDeductionsSum
        );

        return (
          <div className="modal-overlay" onClick={() => setViewingDraftPayslip(null)}>
            <div className="modal-popup max-w-2xl" onClick={(e) => e.stopPropagation()}>
              {/* Header */}
              <div className="modal-popup-header bg-primary text-white flex justify-between items-center px-6 py-4 shrink-0 rounded-t-2xl">
                <div className="flex items-center gap-2">
                  <FileText className="w-5 h-5 text-white" />
                  <h3 className="font-semibold text-white text-sm">Payslip Preview (Draft)</h3>
                </div>
                <button
                  onClick={() => setViewingDraftPayslip(null)}
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
                          <span className="font-semibold text-muted">Employee Name:</span> <span className="font-bold text-ink capitalize ml-1">{viewingDraftPayslip.username}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Designation:</span> <span className="font-bold text-ink capitalize ml-1">{viewingDraftPayslip.user_type}</span>
                        </td>
                      </tr>
                      <tr className="border-b border-hairline">
                        <td className="px-4 py-3 border-r border-hairline">
                          <span className="font-semibold text-muted">Working Days:</span> <span className="font-bold text-ink ml-1">{workingDaysVal}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Loss of Pay (Days):</span> <span className="font-bold text-ink text-error ml-1">{viewingDraftPayslip.lopDays}</span>
                        </td>
                      </tr>
                      <tr>
                        <td colSpan="2" className="px-4 py-3">
                          <span className="font-semibold text-muted">Calendar:</span> <span className="font-bold text-ink ml-1">{viewingDraftPayslip.totalDays} days</span>
                          <span className="text-muted mx-2">|</span>
                          <span className="font-semibold text-muted">Sundays (excluded):</span> <span className="font-bold text-ink ml-1">{sundaysVal}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                {/* Earnings & Deductions Tables */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Earnings */}
                  <div>
                    <h4 className="text-xs font-bold text-primary tracking-wider uppercase mb-3">Earnings</h4>
                    <div className="border border-hairline rounded-lg overflow-hidden">
                      <table className="w-full text-xs text-left">
                        <tbody className="divide-y divide-hairline">
                          <tr>
                            <td className="px-4 py-2.5 text-muted">Basic</td>
                            <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingDraftPayslip.payslipData.earnings.basic.toLocaleString()}</td>
                          </tr>
                          <tr>
                            <td className="px-4 py-2.5 text-muted">HRA</td>
                            <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingDraftPayslip.payslipData.earnings.hra.toLocaleString()}</td>
                          </tr>
                          <tr>
                            <td className="px-4 py-2.5 text-muted">Conveyance</td>
                            <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingDraftPayslip.payslipData.earnings.conveyance.toLocaleString()}</td>
                          </tr>
                          <tr>
                            <td className="px-4 py-2.5 text-muted">Special Allowance</td>
                            <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{viewingDraftPayslip.payslipData.earnings.special.toLocaleString()}</td>
                          </tr>
                          <tr className="bg-surface-soft font-bold text-ink border-t border-hairline">
                            <td className="px-4 py-3">Total Earnings:</td>
                            <td className="px-4 py-3 text-right">₹{viewingDraftPayslip.payslipData.earnings.gross.toLocaleString()}</td>
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
                            <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingDraftPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</td>
                          </tr>
                          <tr>
                            <td className="px-4 py-2.5 text-muted">Professional Tax</td>
                            <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingDraftPayslip.payslipData.deductions.professionalTax.toLocaleString()}</td>
                          </tr>
                          <tr>
                            <td className="px-4 py-2.5 text-muted">Medical Benefit</td>
                            <td className="px-4 py-2.5 text-right font-semibold text-error">₹{viewingDraftPayslip.payslipData.deductions.medical.toLocaleString()}</td>
                          </tr>
                          <tr>
                            <td className="px-4 py-2.5 text-muted">LOP Deduction</td>
                            <td className="px-4 py-2.5 text-right font-semibold text-error">₹{lopDeductionVal.toLocaleString()}</td>
                          </tr>
                          {viewingDraftPayslip.payslipData.deductions.custom && viewingDraftPayslip.payslipData.deductions.custom.map((cust, idx) => (
                            <tr key={idx}>
                              <td className="px-4 py-2.5 text-muted">{cust.name}</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{cust.amount.toLocaleString()}</td>
                            </tr>
                          ))}
                          <tr className="bg-surface-soft font-bold text-error border-t border-hairline">
                            <td className="px-4 py-3">Total Deductions:</td>
                            <td className="px-4 py-3 text-right">₹{viewingDraftPayslip.payslipData.deductions.total.toLocaleString()}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                {/* Net Payout Row & Close Button */}
                <div className="flex justify-between items-center border-t border-hairline pt-6 mt-4">
                  <div>
                    <span className="text-[10px] font-semibold text-muted uppercase tracking-wider block">Net Take Home Pay</span>
                    <span className={`text-xl font-bold block mt-0.5 ${viewingDraftPayslip.netSalary >= 0 ? 'text-success' : 'text-error'}`}>
                      {viewingDraftPayslip.netSalary < 0 ? '-' : ''}₹{Math.abs(viewingDraftPayslip.netSalary).toLocaleString()}
                    </span>
                  </div>
                  <button
                    onClick={() => setViewingDraftPayslip(null)}
                    className="btn-secondary h-9 px-5 text-xs bg-gray-500 hover:bg-gray-600 active:bg-gray-700 text-white border-none"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        );
      })()}
    </div>
  );
};

export default Payroll;
