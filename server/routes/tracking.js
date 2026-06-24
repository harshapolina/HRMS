import express from 'express';
import Booking from '../models/Booking.js';
import Tracking from '../models/Tracking.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// GET list all manual tracking/salary entries
router.get('/', protect, async (req, res) => {
  try {
    const trackings = await Tracking.find().sort({ month: -1, createdAt: -1 });
    res.json(trackings);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST create manual tracking entry
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { month, send_amt, user_name, user_type, bookin_number, gen_revenue, recent_pay, remaning_amt } = req.body;
    if (!month || !user_name || !user_type) {
      return res.status(400).json({ message: 'Month, user_name, and user_type are required' });
    }

    const tracking = await Tracking.create({
      month,
      send_amt,
      user_name,
      user_type,
      bookin_number: bookin_number || 0,
      gen_revenue: gen_revenue || 0,
      recent_pay: recent_pay || 0,
      remaning_amt: remaning_amt || 0
    });

    res.status(201).json(tracking);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// GET aggregated monthly incentives and revenues
router.get('/incentives', protect, async (req, res) => {
  try {
    // We aggregate data from the Booking schema (where approvalStatus = 'approved')
    const bookingStats = await Booking.aggregate([
      { $match: { approvalStatus: 'approved' } },
      {
        $group: {
          _id: '$booking_month',
          totalBookings: { $sum: 1 },
          totalAgreementValue: { $sum: '$agreement_value' },
          totalRevenue: { $sum: '$revenue' },
          totalCashback: { $sum: '$cashback' },
          totalReceived: { $sum: '$recived_amt' }
        }
      },
      { $sort: { _id: -1 } }
    ]);

    // Also get manual tracking payouts aggregated by month
    const trackingStats = await Tracking.aggregate([
      {
        $group: {
          _id: '$month',
          totalPayout: { $sum: '$send_amt' },
          totalGenRevenue: { $sum: '$gen_revenue' },
          totalRecentPay: { $sum: '$recent_pay' },
          totalRemaining: { $sum: '$remaning_amt' }
        }
      },
      { $sort: { _id: -1 } }
    ]);

    res.json({
      bookings: bookingStats,
      trackings: trackingStats
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// GET all payments milestones/invoices
router.get('/payments', protect, async (req, res) => {
  try {
    const { month, search } = req.query;
    let filter = { approvalStatus: 'approved' }; // Only track payments for approved bookings

    if (month) {
      filter.booking_month = month;
    }

    if (search) {
      filter.$or = [
        { customer_name: { $regex: search, $options: 'i' } },
        { unit_no: { $regex: search, $options: 'i' } },
        { builder: { $regex: search, $options: 'i' } },
        { project: { $regex: search, $options: 'i' } }
      ];
    }

    const payments = await Booking.find(filter).sort({ booking_month: -1, createdAt: -1 });
    res.json(payments);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// PUT update advance payment / received amount for a booking
router.put('/payments/:id/advance', protect, adminOnly, async (req, res) => {
  try {
    const { recived_amt, invoice_raise, cashbackverify } = req.body;
    const booking = await Booking.findById(req.params.id);

    if (!booking) {
      return res.status(404).json({ message: 'Booking not found' });
    }

    if (recived_amt !== undefined) {
      booking.recived_amt = Number(recived_amt) || 0;
    }

    if (invoice_raise !== undefined) {
      booking.invoice_raise = Number(invoice_raise) || 0;
    }

    if (cashbackverify !== undefined) {
      booking.cashbackverify = Boolean(cashbackverify);
    }

    await booking.save();
    res.json({ message: 'Payment tracking updated successfully', booking });
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

export default router;
