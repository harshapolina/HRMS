import mongoose from 'mongoose';

const HolidaySchema = new mongoose.Schema({
  date: { type: Date, required: true, unique: true },
  reason: { type: String, required: true }
}, { timestamps: true });

const Holiday = mongoose.model('Holiday', HolidaySchema);
export default Holiday;
