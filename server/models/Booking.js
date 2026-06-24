import mongoose from 'mongoose';

const BookingSchema = new mongoose.Schema({
  booking_date: { type: Date, required: true },
  booking_month: { type: String, required: true }, // Format "YYYY-MM"
  builder: { type: String, required: true },
  project: { type: String, required: true },
  customer_name: { type: String, required: true },
  contact_number: { type: String, required: true },
  email_id: { type: String, required: true },
  project_type: { type: String, required: true },
  unit_no: { type: String, required: true, unique: true }, // Unit number must be globally unique
  size: { type: String, required: true },
  agreement_value: { type: Number, required: true },
  cashback: { type: Number, required: true },
  revenue: { type: Number, required: true },
  ccashback: { type: Number, required: true }, // Cashback percentage
  crevenue: { type: Number, required: true }, // Revenue percentage
  astatus: { type: String, default: 'Processing' }, // e.g. "Processing", "Completed", "Received", "Cancelled"
  recived_amt: { type: Number, default: 0 },
  msalary: { type: Number, default: 0 }, // promoter's salary at the time of booking
  source_table: { type: String, required: true }, // promoter/user tablename who submitted it
  assign_user: [{ type: String }], // Comma-separated list of reporting hierarchy users
  document_path: { type: String },
  source_lead: { type: String },
  remarks: { type: String },
  deduct_agreement: { type: Number }, // Computed reduced agreement value
  city: { type: String },
  approvalStatus: { type: String, enum: ['pending', 'approved', 'rejected'], default: 'pending' },
  approved_by: { type: String },
  approved_at: { type: Date },
  invoice_raise: { type: Number, default: 0 },
  update_in_user_table: { type: Boolean, default: false },
  update_in_invoice_table: { type: Boolean, default: false },
  cashbackverify: { type: Boolean, default: false }
}, { timestamps: true });

const Booking = mongoose.model('Booking', BookingSchema);
export default Booking;

