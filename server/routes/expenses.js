import express from 'express';
import Expense from '../models/Expense.js';

const router = express.Router();

// Get all expenses
router.get('/', async (req, res) => {
  try {
    const expenses = await Expense.find().sort({ payment_date: -1 });
    const totalAmount = expenses.reduce((sum, e) => sum + (e.amount || 0), 0);
    res.json({
      data: expenses,
      totalAmount
    });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Create expense
router.post('/', async (req, res) => {
  try {
    const { category, amount, payment_date, description, created_by } = req.body;
    
    if (!category || !amount || !created_by) {
      return res.status(400).json({ message: 'Category, amount, and creator user are required.' });
    }

    const expense = await Expense.create({
      category,
      amount,
      payment_date: payment_date || new Date(),
      description,
      created_by
    });

    res.status(201).json(expense);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// Delete expense
router.delete('/:id', async (req, res) => {
  try {
    const expense = await Expense.findByIdAndDelete(req.params.id);
    if (!expense) return res.status(404).json({ message: 'Expense ledger not found.' });
    res.json({ message: 'Expense item deleted successfully.' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
