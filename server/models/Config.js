import mongoose from 'mongoose';

const ConfigSchema = new mongoose.Schema({
  project_name: { type: String, required: true },
  group_name: { type: String }, // Used when type is 'group'
  type: { type: String, enum: ['group', 'credential'], default: 'credential' },
  api_key: { type: String },
  lead_source: { type: String }, // e.g. Facebook, WhatsApp, Website
  assign_user: [{ type: String }], // Array of assigned user tablenames
  group_id: [{ type: mongoose.Schema.Types.ObjectId, ref: 'Config' }] // References to Config of type 'group'
}, { timestamps: true });

const Config = mongoose.model('Config', ConfigSchema);
export default Config;
