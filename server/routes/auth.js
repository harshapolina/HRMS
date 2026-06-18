import express from 'express';
import jwt from 'jsonwebtoken';
import User from '../models/User.js';

const router = express.Router();

// Register user (primarily called by admin/setup)
router.post('/register', async (req, res) => {
  try {
    const {
      username,
      useremail,
      phonenumber,
      epassword,
      salary,
      tablename,
      employee_id,
      doj,
      dob,
      one_amt,
      two_amt,
      thrid_amt,
      forth_amt,
      fifth_amt,
      sixth_amt,
      project_name,
      project_type,
      user_type,
      assign_user,
      city
    } = req.body;

    const userExists = await User.findOne({ useremail: useremail.toLowerCase() });
    if (userExists) {
      return res.status(400).json({ message: 'User with this email already exists' });
    }

    // Generate unique tablename if not provided
    const targetTablename = tablename || 'USR_' + Math.random().toString(36).substring(2, 9).toUpperCase();

    const user = await User.create({
      username,
      useremail,
      phonenumber,
      epassword,
      salary,
      tablename: targetTablename,
      employee_id,
      doj,
      dob,
      one_amt,
      two_amt,
      thrid_amt,
      forth_amt,
      fifth_amt,
      sixth_amt,
      project_name,
      project_type,
      user_type,
      assign_user: assign_user ? (Array.isArray(assign_user) ? assign_user : assign_user.split(',').map(x => x.trim())) : [],
      city
    });

    res.status(201).json({
      message: 'User registered successfully',
      user: {
        id: user._id,
        username: user.username,
        useremail: user.useremail,
        tablename: user.tablename,
        user_type: user.user_type
      }
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Login User
router.post('/login', async (req, res) => {
  try {
    const { useremail, password } = req.body;

    if (!useremail || !password) {
      return res.status(400).json({ message: 'Please enter both email and password' });
    }

    const user = await User.findOne({ useremail: useremail.toLowerCase() });
    if (!user) {
      return res.status(400).json({ message: 'Incorrect username and/or password!' });
    }

    if (!user.is_active) {
      return res.status(403).json({ message: 'Your account is inactive. Please contact the administrator.' });
    }

    const isMatch = await user.matchPassword(password);
    if (!isMatch) {
      return res.status(400).json({ message: 'Incorrect username and/or password!' });
    }

    // Generate JWT
    const token = jwt.sign(
      { id: user._id, role: user.user_type, tablename: user.tablename },
      process.env.JWT_SECRET || 'secret',
      { expiresIn: '30d' }
    );

    res.json({
      token,
      user: {
        id: user._id,
        username: user.username,
        useremail: user.useremail,
        tablename: user.tablename,
        user_type: user.user_type,
        role: user.user_type === 'superuseradmin' ? 'superuseradmin' : 'regularuser',
        salary: user.salary,
        assign_user: user.assign_user,
        project_name: user.project_name,
        project_type: user.project_type
      }
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
