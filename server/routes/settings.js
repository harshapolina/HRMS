import express from 'express';
import Setting from '../models/Setting.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// GET all settings (returns as a dictionary of key: value)
router.get('/', protect, adminOnly, async (req, res) => {
  try {
    const settings = await Setting.find();
    const settingsMap = {};
    settings.forEach(s => {
      settingsMap[s.key] = s.value;
    });
    res.json(settingsMap);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST save or update setting
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { key, value } = req.body;
    if (!key) {
      return res.status(400).json({ message: 'Setting key is required.' });
    }

    const setting = await Setting.findOneAndUpdate(
      { key },
      { key, value },
      { upsert: true, new: true }
    );

    res.json(setting);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

export default router;
