import mongoose from 'mongoose';

const TrackingSchema = new mongoose.Schema({
  month: { type: String, required: true }, // Format "YYYY-MM"
  send_amt: { type: Number, required: true }, // Salary payout or sent amount
  user_name: { type: String, required: true }, // e.g. "Search Homes India" or specific username
  user_type: { type: String, enum: ['salary', 'manager', 'teamlead', 'ceo'], required: true },
  bookin_number: { type: Number, default: 0 }, // booking counts or active users
  gen_revenue: { type: Number, default: 0 },
  recent_pay: { type: Number, default: 0 },
  remaning_amt: { type: Number, default: 0 }
}, { timestamps: true });

const Tracking = mongoose.model('Tracking', TrackingSchema);
export default Tracking;
