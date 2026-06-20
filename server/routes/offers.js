import express from 'express';
import OfferLetter from '../models/OfferLetter.js';
import { protect, adminOnly } from '../middleware/auth.js';
import sendEmail from '../config/mailer.js';
import nodemailer from 'nodemailer';

const router = express.Router();

// Helper to generate default Offer Letter HTML template
const getDefaultHtmlTemplate = (data) => {
  const today = new Date().toLocaleDateString('en-GB');
  const joining = new Date(data.joiningDate).toLocaleDateString('en-GB');
  const monthly = parseFloat(data.monthlySalary) || 0;
  const annual = monthly * 12;

  const basic = Math.round(monthly * 0.5);
  const hra = Math.round(monthly * 0.2);
  const conveyance = Math.round(monthly * 0.07);
  const pfEmployer = Math.min(1800, Math.round(basic * 0.12));
  const monthlyGross = monthly - pfEmployer;
  const special = Math.max(0, monthlyGross - (basic + hra + conveyance));
  const pfEmployee = pfEmployer;
  const pt = monthly > 15000 ? 200 : 0;
  const medical = monthly > 10000 ? 817 : 0;
  const deductions = pfEmployee + pt + medical;
  const netPay = monthlyGross - deductions;

  return `
  <div style="font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; color: #333;">
    <h2 style="text-align: center; color: #115b82; text-decoration: underline;">OFFER LETTER</h2>
    <p><strong>Date:</strong> ${today}</p>
    <p><strong>To,</strong><br><strong>${data.candidateName}</strong><br>Email: ${data.email}<br>Phone: ${data.phone}</p>
    
    <p>We are pleased to offer you employment at <strong>Search Homes India Pvt Ltd</strong>. We believe your skills and background will be valuable assets to our team and contribute significantly to our success.</p>
    
    <p>As per our discussion, your position will be <strong>${data.position}</strong> with a fixed Annual Cost to Company (CTC) of <strong>INR ${annual.toLocaleString()}/- LPA</strong>.</p>
    
    <h3>Salary Breakdown (Annexure - A)</h3>
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px;">
      <thead>
        <tr style="background-color: #115b82; color: white; text-align: left;">
          <th style="padding: 8px; border: 1px solid #ddd;">Component</th>
          <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Monthly (INR)</th>
          <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Annual (INR)</th>
        </tr>
      </thead>
      <tbody>
        <tr><td style="padding: 8px; border: 1px solid #ddd;">Basic Salary (50%)</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${basic.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(basic * 12).toLocaleString()}</td></tr>
        <tr><td style="padding: 8px; border: 1px solid #ddd;">HRA (20%)</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${hra.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(hra * 12).toLocaleString()}</td></tr>
        <tr><td style="padding: 8px; border: 1px solid #ddd;">Conveyance Allowance</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${conveyance.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(conveyance * 12).toLocaleString()}</td></tr>
        <tr><td style="padding: 8px; border: 1px solid #ddd;">Special Allowance</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${special.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(special * 12).toLocaleString()}</td></tr>
        <tr style="font-weight: bold; background-color: #f9f9f9;"><td style="padding: 8px; border: 1px solid #ddd;">Monthly Gross Payout</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${monthlyGross.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(monthlyGross * 12).toLocaleString()}</td></tr>
        
        <tr style="background-color: #efefef;"><td colspan="3" style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Statutory Deductions</td></tr>
        <tr><td style="padding: 8px; border: 1px solid #ddd;">PF Employee & Employer Contribution</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${pfEmployee.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(pfEmployee * 12).toLocaleString()}</td></tr>
        <tr><td style="padding: 8px; border: 1px solid #ddd;">Professional Tax (PT)</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${pt.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(pt * 12).toLocaleString()}</td></tr>
        <tr><td style="padding: 8px; border: 1px solid #ddd;">Medical Insurance Benefit</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${medical.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(medical * 12).toLocaleString()}</td></tr>
        
        <tr style="font-weight: bold; background-color: #115b82; color: white;"><td style="padding: 8px; border: 1px solid #ddd;">Net Take Home Pay</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${netPay.toLocaleString()}</td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${(netPay * 12).toLocaleString()}</td></tr>
      </tbody>
    </table>

    <p style="margin-top: 25px;"><strong>Joining Date:</strong> ${joining}<br><strong>Reporting Manager:</strong> ${data.reportingManager || 'HR Manager'}</p>
    
    <p>Please return a signed copy of this offer letter within 3 days as acceptance of the terms outlined above.</p>
    
    <div style="margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end;">
      <div class="hr-sig-slot" style="min-width: 200px; text-align: center; display: inline-block;">
        <div class="hr-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: center;"></div>
        <div style="border-top: 1px solid #333; margin-top: 5px; padding-top: 5px;">
          <strong>HR Manager</strong><br>Search Homes India Pvt Ltd
        </div>
      </div>
      <div class="candidate-sig-slot" style="min-width: 200px; text-align: center; display: inline-block;">
        <div class="candidate-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: center;"></div>
        <div style="border-top: 1px solid #333; margin-top: 5px; padding-top: 5px;">
          <strong>Candidate Signature</strong><br>Date:
        </div>
      </div>
    </div>
  </div>
  `;
};

// Admin: Get all offer letters
router.get('/', protect, adminOnly, async (req, res) => {
  try {
    const { search, status } = req.query;
    let query = {};
    if (status) query.status = status;
    if (search) {
      query.$or = [
        { candidateName: { $regex: search, $options: 'i' } },
        { email: { $regex: search, $options: 'i' } },
        { position: { $regex: search, $options: 'i' } }
      ];
    }

    const offers = await OfferLetter.find(query).sort({ createdAt: -1 });
    res.json(offers);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Get single offer letter
router.get('/:id', protect, adminOnly, async (req, res) => {
  try {
    const offer = await OfferLetter.findById(req.params.id);
    if (!offer) return res.status(404).json({ message: 'Offer letter not found.' });
    res.json(offer);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Create offer letter
router.post('/', protect, adminOnly, async (req, res) => {
  try {
    const { candidateName, email, phone, position, department, monthlySalary, joiningDate, reportingManager, customHtml } = req.body;
    
    if (!candidateName || !email || !phone || !position || !monthlySalary || !joiningDate) {
      return res.status(400).json({ message: 'Required fields are missing.' });
    }

    const htmlContent = customHtml || getDefaultHtmlTemplate({
      candidateName, email, phone, position, department, monthlySalary, joiningDate, reportingManager
    });

    const offer = new OfferLetter({
      candidateName,
      email,
      phone,
      position,
      department,
      monthlySalary,
      joiningDate,
      reportingManager,
      customHtml: htmlContent
    });

    await offer.save();
    res.status(201).json(offer);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Update offer letter
router.put('/:id', protect, adminOnly, async (req, res) => {
  try {
    const offer = await OfferLetter.findById(req.params.id);
    if (!offer) return res.status(404).json({ message: 'Offer letter not found.' });

    Object.assign(offer, req.body);
    await offer.save();
    res.json(offer);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Delete offer letter
router.delete('/:id', protect, adminOnly, async (req, res) => {
  try {
    const offer = await OfferLetter.findByIdAndDelete(req.params.id);
    if (!offer) return res.status(404).json({ message: 'Offer letter not found.' });
    res.json({ message: 'Offer letter deleted successfully.' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Admin: Send offer letter (Actual Email)
router.post('/:id/send', protect, adminOnly, async (req, res) => {
  try {
    const offer = await OfferLetter.findById(req.params.id);
    if (!offer) return res.status(404).json({ message: 'Offer letter not found.' });

    // Send the actual email
    const info = await sendEmail({
      to: offer.email,
      subject: `Employment Offer Letter - Search Homes India Pvt Ltd`,
      html: offer.customHtml
    });

    offer.status = 'Sent';
    offer.emailedAt = new Date();
    offer.emailedBy = req.user.username;
    await offer.save();

    let msg = `Offer letter successfully sent to ${offer.email}.`;
    if (!process.env.SMTP_USER) {
      const previewUrl = nodemailer.getTestMessageUrl(info);
      msg += ` (Dev Mode Ethereal: ${previewUrl})`;
    }

    res.json({ message: msg, data: offer });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
