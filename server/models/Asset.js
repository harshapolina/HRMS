import mongoose from 'mongoose';

const AssetSchema = new mongoose.Schema({
  name: { type: String, required: true },
  type: { type: String, required: true }, // Laptop, Mobile, Mouse, Keyboard, etc.
  serialNumber: { type: String, required: true, unique: true },
  status: { type: String, enum: ['Available', 'Assigned'], default: 'Available' }
}, { timestamps: true });

const Asset = mongoose.model('Asset', AssetSchema);
export default Asset;

