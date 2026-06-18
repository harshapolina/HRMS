import mongoose from 'mongoose';

const ExpenseSchema = new mongoose.Schema({
  category: { type: String, required: true, trim: true },
  amount: { type: Number, required: true },
  payment_date: { type: Date, default: Date.now },
  description: { type: String },
  created_by: { type: String, required: true } // User tablename
}, {
  timestamps: true
});

const Expense = mongoose.model('Expense', ExpenseSchema);
export default Expense;
