import express from 'express';
import EOI from '../models/EOI.js';
import Booking from '../models/Booking.js';
import User from '../models/User.js';
import { protect } from '../middleware/auth.js';

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
    const parent = user.assign_user[0]; // Assume first in array represents manager/parent
    if (!parent || parent === current || visited.has(parent)) {
      break;
    }
    chain.push(parent);
    visited.add(parent);
    current = parent;
  }
  return chain;
}

// GET all EOIs
router.get('/', protect, async (req, res) => {
  try {
    let filter = {};
    // Role gates: promoters/agents can only view their own or reporting chain
    if (!['superuseradmin', 'hradmin'].includes(req.user.user_type)) {
      filter.source_table = req.user.tablename;
    }
    const eois = await EOI.find(filter).sort({ createdAt: -1 });
    res.json(eois);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST create EOI
router.post('/', protect, async (req, res) => {
  try {
    const { booking_date, booking_month, builder, project, customer_name, contact_number, email_id, project_type } = req.body;
    const eoi = await EOI.create({
      booking_date,
      booking_month,
      builder,
      project,
      customer_name,
      contact_number,
      email_id,
      project_type,
      source_table: req.user.tablename
    });
    res.status(201).json(eoi);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// PUT update EOI
router.put('/:id', protect, async (req, res) => {
  try {
    const eoi = await EOI.findById(req.params.id);
    if (!eoi) return res.status(404).json({ message: 'EOI not found' });

    // Restrict updates
    if (!['superuseradmin', 'hradmin'].includes(req.user.user_type) && eoi.source_table !== req.user.tablename) {
      return res.status(403).json({ message: 'Not authorized to edit this EOI' });
    }

    Object.assign(eoi, req.body);
    await eoi.save();
    res.json(eoi);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// DELETE cancel EOI
router.delete('/:id', protect, async (req, res) => {
  try {
    const eoi = await EOI.findById(req.params.id);
    if (!eoi) return res.status(404).json({ message: 'EOI not found' });

    if (!['superuseradmin', 'hradmin'].includes(req.user.user_type) && eoi.source_table !== req.user.tablename) {
      return res.status(403).json({ message: 'Not authorized to delete this EOI' });
    }

    await EOI.findByIdAndDelete(req.params.id);
    res.json({ message: 'EOI cancelled and deleted successfully' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

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

// POST convert EOI to Booking
router.post('/:id/convert', protect, async (req, res) => {
  try {
    const eoi = await EOI.findById(req.params.id);
    if (!eoi) return res.status(404).json({ message: 'EOI not found' });

    const {
      unit_no, size, agreement_value, cashback, revenue,
      ccashback, crevenue, recived_amt, source_lead, remarks, city
    } = req.body;

    if (!unit_no) {
      return res.status(400).json({ message: 'Unit number is required for conversion' });
    }

    // Check unit uniqueness globally across both approved and pending Bookings
    const duplicate = await Booking.findOne({ unit_no, approvalStatus: { $ne: 'rejected' } });
    if (duplicate) {
      return res.status(400).json({
        message: `Unit number ${unit_no} already exists (Project: ${duplicate.project}, Customer: ${duplicate.customer_name}). Please use a unique unit number.`
      });
    }

    // Replicate deductions
    const deduct_agreement = calculateDeductAgreement(agreement_value, ccashback);
    const assign_user = await buildAssignPersonListUp(eoi.source_table);

    // Create a pending booking
    const booking = await Booking.create({
      booking_date: eoi.booking_date,
      booking_month: eoi.booking_month,
      builder: eoi.builder,
      project: eoi.project,
      customer_name: eoi.customer_name,
      contact_number: eoi.contact_number,
      email_id: eoi.email_id,
      project_type: eoi.project_type,
      unit_no,
      size,
      agreement_value,
      cashback,
      revenue,
      ccashback,
      crevenue,
      recived_amt: recived_amt || 0,
      msalary: req.user.salary || 0,
      source_table: eoi.source_table,
      assign_user,
      source_lead,
      remarks,
      deduct_agreement,
      city,
      approvalStatus: 'pending'
    });

    // Delete converted EOI
    await EOI.findByIdAndDelete(req.params.id);

    res.status(201).json({ message: 'EOI converted successfully into a pending Booking', booking });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
