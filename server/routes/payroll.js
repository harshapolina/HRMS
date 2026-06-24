import express from 'express';
import Payroll from '../models/Payroll.js';
import User from '../models/User.js';
import Attendance from '../models/Attendance.js';
import LeaveRequest from '../models/LeaveRequest.js';
import Holiday from '../models/Holiday.js';
import Setting from '../models/Setting.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// Helper: Parse "Jun 2026" to date boundaries
const parseMonthYear = (monthYear) => {
  const parts = monthYear.split(' ');
  if (parts.length !== 2) return null;
  const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  const monthIndex = monthNames.indexOf(parts[0]);
  const year = parseInt(parts[1]);
  if (monthIndex === -1 || isNaN(year)) return null;

  const startDate = new Date(year, monthIndex, 1);
  const endDate = new Date(year, monthIndex + 1, 0); // last day of month
  return { startDate, endDate, month: monthIndex + 1, year, daysInMonth: endDate.getDate() };
};

// Helper: Count Sundays in a month
const countSundays = (start, end) => {
  let count = 0;
  let curr = new Date(start);
  while (curr <= end) {
    if (curr.getDay() === 0) count++;
    curr.setDate(curr.getDate() + 1);
  }
  return count;
};

// Admin: Get finalized payroll records by month
router.get('/', protect, adminOnly, async (req, res) => {
  try {
    const { monthYear } = req.query;
    if (!monthYear) {
      return res.status(400).json({ message: 'monthYear query parameter is required.' });
    }

    const records = await Payroll.find({ monthYear })
      .populate('user', 'username employee_id user_type project_name useremail phonenumber');
    res.json(records);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Employee: Get own payslips
router.get('/my', protect, async (req, res) => {
  try {
    const records = await Payroll.find({ user: req.user._id })
      .populate('user', 'username employee_id user_type')
      .sort({ createdAt: -1 });
    res.json(records);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Get payroll records of a specific user
router.get('/user/:userId', protect, adminOnly, async (req, res) => {
  try {
    const records = await Payroll.find({ user: req.params.userId })
      .populate('user', 'username employee_id user_type')
      .sort({ createdAt: -1 });
    res.json(records);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Run draft payroll calculations
router.get('/calculate', protect, adminOnly, async (req, res) => {
  try {
    const { monthYear } = req.query; // e.g. "Jun 2026"
    if (!monthYear) {
      return res.status(400).json({ message: 'monthYear is required.' });
    }

    const parsed = parseMonthYear(monthYear);
    if (!parsed) {
      return res.status(400).json({ message: 'Invalid monthYear format. Use e.g. "Jun 2026"' });
    }

    const { startDate, endDate, daysInMonth } = parsed;

    // Fetch all active employees (excluding superadmin)
    const employees = await User.find({
      is_active: true,
      user_type: { $ne: 'superuseradmin' }
    });

    // Fetch all holidays in this range
    const holidays = await Holiday.find({
      date: { $gte: startDate, $lte: endDate }
    });
    const holidaysCount = holidays.length;

    const sundaysCount = countSundays(startDate, endDate);

    // Fetch custom deductions from DB settings
    let customDeductions = [];
    try {
      const deductionsRecord = await Setting.findOne({ key: 'custom_deductions' });
      if (deductionsRecord && Array.isArray(deductionsRecord.value)) {
        customDeductions = deductionsRecord.value.filter(d => d.status === 'Active');
      }
    } catch (err) {
      console.error('[Payroll] Error loading custom deductions:', err.message);
    }

    const calculations = [];

    for (const emp of employees) {
      // Find all punch records for the employee in this month
      // Date strings in DB are YYYY-MM-DD
      const datePrefix = `${parsed.year}-${String(parsed.month).padStart(2, '0')}`;
      const punches = await Attendance.find({
        user: emp._id,
        date: { $regex: '^' + datePrefix }
      });

      // Present/Late count
      const presentCount = punches.filter(p => ['Present', 'Late'].includes(p.status)).length;

      // Find all approved leaves overlapping this month
      const leaves = await LeaveRequest.find({
        user: emp._id,
        status: 'Approved',
        startDate: { $lte: endDate },
        endDate: { $gte: startDate }
      });

      let leavePaidCount = 0;
      let leaveUnpaidCount = 0;

      leaves.forEach(l => {
        // Calculate overlap days
        const overlapStart = l.startDate < startDate ? startDate : l.startDate;
        const overlapEnd = l.endDate > endDate ? endDate : l.endDate;
        const timeDiff = Math.abs(overlapEnd - overlapStart);
        const days = Math.ceil(timeDiff / (1000 * 60 * 60 * 24)) + 1;

        if (l.leaveType === 'Unpaid Leave') {
          leaveUnpaidCount += days;
        } else {
          leavePaidCount += days;
        }
      });

      // Paid Days = Present days + Sundays + Holidays + Paid Leaves
      // LOP Days = Days in month - Paid Days
      // In typical Indian payroll, Sundays and holidays are paid.
      // If an employee has 0 present days and 0 paid leaves, they receive 0 paid days.
      let paidDays = 0;
      if (presentCount > 0 || leavePaidCount > 0) {
        paidDays = Math.min(daysInMonth, presentCount + sundaysCount + holidaysCount + leavePaidCount);
      }
      const lopDays = Math.max(0, daysInMonth - paidDays);

      // CTC Calculations
      const monthlyCTC = emp.salary || 0;

      const hasManual = !!(
        (emp.one_amt && parseFloat(emp.one_amt) > 0) ||
        (emp.two_amt && parseFloat(emp.two_amt) > 0) ||
        (emp.thrid_amt && parseFloat(emp.thrid_amt) > 0) ||
        (emp.forth_amt && parseFloat(emp.forth_amt) > 0) ||
        (emp.fifth_amt && parseFloat(emp.fifth_amt) > 0) ||
        (emp.sixth_amt && parseFloat(emp.sixth_amt) > 0)
      );

      const basic = hasManual ? (parseFloat(emp.one_amt) || 0) : Math.round(monthlyCTC * 0.5);
      const hra = hasManual ? (parseFloat(emp.two_amt) || 0) : Math.round(monthlyCTC * 0.2);
      const conveyance = hasManual ? (parseFloat(emp.thrid_amt) || 0) : Math.round(monthlyCTC * 0.07);
      const pfEmployer = hasManual ? (parseFloat(emp.fifth_amt) || 0) : Math.min(1800, Math.round(basic * 0.12));
      const monthlyGross = monthlyCTC - pfEmployer;
      const special = hasManual ? (parseFloat(emp.forth_amt) || 0) : Math.max(0, monthlyGross - (basic + hra + conveyance));

      // Employee deductions (without LOP first)
      const pfEmployee = pfEmployer;
      const professionalTax = monthlyCTC > 15000 ? 200 : 0;
      let medical = 0;
      if (hasManual) {
        medical = Math.max(0, (parseFloat(emp.sixth_amt) || 0) - pfEmployee - professionalTax);
      } else {
        medical = monthlyCTC > 10000 ? 817 : 0;
      }

      // Working days & LOP Deduction
      const workingDays = daysInMonth - sundaysCount;
      const lopDeduction = workingDays > 0 ? Math.round((monthlyGross / workingDays) * lopDays) : 0;

      // Apply dynamic Settings-defined custom deductions
      const appliedCustomDeductions = [];
      let customDeductionsSum = 0;
      customDeductions.forEach(ded => {
        let amt = 0;
        if (ded.type === 'flat') {
          amt = parseFloat(ded.value) || 0;
        } else if (ded.type === 'percentage') {
          amt = Math.round((monthlyCTC * (parseFloat(ded.value) || 0)) / 100);
        }
        if (amt > 0) {
          appliedCustomDeductions.push({
            name: ded.name,
            amount: amt
          });
          customDeductionsSum += amt;
        }
      });

      // Total Deductions
      const totalDeductions = pfEmployee + professionalTax + medical + lopDeduction + customDeductionsSum;

      // Net Pay
      const netPay = Math.round(monthlyGross - totalDeductions);

      calculations.push({
        userId: emp._id,
        username: emp.username,
        employee_id: emp.employee_id,
        user_type: emp.user_type,
        baseSalary: monthlyCTC,
        totalDays: daysInMonth,
        presentDays: presentCount,
        lopDays,
        paidDays,
        netSalary: netPay,
        payslipData: {
          earnings: { basic, hra, conveyance, special, pfEmployer, gross: monthlyGross },
          deductions: { pfEmployee, professionalTax, medical, lopDeduction, custom: appliedCustomDeductions, total: totalDeductions },
          netPay,
          paidDays,
          lopDays,
          totalDays: daysInMonth,
          workingDays,
          sundaysCount
        }
      });
    }

    res.json({ monthYear, calculations });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Save/Process bulk payroll
router.post('/process', protect, adminOnly, async (req, res) => {
  try {
    const { monthYear, records } = req.body;
    if (!monthYear || !records || !Array.isArray(records)) {
      return res.status(400).json({ message: 'monthYear and records array are required.' });
    }

    const savedRecords = [];
    for (const rec of records) {
      // Create or update payroll record
      const payroll = await Payroll.findOneAndUpdate(
        { user: rec.userId, monthYear },
        {
          user: rec.userId,
          monthYear,
          baseSalary: rec.baseSalary,
          presentDays: rec.presentDays,
          totalDays: rec.totalDays,
          deductions: rec.payslipData.deductions.total,
          netSalary: rec.netSalary,
          payslipData: rec.payslipData,
          status: 'Processed',
          employeeName: rec.username,
          employeeId: rec.employee_id,
          userType: rec.user_type
        },
        { upsert: true, new: true }
      );
      savedRecords.push(payroll);
    }

    res.json({ message: 'Payroll processed successfully.', count: savedRecords.length });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
