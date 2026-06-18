import mongoose from 'mongoose';

const AssetAssignmentSchema = new mongoose.Schema({
  asset: { type: mongoose.Schema.Types.ObjectId, ref: 'Asset', required: true },
  user: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
  assignedDate: { type: Date, default: Date.now, required: true },
  returnedDate: { type: Date },
  notes: { type: String }
}, { timestamps: true });

const AssetAssignment = mongoose.model('AssetAssignment', AssetAssignmentSchema);
export default AssetAssignment;
