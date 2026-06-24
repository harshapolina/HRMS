import mongoose from 'mongoose';

const ExitDocumentSchema = new mongoose.Schema({
  user: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
  type: { type: String, enum: ['no_dues_certificate', 'relieving_letter'], required: true },
  customHtml: { type: String, required: true },
  emailedAt: { type: Date }
}, { timestamps: true });

ExitDocumentSchema.index({ user: 1, type: 1 }, { unique: true });

const ExitDocument = mongoose.model('ExitDocument', ExitDocumentSchema);
export default ExitDocument;
