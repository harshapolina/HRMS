import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, BarChart, Bar, Cell } from 'recharts';
import { Users, UserX, Wallet, CheckSquare } from 'lucide-react';
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
    { title: 'Active Employees', value: summary.activeCount, icon: Users, accent: 'text-badge-emerald', bg: 'bg-badge-emerald/10' },
    { title: 'Inactive Employees', value: summary.inactiveCount, icon: UserX, accent: 'text-error', bg: 'bg-error/10' },
    { title: 'Assigned Employees', value: summary.assignedCount, icon: CheckSquare, accent: 'text-badge-violet', bg: 'bg-badge-violet/10' },
    { title: 'Monthly Net Payroll', value: `₹${summary.totalSalary.toLocaleString()}`, icon: Wallet, accent: 'text-badge-orange', bg: 'bg-badge-orange/10' },
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
        <div className="spinner" />
      </div>
    );
  }

  return (
    <div className="page-shell">
      <div className="page-header">
        <div>
          <p className="page-eyebrow mb-1">Enterprise Telemetry</p>
          <h1 className="page-title">Welcome to HRMS Hub</h1>
          <p className="page-subtitle">Real-time enterprise dashboard and lead lifecycle telemetry.</p>
        </div>
        <div className="page-header-actions">
          <span id="dashboard-status-badge" className="badge-pill self-start md:self-auto">System Online</span>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {cardData.map((card, i) => {
          const Icon = card.icon;
          return (
            <div key={i} className="card p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-muted text-xs font-medium uppercase tracking-wide">{card.title}</p>
                  <h3 className="text-display-sm text-ink mt-2 font-display tracking-tight">
                    {card.value}
                  </h3>
                </div>
                <div className={`p-3 rounded-lg ${card.bg}`}>
                  <Icon className={`w-5 h-5 ${card.accent}`} />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="card p-6">
          <h3 className="section-title mb-6">Pipeline Lead Distribution</h3>
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={leadChartData} margin={{ top: 20, right: 10, left: -20, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f3f4f6" />
                <XAxis dataKey="name" tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#6b7280' }} />
                <YAxis tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#6b7280' }} />
                <Tooltip cursor={{ fill: 'transparent' }} contentStyle={{ backgroundColor: '#111111', border: 'none', borderRadius: '8px', color: 'white', fontSize: 12 }} />
                <Bar dataKey="value" radius={[6, 6, 0, 0]}>
                  {leadChartData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="card p-6">
          <h3 className="section-title mb-6">Payroll Outflow Telemetry</h3>
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={revenueData} margin={{ top: 20, right: 10, left: -10, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorAmount" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#111111" stopOpacity={0.12}/>
                    <stop offset="95%" stopColor="#111111" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f3f4f6" />
                <XAxis dataKey="month" tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#6b7280' }} />
                <YAxis tickLine={false} axisLine={false} style={{ fontSize: 12, fill: '#6b7280' }} />
                <Tooltip formatter={(value) => `₹${value.toLocaleString()}`} contentStyle={{ backgroundColor: '#111111', border: 'none', borderRadius: '8px', color: 'white', fontSize: 12 }} />
                <Area type="monotone" dataKey="amount" stroke="#111111" strokeWidth={2} fillOpacity={1} fill="url(#colorAmount)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
