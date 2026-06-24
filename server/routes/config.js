import express from 'express';
import Config from '../models/Config.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// GET all config items (optionally populating groups)
router.get('/', protect, async (req, res) => {
  try {
    const configs = await Config.find()
      .populate('group_id')
      .sort({ createdAt: -1 });
    res.json(configs);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST create config (credential or group)
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { project_name, group_name, type, api_key, lead_source, assign_user, group_id } = req.body;
    
    if (!project_name) {
      return res.status(400).json({ message: 'Project name is required' });
    }

    const config = await Config.create({
      project_name,
      group_name,
      type: type || 'credential',
      api_key,
      lead_source,
      assign_user: assign_user || [],
      group_id: group_id || []
    });

    res.status(201).json(config);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// PUT update config
router.put('/:id', protect, adminOnly, async (req, res) => {
  try {
    const config = await Config.findById(req.params.id);
    if (!config) {
      return res.status(404).json({ message: 'Configuration not found' });
    }

    Object.assign(config, req.body);
    await config.save();

    // Populate for response
    const populated = await Config.findById(config._id).populate('group_id');
    res.json(populated);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// DELETE config
router.delete('/:id', protect, adminOnly, async (req, res) => {
  try {
    const config = await Config.findByIdAndDelete(req.params.id);
    if (!config) {
      return res.status(404).json({ message: 'Configuration not found' });
    }
    res.json({ message: 'Configuration deleted successfully' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
