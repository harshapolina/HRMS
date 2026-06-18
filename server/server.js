import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import cors from 'cors';
import dotenv from 'dotenv';
import cron from 'node-cron';
import axios from 'axios';

import connectDB from './config/db.js';
import authRoutes from './routes/auth.js';
import userRoutes from './routes/users.js';
import leadRoutes from './routes/leads.js';
import whatsappRoutes from './routes/whatsapp.js';
import expenseRoutes from './routes/expenses.js';
import attendanceRoutes from './routes/attendance.js';
import leaveRoutes from './routes/leaves.js';
import payrollRoutes from './routes/payroll.js';
import offerRoutes from './routes/offers.js';
import assetRoutes from './routes/assets.js';
import holidayRoutes from './routes/holidays.js';
import fnfRoutes from './routes/fnf.js';
import Lead from './models/Lead.js';
import User from './models/User.js';

dotenv.config();

// Establish Database Connection
connectDB();

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST', 'PUT', 'DELETE']
  }
});

// Cache socket io to express context
app.set('io', io);

// Global Middlewares
app.use(cors());
app.use(express.json());

// API Routes
app.use('/api/auth', authRoutes);
app.use('/api/users', userRoutes);
app.use('/api/leads', leadRoutes);
app.use('/api/whatsapp', whatsappRoutes);
app.use('/api/expenses', expenseRoutes);
app.use('/api/attendance', attendanceRoutes);
app.use('/api/leaves', leaveRoutes);
app.use('/api/payroll', payrollRoutes);
app.use('/api/offers', offerRoutes);
app.use('/api/assets', assetRoutes);
app.use('/api/holidays', holidayRoutes);
app.use('/api/fnf', fnfRoutes);

app.get('/', (req, res) => {
  res.send('HRMS MERN Application API is Online.');
});

// Socket Connections Logger
io.on('connection', (socket) => {
  console.log(`Realtime client connected: ${socket.id}`);
  
  socket.on('disconnect', () => {
    console.log(`Realtime client disconnected: ${socket.id}`);
  });
});

// Cron Task: Run Esha WhatsApp Auto Outreach scanner
// Scans pending leads every minute (replacing cron_auto_whatsapp.php)
cron.schedule('*/1 * * * *', async () => {
  console.log('[Cron Worker] Checking for pending auto outreach leads...');
  try {
    const leads = await Lead.find({
      status: 'Pending',
      wa_bot_sent: false
    }).limit(25);

    if (leads.length === 0) {
      return;
    }

    const ESHA_BASE = 'https://omegaappbuilder.com';
    
    for (const lead of leads) {
      let senderPhone = process.env.ESHA_SENDER_PHONE || '+919632056699';
      let senderName = 'Agent';
      
      if (lead.assign_to_user !== 'unassigned') {
        const user = await User.findOne({ tablename: lead.assign_to_user });
        if (user) {
          senderPhone = user.phonenumber || senderPhone;
          senderName = user.username || senderName;
        }
      }

      const formattedPhone = lead.number.replace(/\D/g, '').slice(-10);
      const e164Phone = '+91' + formattedPhone;
      const projectSafe = lead.project ? lead.project.toLowerCase().replace(/[^a-z0-9_]/gi, '_') : 'default_project';

      const payload = {
        salesperson_id: `sp_${lead.assign_to_user}(${senderName})`,
        salesperson_phone: senderPhone,
        project_ids: [`proj_${projectSafe}(${lead.project || 'Project'})`],
        row_ids: [`row_${lead._id}`],
        lead_numbers: [e164Phone],
        sendInitialOutreach: true
      };

      try {
        await axios.post(`${ESHA_BASE}/api/initiate`, payload, {
          headers: { 'Content-Type': 'application/json' },
          timeout: 12000
        });

        lead.wa_bot_sent = true;
        await lead.save();
        console.log(`[Cron Worker] Automated outreach sent for Lead: ${lead._id}`);
      } catch (err) {
        console.error(`[Cron Worker] Automated outreach failed for Lead: ${lead._id} - ${err.message}`);
      }
    }
  } catch (error) {
    console.error(`[Cron Worker] Exception in whatsapp auto cron: ${error.message}`);
  }
});

const PORT = process.env.PORT || 5000;
server.listen(PORT, () => {
  console.log(`Express server listening on port ${PORT}`);
});
