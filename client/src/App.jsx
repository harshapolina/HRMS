import React, { useState, useContext, useEffect, useRef } from 'react';
import axios from 'axios';
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
import EOIPage from './pages/EOIPage';
import PropertyBookings from './pages/PropertyBookings';
import NoticeAlerts from './pages/NoticeAlerts';
import GlobalConfig from './pages/GlobalConfig';
import CronTracker from './pages/CronTracker';
import IncentiveTracker from './pages/IncentiveTracker';
import PaymentTracker from './pages/PaymentTracker';

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
  Settings,
  ChevronLeft,
  ChevronRight,
  PanelLeftClose,
  PanelLeftOpen,
  ClipboardList,
  Key,
  Award,
  DollarSign,
  Activity,
  Megaphone,
  Sliders
} from 'lucide-react';
import io from 'socket.io-client';

const MainApp = () => {
  const { user, logout } = useContext(AuthContext);
  const [activeTab, setActiveTab] = useState('dashboard');
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [showNotificationDropdown, setShowNotificationDropdown] = useState(false);
  const [pageKey, setPageKey] = useState(0);
  const [theme, setTheme] = useState(localStorage.getItem('theme') || 'light');
  const [pendingNotice, setPendingNotice] = useState(null);
  const notifRef = useRef(null);

  // Close notification dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (notifRef.current && !notifRef.current.contains(e.target)) {
        setShowNotificationDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (user && user.tablename) {
      checkPendingNotice();
    }
  }, [user]);

  const checkPendingNotice = async () => {
    try {
      const res = await axios.get('/api/notices/pending');
      setPendingNotice(res.data || null);
    } catch (err) {
      console.error('Error checking pending notices', err);
    }
  };

  const handleAcceptNotice = async () => {
    if (!pendingNotice) return;
    try {
      await axios.post('/api/notices/accept', { alert_id: pendingNotice._id });
      setPendingNotice(null);
      checkPendingNotice();
    } catch (err) {
      alert('Accept failed: ' + err.message);
    }
  };

  useEffect(() => {
    const socket = io('http://localhost:5001');

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
    { id: 'expenses', label: 'Ledger Expenses', icon: CreditCard, roles: ['superuseradmin'] },
    { id: 'eois', label: 'EOI List', icon: ClipboardList, roles: ['superuseradmin', 'promoter', 'business head', 'manager', 'team lead', 'user', 'hradmin'] },
    { id: 'bookings', label: 'Property Bookings', icon: Key, roles: ['superuseradmin', 'promoter', 'business head', 'manager', 'team lead', 'user', 'hradmin'] },
    { id: 'incentive_tracker', label: 'Incentive target Payouts', icon: Award, roles: ['superuseradmin', 'hradmin'] },
    { id: 'payment_tracker', label: 'Accounts Milestone', icon: DollarSign, roles: ['superuseradmin'] },
    { id: 'cron_tracker', label: 'Leads Automation Cron', icon: Activity, roles: ['superuseradmin'] },
    { id: 'create_notice', label: 'Notice Board Publisher', icon: Megaphone, roles: ['superuseradmin'] },
    { id: 'global_config', label: 'Integrations Config', icon: Sliders, roles: ['superuseradmin'] }
  ];

  const allowedMenuItems = menuItems.filter(item => item.roles.includes(user.user_type));

  const handleTabChange = (tabId) => {
    setActiveTab(tabId);
    setPageKey(prev => prev + 1);
    // Close mobile sidebar on nav
    if (window.innerWidth < 768) setSidebarOpen(false);
  };

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard': return <Dashboard />;
      case 'employees': return <Employees />;
      case 'attendance_report': return <AttendanceReport />;
      case 'live_tracking': return <LiveTracking />;
      case 'leave_management': return <LeaveManagement />;
      case 'payroll': return <Payroll />;
      case 'offer_letter': return <OfferLetterPage />;
      case 'payslips': return <PayslipsPage />;
      case 'company_assets': return <CompanyAssets />;
      case 'settings': return <SettingsPage />;
      case 'my_portal': return <EmployeePortal />;
      case 'leads': return <LeadsBoard />;
      case 'expenses': return <Expenses />;
      case 'eois': return <EOIPage />;
      case 'bookings': return <PropertyBookings />;
      case 'incentive_tracker': return <IncentiveTracker />;
      case 'payment_tracker': return <PaymentTracker />;
      case 'cron_tracker': return <CronTracker />;
      case 'create_notice': return <NoticeAlerts />;
      case 'global_config': return <GlobalConfig />;
      default: return <Dashboard />;
    }
  };

  const activeLabel = allowedMenuItems.find(item => item.id === activeTab)?.label
    || activeTab.replace(/_/g, ' ');

  const sidebarWidth = sidebarCollapsed ? 'w-[72px]' : 'w-64';
  const mainPadding = sidebarCollapsed ? 'md:pl-[72px]' : 'md:pl-64';

  return (
    <div className="min-h-screen bg-surface-soft flex">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="md:hidden fixed inset-0 bg-surface-dark/30 backdrop-blur-sm z-20 animate-fade-in"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`
          sidebar bg-canvas border-r border-hairline flex flex-col fixed inset-y-0 left-0 z-30
          ${sidebarWidth}
          ${sidebarCollapsed ? 'sidebar-collapsed' : 'sidebar-expanded'}
          ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
          md:translate-x-0
        `}
      >
        {/* Collapse toggle — desktop only */}
        <button
          id="collapse-sidebar-btn"
          onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
          className="collapse-toggle hidden md:flex"
          title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {sidebarCollapsed
            ? <ChevronRight className="w-3.5 h-3.5" />
            : <ChevronLeft className="w-3.5 h-3.5" />
          }
        </button>

        {/* Logo area */}
        <div className="h-16 px-4 border-b border-hairline flex items-center justify-between shrink-0 overflow-hidden">
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-8 h-8 rounded-md bg-primary flex items-center justify-center font-semibold text-white text-sm shrink-0">
              SH
            </div>
            <span className="sidebar-label font-display font-semibold text-ink text-sm tracking-tight">
              Search Homes
            </span>
          </div>
          <button id="close-sidebar-btn" onClick={() => setSidebarOpen(false)} className="md:hidden btn-icon w-8 h-8">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 p-3 overflow-y-auto">
          <div className="nav-pill-group flex-col w-full rounded-lg p-1.5 gap-0.5">
          {allowedMenuItems.map((item, index) => {
            const Icon = item.icon;
            const active = activeTab === item.id;
            return (
              <button
                id={`nav-item-${item.id}`}
                key={item.id}
                onClick={() => handleTabChange(item.id)}
                className={`nav-item group ${active ? 'nav-item-active' : ''}`}
                title={sidebarCollapsed ? item.label : ''}
                style={{ animationDelay: `${index * 30}ms` }}
              >
                <Icon className="w-4 h-4 shrink-0" />
                <span className="sidebar-label">{item.label}</span>

                {/* Tooltip when collapsed */}
                {sidebarCollapsed && (
                  <span className="sidebar-tooltip">{item.label}</span>
                )}
              </button>
            );
            })}
          </div>
        </nav>

        {/* User footer */}
        <div className="p-4 border-t border-hairline flex items-center justify-between gap-3 shrink-0 overflow-hidden">
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-9 h-9 rounded-full bg-surface-card flex items-center justify-center text-ink font-semibold shrink-0 text-xs">
              {user.username ? user.username.charAt(0).toUpperCase() : 'U'}
            </div>
            <div className="sidebar-label min-w-0">
              <span className="block font-semibold text-ink text-xs truncate capitalize">{user.username}</span>
              <span className="block text-[11px] text-muted capitalize">{user.user_type}</span>
            </div>
          </div>
          <button
            id="signout-btn"
            onClick={logout}
            className="btn-icon shrink-0 text-muted w-8 h-8"
            title="Sign Out"
          >
            <LogOut className="w-4 h-4" />
          </button>
        </div>
      </aside>

      {/* Main area */}
      <div className={`main-area flex-1 ${mainPadding} flex flex-col min-h-screen`}>
        <header className="bg-canvas border-b border-hairline h-16 px-6 flex items-center justify-between sticky top-0 z-20">
          <div className="flex items-center gap-4">
            <button id="toggle-sidebar-btn" onClick={() => setSidebarOpen(!sidebarOpen)} className="md:hidden btn-icon">
              <Menu className="w-5 h-5" />
            </button>
            <h2 className="font-display text-title-md text-ink capitalize animate-fade-in">
              {activeLabel}
            </h2>
          </div>

          <div className="flex items-center gap-4 relative" ref={notifRef}>
            <button
              id="notification-dropdown-btn"
              onClick={() => setShowNotificationDropdown(!showNotificationDropdown)}
              className="btn-icon relative"
            >
              <Bell className="w-4 h-4" />
              {notifications.length > 0 && (
                <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-error rounded-full animate-pulse-dot" />
              )}
            </button>

            {showNotificationDropdown && (
              <div className="notification-enter absolute right-0 top-full mt-2 bg-canvas border border-hairline shadow-elevated rounded-lg w-72 z-30 p-2 max-h-80 overflow-y-auto">
                <div className="flex items-center justify-between p-2 border-b border-hairline-soft mb-2">
                  <span className="font-semibold text-xs text-body">Activity Alerts</span>
                  <button id="clear-notifications-btn" onClick={() => setNotifications([])} className="text-xs text-ink font-semibold underline">Clear all</button>
                </div>
                {notifications.length === 0 ? (
                  <p className="text-center text-xs text-muted py-6">No new alerts.</p>
                ) : (
                  notifications.map((notif, idx) => (
                    <div key={idx} className="p-2 border-b border-hairline-soft rounded-md text-xs leading-relaxed mb-1 text-body animate-fade-in-up" style={{ animationDelay: `${idx * 50}ms` }}>
                      <p>{notif.text}</p>
                      <span className="text-[10px] text-muted-soft mt-1 block text-right">{notif.time}</span>
                    </div>
                  ))
                )}
              </div>
            )}

            <div className="w-px h-6 bg-hairline" />

            <div className="flex items-center gap-2">
              <div className="avatar-circle w-8 h-8 capitalize">
                {user.username ? user.username.charAt(0) : 'U'}
              </div>
              <span className="text-ink font-semibold text-xs hidden sm:block capitalize">{user.username}</span>
            </div>
          </div>
        </header>

        <main className="flex-1 p-6 overflow-y-auto">
          <div className="max-w-content mx-auto w-full page-transition" key={pageKey}>
            {renderContent()}
          </div>
        </main>
      </div>
      {/* Global Blocking Notice Payout / Alert Modal */}
      {pendingNotice && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-6 z-[9999]">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-8 space-y-6">
            <div className="flex items-center gap-3 text-rose-500 border-b border-slate-100 dark:border-slate-800 pb-4">
              <Megaphone className="w-8 h-8 animate-bounce" />
              <div>
                <h3 className="text-slate-800 dark:text-white font-extrabold text-lg uppercase tracking-wider">Mandatory Announcement</h3>
                <p className="text-[10px] text-slate-400 mt-0.5">Please accept to continue</p>
              </div>
            </div>
            
            <div 
              className="text-slate-700 dark:text-slate-300 text-xs leading-relaxed max-h-60 overflow-y-auto pr-2 prose dark:prose-invert"
              dangerouslySetInnerHTML={{ __html: pendingNotice.alert_message }}
            />

            <div className="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
              <button
                onClick={handleAcceptNotice}
                className="px-6 py-3 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold shadow-lg shadow-brand-500/20 transition-all w-full text-center"
              >
                I Have Read & Accept
              </button>
            </div>
          </div>
        </div>
      )}
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
