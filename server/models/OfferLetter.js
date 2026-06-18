import mongoose from 'mongoose';

const OfferLetterSchema = new mongoose.Schema({
  user: { type: mongoose.Schema.Types.ObjectId, ref: 'User' }, // optional ref if candidate is converted to employee
  candidateName: { type: String, required: true },
  email: { type: String, required: true },
  phone: { type: String, required: true },
  position: { type: String, required: true },
  department: { type: String },
  monthlySalary: { type: Number, required: true },
  joiningDate: { type: Date, required: true },
  reportingManager: { type: String },
  status: { type: String, enum: ['Draft', 'Sent', 'Accepted', 'Rejected'], default: 'Draft' },
  emailedAt: { type: Date },
  emailedBy: { type: String },
  customHtml: { type: String } // Stores the editable HTML layout of the offer letter
}, { timestamps: true });

const OfferLetter = mongoose.model('OfferLetter', OfferLetterSchema);
export default OfferLetter;
