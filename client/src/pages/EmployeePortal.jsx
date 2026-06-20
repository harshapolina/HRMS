import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Clock, Calendar, CheckCircle, AlertCircle, FileText, Send, User, ChevronRight, Moon, Sun } from 'lucide-react';
import io from 'socket.io-client';

const EmployeePortal = () => {
  const [activeTab, setActiveTab] = useState('attendance');
  const [currentTime, setCurrentTime] = useState(new Date());
  
  // Attendance states
  const [todayLog, setTodayLog] = useState({ status: 'Not Logged' });
  const [punchLogs, setPunchLogs] = useState([]);
  const [locationText, setLocationText] = useState('Location not acquired');
  const [coords, setCoords] = useState(null);
  const [trackingActive, setTrackingActive] = useState(false);

  // Leave states
  const [leaveBalances, setLeaveBalances] = useState({ sickRemaining: 12, casualRemaining: 12, paidRemaining: 15, unpaidUsed: 0 });
  const [myRequests, setMyRequests] = useState([]);
  const [leaveForm, setLeaveForm] = useState({ leaveType: 'Sick Leave', startDate: '', endDate: '', reason: '' });
  const [leaveMessage, setLeaveMessage] = useState(null);

  // Holidays state
  const [holidays, setHolidays] = useState([]);

  // Payslips state
  const [payslips, setPayslips] = useState([]);
  const [selectedPayslip, setSelectedPayslip] = useState(null);

  const fetchTodayLogRef = useRef(null);
  const fetchPunchLogsRef = useRef(null);
  const fetchLeaveDataRef = useRef(null);

  useEffect(() => {
    fetchTodayLogRef.current = fetchTodayLog;
    fetchPunchLogsRef.current = fetchPunchLogs;
    fetchLeaveDataRef.current = fetchLeaveData;
  });

  useEffect(() => {
    const socket = io();
    socket.on('leave_update', () => {
      console.log('Real-time leave update received. Reloading leave balances...');
      if (fetchLeaveDataRef.current) {
        fetchLeaveDataRef.current();
      }
    });

    socket.on('attendance_update', () => {
      console.log('Real-time attendance update received. Reloading attendance...');
      if (fetchTodayLogRef.current) fetchTodayLogRef.current();
      if (fetchPunchLogsRef.current) fetchPunchLogsRef.current();
    });

    return () => {
      socket.disconnect();
    };
  }, []);

  useEffect(() => {
    // Clock tick
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    acquireLocation();
    fetchTodayLog();
    fetchPunchLogs();
    fetchLeaveData();
    fetchHolidays();
    fetchPayslips();
  }, []);

  // Live Tracking effect: watch location in real-time if punched in and not out
  useEffect(() => {
    let watchId;
    if (todayLog.punchIn && !todayLog.punchOut) {
      setTrackingActive(true);
      
      if (navigator.geolocation) {
        let lastCoords = null;

        // Haversine formula to calculate distance in meters
        const getDistance = (lat1, lon1, lat2, lon2) => {
          const R = 6371e3; // Earth's radius in meters
          const phi1 = (lat1 * Math.PI) / 180;
          const phi2 = (lat2 * Math.PI) / 180;
          const deltaPhi = ((lat2 - lat1) * Math.PI) / 180;
          const deltaLambda = ((lon2 - lon1) * Math.PI) / 180;

          const a =
            Math.sin(deltaPhi / 2) * Math.sin(deltaPhi / 2) +
            Math.cos(phi1) * Math.cos(phi2) *
            Math.sin(deltaLambda / 2) * Math.sin(deltaLambda / 2);
          const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

          return R * c; // distance in meters
        };

        watchId = navigator.geolocation.watchPosition(
          async (position) => {
            const { latitude, longitude } = position.coords;
            
            // If we have previous coordinates, check if user moved at least 10 meters
            if (lastCoords) {
              const distance = getDistance(lastCoords.latitude, lastCoords.longitude, latitude, longitude);
              if (distance < 10) {
                console.log(`User is stationary (moved ${distance.toFixed(1)}m). Skipping update.`);
                return;
              }
            }

            try {
              await axios.post('/api/attendance/location', { latitude, longitude });
              console.log('Real-time location updated (moved):', latitude, longitude);
              lastCoords = { latitude, longitude };
            } catch (err) {
              console.error('Error sending real-time coordinates:', err);
            }
          },
          (error) => console.error('Geolocation watch error:', error),
          { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
      }
    } else {
      setTrackingActive(false);
    }

    return () => {
      if (watchId && navigator.geolocation) {
        navigator.geolocation.clearWatch(watchId);
      }
    };
  }, [todayLog]);

  const acquireLocation = () => {
    if (navigator.geolocation) {
      setLocationText('Acquiring location...');
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude } = position.coords;
          setCoords({ latitude, longitude });
          setLocationText(`GPS: ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`);
        },
        (error) => {
          setLocationText('Permission denied or GPS offline');
          console.error(error);
        },
        { enableHighAccuracy: true }
      );
    } else {
      setLocationText('Geolocation unsupported');
    }
  };

  const fetchTodayLog = async () => {
    try {
      const res = await axios.get('/api/attendance/today');
      setTodayLog(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchPunchLogs = async () => {
    try {
      const res = await axios.get('/api/attendance/my');
      setPunchLogs(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchLeaveData = async () => {
    try {
      const res = await axios.get('/api/leaves/my');
      setMyRequests(res.data.requests);
      setLeaveBalances(res.data.balances);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchHolidays = async () => {
    try {
      const res = await axios.get('/api/holidays');
      setHolidays(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchPayslips = async () => {
    try {
      const res = await axios.get('/api/payroll/my');
      setPayslips(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const handlePunchIn = async (e) => {
    e.preventDefault();
    const performPunchIn = async (punchCoords) => {
      try {
        await axios.post('/api/attendance/punch-in', punchCoords || {});
        fetchTodayLog();
        fetchPunchLogs();
      } catch (err) {
        alert(err.response?.data?.message || 'Punch in failed');
      }
    };

    if (!coords && navigator.geolocation) {
      setLocationText('Acquiring location...');
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          const { latitude, longitude } = position.coords;
          const newCoords = { latitude, longitude };
          setCoords(newCoords);
          setLocationText(`GPS: ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`);
          await performPunchIn(newCoords);
        },
        async (error) => {
          setLocationText('Permission denied or GPS offline');
          await performPunchIn({});
        },
        { enableHighAccuracy: true }
      );
    } else {
      await performPunchIn(coords);
    }
  };

  const handlePunchOut = async (e) => {
    e.preventDefault();
    const performPunchOut = async (punchCoords) => {
      try {
        await axios.post('/api/attendance/punch-out', punchCoords || {});
        fetchTodayLog();
        fetchPunchLogs();
      } catch (err) {
        alert(err.response?.data?.message || 'Punch out failed');
      }
    };

    if (!coords && navigator.geolocation) {
      setLocationText('Acquiring location...');
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          const { latitude, longitude } = position.coords;
          const newCoords = { latitude, longitude };
          setCoords(newCoords);
          setLocationText(`GPS: ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`);
          await performPunchOut(newCoords);
        },
        async (error) => {
          setLocationText('Permission denied or GPS offline');
          await performPunchOut({});
        },
        { enableHighAccuracy: true }
      );
    } else {
      await performPunchOut(coords);
    }
  };

  const handleLeaveSubmit = async (e) => {
    e.preventDefault();
    setLeaveMessage(null);
    try {
      await axios.post('/api/leaves', leaveForm);
      setLeaveMessage({ type: 'success', text: 'Leave request submitted successfully!' });
      setLeaveForm({ leaveType: 'Sick Leave', startDate: '', endDate: '', reason: '' });
      fetchLeaveData();
    } catch (err) {
      setLeaveMessage({ type: 'error', text: err.response?.data?.message || 'Submission failed' });
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-8">
      {/* Upper header */}
      <div className="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-wrap items-center justify-between gap-6">
        <div>
          <span className="text-xxs uppercase tracking-widest text-indigo-400 font-extrabold">Employee Portal</span>
          <h1 className="text-2xl font-black text-white mt-1">Personal Workstation</h1>
        </div>
        <div className="flex items-center gap-4">
          {trackingActive && (
            <div className="flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider animate-pulse">
              <span className="w-2.5 h-2.5 bg-emerald-400 rounded-full"></span>
              Live GPS Syncing
            </div>
          )}
          <button
            onClick={acquireLocation}
            className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold border border-slate-700 transition-all"
          >
            Refresh Coordinates
          </button>
        </div>
      </div>

      {/* Tabs list */}
      <div className="flex border-b border-slate-200 gap-4">
        {['attendance', 'leaves', 'payslips', 'holidays'].map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`pb-4 px-2 text-sm font-extrabold capitalize transition-all border-b-2 -mb-[2px] ${activeTab === tab ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-400 hover:text-slate-600'}`}
          >
            {tab}
          </button>
        ))}
      </div>

      {/* Workspace content output */}
      <div className="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm min-h-[50vh]">
        {activeTab === 'attendance' && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="flex flex-col items-center justify-center p-6 bg-slate-50 border border-slate-100 rounded-2xl text-center space-y-4">
              <div className="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-500">
                <Clock className="w-8 h-8" />
              </div>
              <div className="text-3xl font-black text-slate-800 tracking-tight">
                {currentTime.toLocaleTimeString('en-US', { hour12: false })}
              </div>
              <div className="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                <Calendar className="w-4 h-4" />
                {currentTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
              </div>

              <div className="w-full pt-4">
                {locationText && (
                  <p className="text-xxs text-slate-400 font-semibold mb-4 capitalize">
                    {locationText}
                  </p>
                )}

                {!todayLog.punchIn ? (
                  <form onSubmit={handlePunchIn} className="w-full">
                    <button
                      type="submit"
                      className="w-full py-4 bg-brand-500 hover:bg-brand-600 text-white rounded-xl font-bold shadow-lg shadow-brand-500/10 transition-all flex items-center justify-center gap-2"
                    >
                      Clock In Shift
                    </button>
                  </form>
                ) : !todayLog.punchOut ? (
                  <div className="space-y-4 w-full">
                    <form onSubmit={handlePunchOut} className="w-full">
                      <button
                        type="submit"
                        className="w-full py-4 bg-rose-500 hover:bg-rose-600 text-white rounded-xl font-bold shadow-lg shadow-rose-500/10 transition-all flex items-center justify-center gap-2"
                      >
                        Clock Out Shift
                      </button>
                    </form>
                    <span className="block text-xs font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 px-3 py-2 rounded-xl">
                      Shift Started: {new Date(todayLog.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })} ({todayLog.status})
                    </span>
                  </div>
                ) : (
                  <div className="bg-slate-100 border border-slate-200 p-4 rounded-xl text-slate-500 text-sm font-bold">
                    Shift Completed Today. Total Hours: {todayLog.totalHours} hrs.
                  </div>
                )}
              </div>
            </div>

            {/* Attendance Logs List */}
            <div className="space-y-4">
              <h3 className="text-slate-700 font-extrabold text-sm uppercase tracking-wider">Recent Punch History</h3>
              <div className="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                {punchLogs.map((log) => (
                  <div key={log._id} className="border border-slate-100 rounded-xl p-3 flex items-center justify-between text-xs hover:bg-slate-50 transition-all">
                    <div>
                      <span className="font-extrabold text-slate-800">{log.date}</span>
                      <div className="text-[10px] text-slate-400 mt-1 space-x-2">
                        {log.punchIn && (
                          <span>In: {new Date(log.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                        )}
                        {log.punchOut && (
                          <span>Out: {new Date(log.punchOut).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                        )}
                      </div>
                    </div>
                    <span className={`px-2 py-0.5 rounded-full text-xxs font-extrabold uppercase tracking-wider ${log.status === 'Present' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : log.status === 'Late' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}`}>
                      {log.status}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {activeTab === 'leaves' && (
          <div className="space-y-8 animate-fade-in">
            {/* Balances widgets */}
            <div className="grid grid-cols-3 gap-4">
              <div className="bg-slate-50 border border-slate-100 p-4 rounded-2xl text-center">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Sick Leave</span>
                <span className="text-2xl font-black text-indigo-600 block mt-1">{leaveBalances.sickRemaining}</span>
                <span className="text-[9px] text-slate-400 block mt-0.5">/ 12 days left</span>
              </div>
              <div className="bg-slate-50 border border-slate-100 p-4 rounded-2xl text-center">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Casual Leave</span>
                <span className="text-2xl font-black text-indigo-600 block mt-1">{leaveBalances.casualRemaining}</span>
                <span className="text-[9px] text-slate-400 block mt-0.5">/ 12 days left</span>
              </div>
              <div className="bg-slate-50 border border-slate-100 p-4 rounded-2xl text-center">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Paid Leave</span>
                <span className="text-2xl font-black text-indigo-600 block mt-1">{leaveBalances.paidRemaining}</span>
                <span className="text-[9px] text-slate-400 block mt-0.5">/ 15 days left</span>
              </div>
            </div>

            {/* Leave Apply Form */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div className="space-y-4">
                <h3 className="text-slate-700 font-extrabold text-sm uppercase tracking-wider">Request Time Off</h3>
                
                {leaveMessage && (
                  <div className={`p-3 rounded-xl text-xs font-bold flex items-center gap-2 ${leaveMessage.type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}`}>
                    {leaveMessage.type === 'success' ? <CheckCircle className="w-4 h-4" /> : <AlertCircle className="w-4 h-4" />}
                    {leaveMessage.text}
                  </div>
                )}

                <form onSubmit={handleLeaveSubmit} className="space-y-4">
                  <div>
                    <label className="block text-slate-500 text-xxs font-bold uppercase mb-1">Leave Type</label>
                    <select
                      value={leaveForm.leaveType}
                      onChange={(e) => setLeaveForm({ ...leaveForm, leaveType: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:border-brand-500 bg-transparent"
                    >
                      <option value="Sick Leave">Sick Leave</option>
                      <option value="Casual Leave">Casual Leave</option>
                      <option value="Paid Leave">Paid Leave</option>
                      <option value="Unpaid Leave">Unpaid Leave</option>
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-500 text-xxs font-bold uppercase mb-1">Start Date</label>
                      <input
                        type="date"
                        required
                        value={leaveForm.startDate}
                        onChange={(e) => setLeaveForm({ ...leaveForm, startDate: e.target.value })}
                        className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500"
                      />
                    </div>
                    <div>
                      <label className="block text-slate-500 text-xxs font-bold uppercase mb-1">End Date</label>
                      <input
                        type="date"
                        required
                        value={leaveForm.endDate}
                        onChange={(e) => setLeaveForm({ ...leaveForm, endDate: e.target.value })}
                        className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-slate-500 text-xxs font-bold uppercase mb-1">Reason / Explanation</label>
                    <textarea
                      required
                      rows="3"
                      value={leaveForm.reason}
                      onChange={(e) => setLeaveForm({ ...leaveForm, reason: e.target.value })}
                      placeholder="Specify your reason..."
                      className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-brand-500"
                    ></textarea>
                  </div>

                  <button
                    type="submit"
                    className="px-6 py-2.5 bg-brand-500 hover:bg-brand-600 text-white rounded-xl text-xs font-bold shadow-lg shadow-brand-500/10 transition-all flex items-center gap-2"
                  >
                    <Send className="w-3.5 h-3.5" /> Submit Request
                  </button>
                </form>
              </div>

              {/* History List */}
              <div className="space-y-4">
                <h3 className="text-slate-700 font-extrabold text-sm uppercase tracking-wider">Leave Applications</h3>
                <div className="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                  {myRequests.map((reqItem) => (
                    <div key={reqItem._id} className="border border-slate-100 rounded-xl p-3 flex flex-col gap-2 hover:bg-slate-50 transition-all text-xs">
                      <div className="flex items-center justify-between">
                        <span className="font-extrabold text-slate-800">{reqItem.leaveType}</span>
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${reqItem.status === 'Approved' ? 'bg-emerald-50 text-emerald-700' : reqItem.status === 'Rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'}`}>
                          {reqItem.status}
                        </span>
                      </div>
                      <div className="text-[10px] text-slate-400">
                        {new Date(reqItem.startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - {new Date(reqItem.endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ({reqItem.leaveDays} days)
                      </div>
                      {reqItem.adminRemarks && (
                        <p className="text-[10px] bg-slate-100 p-2 rounded-lg text-slate-500">
                          <strong>Remarks:</strong> {reqItem.adminRemarks}
                        </p>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'payslips' && (
          <div className="space-y-6">
            <h3 className="text-slate-700 font-extrabold text-sm uppercase tracking-wider">Salary Payslips</h3>
            
            {payslips.length === 0 ? (
              <p className="text-center py-10 text-slate-400 text-xs">No payroll slips processed for your account.</p>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {payslips.map(slip => (
                  <button
                    key={slip._id}
                    onClick={() => setSelectedPayslip(slip)}
                    className="p-4 border border-slate-100 rounded-2xl flex items-center justify-between hover:bg-brand-50/20 hover:border-brand-100 text-left transition-all group"
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:bg-brand-100/50 transition-colors">
                        <FileText className="w-5 h-5" />
                      </div>
                      <div>
                        <span className="block font-extrabold text-slate-800 text-xs">{slip.monthYear}</span>
                        <span className="block text-[10px] text-slate-400 mt-0.5">Net Payout: ₹{slip.netSalary.toLocaleString()}</span>
                      </div>
                    </div>
                    <ChevronRight className="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {activeTab === 'holidays' && (
          <div className="space-y-6">
            <h3 className="text-slate-700 font-extrabold text-sm uppercase tracking-wider">Company Holidays Calendar</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {holidays.map(h => (
                <div key={h._id} className="p-4 border border-slate-100 rounded-2xl flex items-start gap-3 hover:bg-slate-50 transition-all">
                  <div className="bg-indigo-50 border border-indigo-100 text-indigo-600 font-bold p-2.5 rounded-xl text-center shrink-0 w-12">
                    <span className="block text-[10px] uppercase">{new Date(h.date).toLocaleDateString('en-US', { month: 'short' })}</span>
                    <span className="block text-base leading-none mt-1">{new Date(h.date).getDate()}</span>
                  </div>
                  <div>
                    <h4 className="font-extrabold text-slate-800 text-xs">{h.reason}</h4>
                    <span className="text-[10px] text-slate-400 mt-1 block">
                      {new Date(h.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric' })}
                    </span>
                  </div>
                </div>
              ))}
              {holidays.length === 0 && (
                <p className="text-center col-span-2 py-10 text-slate-400 text-xs">No upcoming holidays scheduled.</p>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Interactive Payslip PDF Modal */}
      {selectedPayslip && (
        <div className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm flex items-center justify-center p-6 z-50 animate-fade-in">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full p-6 shadow-2xl overflow-y-auto max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
              <h3 className="font-extrabold text-slate-800 text-sm uppercase">Payslip Document</h3>
              <button
                onClick={() => setSelectedPayslip(null)}
                className="px-3 py-1.5 hover:bg-slate-50 border border-slate-200 text-slate-500 rounded-lg text-xs font-bold transition-all"
              >
                Close
              </button>
            </div>

            {/* Payslip sheet layout */}
            <div className="border border-slate-300 p-6 rounded-2xl text-xs space-y-6 text-slate-700 bg-slate-50/50">
              <div className="text-center border-b border-slate-200 pb-4">
                <h2 className="font-extrabold text-slate-800 text-sm uppercase">Search Homes India Pvt Ltd</h2>
                <p className="text-[10px] text-slate-400 mt-0.5">Salary Statement for {selectedPayslip.monthYear}</p>
              </div>

              {/* Employee metadata */}
              <div className="grid grid-cols-2 gap-4 border-b border-slate-200 pb-4 font-semibold text-[11px]">
                <div>
                  <p className="text-slate-400">Employee Name</p>
                  <p className="text-slate-800 font-bold capitalize mt-0.5">{selectedPayslip.user?.username || 'Employee'}</p>
                </div>
                <div>
                  <p className="text-slate-400">Employee ID</p>
                  <p className="text-slate-800 font-bold mt-0.5">{selectedPayslip.user?.employee_id || '-'}</p>
                </div>
                <div>
                  <p className="text-slate-400">Designation</p>
                  <p className="text-slate-800 font-bold capitalize mt-0.5">{selectedPayslip.user?.user_type || 'User'}</p>
                </div>
                <div>
                  <p className="text-slate-400">Paid Days</p>
                  <p className="text-slate-800 font-bold mt-0.5">{selectedPayslip.payslipData?.paidDays} / {selectedPayslip.payslipData?.totalDays} days</p>
                </div>
              </div>

              {/* Detailed Breakdown */}
              <div className="grid grid-cols-2 gap-6">
                <div>
                  <h4 className="font-extrabold text-indigo-700 uppercase tracking-wider text-[10px] border-b border-slate-200 pb-1.5 mb-3">Earnings</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>Basic Salary (50%)</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.earnings?.basic?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>House Rent Allowance (HRA)</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.earnings?.hra?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Conveyance Allowance</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.earnings?.conveyance?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Special Allowance</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.earnings?.special?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>PF Employer Part</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.earnings?.pfEmployer?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between border-t border-slate-200 pt-2 font-bold text-slate-800">
                      <span>Gross Payout</span>
                      <span>₹{selectedPayslip.payslipData?.earnings?.gross?.toLocaleString()}</span>
                    </div>
                  </div>
                </div>

                <div>
                  <h4 className="font-extrabold text-rose-700 uppercase tracking-wider text-[10px] border-b border-slate-200 pb-1.5 mb-3">Deductions</h4>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>PF (Employee Part)</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.deductions?.pfEmployee?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Professional Tax (PT)</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.deductions?.professionalTax?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Medical Benefit</span>
                      <span className="font-bold">₹{selectedPayslip.payslipData?.deductions?.medical?.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between border-t border-slate-200 pt-2 font-bold text-slate-800">
                      <span>Total Deductions</span>
                      <span>₹{selectedPayslip.payslipData?.deductions?.total?.toLocaleString()}</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Net Payout Wrap */}
              <div className="border-t border-slate-300 pt-4 flex items-center justify-between bg-indigo-50 border border-indigo-100 p-4 rounded-xl">
                <div>
                  <span className="text-[10px] font-bold text-indigo-400 uppercase tracking-widest block">Net Take Home Pay</span>
                  <span className="text-xl font-black text-indigo-700 mt-1 block">₹{selectedPayslip.netSalary.toLocaleString()}</span>
                </div>
                <button
                  onClick={() => window.print()}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shrink-0 shadow-md shadow-indigo-600/15"
                >
                  Print Payslip
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default EmployeePortal;
