import mongoose from 'mongoose';

const AttendanceSchema = new mongoose.Schema({
  user: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
  date: { type: String, required: true }, // YYYY-MM-DD format
  punchIn: { type: Date },
  punchOut: { type: Date },
  status: { type: String, enum: ['Not Logged', 'Present', 'Late', 'Absent', 'Leave', 'Holiday'], default: 'Not Logged' },
  latIn: { type: Number },
  lngIn: { type: Number },
  latOut: { type: Number },
  lngOut: { type: Number },
  ip: { type: String },
  totalHours: { type: Number },
  locationHistory: [{
    latitude: { type: Number },
    longitude: { type: Number },
    capturedAt: { type: Date, default: Date.now }
  }]
}, { timestamps: true });

// Ensure each user has at most one attendance record per day
AttendanceSchema.index({ user: 1, date: 1 }, { unique: true });

const Attendance = mongoose.model('Attendance', AttendanceSchema);
export default Attendance;
