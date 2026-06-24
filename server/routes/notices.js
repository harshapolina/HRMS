import express from 'express';
import Notice from '../models/Notice.js';
import NoticeAcceptance from '../models/NoticeAcceptance.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// GET check if there is a pending (unaccepted) notice for the current user
router.get('/pending', protect, async (req, res) => {
  try {
    const userId = req.user.tablename;

    // Get all notices
    const notices = await Notice.find().sort({ createdAt: -1 });
    if (!notices.length) {
      return res.json(null);
    }

    // Find if the user has accepted the latest notice
    // In legacy logic, we typically block if the most recent notice is unaccepted
    const latestNotice = notices[0];
    const acceptance = await NoticeAcceptance.findOne({
      user_id: userId,
      alert_id: latestNotice._id
    });

    if (!acceptance) {
      // User has not accepted the latest notice
      return res.json(latestNotice);
    }

    // Otherwise, check if there's any notice that hasn't been accepted yet
    for (const notice of notices) {
      const isAccepted = await NoticeAcceptance.findOne({
        user_id: userId,
        alert_id: notice._id
      });
      if (!isAccepted) {
        return res.json(notice);
      }
    }

    return res.json(null);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST accept a notice
router.post('/accept', protect, async (req, res) => {
  try {
    const { alert_id } = req.body;
    if (!alert_id) {
      return res.status(400).json({ message: 'alert_id is required' });
    }

    const userId = req.user.tablename;

    // Use upsert/findAndUpdate to prevent double inserts due to concurrency or clicking twice
    const acceptance = await NoticeAcceptance.findOneAndUpdate(
      { user_id: userId, alert_id },
      { alert_accepted: true },
      { upsert: true, new: true }
    );

    res.json({ message: 'Notice accepted successfully', acceptance });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST create notice (admin only)
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { alert_message } = req.body;
    if (!alert_message) {
      return res.status(400).json({ message: 'alert_message is required' });
    }

    const notice = await Notice.create({
      alert_message,
      created_by: req.user.tablename
    });

    res.status(201).json(notice);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// GET list all notices
router.get('/', protect, async (req, res) => {
  try {
    const notices = await Notice.find().sort({ createdAt: -1 });
    res.json(notices);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
