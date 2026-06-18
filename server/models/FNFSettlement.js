import mongoose from 'mongoose';

const FNFSettlementSchema = new mongoose.Schema({
  user: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, unique: true },
  lastWorkingDay: { type: Date, required: true },
  unpaidSalary: { type: Number, default: 0 },
  leaveEncashment: { type: Number, default: 0 },
  bonusIncentives: { type: Number, default: 0 },
  deductions: { type: Number, default: 0 },
  netSettlement: { type: Number, required: true },
  status: { type: String, enum: ['Pending', 'Settled'], default: 'Pending' },
  assetsReturned: { type: Boolean, default: false }
}, { timestamps: true });

const FNFSettlement = mongoose.model('FNFSettlement', FNFSettlementSchema);
export default FNFSettlement;
