import express from 'express';
import Lead from '../models/Lead.js';
import axios from 'axios';

const router = express.Router();

// Retrieve WhatsApp conversation history
router.get('/chat', async (req, res) => {
  try {
    const { phone, lead_id } = req.query;

    let lead = null;
    if (lead_id) {
      lead = await Lead.findById(lead_id);
    } else if (phone) {
      const cleanPhone = phone.replace(/\D/g, '').slice(-10);
      lead = await Lead.findOne({ number: { $regex: cleanPhone } });
    }

    if (!lead) {
      return res.json({ messages: [], auto_reply: true });
    }

    // Reset unread count upon opening chat
    lead.unread_wa_count = 0;
    await lead.save();

    res.json({
      phone: lead.number,
      lead_id: lead._id,
      auto_reply: lead.wa_auto_reply,
      messages: lead.whatsapp_history || []
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Proxy: Send WhatsApp outreach via Esha API
router.post('/send', async (req, res) => {
  try {
    const { lead_id, text } = req.body;

    const lead = await Lead.findById(lead_id);
    if (!lead) {
      return res.status(404).json({ message: 'Lead not found' });
    }

    const ESHA_BASE = 'https://omegaappbuilder.com';
    const ESHA_ADMIN_TOKEN = process.env.ESHA_ADMIN_TOKEN || 'spool_admin_523f6e1341451f1bc876fa3b';
    
    const payload = {
      row_id: `row_${lead._id}`,
      text
    };

    const response = await axios.post(`${ESHA_BASE}/api/messages/send`, payload, {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${ESHA_ADMIN_TOKEN}`
      },
      timeout: 20000
    });

    const entry = {
      lead_number: lead.number,
      message: text,
      sender_number: process.env.ESHA_SENDER_PHONE || '9632056699',
      role: 'esha',
      direction: 'OUTBOUND',
      time: new Date()
    };

    lead.whatsapp_history.push(entry);
    await lead.save();

    res.json({ ok: true, stored: entry, esha_response: response.data });
  } catch (err) {
    res.status(500).json({ message: 'Failed to send WhatsApp message: ' + (err.response?.data?.error || err.message) });
  }
});

// Webhook Receiver: Handles Esha inbound/outbound callbacks
router.post('/webhook', async (req, res) => {
  try {
    const body = req.body;
    
    // Resolve parameters from Esha's event payload
    const phone = body.lead_number || body.lead?.phone || '';
    const message = body.message?.text || '';
    const direction = body.direction || body.message?.direction || 'INBOUND';
    
    if (!phone) {
      return res.status(400).json({ message: 'No lead phone number received.' });
    }

    const cleanPhone = phone.replace(/\D/g, '').slice(-10);
    const lead = await Lead.findOne({ number: { $regex: cleanPhone } });

    if (!lead) {
      return res.status(404).json({ message: 'Lead not found in CRM for phone number: ' + cleanPhone });
    }

    const entry = {
      lead_number: cleanPhone,
      message,
      sender_number: body.sender_number || (direction === 'INBOUND' ? cleanPhone : '9632056699'),
      role: direction === 'INBOUND' ? 'lead' : 'esha',
      direction: direction.toUpperCase(),
      time: body.timestamp || new Date(),
      media_url: body.media_url || body.file_url || '',
      attachments: body.attachments || []
    };

    lead.whatsapp_history.push(entry);
    if (direction.toUpperCase() === 'INBOUND') {
      lead.unread_wa_count += 1;
    }
    await lead.save();

    // Broadcast message via Socket.io
    const io = req.app.get('io');
    if (io) {
      io.emit('whatsapp_message', {
        lead_id: lead._id,
        message: entry,
        unread_count: lead.unread_wa_count
      });
    }

    res.json({ ok: true, lead_id: lead._id });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Toggle automatic AI replies on Esha
router.post('/auto-reply', async (req, res) => {
  try {
    const { lead_id, enabled } = req.body;
    const lead = await Lead.findById(lead_id);
    if (!lead) return res.status(404).json({ message: 'Lead not found' });

    lead.wa_auto_reply = enabled;
    await lead.save();

    try {
      const ESHA_BASE = 'https://omegaappbuilder.com';
      await axios.post(`${ESHA_BASE}/api/auto-reply`, {
        row_id: `row_${lead_id}`,
        enabled: !!enabled
      }, {
        headers: { 'Content-Type': 'application/json' },
        timeout: 10000
      });
    } catch (err) {
      // Silent error, backend DB status takes priority
    }

    res.json({ ok: true, auto_reply: lead.wa_auto_reply });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
