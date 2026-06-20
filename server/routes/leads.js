import express from 'express';
import Lead from '../models/Lead.js';
import User from '../models/User.js';
import axios from 'axios';

const router = express.Router();

// Get all leads with paginated search filters
router.get('/', async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 10;
    const page = parseInt(req.query.page) || 1;
    const skip = (page - 1) * limit;
    const search = req.query.search || '';
    const status = req.query.status || '';
    const project = req.query.project || '';
    const tablename = req.query.tablename || '';
    const userRole = req.query.user_role || '';

    let filter = {};
    if (search) {
      filter.$or = [
        { name: { $regex: search, $options: 'i' } },
        { number: { $regex: search, $options: 'i' } },
        { email: { $regex: search, $options: 'i' } }
      ];
    }

    if (status) filter.status = status;
    if (project) filter.project = { $regex: project, $options: 'i' };

    // Managers/agents can only view leads assigned to them (excluding superuseradmin and promoter)
    if (tablename && !['superuseradmin', 'promoter'].includes(userRole)) {
      filter.assign_to_user = tablename;
    }

    const total = await Lead.countDocuments(filter);
    const leads = await Lead.find(filter)
      .sort({ createdAt: -1 })
      .skip(skip)
      .limit(limit);

    // Summary stats based on filters
    const stats = {
      total: await Lead.countDocuments(filter),
      pending: await Lead.countDocuments({ ...filter, status: 'Pending' }),
      contacted: await Lead.countDocuments({ ...filter, status: 'Contacted' }),
      interested: await Lead.countDocuments({ ...filter, status: 'Interested' }),
      booked: await Lead.countDocuments({ ...filter, status: 'Booked' }),
      eoi: await Lead.countDocuments({ ...filter, status: 'EOI' })
    };

    res.json({
      data: leads,
      total,
      page,
      limit,
      totalPages: Math.ceil(total / limit),
      stats
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Single lead detail
router.get('/:id', async (req, res) => {
  try {
    const lead = await Lead.findById(req.params.id);
    if (!lead) return res.status(404).json({ message: 'Lead not found' });
    res.json(lead);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Ingest leads (Google & Facebook Ads Integration webhook)
router.post('/ingest', async (req, res) => {
  try {
    const { name, email, number, location, project_name, subsource_of_lead } = req.body;

    if (!name || !number) {
      return res.status(400).json({ message: 'Name and phone number are required for lead ingestion.' });
    }

    const cleanNumber = number.replace(/\D/g, '').slice(-10);

    // Duplicate check on project + number
    const duplicate = await Lead.findOne({ number: { $regex: cleanNumber }, project: project_name });
    if (duplicate) {
      duplicate.lead_count = (duplicate.lead_count || 1) + 1;
      await duplicate.save();
      return res.json({ status: 'duplicate', message: 'Duplicate lead, counter incremented.', lead_id: duplicate._id });
    }

    // Lead Rotation (Round-robin assignment across active users in project)
    const users = await User.find({ project_name, is_active: true }).sort({ tablename: 1 });
    let assignedUser = 'unassigned';
    let userNumber = '';

    if (users.length > 0) {
      const lastAssignedLead = await Lead.findOne({ project: project_name, assign_to_user: { $ne: 'unassigned' } })
        .sort({ createdAt: -1 });

      let nextIndex = 0;
      if (lastAssignedLead) {
        const lastIdx = users.findIndex(u => u.tablename === lastAssignedLead.assign_to_user);
        if (lastIdx !== -1) {
          nextIndex = (lastIdx + 1) % users.length;
        }
      }

      const chosenUser = users[nextIndex];
      assignedUser = chosenUser.tablename;
      userNumber = chosenUser.phonenumber;
    }

    const lead = await Lead.create({
      name,
      email,
      number: cleanNumber,
      location,
      project: project_name,
      subsource_of_lead,
      assign_to_user: assignedUser,
      status: 'Pending'
    });

    // Send SMS Notification (Fast2SMS API)
    let smsStatus = 'skipped';
    if (userNumber && process.env.FAST2SMS_KEY) {
      try {
        const payload = {
          route: 'dlt_manual',
          sender_id: 'SHHOME',
          message: `Property- ${project_name}. Name- ${name}, Mobile- .XXXXX., Email- ${email || ''} Regards, SearchHomes`,
          template_id: '1207163731895114985',
          entity_id: '1201159178483176795',
          numbers: userNumber
        };

        const response = await axios.post('https://www.fast2sms.com/dev/bulkV2', payload, {
          headers: {
            authorization: process.env.FAST2SMS_KEY,
            'content-type': 'application/json'
          },
          timeout: 10000
        });
        smsStatus = 'success';
      } catch (err) {
        smsStatus = 'error: ' + err.message;
      }
    }

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('lead_update', lead);
    }

    res.json({
      status: 'success',
      lead_id: lead._id,
      assigned_user: assignedUser,
      sms: smsStatus
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Update Status
router.put('/:id/status', async (req, res) => {
  try {
    const { status } = req.body;
    const lead = await Lead.findById(req.params.id);
    if (!lead) return res.status(404).json({ message: 'Lead not found' });

    lead.status = status;
    await lead.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('lead_update', lead);
    }

    res.json(lead);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Append Remarks
router.post('/:id/remarks', async (req, res) => {
  try {
    const { text, created_by } = req.body;
    const lead = await Lead.findById(req.params.id);
    if (!lead) return res.status(404).json({ message: 'Lead not found' });

    lead.remarks.push({ text, created_by });
    await lead.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('lead_update', lead);
    }

    res.json(lead);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Update Assignee
router.put('/:id/assignee', async (req, res) => {
  try {
    const { assign_to_user } = req.body;
    const lead = await Lead.findById(req.params.id);
    if (!lead) return res.status(404).json({ message: 'Lead not found' });

    lead.assign_to_user = assign_to_user;
    await lead.save();

    // Emit socket update
    const io = req.app.get('io');
    if (io) {
      io.emit('lead_update', lead);
    }

    res.json(lead);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
