import mongoose from 'mongoose';

const RemarkSchema = new mongoose.Schema({
  text: { type: String, required: true },
  created_by: { type: String, required: true }, // User tablename
  created_at: { type: Date, default: Date.now }
});

const WhatsAppMessageSchema = new mongoose.Schema({
  lead_number: { type: String, required: true },
  message: { type: String },
  sender_number: { type: String },
  role: { type: String, enum: ['lead', 'esha'], required: true },
  direction: { type: String, enum: ['INBOUND', 'OUTBOUND'], required: true },
  time: { type: Date, default: Date.now },
  media_url: { type: String },
  attachments: [mongoose.Schema.Types.Mixed]
});

const LeadSchema = new mongoose.Schema({
  name: { type: String, required: true, trim: true },
  email: { type: String, lowercase: true, trim: true },
  number: { type: String, required: true, trim: true },
  location: { type: String },
  type: { type: String, default: '3 BHK' },
  source_of_lead: { type: String },
  subsource_of_lead: { type: String },
  assign_to_user: { type: String, default: 'unassigned' },
  lead_count: { type: Number, default: 1 },
  project: { type: String },
  status: { type: String, enum: ['Pending', 'Contacted', 'Interested', 'Not Interested', 'Booked', 'EOI'], default: 'Pending' },
  remarks: [RemarkSchema],
  whatsapp_history: [WhatsAppMessageSchema],
  wa_bot_sent: { type: Boolean, default: false },
  unread_wa_count: { type: Number, default: 0 },
  wa_auto_reply: { type: Boolean, default: true }
}, {
  timestamps: true
});

// Create index for fast phone search
LeadSchema.index({ number: 1 });
LeadSchema.index({ project: 1 });

const Lead = mongoose.model('Lead', LeadSchema);
export default Lead;
