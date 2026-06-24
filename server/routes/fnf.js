import express from 'express';
import FNFSettlement from '../models/FNFSettlement.js';
import ExitDocument from '../models/ExitDocument.js';
import AssetAssignment from '../models/AssetAssignment.js';
import Payroll from '../models/Payroll.js';
import User from '../models/User.js';
import { protect, adminOnly } from '../middleware/auth.js';
import sendEmail from '../config/mailer.js';
import { getLetterTemplate, getLetterTitle } from '../utils/exitLetterTemplates.js';

const router = express.Router();

const serializeUser = (user, fnfRecord) => ({
  id: user._id,
  username: user.username,
  employee_id: user.employee_id,
  salary: user.salary,
  user_type: user.user_type,
  project_name: user.project_name,
  city: user.city,
  doj: user.doj,
  deactivated_at: user.deactivated_at,
  useremail: user.useremail,
  lastWorkingDay: fnfRecord?.lastWorkingDay || user.deactivated_at
});

// Admin: Get letter (saved or generated template)
router.get('/:userId/letters/:type', protect, adminOnly, async (req, res) => {
  try {
    const { userId, type } = req.params;
    if (!['no_dues_certificate', 'relieving_letter'].includes(type)) {
      return res.status(400).json({ message: 'Invalid letter type.' });
    }

    const user = await User.findById(userId);
    if (!user) return res.status(404).json({ message: 'Employee not found.' });

    const fnfRecord = await FNFSettlement.findOne({ user: userId });
    const saved = await ExitDocument.findOne({ user: userId, type });
    const forceFresh = req.query.fresh === '1';

    res.json({
      type,
      title: getLetterTitle(type),
      customHtml: (!forceFresh && saved?.customHtml) ? saved.customHtml : getLetterTemplate(type, serializeUser(user, fnfRecord)),
      isGenerated: Boolean(saved),
      emailedAt: saved?.emailedAt || null
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Save letter
router.put('/:userId/letters/:type', protect, adminOnly, async (req, res) => {
  try {
    const { userId, type } = req.params;
    const { customHtml } = req.body;

    if (!['no_dues_certificate', 'relieving_letter'].includes(type)) {
      return res.status(400).json({ message: 'Invalid letter type.' });
    }
    if (!customHtml) return res.status(400).json({ message: 'Letter content is required.' });

    const user = await User.findById(userId);
    if (!user) return res.status(404).json({ message: 'Employee not found.' });

    const saved = await ExitDocument.findOneAndUpdate(
      { user: userId, type },
      { user: userId, type, customHtml },
      { upsert: true, new: true }
    );

    res.json({ message: 'Letter saved successfully.', data: saved });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Email single letter
router.post('/:userId/letters/:type/send', protect, adminOnly, async (req, res) => {
  try {
    const { userId, type } = req.params;
    const user = await User.findById(userId);
    if (!user) return res.status(404).json({ message: 'Employee not found.' });

    const saved = await ExitDocument.findOne({ user: userId, type });
    if (!saved) return res.status(400).json({ message: 'Please generate and save the letter first.' });

    await sendEmail({
      to: user.useremail,
      subject: `${getLetterTitle(type)} — Search Homes India Pvt Ltd`,
      html: saved.customHtml
    });

    saved.emailedAt = new Date();
    await saved.save();

    res.json({ message: 'Letter emailed successfully.' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Send final FNF mail with all exit documents
router.post('/:userId/send-final-mail', protect, adminOnly, async (req, res) => {
  try {
    const { userId } = req.params;
    const user = await User.findById(userId);
    if (!user) return res.status(404).json({ message: 'Employee not found.' });

    const documents = await ExitDocument.find({ user: userId });
    if (documents.length === 0) {
      return res.status(400).json({ message: 'Generate exit documents before sending final mail.' });
    }

    const listItems = documents.map((doc) => `<li>${getLetterTitle(doc.type)}</li>`).join('');
    const attachmentsHtml = documents.map((doc) => `
      <div style="margin-top: 24px; border-top: 1px solid #ddd; padding-top: 16px;">
        <h3 style="margin: 0 0 12px; font-size: 14px;">${getLetterTitle(doc.type)}</h3>
        ${doc.customHtml}
      </div>
    `).join('');

    await sendEmail({
      to: user.useremail,
      subject: `Full & Final Settlement Documents — ${user.username}`,
      html: `
        <p>Dear ${user.username},</p>
        <p>Please find your Full &amp; Final settlement documents attached below:</p>
        <ul>${listItems}</ul>
        ${attachmentsHtml}
        <p>Regards,<br/>HR Team<br/>Search Homes India Pvt Ltd</p>
      `
    });

    await ExitDocument.updateMany({ user: userId }, { emailedAt: new Date() });

    res.json({ message: 'Final settlement mail sent successfully.' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Check pending assets and calculate FNF draft
router.get('/:userId', protect, adminOnly, async (req, res) => {
  try {
    const userId = req.params.userId;
    const user = await User.findById(userId);
    if (!user) return res.status(404).json({ message: 'Employee not found.' });

    const pendingAssetsCount = await AssetAssignment.countDocuments({
      user: userId,
      returnedDate: { $exists: false }
    });

    const existing = await FNFSettlement.findOne({ user: userId });
    const documents = await ExitDocument.find({ user: userId });
    const payslips = await Payroll.find({ user: userId }).sort({ createdAt: -1 }).limit(12);

    const letters = {
      no_dues_certificate: documents.find((d) => d.type === 'no_dues_certificate') || null,
      relieving_letter: documents.find((d) => d.type === 'relieving_letter') || null
    };

    res.json({
      user: serializeUser(user, existing),
      pendingAssetsCount,
      existing: existing || null,
      letters,
      payslips
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

    if (status === 'Settled') {
      const user = await User.findById(userId);
      if (user) {
        user.is_active = false;
        user.deactivated_at = user.deactivated_at || new Date(lastWorkingDay);
        await user.save();
      }
    }

    res.json({ message: 'FNF Settlement processed successfully.', data: record });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
