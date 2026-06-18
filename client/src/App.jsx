import React, { useState, useContext, useEffect } from 'react';
import { AuthProvider, AuthContext } from './context/AuthContext';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Employees from './pages/Employees';
import LeadsBoard from './pages/LeadsBoard';
import Expenses from './pages/Expenses';
import EmployeePortal from './pages/EmployeePortal';
import AttendanceReport from './pages/AttendanceReport';
import LiveTracking from './pages/LiveTracking';
import LeaveManagement from './pages/LeaveManagement';
import Payroll from './pages/Payroll';
import OfferLetterPage from './pages/OfferLetterPage';
import PayslipsPage from './pages/PayslipsPage';
import CompanyAssets from './pages/CompanyAssets';
import SettingsPage from './pages/Settings';

import {
  LayoutDashboard,
  Users,
  MessageSquare,
  CreditCard,
  LogOut,
  User as UserIcon,
  Menu,
  X,
  Bell,
  UserCheck,
  Calendar,
  Map,
  ShieldAlert,
  Wallet,
  FileText,
  Receipt,
  HardDrive,
  Sun,
  Moon,
  Settings
} from 'lucide-react';
import io from 'socket.io-client';

const MainApp = () => {
  const { user, logout } = useContext(AuthContext);
  const [activeTab, setActiveTab] = useState('dashboard');
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [notifications, setNotifications] = useState([]);
  const [showNotificationDropdown, setShowNotificationDropdown] = useState(false);
  const [theme, setTheme] = useState(localStorage.getItem('theme') || 'light');

  useEffect(() => {
    const socket = io('http://localhost:5000');

    socket.on('whatsapp_message', (data) => {
      if (data.message.role === 'lead') {
        const text = `New WhatsApp message from ${data.message.sender_number}: "${data.message.message}"`;
        setNotifications(prev => [
          { text, time: new Date().toLocaleTimeString() },
          ...prev
        ]);
      }
    });

    return () => {
      socket.disconnect();
    };
  }, []);

  // Sync theme with DOM and localStorage
  useEffect(() => {
    if (theme === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    localStorage.setItem('theme', theme);
  }, [theme]);

  const toggleTheme = () => {
    setTheme(prev => (prev === 'light' ? 'dark' : 'light'));
  };

  const menuItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, roles: ['superuseradmin', 'promoter', 'business head', 'manager', 'team lead', 'user', 'hradmin'] },
    { id: 'employees', label: 'Employees', icon: Users, roles: ['superuseradmin', 'hradmin'] },
    { id: 'attendance_report', label: 'Attendance Report', icon: Calendar, roles: ['superuseradmin', 'hradmin'] },
    { id: 'live_tracking', label: 'Live Tracking', icon: Map, roles: ['superuseradmin', 'hradmin'] },
    { id: 'leave_management', label: 'Leave Management', icon: ShieldAlert, roles: ['superuseradmin', 'hradmin'] },
    { id: 'payroll', label: 'Payroll', icon: Wallet, roles: ['superuseradmin', 'hradmin'] },
    { id: 'offer_letter', label: 'Offer Letter', icon: FileText, roles: ['superuseradmin', 'hradmin'] },
    { id: 'payslips', label: 'Payslip', icon: Receipt, roles: ['superuseradmin', 'hradmin'] },
    { id: 'company_assets', label: 'Company Assets', icon: HardDrive, roles: ['superuseradmin', 'hradmin'] },
    { id: 'settings', label: 'HR Settings', icon: Settings, roles: ['superuseradmin', 'hradmin'] },
    { id: 'my_portal', label: 'My Portal', icon: UserCheck, roles: ['promoter', 'business head', 'manager', 'team lead', 'user'] },
    { id: 'leads', label: 'Leads & Chats', icon: MessageSquare, roles: ['superuseradmin', 'promoter', 'business head', 'manager', 'team lead', 'user'] },
    { id: 'expenses', label: 'Ledger Expenses', icon: CreditCard, roles: ['superuseradmin'] }
  ];

  // Filter menu items by user role
  const allowedMenuItems = menuItems.filter(item => item.roles.includes(user.user_type));

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <Dashboard />;
      case 'employees':
        return <Employees />;
      case 'attendance_report':
        return <AttendanceReport />;
      case 'live_tracking':
        return <LiveTracking />;
      case 'leave_management':
        return <LeaveManagement />;
      case 'payroll':
        return <Payroll />;
      case 'offer_letter':
        return <OfferLetterPage />;
      case 'payslips':
        return <PayslipsPage />;
      case 'company_assets':
        return <CompanyAssets />;
      case 'settings':
        return <SettingsPage />;
      case 'my_portal':
        return <EmployeePortal />;
      case 'leads':
        return <LeadsBoard />;
      case 'expenses':
        return <Expenses />;
      default:
        return <Dashboard />;
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex transition-colors duration-200">
      {/* Sidebar Panel */}
      <aside className={`bg-slate-900 border-r border-slate-800 text-slate-300 w-64 flex flex-col fixed inset-y-0 left-0 z-30 transition-transform duration-300 transform ${sidebarOpen ? 'translate-x-0' : '-translate-x-0'} md:translate-x-0`}>
        {/* Logo */}
        <div className="p-6 border-b border-slate-800 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center font-bold text-white text-base">
              SH
            </div>
            <span className="font-extrabold text-white tracking-wider text-base">SEARCH HOMES</span>
          </div>
          <button onClick={() => setSidebarOpen(false)} className="md:hidden p-1.5 hover:bg-slate-800 rounded-lg text-slate-400">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation Menu */}
        <nav className="flex-1 px-4 py-6 space-y-2 overflow-y-auto scrollbar-none">
          {allowedMenuItems.map((item) => {
            const Icon = item.icon;
            const active = activeTab === item.id;
            return (
              <button
                key={item.id}
                onClick={() => setActiveTab(item.id)}
                className={`w-full flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-semibold transition-all ${active ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/15' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200'}`}
              >
                <Icon className="w-5 h-5 shrink-0" />
                <span>{item.label}</span>
              </button>
            );
          })}
        </nav>

        {/* Dark Mode Switch */}
        <div className="px-6 py-4 border-t border-slate-800 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-2 text-slate-400">
            {theme === 'dark' ? <Moon className="w-4 h-4" /> : <Sun className="w-4 h-4" />}
            <span className="text-xs font-semibold">Dark Mode</span>
          </div>
          <button
            onClick={toggleTheme}
            className={`w-10 h-5 rounded-full p-0.5 transition-colors duration-200 focus:outline-none ${theme === 'dark' ? 'bg-brand-500' : 'bg-slate-700'
              }`}
          >
            <div
              className={`w-4 h-4 rounded-full bg-white shadow-md transform duration-200 ${theme === 'dark' ? 'translate-x-5' : 'translate-x-0'
                }`}
            />
          </button>
        </div>

        {/* User Card */}
        <div className="p-4 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between gap-3 shrink-0">
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 font-bold shrink-0">
              <UserIcon className="w-4 h-4" />
            </div>
            <div className="min-w-0">
              <span className="block font-bold text-white text-xs truncate capitalize">{user.username}</span>
              <span className="block text-[10px] text-slate-500 capitalize tracking-wider font-semibold">{user.user_type}</span>
            </div>
          </div>
          <button
            onClick={logout}
            className="p-2 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-rose-400 transition-all shrink-0"
            title="Sign Out"
          >
            <LogOut className="w-5 h-5" />
          </button>
        </div>
      </aside>

      {/* Main Container Wrapper */}
      <div className="flex-1 md:pl-64 flex flex-col min-h-screen">

        {/* Header Console */}
        <header className="bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 h-16 px-6 flex items-center justify-between sticky top-0 z-20 transition-colors duration-200">
          <div className="flex items-center gap-4">
            <button onClick={() => setSidebarOpen(!sidebarOpen)} className="md:hidden p-1.5 hover:bg-slate-50 dark:hover:bg-slate-855 rounded-lg text-slate-600 dark:text-slate-300">
              <Menu className="w-6 h-6" />
            </button>
            <h2 className="text-slate-800 dark:text-white font-extrabold capitalize text-base tracking-tight">
              {activeTab.replace(/_/g, ' ')} Workspace
            </h2>
          </div>

          <div className="flex items-center gap-4 relative">

            {/* Notification Bell */}
            <button
              onClick={() => setShowNotificationDropdown(!showNotificationDropdown)}
              className="p-2 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-full text-slate-600 dark:text-slate-300 relative"
            >
              <Bell className="w-5 h-5" />
              {notifications.length > 0 && (
                <span className="absolute top-1 right-1 w-2.5 h-2.5 bg-rose-500 rounded-full"></span>
              )}
            </button>

            {/* Notification Dropdown */}
            {showNotificationDropdown && (
              <div className="absolute right-0 top-full mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl rounded-xl w-72 z-30 p-2 max-h-80 overflow-y-auto">
                <div className="flex items-center justify-between p-2 border-b border-slate-100 dark:border-slate-800 mb-2">
                  <span className="font-bold text-xs text-slate-600 dark:text-slate-300">System Activity Alerts</span>
                  <button onClick={() => setNotifications([])} className="text-xxs text-brand-500 font-bold hover:underline">Clear all</button>
                </div>
                {notifications.length === 0 ? (
                  <p className="text-center text-xs text-slate-400 dark:text-slate-500 py-6">No new alerts.</p>
                ) : (
                  notifications.map((notif, idx) => (
                    <div key={idx} className="p-2 border-b border-slate-50 dark:border-slate-800/50 hover:bg-slate-50 dark:hover:bg-slate-800/30 rounded-lg text-[10px] leading-relaxed mb-1 text-slate-700 dark:text-slate-300">
                      <p>{notif.text}</p>
                      <span className="text-[9px] text-slate-400 dark:text-slate-500 mt-1 block text-right">{notif.time}</span>
                    </div>
                  ))
                )}
              </div>
            )}

            <div className="w-px h-6 bg-slate-200 dark:bg-slate-800"></div>

            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-full bg-brand-50 dark:bg-brand-950/40 border border-brand-100 dark:border-brand-900 flex items-center justify-center font-bold text-brand-700 dark:text-brand-400 text-xs capitalize">
                {user.username ? user.username.charAt(0) : 'U'}
              </div>
              <span className="text-slate-700 dark:text-slate-300 font-bold text-xs hidden sm:block capitalize">{user.username}</span>
            </div>
          </div>
        </header>

        {/* Content Render Outlet */}
        <main className="flex-1 p-6 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-200">
          {renderContent()}
        </main>
      </div>
    </div>
  );
};

const App = () => {
  return (
    <AuthProvider>
      <AuthContext.Consumer>
        {({ user }) => (user ? <MainApp /> : <Login />)}
      </AuthContext.Consumer>
    </AuthProvider>
  );
};

export default App;
