import express from 'express';
import LeaveRequest from '../models/LeaveRequest.js';
import User from '../models/User.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// Helper to compute number of days between two dates
const getLeaveDaysCount = (start, end) => {
  const s = new Date(start);
  const e = new Date(end);
  const diffTime = Math.abs(e - s);
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
};

// Helper to compute leave balances for a user in the current year
const calculateBalances = async (userId) => {
  const currentYear = new Date().getFullYear();
  const startOfYear = new Date(`${currentYear}-01-01T00:00:00.000Z`);
  const endOfYear = new Date(`${currentYear}-12-31T23:59:59.999Z`);

  const approvedLeaves = await LeaveRequest.find({
    user: userId,
    status: 'Approved',
    startDate: { $gte: startOfYear, $lte: endOfYear }
  });

  const used = {
    'Sick Leave': 0,
    'Casual Leave': 0,
    'Paid Leave': 0,
    'Unpaid Leave': 0
  };

  approvedLeaves.forEach(leave => {
    const days = getLeaveDaysCount(leave.startDate, leave.endDate);
    if (used[leave.leaveType] !== undefined) {
      used[leave.leaveType] += days;
    }
  });

  return {
    sickRemaining: Math.max(0, 12 - used['Sick Leave']),
    casualRemaining: Math.max(0, 12 - used['Casual Leave']),
    paidRemaining: Math.max(0, 15 - used['Paid Leave']),
    unpaidUsed: used['Unpaid Leave'],
    limits: { sick: 12, casual: 12, paid: 15 }
  };
};

// Admin: Get all leave requests
router.get('/', protect, adminOnly, async (req, res) => {
  try {
    const { search, status, from, to } = req.query;
    let query = {};

    if (status) query.status = status;
    if (from || to) {
      query.$or = [];
      if (from) query.$or.push({ startDate: { $gte: new Date(from) } });
      if (to) query.$or.push({ endDate: { $lte: new Date(to) } });
      if (query.$or.length === 0) delete query.$or;
    }

    if (search) {
      const users = await User.find({
        $or: [
          { username: { $regex: search, $options: 'i' } },
          { useremail: { $regex: search, $options: 'i' } },
          { employee_id: { $regex: search, $options: 'i' } }
        ]
      }, '_id');
      query.user = { $in: users.map(u => u._id) };
    }

    const requests = await LeaveRequest.find(query)
      .populate('user', 'username employee_id user_type useremail phonenumber project_name')
      .sort({ createdAt: -1 });

    // Attach days count
    const formattedRequests = requests.map(reqItem => {
      const doc = reqItem.toObject();
      doc.leaveDays = getLeaveDaysCount(doc.startDate, doc.endDate);
      return doc;
    });

    res.json(formattedRequests);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Employee: Get my leave requests and balances
router.get('/my', protect, async (req, res) => {
  try {
    const requests = await LeaveRequest.find({ user: req.user._id })
      .sort({ createdAt: -1 });

    const balances = await calculateBalances(req.user._id);

    // Format requests
    const formattedRequests = requests.map(reqItem => {
      const doc = reqItem.toObject();
      doc.leaveDays = getLeaveDaysCount(doc.startDate, doc.endDate);
      return doc;
    });

    res.json({
      requests: formattedRequests,
      balances
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Employee: Submit leave request
router.post('/', protect, async (req, res) => {
  try {
    const { leaveType, startDate, endDate, reason } = req.body;
    if (!leaveType || !startDate || !endDate || !reason) {
      return res.status(400).json({ message: 'All fields are required.' });
    }

    const start = new Date(startDate);
    const end = new Date(endDate);
    if (start > end) {
      return res.status(400).json({ message: 'Start date cannot be after end date.' });
    }

    const requestedDays = getLeaveDaysCount(startDate, endDate);

    // If it's a paid/sick/casual leave type, verify balance limits
    if (leaveType !== 'Unpaid Leave') {
      const balances = await calculateBalances(req.user._id);
      let limitKey = 'sickRemaining';
      if (leaveType === 'Casual Leave') limitKey = 'casualRemaining';
      if (leaveType === 'Paid Leave') limitKey = 'paidRemaining';

      if (requestedDays > balances[limitKey]) {
        return res.status(400).json({
          message: `Insufficient leave balance. You requested ${requestedDays} days but only have ${balances[limitKey]} days left.`
        });
      }
    }

    const newRequest = new LeaveRequest({
      user: req.user._id,
      leaveType,
      startDate: start,
      endDate: end,
      reason,
      status: 'Pending'
    });

    await newRequest.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('leave_update', newRequest);
    }

    res.status(201).json(newRequest);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Approve or Reject leave request
router.put('/:id/status', protect, adminOnly, async (req, res) => {
  try {
    const { status, adminRemarks } = req.body;
    if (!['Approved', 'Rejected'].includes(status)) {
      return res.status(400).json({ message: 'Invalid status. Must be Approved or Rejected.' });
    }

    const request = await LeaveRequest.findById(req.params.id);
    if (!request) {
      return res.status(404).json({ message: 'Leave request not found.' });
    }

    if (request.status !== 'Pending') {
      return res.status(400).json({ message: 'Leave request has already been processed.' });
    }

    request.status = status;
    request.adminRemarks = adminRemarks || '';
    await request.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('leave_update', request);
    }

    res.json({ message: `Leave request has been ${status.toLowerCase()} successfully.`, data: request });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
