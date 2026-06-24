import mongoose from 'mongoose';

const NoticeSchema = new mongoose.Schema({
  alert_message: { type: String, required: true }, // TinyMCE HTML alert message
  created_by: { type: String, required: true } // Creator tablename
}, { timestamps: true });

const Notice = mongoose.model('Notice', NoticeSchema);
export default Notice;
