import mongoose from 'mongoose';

const NoticeAcceptanceSchema = new mongoose.Schema({
  user_id: { type: String, required: true }, // User tablename
  alert_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Notice', required: true },
  alert_accepted: { type: Boolean, default: true }
}, { timestamps: true });

// Make user_id and alert_id unique together to prevent double inserts
NoticeAcceptanceSchema.index({ user_id: 1, alert_id: 1 }, { unique: true });

const NoticeAcceptance = mongoose.model('NoticeAcceptance', NoticeAcceptanceSchema);
export default NoticeAcceptance;
