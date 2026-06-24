import mongoose from 'mongoose';

const EOISchema = new mongoose.Schema({
  booking_date: { type: Date, default: Date.now },
  booking_month: { type: String, required: true }, // Format "YYYY-MM"
  builder: { type: String, required: true },
  project: { type: String, required: true },
  customer_name: { type: String, required: true },
  contact_number: { type: String, required: true },
  email_id: { type: String, required: true },
  project_type: { type: String, required: true },
  canceleoi: { type: Boolean, default: false },
  source_table: { type: String, required: true }, // tablename of promoter/agent
  astatus: { type: String, default: 'Processing' } // e.g. "Processing", "Cancelled", "Converted"
}, { timestamps: true });

const EOI = mongoose.model('EOI', EOISchema);
export default EOI;
