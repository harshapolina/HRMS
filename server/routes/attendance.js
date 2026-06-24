import express from 'express';
import Attendance from '../models/Attendance.js';
import User from '../models/User.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// Get Kolkata local timezone details
const getKolkataDetails = () => {
  const options = { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' };
  const formatter = new Intl.DateTimeFormat('en-CA', options); // YYYY-MM-DD
  const dateStr = formatter.format(new Date());

  const timeFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Kolkata',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  });
  const timeStr = timeFormatter.format(new Date());
  
  return { dateStr, timeStr };
};

// Admin: Get all attendance logs
// Admin: Get all attendance logs
router.get('/', protect, adminOnly, async (req, res) => {
  try {
    const { search, status, from, to, userId } = req.query;
    console.log('[API GET /] Received query params:', { search, status, from, to, userId });

    // 1. If specific userId is requested, return their raw logs (useful for monthly calendar summary)
    if (userId) {
      let query = { user: userId };
      if (from || to) {
        query.date = {};
        if (from) query.date.$gte = from;
        if (to) query.date.$lte = to;
      }
      const logs = await Attendance.find(query)
        .populate('user', 'username employee_id user_type useremail phonenumber project_name')
        .sort({ date: -1, createdAt: -1 });
      return res.json(logs);
    }

    // 2. Fetch matched active users who are not superuseradmin
    let userQuery = { user_type: { $ne: 'superuseradmin' }, is_active: { $ne: false } };
    if (search) {
      userQuery.$or = [
        { username: { $regex: search, $options: 'i' } },
        { useremail: { $regex: search, $options: 'i' } },
        { employee_id: { $regex: search, $options: 'i' } }
      ];
    }
    const matchedUsers = await User.find(userQuery);
    const matchedUserIds = matchedUsers.map(u => u._id);

    // 3. Build Attendance Query for existing logs
    let attendanceQuery = { user: { $in: matchedUserIds } };
    if (from || to) {
      attendanceQuery.date = {};
      if (from) attendanceQuery.date.$gte = from;
      if (to) attendanceQuery.date.$lte = to;
    }
    if (status) {
      attendanceQuery.status = status;
    }

    const existingLogs = await Attendance.find(attendanceQuery)
      .populate('user', 'username employee_id user_type useremail phonenumber project_name')
      .sort({ date: -1, createdAt: -1 });

    // 4. Resolve date range to populate placeholders for absent (un-punched) users
    const { dateStr } = getKolkataDetails();
    const startDate = from || dateStr;
    const endDate = to || dateStr;

    // Helper to get dates in range
    const getDatesInRange = (fromStr, toStr) => {
      const dates = [];
      let current = new Date(fromStr);
      const end = new Date(toStr);
      let cap = 0;
      while (current <= end && cap < 31) {
        dates.push(current.toISOString().split('T')[0]);
        current.setDate(current.getDate() + 1);
        cap++;
      }
      return dates;
    };
    const dates = getDatesInRange(startDate, endDate);

    // Create lookup map of existing logs: "userId_date" -> log
    const logMap = new Map();
    existingLogs.forEach(log => {
      const uId = log.user?._id?.toString() || log.user?.toString();
      logMap.set(`${uId}_${log.date}`, log);
    });

    const combinedLogs = [];
    for (const date of dates) {
      for (const user of matchedUsers) {
        const key = `${user._id.toString()}_${date}`;
        if (logMap.has(key)) {
          combinedLogs.push(logMap.get(key));
        } else {
          // If status filter is active, only include if they filtered by 'Absent'
          if (status && status !== 'Absent') {
            continue;
          }
          
          combinedLogs.push({
            _id: `placeholder_${user._id}_${date}`,
            user: {
              _id: user._id,
              username: user.username,
              employee_id: user.employee_id,
              user_type: user.user_type,
              useremail: user.useremail,
              phonenumber: user.phonenumber,
              project_name: user.project_name
            },
            date,
            punchIn: null,
            punchOut: null,
            totalHours: null,
            status: 'Absent', // Not punched in means absent
            locationHistory: []
          });
        }
      }
    }

    // Sort logs by date descending, then user username ascending
    combinedLogs.sort((a, b) => {
      const dateCompare = new Date(b.date) - new Date(a.date);
      if (dateCompare !== 0) return dateCompare;
      return (a.user?.username || '').localeCompare(b.user?.username || '');
    });

    console.log('[API GET /] Combined logs count:', combinedLogs.length);
    res.json(combinedLogs);
  } catch (err) {
    console.error('[API GET /] Error:', err.message);
    res.status(500).json({ message: err.message });
  }
});

// Employee: Get my attendance logs
router.get('/my', protect, async (req, res) => {
  try {
    const logs = await Attendance.find({ user: req.user._id })
      .sort({ date: -1 })
      .limit(60); // Last 2 months
    res.json(logs);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Employee: Get today's attendance state
router.get('/today', protect, async (req, res) => {
  try {
    const { dateStr } = getKolkataDetails();
    const log = await Attendance.findOne({ user: req.user._id, date: dateStr });
    res.json(log || { status: 'Not Logged' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Employee: Punch In
router.post('/punch-in', protect, async (req, res) => {
  try {
    const { dateStr } = getKolkataDetails();
    const { latitude, longitude } = req.body;
    const ip = req.ip || req.headers['x-forwarded-for'] || '';

    let attendance = await Attendance.findOne({ user: req.user._id, date: dateStr });
    if (attendance && attendance.punchIn) {
      return res.status(400).json({ message: 'Already punched in for today.' });
    }

    // Determine Late status (Shift starts at 09:00:00 with 15 mins grace = 09:15:00)
    const officeStartHour = 9;
    const officeStartMin = 15;
    const now = new Date();
    const kolkataNow = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
    
    let status = 'Present';
    if (kolkataNow.getHours() > officeStartHour || (kolkataNow.getHours() === officeStartHour && kolkataNow.getMinutes() > officeStartMin)) {
      status = 'Late';
    }

    if (!attendance) {
      attendance = new Attendance({
        user: req.user._id,
        date: dateStr,
        punchIn: new Date(),
        status,
        latIn: latitude,
        lngIn: longitude,
        ip,
        locationHistory: latitude && longitude ? [{ latitude, longitude, capturedAt: new Date() }] : []
      });
    } else {
      attendance.punchIn = new Date();
      attendance.status = status;
      attendance.latIn = latitude;
      attendance.lngIn = longitude;
      attendance.ip = ip;
      if (latitude && longitude) {
        attendance.locationHistory.push({ latitude, longitude, capturedAt: new Date() });
      }
    }

    await attendance.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('attendance_update', attendance);
    }

    res.json({ message: 'Punched in successfully', data: attendance });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Employee: Punch Out
router.post('/punch-out', protect, async (req, res) => {
  try {
    const { dateStr } = getKolkataDetails();
    const { latitude, longitude } = req.body;

    const attendance = await Attendance.findOne({ user: req.user._id, date: dateStr });
    if (!attendance) {
      return res.status(400).json({ message: 'Must punch in before punching out.' });
    }
    if (attendance.punchOut) {
      return res.status(400).json({ message: 'Already punched out for today.' });
    }

    attendance.punchOut = new Date();
    attendance.latOut = latitude;
    attendance.lngOut = longitude;
    if (latitude && longitude) {
      attendance.locationHistory.push({ latitude, longitude, capturedAt: new Date() });
    }

    // Calculate total hours
    if (attendance.punchIn) {
      const diffMs = attendance.punchOut - attendance.punchIn;
      const hours = diffMs / (1000 * 60 * 60);
      attendance.totalHours = Math.max(0, parseFloat(hours.toFixed(2)));
    }

    await attendance.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('attendance_update', attendance);
    }

    res.json({ message: 'Punched out successfully', data: attendance });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Employee: Live coordinates log
router.post('/location', protect, async (req, res) => {
  try {
    const { dateStr } = getKolkataDetails();
    const { latitude, longitude } = req.body;

    if (!latitude || !longitude) {
      return res.status(400).json({ message: 'Coordinates are required.' });
    }

    const attendance = await Attendance.findOne({ user: req.user._id, date: dateStr });
    if (attendance && attendance.punchIn && !attendance.punchOut) {
      attendance.locationHistory.push({ latitude, longitude, capturedAt: new Date() });
      await attendance.save();
      
      // Notify parent app (Socket.io)
      const io = req.app.get('io');
      if (io) {
        io.emit('live_location_update', {
          userId: req.user._id,
          username: req.user.username,
          latitude,
          longitude,
          time: new Date()
        });
      }
      return res.json({ success: true, message: 'Location recorded.' });
    }
    res.status(400).json({ message: 'No active punch-in shift found to log tracking data.' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
