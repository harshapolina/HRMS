import mongoose from 'mongoose';
import bcrypt from 'bcryptjs';

const UserSchema = new mongoose.Schema({
  username: { type: String, required: true },
  useremail: { type: String, required: true, unique: true, lowercase: true, trim: true },
  phonenumber: { type: String, trim: true },
  epassword: { type: String, required: true },
  salary: { type: Number, default: 0 },
  tablename: { type: String, unique: true, required: true },
  employee_id: { type: String },
  doj: { type: Date },
  dob: { type: Date },
  one_amt: { type: Number, default: 0 },
  two_amt: { type: Number, default: 0 },
  thrid_amt: { type: Number, default: 0 }, // keep matching original column names
  forth_amt: { type: Number, default: 0 },
  fifth_amt: { type: Number, default: 0 },
  sixth_amt: { type: Number, default: 0 },
  project_name: { type: String },
  project_type: { type: String },
  user_type: { type: String, enum: ['promoter', 'business head', 'manager', 'team lead', 'user', 'superuseradmin', 'hradmin'], default: 'user' },
  assign_user: [{ type: String }], // Array of manager/team member tablenames
  city: { type: String },
  is_active: { type: Boolean, default: true },
  deactivated_at: { type: Date },
  flag_user_login: { type: Date }
}, {
  timestamps: true
});

// Hash password before saving
UserSchema.pre('save', async function (next) {
  if (!this.isModified('epassword')) return next();
  try {
    const salt = await bcrypt.genSalt(10);
    this.epassword = await bcrypt.hash(this.epassword, salt);
    next();
  } catch (err) {
    next(err);
  }
});

// Compare password method
UserSchema.methods.matchPassword = async function (enteredPassword) {
  return await bcrypt.compare(enteredPassword, this.epassword);
};

const User = mongoose.model('User', UserSchema);
export default User;
