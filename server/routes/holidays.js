import express from 'express';
import Holiday from '../models/Holiday.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// Public / Employees: Get upcoming holidays list
router.get('/', protect, async (req, res) => {
  try {
    const holidays = await Holiday.find().sort({ date: 1 });
    res.json(holidays);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Add a holiday
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { date, reason } = req.body;
    if (!date || !reason) {
      return res.status(400).json({ message: 'Date and reason are required.' });
    }

    const exists = await Holiday.findOne({ date });
    if (exists) {
      return res.status(400).json({ message: 'A holiday on this date already exists.' });
    }

    const holiday = new Holiday({ date, reason });
    await holiday.save();
    res.status(201).json(holiday);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Delete a holiday
router.delete('/:id', protect, adminOnly, async (req, res) => {
  try {
    const holiday = await Holiday.findByIdAndDelete(req.params.id);
    if (!holiday) return res.status(404).json({ message: 'Holiday not found.' });
    res.json({ message: 'Holiday deleted successfully.' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
