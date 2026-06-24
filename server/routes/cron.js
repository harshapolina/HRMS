import express from 'express';
import CronJob from '../models/CronJob.js';
import { protect, adminOnly } from '../middleware/auth.js';

const router = express.Router();

// GET all cron jobs
router.get('/', protect, async (req, res) => {
  try {
    const cronJobs = await CronJob.find()
      .populate('row_id') // Populate details of the leads in the queue if needed
      .sort({ createdAt: -1 });
    res.json(cronJobs);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST create a cron job
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { assigned_user, project_name, source_lead, interval_time, location, is_active } = req.body;

    if (!project_name || !interval_time) {
      return res.status(400).json({ message: 'Project name and interval time are required' });
    }

    const cronJob = await CronJob.create({
      row_id: [],
      assigned_user: assigned_user || [],
      project_name,
      source_lead,
      interval_time,
      location,
      is_active: is_active !== undefined ? is_active : true
    });

    res.status(201).json(cronJob);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// PUT update cron job
router.put('/:id', protect, adminOnly, async (req, res) => {
  try {
    const cronJob = await CronJob.findById(req.params.id);
    if (!cronJob) {
      return res.status(404).json({ message: 'Cron job not found' });
    }

    Object.assign(cronJob, req.body);
    await cronJob.save();

    const populated = await CronJob.findById(cronJob._id).populate('row_id');
    res.json(populated);
  } catch (err) {
    res.status(400).json({ message: err.message });
  }
});

// DELETE cron job
router.delete('/:id', protect, adminOnly, async (req, res) => {
  try {
    const cronJob = await CronJob.findByIdAndDelete(req.params.id);
    if (!cronJob) {
      return res.status(404).json({ message: 'Cron job not found' });
    }
    res.json({ message: 'Cron job deleted successfully' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
