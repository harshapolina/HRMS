import mongoose from 'mongoose';
import bcrypt from 'bcryptjs';
import dotenv from 'dotenv';
import User from './models/User.js';
import Lead from './models/Lead.js';
import Expense from './models/Expense.js';
import Holiday from './models/Holiday.js';
import Asset from './models/Asset.js';

dotenv.config();

const seed = async () => {
  try {
    await mongoose.connect(process.env.MONGODB_URI || 'mongodb://localhost:27017/hrms_db');
    console.log('Connected to MongoDB for seeding...');

    // Clear existing collections
    await User.deleteMany();
    await Lead.deleteMany();
    await Expense.deleteMany();
    await Holiday.deleteMany();
    await Asset.deleteMany();

    // 1. Create default Admin user
    const admin = await User.create({
      username: 'Administrator',
      useremail: 'admin@company.com',
      phonenumber: '+919632056699',
      epassword: 'admin123',
      salary: 120000,
      tablename: 'USR_ADMIN',
      employee_id: 'SHI_001',
      doj: new Date('2024-01-01'),
      dob: new Date('1990-01-01'),
      user_type: 'superuseradmin',
      is_active: true
    });
    console.log('Seeded superuseradmin user: admin@company.com / admin123');

    // 2. Create standard manager/user
    const manager = await User.create({
      username: 'Rahul Roy',
      useremail: 'rahul@company.com',
      phonenumber: '+919876543210',
      epassword: 'manager123',
      salary: 65000,
      tablename: 'USR_RAHUL रॉय',
      employee_id: 'SHI_002',
      doj: new Date('2024-03-01'),
      dob: new Date('1993-05-15'),
      user_type: 'manager',
      is_active: true
    });
    console.log('Seeded manager employee: rahul@company.com / manager123');

    // 2b. Create HR Admin user
    const hrAdmin = await User.create({
      username: 'Shivali Rai',
      useremail: 'hr@company.com',
      phonenumber: '+919999988888',
      epassword: 'hradmin123',
      salary: 55000,
      tablename: 'USR_SHIVALI',
      employee_id: 'SHI_003',
      doj: new Date('2024-02-01'),
      dob: new Date('1992-08-20'),
      user_type: 'hradmin',
      is_active: true
    });
    console.log('Seeded HR Admin employee: hr@company.com / hradmin123');

    // 2c. Create screenshot legacy employees
    const screenshotEmployees = [
      {
        username: 'employee01',
        useremail: 'harshapolinax@gmail.com',
        phonenumber: '+919876543201',
        epassword: 'password123',
        salary: 45000,
        tablename: '01123',
        employee_id: '01123',
        doj: new Date('2024-04-01'),
        dob: new Date('1995-10-10'),
        user_type: 'manager',
        is_active: true
      },
      {
        username: 'kaushik123',
        useremail: 'saikaushik090@gmail.com',
        phonenumber: '+919876543202',
        epassword: 'password123',
        salary: 30000,
        tablename: 'Q23456',
        employee_id: 'Q23456',
        doj: new Date('2024-05-15'),
        dob: new Date('1996-12-05'),
        user_type: 'user',
        is_active: true
      },
      {
        username: 'Ayush Tripathi',
        useremail: 'manulitoriya1666@gmail.com',
        phonenumber: '+919876543203',
        epassword: 'password123',
        salary: 32000,
        tablename: 'raghuc1053',
        employee_id: 'raghuc1053',
        doj: new Date('2024-06-01'),
        dob: new Date('1994-07-22'),
        user_type: 'user',
        is_active: true
      },
      {
        username: 'koushik',
        useremail: 'manojjami09@gmail.com',
        phonenumber: '+919876543204',
        epassword: 'password123',
        salary: 28000,
        tablename: 'rahul00761',
        employee_id: 'rahul00761',
        doj: new Date('2024-02-10'),
        dob: new Date('1997-03-14'),
        user_type: 'promoter',
        is_active: true
      },
      {
        username: 'Manoj Jami U',
        useremail: 'rishwireddyshetty@gmail.com',
        phonenumber: '+919876543205',
        epassword: 'password123',
        salary: 85000,
        tablename: 'rakesh6608b',
        employee_id: 'rakesh6608b',
        doj: new Date('2023-11-01'),
        dob: new Date('1990-09-30'),
        user_type: 'business head',
        is_active: true
      },
      {
        username: 'Manoj M',
        useremail: 'manojjami28@gmail.com',
        phonenumber: '+919876543206',
        epassword: 'password123',
        salary: 50000,
        tablename: 'venkey2212',
        employee_id: 'venkey2212',
        doj: new Date('2024-01-15'),
        dob: new Date('1991-04-12'),
        user_type: 'manager',
        is_active: true
      },
      {
        username: 'Monu Atox',
        useremail: 'nxthub@gmail.com',
        phonenumber: '+919876543207',
        epassword: 'password123',
        salary: 25000,
        tablename: 'manu1102',
        employee_id: 'manu1102',
        doj: new Date('2024-06-10'),
        dob: new Date('1998-11-18'),
        user_type: 'user',
        is_active: true
      },
      {
        username: 'Shivam Pandey',
        useremail: 'sample3877@gmail.com',
        phonenumber: '+919876543208',
        epassword: 'password123',
        salary: 31000,
        tablename: 'vipul005',
        employee_id: 'vipul005',
        doj: new Date('2024-03-22'),
        dob: new Date('1996-01-08'),
        user_type: 'user',
        is_active: true
      },
      {
        username: 'accounts',
        useremail: 'antigravityyy1111@gmail.com',
        phonenumber: '+919876543209',
        epassword: 'password123',
        salary: 40000,
        tablename: 'accounts',
        employee_id: 'accounts',
        doj: new Date('2024-05-01'),
        dob: new Date('1993-02-28'),
        user_type: 'user',
        is_active: true
      }
    ];

    for (const emp of screenshotEmployees) {
      await User.create(emp);
    }
    console.log('Seeded legacy employees matching screenshot database...');

    // 3. Seed some leads
    await Lead.create([
      {
        name: 'Karan Sharma',
        email: 'karan@gmail.com',
        number: '9876543298',
        location: 'Bangalore, IND',
        type: '3 BHK',
        source_of_lead: 'Google Ads',
        assign_to_user: 'USR_RAHUL रॉय',
        lead_count: 1,
        project: 'Prestige Lakeside',
        status: 'Pending',
        remarks: [{ text: 'Assigned automatically to manager Rahul Roy.', created_by: 'system' }],
        whatsapp_history: [
          {
            lead_number: '9876543298',
            message: 'Hi Karan, thank you for showing interest in Prestige Lakeside. Our team will contact you shortly.',
            sender_number: '+919632056699',
            role: 'esha',
            direction: 'OUTBOUND',
            time: new Date(Date.now() - 3600000)
          }
        ],
        wa_bot_sent: true
      },
      {
        name: 'Anita Patel',
        email: 'anita@yahoo.com',
        number: '9123456780',
        location: 'Mumbai, IND',
        type: '2 BHK',
        source_of_lead: 'Facebook Form',
        assign_to_user: 'USR_ADMIN',
        lead_count: 2,
        project: 'Godrej Woods',
        status: 'Interested',
        remarks: [{ text: 'Lead is interested. Requesting property pricing detail sheets.', created_by: 'USR_ADMIN' }]
      }
    ]);
    console.log('Seeded mock leads...');

    // 4. Seed some expenses
    await Expense.create([
      { category: 'Rent', amount: 35000, description: 'Office lease payment', created_by: 'USR_ADMIN' },
      { category: 'Utilities', amount: 8200, description: 'Broadband + Electricity bills', created_by: 'USR_ADMIN' },
      { category: 'Marketing', amount: 15000, description: 'Google Ads campaign credits', created_by: 'USR_ADMIN' }
    ]);
    console.log('Seeded mock ledger expenses...');

    // 5. Seed holidays
    await Holiday.create([
      { date: new Date('2026-08-15'), reason: 'Independence Day' },
      { date: new Date('2026-10-02'), reason: 'Gandhi Jayanti' },
      { date: new Date('2026-12-25'), reason: 'Christmas Day' },
      { date: new Date('2026-11-05'), reason: 'Diwali Festival' }
    ]);
    console.log('Seeded company holidays...');

    // 6. Seed assets
    await Asset.create([
      { name: 'Dell Latitude 5420', type: 'Laptop', serialNumber: 'DL-5420-XYZ01', status: 'Available' },
      { name: 'MacBook Air M2', type: 'Laptop', serialNumber: 'MB-AIR-M2-ABC02', status: 'Available' },
      { name: 'Samsung Galaxy A34', type: 'Mobile', serialNumber: 'SS-GALAXY-A34-03', status: 'Available' },
      { name: 'Logitech Wireless Mouse', type: 'Accessory', serialNumber: 'LT-MOUSE-04', status: 'Available' }
    ]);
    console.log('Seeded hardware assets catalog...');

    console.log('Database Seeding Completed Successfully.');
    process.exit(0);
  } catch (error) {
    console.error('Seeding error:', error.message);
    process.exit(1);
  }
};

seed();
