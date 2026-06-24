import mongoose from 'mongoose';

const CronJobSchema = new mongoose.Schema({
  row_id: [{ type: mongoose.Schema.Types.ObjectId, ref: 'Lead' }], // Array of lead IDs in queue
  assigned_user: [{ type: String }], // Comma-separated or array of user tablenames to distribute to
  project_name: { type: String, required: true },
  source_lead: { type: String },
  last_assigned_user: { type: String },
  interval_time: { type: Number, required: true }, // in minutes
  location: { type: String },
  is_active: { type: Boolean, default: true }
}, { timestamps: true });

const CronJob = mongoose.model('CronJob', CronJobSchema);
export default CronJob;
