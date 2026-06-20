import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, BarChart, Bar, Cell } from 'recharts';
import { Users, UserX, Wallet, CheckSquare, Sparkles } from 'lucide-react';
import io from 'socket.io-client';

const Dashboard = () => {
  const [summary, setSummary] = useState({
    activeCount: 0,
    inactiveCount: 0,
    assignedCount: 0,
    totalSalary: 0
  });
  const [leadStats, setLeadStats] = useState({
    total: 0,
    pending: 0,
    contacted: 0,
    interested: 0,
    booked: 0,
    eoi: 0
  });
  const [loading, setLoading] = useState(true);

  const fetchDashboardDataRef = useRef(null);

  useEffect(() => {
    fetchDashboardDataRef.current = fetchDashboardData;
  });

  useEffect(() => {
    const socket = io();
    
    socket.on('user_update', () => {
      console.log('Real-time user update received on Dashboard. Reloading...');
      if (fetchDashboardDataRef.current) fetchDashboardDataRef.current();
    });

    socket.on('lead_update', () => {
      console.log('Real-time lead update received on Dashboard. Reloading...');
      if (fetchDashboardDataRef.current) fetchDashboardDataRef.current();
    });

    return () => {
      socket.disconnect();
    };
  }, []);

  const fetchDashboardData = async () => {
    try {
      const userRes = await axios.get('/api/users?limit=1');
      if (userRes.data.summary) {
        setSummary(userRes.data.summary);
      }

      const leadRes = await axios.get('/api/leads?limit=1');
      if (leadRes.data.stats) {
        setLeadStats(leadRes.data.stats);
      }
    } catch (err) {
      console.error('Error fetching dashboard stats', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const cardData = [
    { title: 'Active Employees', value: summary.activeCount, icon: Users, color: 'text-emerald-500', bg: 'bg-emerald-500/10' },
    { title: 'Inactive Employees', value: summary.inactiveCount, icon: UserX, color: 'text-rose-500', bg: 'bg-rose-500/10' },
    { title: 'Assigned Employees', value: summary.assignedCount, icon: CheckSquare, color: 'text-indigo-500', bg: 'bg-indigo-500/10' },
    { title: 'Monthly Net Payroll', value: `₹${summary.totalSalary.toLocaleString()}`, icon: Wallet, color: 'text-amber-500', bg: 'bg-amber-500/10' },
  ];

  const leadChartData = [
    { name: 'Pending', value: leadStats.pending, color: '#f59e0b' },
    { name: 'Contacted', value: leadStats.contacted, color: '#3b82f6' },
    { name: 'Interested', value: leadStats.interested, color: '#8b5cf6' },
    { name: 'EOI', value: leadStats.eoi, color: '#ec4899' },
    { name: 'Booked', value: leadStats.booked, color: '#10b981' },
  ];

  const revenueData = [
    { month: 'Jan', amount: 450000 },
    { month: 'Feb', amount: 520000 },
    { month: 'Mar', amount: 490000 },
    { month: 'Apr', amount: 630000 },
    { month: 'May', amount: 710000 },
    { month: 'Jun', amount: 820000 },
  ];

  if (loading) {
    return (
      <div className="flex h-[60vh] items-center justify-center">
        <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-brand-500"></div>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      {/* Banner */}
      <div className="bg-gradient-to-r from-slate-900 via-brand-950 to-slate-900 border border-slate-800 p-6 rounded-2xl flex items-center justify-between shadow-xl">
        <div>
          <h1 className="text-2xl font-bold text-white flex items-center gap-2">
            Welcome to HRMS Hub <Sparkles className="w-5 h-5 text-amber-400 animate-pulse" />
          </h1>
          <p className="text-slate-400 text-sm mt-1">Real-time enterprise dashboard and lead lifecycle telemetry.</p>
        </div>
        <div className="hidden md:block text-right">
          <span className="text-xs font-semibold px-3 py-1 bg-brand-500/10 text-brand-400 rounded-full border border-brand-500/20">
            System Online
          </span>
        </div>
      </div>

      {/* Grid of counter cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {cardData.map((card, i) => {
          const Icon = card.icon;
          return (
            <div key={i} className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all group">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">{card.title}</p>
                  <h3 className="text-2xl font-bold text-slate-800 dark:text-white mt-2 group-hover:scale-105 transition-transform origin-left">
                    {card.value}
                  </h3>
                </div>
                <div className={`p-4 rounded-xl ${card.bg}`}>
                  <Icon className={`w-6 h-6 ${card.color}`} />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Charts section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Lead Stages Bar Chart */}
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
          <h3 className="text-slate-700 dark:text-white font-bold text-base mb-6">Pipeline Lead Distribution</h3>
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={leadChartData} margin={{ top: 20, right: 10, left: -20, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" className="dark:stroke-slate-800" />
                <XAxis dataKey="name" tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#64748b' }} />
                <YAxis tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#64748b' }} />
                <Tooltip cursor={{ fill: 'transparent' }} contentStyle={{ backgroundColor: 'rgb(15 23 42)', border: 'none', borderRadius: '8px', color: 'white' }} />
                <Bar dataKey="value" radius={[8, 8, 0, 0]}>
                  {leadChartData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Salary payroll trends (Area chart) */}
        <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
          <h3 className="text-slate-700 dark:text-white font-bold text-base mb-6">Payroll Outflow Telemetry</h3>
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={revenueData} margin={{ top: 20, right: 10, left: -10, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorAmount" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#477ca9" stopOpacity={0.2}/>
                    <stop offset="95%" stopColor="#477ca9" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" className="dark:stroke-slate-800" />
                <XAxis dataKey="month" tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#64748b' }} />
                <YAxis tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#64748b' }} />
                <Tooltip formatter={(value) => `₹${value.toLocaleString()}`} contentStyle={{ backgroundColor: 'rgb(15 23 42)', border: 'none', borderRadius: '8px', color: 'white' }} />
                <Area type="monotone" dataKey="amount" stroke="#477ca9" strokeWidth={3} fillOpacity={1} fill="url(#colorAmount)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
