import express from 'express';
import FNFSettlement from '../models/FNFSettlement.js';
import AssetAssignment from '../models/AssetAssignment.js';
import User from '../models/User.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// Admin: Check pending assets and calculate FNF draft
router.get('/:userId', protect, adminOnly, async (req, res) => {
  try {
    const userId = req.params.userId;
    const user = await User.findById(userId);
    if (!user) return res.status(404).json({ message: 'Employee not found.' });

    // Check count of unreturned assets
    const pendingAssetsCount = await AssetAssignment.countDocuments({
      user: userId,
      returnedDate: { $exists: false }
    });

    // Fetch saved FNF if exists
    const existing = await FNFSettlement.findOne({ user: userId });

    res.json({
      user: {
        id: user._id,
        username: user.username,
        employee_id: user.employee_id,
        salary: user.salary,
        user_type: user.user_type
      },
      pendingAssetsCount,
      existing: existing || null
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Settle / Save FNF record
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { userId, lastWorkingDay, unpaidSalary, leaveEncashment, bonusIncentives, deductions, netSettlement, status, assetsReturned } = req.body;
    if (!userId || !lastWorkingDay || netSettlement === undefined) {
      return res.status(400).json({ message: 'User ID, last working day, and net settlement are required.' });
    }

    const record = await FNFSettlement.findOneAndUpdate(
      { user: userId },
      {
        user: userId,
        lastWorkingDay: new Date(lastWorkingDay),
        unpaidSalary: unpaidSalary || 0,
        leaveEncashment: leaveEncashment || 0,
        bonusIncentives: bonusIncentives || 0,
        deductions: deductions || 0,
        netSettlement,
        status: status || 'Pending',
        assetsReturned: assetsReturned || false
      },
      { upsert: true, new: true }
    );

    // If FNF is settled, we can also mark user inactive
    if (status === 'Settled') {
      const user = await User.findById(userId);
      if (user) {
        user.is_active = false;
        user.deactivated_at = new Date();
        await user.save();
      }
    }

    res.json({ message: 'FNF Settlement processed successfully.', data: record });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
