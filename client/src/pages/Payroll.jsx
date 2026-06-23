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
    <div className="page-shell">
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Superadmin Panel</p>
          <h1 className="page-title">Payroll Processing</h1>
          <p className="page-subtitle">Calculate and finalize monthly payroll for all employees.</p>
        </div>
      </div>

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

      {viewingDraftPayslip && (
        <div className="modal-overlay">
          <div className="modal-panel-lg">
            <div className="modal-header mb-0 pb-4">
              <h3 className="font-semibold text-ink text-sm">Draft Salary Statement ({formatToMonthYearString(payrollMonth)})</h3>
              <button id="close-statement-modal-btn" onClick={() => setViewingDraftPayslip(null)} className="btn-secondary btn-sm">Close</button>
            </div>

            <div className="border border-hairline p-6 rounded-lg text-xs space-y-6 text-body bg-surface-soft/50 mt-6">
              <div className="text-center border-b border-hairline pb-4">
                <h2 className="font-semibold text-ink text-sm uppercase">Draft Payroll Sheet</h2>
                <p className="text-[10px] text-muted mt-0.5">{viewingDraftPayslip.username} ({viewingDraftPayslip.employee_id})</p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-hairline pb-4 font-semibold text-[11px]">
                <div>
                  <p className="text-muted">Basic Payout CTC</p>
                  <p className="text-ink font-semibold mt-0.5">₹{viewingDraftPayslip.baseSalary.toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-muted">Attendance Days</p>
                  <p className="text-ink font-semibold mt-0.5">{viewingDraftPayslip.presentDays} Present / {viewingDraftPayslip.lopDays} LOP Days</p>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 className="statement-section-title">Earnings Breakdown</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between gap-4"><span>Basic Salary (50%)</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.basic.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4"><span>House Rent Allowance (HRA)</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.hra.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4"><span>Conveyance Allowance</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.conveyance.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4"><span>Special Allowance</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.special.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4"><span>PF Employer Part</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.earnings.pfEmployer.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4 border-t border-hairline pt-2 font-semibold text-ink"><span>Gross Payout</span><span>₹{viewingDraftPayslip.payslipData.earnings.gross.toLocaleString()}</span></div>
                  </div>
                </div>
                <div>
                  <h4 className="statement-section-title">Deductions Breakdown</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between gap-4"><span>PF (Employee Part)</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4"><span>Professional Tax (PT)</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.deductions.professionalTax.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4"><span>Medical Benefit</span><span className="font-semibold">₹{viewingDraftPayslip.payslipData.deductions.medical.toLocaleString()}</span></div>
                    <div className="flex justify-between gap-4 border-t border-hairline pt-2 font-semibold text-ink"><span>Total Deductions</span><span>₹{viewingDraftPayslip.payslipData.deductions.total.toLocaleString()}</span></div>
                  </div>
                </div>
              </div>

              <div className="border-t border-hairline pt-4 flex items-center justify-between bg-surface-soft border border-hairline p-4 rounded-lg">
                <div>
                  <span className="statement-net-label">Net Salary Payout</span>
                  <span className="statement-net-value">₹{viewingDraftPayslip.netSalary.toLocaleString()}</span>
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
