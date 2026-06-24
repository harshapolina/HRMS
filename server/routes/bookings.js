import express from 'express';
import Booking from '../models/Booking.js';
import User from '../models/User.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// Helper to get reporting hierarchy (same to same)
async function buildAssignPersonListUp(startTablename) {
  if (!startTablename) return [];
  const chain = [];
  const visited = new Set();
  let current = startTablename;

  while (current) {
    const user = await User.findOne({ tablename: current });
    if (!user || !user.assign_user || user.assign_user.length === 0) {
      break;
    }
    const parent = user.assign_user[0];
    if (!parent || parent === current || visited.has(parent)) {
      break;
    }
    chain.push(parent);
    visited.add(parent);
    current = parent;
  }
  return chain;
}

// Helper for Cashback Agreement Slab Deduction (same to same)
function calculateDeductAgreement(agreementValue, cashbackPct) {
  const agreement = Number(agreementValue) || 0;
  const cashback = Number(cashbackPct) || 0;

  if (agreement <= 0) return 0;

  let reductionPct = 0;
  if (cashback >= 0.1 && cashback <= 0.50) {
    reductionPct = 25.0;
  } else if (cashback > 0.50 && cashback <= 1.00) {
    reductionPct = 50.0;
  } else if (cashback > 1.00 && cashback <= 1.50) {
    reductionPct = 75.0;
  } else if (cashback > 1.50) {
    reductionPct = 100.0;
  }

  return Number((agreement - (agreement * (reductionPct / 100.0))).toFixed(2));
}

// GET all bookings (filters by approved vs pending, role gates apply)
router.get('/', protect, async (req, res) => {
  try {
    const { status, approvalStatus, city, year, search } = req.query;
    let filter = {};

    if (approvalStatus) {
      filter.approvalStatus = approvalStatus;
    }

    if (status) {
      filter.astatus = status;
    }

    if (city) {
      filter.city = city;
    }

    if (year) {
      // Filter by financial year e.g. "2026-2027"
      const [startYear, endYear] = year.split('-');
      filter.booking_month = {
        $gte: `${startYear}-04`,
        $lte: `${endYear}-03`
      };
    }

    if (search) {
      filter.$or = [
        { customer_name: { $regex: search, $options: 'i' } },
        { unit_no: { $regex: search, $options: 'i' } },
        { builder: { $regex: search, $options: 'i' } },
        { project: { $regex: search, $options: 'i' } }
      ];
    }

    // Role gate: non-admins can only see bookings associated with their tablename (or reporting)
    if (!['superuseradmin', 'hradmin'].includes(req.user.user_type)) {
      filter.$or = [
        { source_table: req.user.tablename },
        { assign_user: req.user.tablename }
      ];
    }

    const bookings = await Booking.find(filter).sort({ createdAt: -1 });
    res.json(bookings);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST create booking directly (goes to pending)
router.post('/', protect, async (req, res) => {
  try {
    const {
      booking_date, booking_month, builder, project, customer_name, contact_number, email_id, project_type,
      unit_no, size, agreement_value, cashback, revenue, ccashback, crevenue, recived_amt, source_lead, remarks, city
    } = req.body;

    if (!unit_no) {
      return res.status(400).json({ message: 'Unit number is required' });
    }

    // Check unique unit globally
    const duplicate = await Booking.findOne({ unit_no, approvalStatus: { $ne: 'rejected' } });
    if (duplicate) {
      return res.status(400).json({
        message: `Unit number ${unit_no} already exists in the system (Project: ${duplicate.project}, Customer: ${duplicate.customer_name}). Please use a unique unit number.`
      });
    }

    const deduct_agreement = calculateDeductAgreement(agreement_value, ccashback);
    const assign_user = await buildAssignPersonListUp(req.user.tablename);

    const booking = await Booking.create({
      booking_date,
      booking_month,
      builder,
      project,
      customer_name,
      contact_number,
      email_id,
      project_type,
      unit_no,
      size,
      agreement_value,
      cashback,
      revenue,
      ccashback,
      crevenue,
      recived_amt: recived_amt || 0,
      msalary: req.user.salary || 0,
      source_table: req.user.tablename,
      assign_user,
      source_lead,
      remarks,
      deduct_agreement,
      city,
      approvalStatus: 'pending' // default staging state
    });

    res.status(201).json(booking);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// PUT update booking
router.put('/:id', protect, async (req, res) => {
  try {
    const booking = await Booking.findById(req.params.id);
    if (!booking) return res.status(404).json({ message: 'Booking not found' });

    if (!['superuseradmin', 'hradmin'].includes(req.user.user_type) && booking.source_table !== req.user.tablename) {
      return res.status(403).json({ message: 'Not authorized to edit this booking' });
    }

    // If unit number is updated, verify uniqueness
    if (req.body.unit_no && req.body.unit_no !== booking.unit_no) {
      const duplicate = await Booking.findOne({
        _id: { $ne: booking._id },
        unit_no: req.body.unit_no,
        approvalStatus: { $ne: 'rejected' }
      });
      if (duplicate) {
        return res.status(400).json({
          message: `Unit number ${req.body.unit_no} already exists (Project: ${duplicate.project}, Customer: ${duplicate.customer_name}). Please use a unique unit number.`
        });
      }
    }

    // Recompute slab deduction if agreement value or cashback pct changed
    if (req.body.agreement_value !== undefined || req.body.ccashback !== undefined) {
      const agreement = req.body.agreement_value !== undefined ? req.body.agreement_value : booking.agreement_value;
      const ccashback = req.body.ccashback !== undefined ? req.body.ccashback : booking.ccashback;
      req.body.deduct_agreement = calculateDeductAgreement(agreement, ccashback);
    }

    Object.assign(booking, req.body);
    await booking.save();
    res.json(booking);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// PUT approve booking (Admin or manager in reporting hierarchy chain)
router.put('/:id/approve', protect, async (req, res) => {
  try {
    const booking = await Booking.findById(req.params.id);
    if (!booking) return res.status(404).json({ message: 'Booking not found' });

    const isApproverAdmin = ['superuseradmin', 'hradmin'].includes(req.user.user_type);
    const isApproverManager = booking.assign_user.includes(req.user.tablename);

    if (!isApproverAdmin && !isApproverManager) {
      return res.status(403).json({ message: 'Not authorized to approve this booking' });
    }

    booking.approvalStatus = 'approved';
    booking.approved_by = req.user.tablename;
    booking.approved_at = new Date();
    await booking.save();

    res.json({ message: 'Booking approved successfully', booking });
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// PUT reject booking
router.put('/:id/reject', protect, async (req, res) => {
  try {
    const booking = await Booking.findById(req.params.id);
    if (!booking) return res.status(404).json({ message: 'Booking not found' });

    const isApproverAdmin = ['superuseradmin', 'hradmin'].includes(req.user.user_type);
    const isApproverManager = booking.assign_user.includes(req.user.tablename);

    if (!isApproverAdmin && !isApproverManager) {
      return res.status(403).json({ message: 'Not authorized to reject this booking' });
    }

    booking.approvalStatus = 'rejected';
    await booking.save();

    res.json({ message: 'Booking rejected successfully', booking });
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// DELETE delete booking
router.delete('/:id', protect, adminOnly, async (req, res) => {
  try {
    const booking = await Booking.findByIdAndDelete(req.params.id);
    if (!booking) return res.status(404).json({ message: 'Booking not found' });
    res.json({ message: 'Booking deleted successfully' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
