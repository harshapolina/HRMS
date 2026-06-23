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
    const username = rec.user?.username || rec.employeeName || '';
    const empId = rec.user?.employee_id || rec.employeeId || '';
    return (
      username.toLowerCase().includes(term) ||
      empId.toLowerCase().includes(term)
    );
  });

  return (
    <div className="page-shell">
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Finance Portal</p>
          <h1 className="page-title">Processed Payslips</h1>
          <p className="page-subtitle">View and print finalized payslip statements by month.</p>
        </div>
      </div>

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

      {viewingPayslip && (
        <div className="modal-overlay">
          <div className="modal-panel-lg">
            <div className="modal-header mb-0 pb-4">
              <h3 className="font-semibold text-ink text-sm">Official Payslip ({formatToMonthYearString(monthYear)})</h3>
              <button id="close-payslip-modal-btn" onClick={() => setViewingPayslip(null)} className="btn-secondary btn-sm">Close</button>
            </div>

            <div className="border border-hairline p-6 rounded-lg text-xs space-y-6 text-body bg-surface-soft/50 mt-6">
              <div className="text-center border-b border-hairline pb-4">
                <h2 className="font-semibold text-ink text-sm uppercase">Search Homes India Pvt Ltd</h2>
                <p className="text-[10px] text-muted mt-0.5">Pay Slip for the month of {formatToMonthYearString(monthYear)}</p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-hairline pb-4 font-semibold text-[11px]">
                <div>
                  <p className="text-muted">Employee Name: <span className="text-ink font-semibold ml-1 capitalize">{viewingPayslip.user?.username || viewingPayslip.employeeName || 'Deleted Employee'}</span></p>
                  <p className="text-muted mt-1">Employee ID: <span className="text-ink font-semibold ml-1">{viewingPayslip.user?.employee_id || viewingPayslip.employeeId || '-'}</span></p>
                </div>
                <div>
                  <p className="text-muted">Basic Payout CTC: <span className="text-ink font-semibold ml-1">₹{viewingPayslip.baseSalary.toLocaleString()}</span></p>
                  <p className="text-muted mt-1">Paid Days: <span className="text-ink font-semibold ml-1">{viewingPayslip.payslipData?.paidDays} / {viewingPayslip.totalDays} days</span></p>
                </div>
              </div>

              {viewingPayslip.payslipData && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <h4 className="statement-section-title">Earnings Breakdown</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between gap-4"><span>Basic Salary</span><span className="font-semibold">₹{viewingPayslip.payslipData.earnings.basic.toLocaleString()}</span></div>
                      <div className="flex justify-between gap-4"><span>HRA</span><span className="font-semibold">₹{viewingPayslip.payslipData.earnings.hra.toLocaleString()}</span></div>
                      <div className="flex justify-between gap-4"><span>Conveyance</span><span className="font-semibold">₹{viewingPayslip.payslipData.earnings.conveyance.toLocaleString()}</span></div>
                      <div className="flex justify-between gap-4"><span>Special Allowance</span><span className="font-semibold">₹{viewingPayslip.payslipData.earnings.special.toLocaleString()}</span></div>
                      <div className="flex justify-between gap-4 border-t border-hairline pt-2 font-semibold text-ink"><span>Gross Salary</span><span>₹{viewingPayslip.payslipData.earnings.gross.toLocaleString()}</span></div>
                    </div>
                  </div>
                  <div>
                    <h4 className="statement-section-title">Deductions Breakdown</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between gap-4"><span>Provident Fund (PF)</span><span className="font-semibold">₹{viewingPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</span></div>
                      <div className="flex justify-between gap-4"><span>Professional Tax (PT)</span><span className="font-semibold">₹{viewingPayslip.payslipData.deductions.professionalTax.toLocaleString()}</span></div>
                      <div className="flex justify-between gap-4"><span>Medical Insurance Benefit</span><span className="font-semibold">₹{viewingPayslip.payslipData.deductions.medical.toLocaleString()}</span></div>
                      <div className="flex justify-between gap-4 border-t border-hairline pt-2 font-semibold text-ink"><span>Total Deductions</span><span>₹{viewingPayslip.payslipData.deductions.total.toLocaleString()}</span></div>
                    </div>
                  </div>
                </div>
              )}

              <div className="border-t border-hairline pt-4 flex items-center justify-between bg-surface-soft border border-hairline p-4 rounded-lg">
                <div>
                  <span className="statement-net-label">Net Payout Amount</span>
                  <span className="statement-net-value">₹{viewingPayslip.netSalary.toLocaleString()}</span>
                </div>
                <button id="payslip-print-btn" onClick={() => window.print()} className="btn-secondary btn-sm shrink-0">
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
