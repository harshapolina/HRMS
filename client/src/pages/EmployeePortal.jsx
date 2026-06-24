import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Clock, Calendar, CheckCircle, AlertCircle, FileText, Send, User, ChevronRight, Moon, Sun, X, Download } from 'lucide-react';
import io from 'socket.io-client';

const EmployeePortal = () => {
  const [activeTab, setActiveTab] = useState('attendance');
  const [currentTime, setCurrentTime] = useState(new Date());

  const getSundaysCountForMonth = (monthYearStr) => {
    if (!monthYearStr) return 4;
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const parts = monthYearStr.split(' ');
    if (parts.length !== 2) return 4;
    const monthIndex = monthNames.indexOf(parts[0]);
    const year = parseInt(parts[1], 10);
    if (monthIndex === -1 || isNaN(year)) return 4;
    
    let count = 0;
    const date = new Date(year, monthIndex, 1);
    while (date.getMonth() === monthIndex) {
      if (date.getDay() === 0) count++;
      date.setDate(date.getDate() + 1);
    }
    return count;
  };
  
  // Attendance states
  const [todayLog, setTodayLog] = useState({ status: 'Not Logged' });
  const [punchLogs, setPunchLogs] = useState([]);
  const [locationText, setLocationText] = useState('Location not acquired');
  const [coords, setCoords] = useState(null);
  const [trackingActive, setTrackingActive] = useState(false);
  const lastCoordsRef = useRef(null);

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
    
    if (!todayLog.punchIn || todayLog.punchOut) {
      lastCoordsRef.current = null;
      setTrackingActive(false);
      return;
    }

    setTrackingActive(true);
    
    if (navigator.geolocation) {
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
          const { latitude, longitude, accuracy } = position.coords;
          
          // Filter out low-accuracy readings (e.g., > 40 meters) to avoid false updates due to indoors GPS drift
          if (accuracy && accuracy > 40) {
            console.log(`GPS accuracy is poor (${accuracy.toFixed(1)}m). Skipping update.`);
            return;
          }

          // If we have previous coordinates, check if user moved at least 25 meters
          if (lastCoordsRef.current) {
            const distance = getDistance(lastCoordsRef.current.latitude, lastCoordsRef.current.longitude, latitude, longitude);
            if (distance < 25) {
              console.log(`User is stationary (moved ${distance.toFixed(1)}m). Skipping update.`);
              return;
            }
          }

          try {
            await axios.post('/api/attendance/location', { latitude, longitude });
            console.log('Real-time location updated (moved):', latitude, longitude);
            lastCoordsRef.current = { latitude, longitude };
          } catch (err) {
            console.error('Error sending real-time coordinates:', err);
          }
        },
        (error) => console.error('Geolocation watch error:', error),
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
      );
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
      if (res.data && res.data.locationHistory && res.data.locationHistory.length > 0) {
        const lastPoint = res.data.locationHistory[res.data.locationHistory.length - 1];
        lastCoordsRef.current = { latitude: lastPoint.latitude, longitude: lastPoint.longitude };
      }
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
        if (punchCoords && punchCoords.latitude && punchCoords.longitude) {
          lastCoordsRef.current = { latitude: punchCoords.latitude, longitude: punchCoords.longitude };
        }
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
    <div className="page-shell max-w-4xl mx-auto">

      <div className="tab-bar">
        {['attendance', 'leaves', 'payslips', 'holidays'].map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`tab-bar-item ${activeTab === tab ? 'tab-bar-item-active' : 'tab-bar-item-inactive'}`}
          >
            {tab}
          </button>
        ))}
      </div>

      <div className="card p-6 min-h-[50vh]">
        {activeTab === 'attendance' && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="flex flex-col items-center justify-center p-6 bg-surface-soft border border-hairline-soft rounded-lg text-center space-y-4">
              {trackingActive && (
                <div className="badge-success uppercase tracking-wider gap-2 px-3 py-1.5 rounded-full text-xs">
                  <span className="w-2 h-2 bg-success rounded-full inline-block animate-pulse" />
                  Live GPS Syncing
                </div>
              )}
              <div className="w-16 h-16 rounded-lg bg-surface-soft border border-hairline-soft flex items-center justify-center text-accent">
                <Clock className="w-8 h-8" />
              </div>
              <div className="text-3xl font-semibold text-ink tracking-tight">
                {currentTime.toLocaleTimeString('en-US', { hour12: false })}
              </div>
              <div className="text-xs font-semibold text-muted uppercase tracking-widest flex items-center gap-1.5">
                <Calendar className="w-4 h-4" />
                {currentTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
              </div>

              <div className="w-full pt-4">
                {locationText && (
                  <p className="text-xxs text-muted font-semibold mb-3 capitalize">
                    {locationText}
                  </p>
                )}

                <div className="flex gap-2 mb-4">
                  <button type="button" onClick={acquireLocation} className="btn-secondary btn-sm w-full">
                    Refresh Coordinates
                  </button>
                </div>

                {!todayLog.punchIn ? (
                  <form onSubmit={handlePunchIn} className="w-full">
                    <button type="submit" className="btn-primary w-full h-12">
                      Clock In Shift
                    </button>
                  </form>
                ) : !todayLog.punchOut ? (
                  <div className="space-y-4 w-full">
                    <form onSubmit={handlePunchOut} className="w-full">
                      <button type="submit" className="btn-secondary w-full h-12 border-error/30 text-error">
                        Clock Out Shift
                      </button>
                    </form>
                    <span className="block text-xs font-semibold badge-success px-3 py-2">
                      Shift Started: {new Date(todayLog.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })} ({todayLog.status})
                    </span>
                  </div>
                ) : (
                  <div className="bg-surface-soft border border-hairline p-4 rounded-lg text-muted text-sm font-semibold">
                    Shift Completed Today. Total Hours: {todayLog.totalHours} hrs.
                  </div>
                )}
              </div>
            </div>

            {/* Attendance Logs List */}
            <div className="space-y-4">
              <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Recent Punch History</h3>
              <div className="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                {punchLogs.map((log) => (
                  <div key={log._id} className="border border-hairline-soft rounded-lg p-3 flex items-center justify-between text-xs hover:bg-surface-soft transition-all">
                    <div>
                      <span className="font-semibold text-ink">{log.date}</span>
                      <div className="text-[10px] text-muted mt-1 space-x-2">
                        {log.punchIn && (
                          <span>In: {new Date(log.punchIn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                        )}
                        {log.punchOut && (
                          <span>Out: {new Date(log.punchOut).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                        )}
                      </div>
                    </div>
                    <span className={`${log.status === 'Present' ? 'badge-success' : log.status === 'Late' ? 'badge-warning' : 'badge-error'} uppercase text-[10px]`}>
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
              <div className="bg-surface-soft border border-hairline-soft p-4 rounded-lg text-center">
                <span className="text-[10px] font-semibold text-muted uppercase tracking-widest block">Sick Leave</span>
                <span className="text-2xl font-semibold text-accent block mt-1">{leaveBalances.sickRemaining}</span>
                <span className="text-[9px] text-muted block mt-0.5">/ 12 days left</span>
              </div>
              <div className="bg-surface-soft border border-hairline-soft p-4 rounded-lg text-center">
                <span className="text-[10px] font-semibold text-muted uppercase tracking-widest block">Casual Leave</span>
                <span className="text-2xl font-semibold text-accent block mt-1">{leaveBalances.casualRemaining}</span>
                <span className="text-[9px] text-muted block mt-0.5">/ 12 days left</span>
              </div>
              <div className="bg-surface-soft border border-hairline-soft p-4 rounded-lg text-center">
                <span className="text-[10px] font-semibold text-muted uppercase tracking-widest block">Paid Leave</span>
                <span className="text-2xl font-semibold text-accent block mt-1">{leaveBalances.paidRemaining}</span>
                <span className="text-[9px] text-muted block mt-0.5">/ 15 days left</span>
              </div>
            </div>

            {/* Leave Apply Form */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div className="space-y-4">
                <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Request Time Off</h3>
                
                {leaveMessage && (
                  <div className={`p-3 rounded-md text-xs font-semibold flex items-center gap-2 ${leaveMessage.type === 'success' ? 'alert-success' : 'alert-error'}`}>
                    {leaveMessage.type === 'success' ? <CheckCircle className="w-4 h-4" /> : <AlertCircle className="w-4 h-4" />}
                    {leaveMessage.text}
                  </div>
                )}

                <form onSubmit={handleLeaveSubmit} className="space-y-4">
                  <div>
                    <label className="block text-muted text-xxs font-semibold uppercase mb-1">Leave Type</label>
                    <select
                      value={leaveForm.leaveType}
                      onChange={(e) => setLeaveForm({ ...leaveForm, leaveType: e.target.value })}
                      className="w-full border border-hairline rounded-lg px-3 py-2.5 text-xs focus:outline-none focus:border-ink bg-transparent"
                    >
                      <option value="Sick Leave">Sick Leave</option>
                      <option value="Casual Leave">Casual Leave</option>
                      <option value="Paid Leave">Paid Leave</option>
                      <option value="Unpaid Leave">Unpaid Leave</option>
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-muted text-xxs font-semibold uppercase mb-1">Start Date</label>
                      <input
                        type="date"
                        required
                        value={leaveForm.startDate}
                        onChange={(e) => setLeaveForm({ ...leaveForm, startDate: e.target.value })}
                        className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink"
                      />
                    </div>
                    <div>
                      <label className="block text-muted text-xxs font-semibold uppercase mb-1">End Date</label>
                      <input
                        type="date"
                        required
                        value={leaveForm.endDate}
                        onChange={(e) => setLeaveForm({ ...leaveForm, endDate: e.target.value })}
                        className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-muted text-xxs font-semibold uppercase mb-1">Reason / Explanation</label>
                    <textarea
                      required
                      rows="3"
                      value={leaveForm.reason}
                      onChange={(e) => setLeaveForm({ ...leaveForm, reason: e.target.value })}
                      placeholder="Specify your reason..."
                      className="w-full border border-hairline rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-ink"
                    ></textarea>
                  </div>

                  <button
                    type="submit"
                    className="px-6 py-2.5 bg-primary hover:bg-primary-active text-white rounded-lg text-xs font-semibold shadow-lg shadow-card transition-all flex items-center gap-2"
                  >
                    <Send className="w-3.5 h-3.5" /> Submit Request
                  </button>
                </form>
              </div>

              {/* History List */}
              <div className="space-y-4">
                <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Leave Applications</h3>
                <div className="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                  {myRequests.map((reqItem) => (
                    <div key={reqItem._id} className="border border-hairline-soft rounded-lg p-3 flex flex-col gap-2 hover:bg-surface-soft transition-all text-xs">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-ink">{reqItem.leaveType}</span>
                        <span className={`${reqItem.status === 'Approved' ? 'badge-success' : reqItem.status === 'Rejected' ? 'badge-error' : 'badge-warning'} uppercase text-[10px]`}>
                          {reqItem.status}
                        </span>
                      </div>
                      <div className="text-[10px] text-muted">
                        {new Date(reqItem.startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - {new Date(reqItem.endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ({reqItem.leaveDays} days)
                      </div>
                      {reqItem.adminRemarks && (
                        <p className="text-[10px] bg-surface-soft p-2 rounded-lg text-muted">
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
            <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Salary Payslips</h3>
            
            {payslips.length === 0 ? (
              <p className="text-center py-10 text-muted text-xs">No payroll slips processed for your account.</p>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {payslips.map(slip => (
                  <button
                    key={slip._id}
                    onClick={() => setSelectedPayslip(slip)}
                    className="p-4 border border-hairline-soft rounded-lg flex items-center justify-between hover:bg-surface-soft/20 hover:border-hairline text-left transition-all group"
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-lg bg-surface-soft flex items-center justify-center text-accent active:bg-surface-card transition-colors">
                        <FileText className="w-5 h-5" />
                      </div>
                      <div>
                        <span className="block font-semibold text-ink text-xs">{slip.monthYear}</span>
                        <span className="block text-[10px] text-muted mt-0.5">Net Payout: ₹{slip.netSalary.toLocaleString()}</span>
                      </div>
                    </div>
                    <ChevronRight className="w-4 h-4 text-muted group-hover:translate-x-0.5 transition-transform" />
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {activeTab === 'holidays' && (
          <div className="space-y-6">
            <h3 className="text-body font-semibold text-sm uppercase tracking-wider">Company Holidays Calendar</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {holidays.map(h => (
                <div key={h._id} className="p-4 border border-hairline-soft rounded-lg flex items-start gap-3 hover:bg-surface-soft transition-all">
                  <div className="bg-surface-soft border border-hairline-soft text-accent font-semibold p-2.5 rounded-lg text-center shrink-0 w-12">
                    <span className="block text-[10px] uppercase">{new Date(h.date).toLocaleDateString('en-US', { month: 'short' })}</span>
                    <span className="block text-base leading-none mt-1">{new Date(h.date).getDate()}</span>
                  </div>
                  <div>
                    <h4 className="font-semibold text-ink text-xs">{h.reason}</h4>
                    <span className="text-[10px] text-muted mt-1 block">
                      {new Date(h.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric' })}
                    </span>
                  </div>
                </div>
              ))}
              {holidays.length === 0 && (
                <p className="text-center col-span-2 py-10 text-muted text-xs">No upcoming holidays scheduled.</p>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Interactive Payslip PDF Modal */}
      {selectedPayslip && (() => {
        const sundaysVal = selectedPayslip.payslipData?.sundaysCount ?? getSundaysCountForMonth(selectedPayslip.monthYear);
        const workingDaysVal = selectedPayslip.payslipData?.workingDays ?? (selectedPayslip.totalDays - sundaysVal);
        const customDeductionsSum = (selectedPayslip.payslipData?.deductions?.custom || []).reduce((acc, d) => acc + (d.amount || 0), 0);
        const lopDeductionVal = selectedPayslip.payslipData?.deductions?.lopDeduction ?? (
          (selectedPayslip.payslipData?.deductions?.total ?? 0) -
          (selectedPayslip.payslipData?.deductions?.pfEmployee ?? 0) -
          (selectedPayslip.payslipData?.deductions?.professionalTax ?? 0) -
          (selectedPayslip.payslipData?.deductions?.medical ?? 0) -
          customDeductionsSum
        );

        return (
          <div className="modal-overlay" onClick={() => setSelectedPayslip(null)}>
            <div className="modal-popup max-w-2xl" onClick={(e) => e.stopPropagation()}>
              {/* Header */}
              <div className="modal-popup-header bg-primary text-white flex justify-between items-center px-6 py-4 shrink-0 rounded-t-2xl">
                <div className="flex items-center gap-2">
                  <FileText className="w-5 h-5 text-white" />
                  <h3 className="font-semibold text-white text-sm">Payslip Preview</h3>
                </div>
                <button
                  onClick={() => setSelectedPayslip(null)}
                  className="p-1.5 hover:bg-white/10 rounded-lg text-white/80 hover:text-white transition-colors shrink-0"
                  aria-label="Close Preview"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              {/* Body */}
              <div className="modal-panel-body p-6 space-y-6 text-body">
                {/* Employee Details Grid */}
                <div className="border border-hairline rounded-lg overflow-hidden bg-canvas">
                  <table className="w-full text-xs text-left border-collapse border-spacing-0">
                    <tbody>
                      <tr className="border-b border-hairline">
                        <td className="px-4 py-3 border-r border-hairline w-1/2">
                          <span className="font-semibold text-muted">Employee Name:</span> <span className="font-bold text-ink capitalize ml-1">{selectedPayslip.user?.username || 'Employee'}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Designation:</span> <span className="font-bold text-ink capitalize ml-1">{selectedPayslip.user?.user_type || 'User'}</span>
                        </td>
                      </tr>
                      <tr className="border-b border-hairline">
                        <td className="px-4 py-3 border-r border-hairline">
                          <span className="font-semibold text-muted">Working Days:</span> <span className="font-bold text-ink ml-1">{workingDaysVal}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-muted">Loss of Pay (Days):</span> <span className="font-bold text-ink text-error ml-1">{selectedPayslip.payslipData?.lopDays ?? 0}</span>
                        </td>
                      </tr>
                      <tr>
                        <td colSpan="2" className="px-4 py-3">
                          <span className="font-semibold text-muted">Calendar:</span> <span className="font-bold text-ink ml-1">{selectedPayslip.totalDays} days</span>
                          <span className="text-muted mx-2">|</span>
                          <span className="font-semibold text-muted">Sundays (excluded):</span> <span className="font-bold text-ink ml-1">{sundaysVal}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                {/* Earnings & Deductions Tables */}
                {selectedPayslip.payslipData && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Earnings */}
                    <div>
                      <h4 className="text-xs font-bold text-primary tracking-wider uppercase mb-3">Earnings</h4>
                      <div className="border border-hairline rounded-lg overflow-hidden">
                        <table className="w-full text-xs text-left">
                          <tbody className="divide-y divide-hairline">
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Basic</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{selectedPayslip.payslipData.earnings.basic.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">HRA</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{selectedPayslip.payslipData.earnings.hra.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Conveyance</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{selectedPayslip.payslipData.earnings.conveyance.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Special Allowance</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-ink">₹{selectedPayslip.payslipData.earnings.special.toLocaleString()}</td>
                            </tr>
                            <tr className="bg-surface-soft font-bold text-ink border-t border-hairline">
                              <td className="px-4 py-3">Total Earnings:</td>
                              <td className="px-4 py-3 text-right">₹{selectedPayslip.payslipData.earnings.gross.toLocaleString()}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    {/* Deductions */}
                    <div>
                      <h4 className="text-xs font-bold text-primary tracking-wider uppercase mb-3">Deductions</h4>
                      <div className="border border-hairline rounded-lg overflow-hidden">
                        <table className="w-full text-xs text-left">
                          <tbody className="divide-y divide-hairline">
                            <tr>
                              <td className="px-4 py-2.5 text-muted">PF</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{selectedPayslip.payslipData.deductions.pfEmployee.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Professional Tax</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{selectedPayslip.payslipData.deductions.professionalTax.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">Medical Benefit</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{selectedPayslip.payslipData.deductions.medical.toLocaleString()}</td>
                            </tr>
                            <tr>
                              <td className="px-4 py-2.5 text-muted">LOP Deduction</td>
                              <td className="px-4 py-2.5 text-right font-semibold text-error">₹{lopDeductionVal.toLocaleString()}</td>
                            </tr>
                            {selectedPayslip.payslipData.deductions.custom && selectedPayslip.payslipData.deductions.custom.map((cust, idx) => (
                              <tr key={idx}>
                                <td className="px-4 py-2.5 text-muted">{cust.name}</td>
                                <td className="px-4 py-2.5 text-right font-semibold text-error">₹{cust.amount.toLocaleString()}</td>
                              </tr>
                            ))}
                            <tr className="bg-surface-soft font-bold text-error border-t border-hairline">
                              <td className="px-4 py-3">Total Deductions:</td>
                              <td className="px-4 py-3 text-right">₹{selectedPayslip.payslipData.deductions.total.toLocaleString()}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                )}

                {/* Net Payout Row & Close / Print Buttons */}
                <div className="flex justify-between items-center border-t border-hairline pt-6 mt-4">
                  <div>
                    <span className="text-[10px] font-semibold text-muted uppercase tracking-wider block">Net Take Home Pay</span>
                    <span className={`text-xl font-bold block mt-0.5 ${selectedPayslip.netSalary >= 0 ? 'text-success' : 'text-error'}`}>
                      {selectedPayslip.netSalary < 0 ? '-' : ''}₹{Math.abs(selectedPayslip.netSalary).toLocaleString()}
                    </span>
                  </div>
                  <div className="flex gap-3">
                    <button
                      onClick={() => setSelectedPayslip(null)}
                      className="btn-secondary h-9 px-5 text-xs bg-gray-500 hover:bg-gray-600 active:bg-gray-700 text-white border-none"
                    >
                      Close
                    </button>
                    <button
                      onClick={() => window.print()}
                      className="btn-primary h-9 px-5 text-xs flex items-center gap-1.5"
                    >
                      <Download className="w-3.5 h-3.5" /> Print Payslip
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        );
      })()}
    </div>
  );
};

export default EmployeePortal;
