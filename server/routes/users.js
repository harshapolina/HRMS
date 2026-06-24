import express from 'express';
import User from '../models/User.js';
import Attendance from '../models/Attendance.js';
import Payroll from '../models/Payroll.js';

const router = express.Router();

// Get all users with paginated filters
router.get('/', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 10;
    const page = parseInt(req.query.page) || 1;
    const skip = (page - 1) * limit;
    const search = req.query.search || '';

    let filter = {};
    if (search) {
      filter = {
        $or: [
          { username: { $regex: search, $options: 'i' } },
          { useremail: { $regex: search, $options: 'i' } },
          { tablename: { $regex: search, $options: 'i' } },
          { employee_id: { $regex: search, $options: 'i' } }
        ]
      };
    }

    // Role, status, project filters
    if (req.query.role) filter.user_type = req.query.role;
    if (req.query.status !== undefined && req.query.status !== '') filter.is_active = req.query.status === '1';
    if (req.query.project) filter.project_name = { $regex: req.query.project, $options: 'i' };
    if (req.query.assigned === '1') {
      filter.is_active = true;
      filter.assign_user = { $exists: true, $not: { $size: 0 } };
    }
    if (req.query.hasSalary === '1') {
      filter.is_active = true;
      filter.salary = { $gt: 0 };
    }

    const total = await User.countDocuments(filter);
    const users = await User.find(filter)
      .sort({ createdAt: -1 })
      .skip(skip)
      .limit(limit);

    // Summary counters
    const activeCount = await User.countDocuments({ is_active: true });
    const inactiveCount = await User.countDocuments({ is_active: false });
    const activeUsers = await User.find({ is_active: true }, 'salary');
    const totalSalary = activeUsers.reduce((sum, u) => sum + (u.salary || 0), 0);
    const assignedCount = await User.countDocuments({
      is_active: true,
      assign_user: { $exists: true, $not: { $size: 0 } }
    });

    res.json({
      data: users,
      total,
      page,
      limit,
      totalPages: Math.ceil(total / limit),
      summary: {
        activeCount,
        inactiveCount,
        assignedCount,
        totalSalary
      }
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Get list of potential managers / assignees for dropdown select
router.get('/assignees', async (req, res) => {
  try {
    const assignees = await User.find({
      user_type: { $in: ['promoter', 'business head', 'manager', 'team lead'] },
      is_active: true
    }, 'username tablename user_type');
    res.json(assignees);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Get all active users (for lead reassignment)
router.get('/all-active', async (req, res) => {
  try {
    const users = await User.find({ is_active: true }, 'username tablename user_type');
    res.json(users);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Get single employee details
router.get('/:id', async (req, res) => {
  try {
    const user = await User.findById(req.params.id);
    if (!user) return res.status(404).json({ message: 'Employee not found' });
    res.json(user);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Create Employee
router.post('/', async (req, res) => {
  try {
    const { useremail, tablename } = req.body;
    
    // Check duplication
    const emailExists = await User.findOne({ useremail: useremail.toLowerCase() });
    if (emailExists) return res.status(400).json({ message: 'Email already exists' });

    if (tablename) {
      const tableExists = await User.findOne({ tablename });
      if (tableExists) return res.status(400).json({ message: 'Unique ID / Tablename already exists' });
    }

    const user = new User({
      ...req.body,
      tablename: tablename || 'USR_' + Math.random().toString(36).substring(2, 9).toUpperCase()
    });

    const savedUser = await user.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('user_update', savedUser);
    }

    res.status(201).json(savedUser);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// Update Employee
router.put('/:id', async (req, res) => {
  try {
    const user = await User.findById(req.params.id);
    if (!user) return res.status(404).json({ message: 'Employee not found' });

    if (req.body.is_active !== undefined) {
      const activeState = req.body.is_active === true || req.body.is_active === '1' || req.body.is_active === 1;
      if (!activeState && user.is_active) {
        req.body.deactivated_at = new Date();
      } else if (activeState) {
        req.body.deactivated_at = null;
      }
      req.body.is_active = activeState;
    }

    if (req.body.assign_user && !Array.isArray(req.body.assign_user)) {
      req.body.assign_user = req.body.assign_user.split(',').map(x => x.trim()).filter(Boolean);
    }

    Object.assign(user, req.body);
    user.flag_user_login = new Date();
    const updatedUser = await user.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('user_update', updatedUser);
    }

    res.json(updatedUser);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// Delete Employee (Cascading relation deletes)
router.delete('/:id', async (req, res) => {
  try {
    const user = await User.findByIdAndDelete(req.params.id);
    if (!user) return res.status(404).json({ message: 'Employee not found' });

    // Cascade delete logs associated with this user
    await Attendance.deleteMany({ user: req.params.id });
    await Payroll.deleteMany({ user: req.params.id });

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('user_update', { deletedId: req.params.id });
    }

    res.json({ message: 'Employee and dependencies deleted successfully.' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
