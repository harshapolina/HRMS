import mongoose from 'mongoose';

const PayrollSchema = new mongoose.Schema({
  user: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
  monthYear: { type: String, required: true }, // e.g., "Jun 2026"
  baseSalary: { type: Number, required: true },
  presentDays: { type: Number, required: true },
  totalDays: { type: Number, required: true },
  deductions: { type: Number, default: 0 },
  netSalary: { type: Number, required: true },
  status: { type: String, enum: ['Processed', 'Paid'], default: 'Processed' },
  payslipData: { type: Object }, // Stores full breakdown of earnings, statutory benefits, and net payouts
  employeeName: { type: String },
  employeeId: { type: String },
  userType: { type: String }
}, { timestamps: true });

PayrollSchema.index({ user: 1, monthYear: 1 }, { unique: true });

const Payroll = mongoose.model('Payroll', PayrollSchema);
export default Payroll;
