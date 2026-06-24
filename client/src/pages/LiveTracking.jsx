import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import io from 'socket.io-client';
import { Clock, Radio, RefreshCw, User } from 'lucide-react';

const LiveTracking = () => {
  const [employees, setEmployees] = useState([]);
  const [selectedUser, setSelectedUser] = useState('all');
  const [historyDate, setHistoryDate] = useState(() => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  });
  const [isViewingHistory, setIsViewingHistory] = useState(false);
  const [loading, setLoading] = useState(false);

  const mapContainerRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const liveMarkersRef = useRef({});
  const historyLayerRef = useRef(null);
  const socketRef = useRef(null);
  const fetchLiveLocationsRef = useRef(null);
  const isViewingHistoryRef = useRef(isViewingHistory);
  const selectedUserRef = useRef(selectedUser);

  // Initialize Map
  useEffect(() => {
    if (!window.L) {
      console.error('Leaflet is not loaded');
      return;
    }

    const map = window.L.map(mapContainerRef.current).setView([20.5937, 78.9629], 5);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    mapInstanceRef.current = map;
    historyLayerRef.current = window.L.layerGroup().addTo(map);

    // Initial load
    fetchEmployees();
    fetchLiveLocations();

    // Setup Socket
    const socket = io();
    socketRef.current = socket;

    socket.on('live_location_update', (data) => {
      if (isViewingHistoryRef.current) return;
      handleLiveLocationUpdate(data);
    });

    socket.on('attendance_update', (log) => {
      if (isViewingHistoryRef.current) return;
      const uId = log.user?._id || log.user;
      
      // If the user has punched out, remove their marker from the map
      if (log.punchOut) {
        if (liveMarkersRef.current[uId]) {
          if (mapInstanceRef.current && mapInstanceRef.current.hasLayer(liveMarkersRef.current[uId])) {
            mapInstanceRef.current.removeLayer(liveMarkersRef.current[uId]);
          }
          delete liveMarkersRef.current[uId];
        }
      } else {
        if (fetchLiveLocationsRef.current) {
          fetchLiveLocationsRef.current();
        }
      }
    });

    return () => {
      socket.disconnect();
      map.remove();
    };
  }, []);

  // Update refs on every render to avoid stale closures in socket event listeners
  useEffect(() => {
    fetchLiveLocationsRef.current = fetchLiveLocations;
    isViewingHistoryRef.current = isViewingHistory;
    selectedUserRef.current = selectedUser;
  });

  // Fetch employees
  const fetchEmployees = async () => {
    try {
      const res = await axios.get('/api/users?limit=1000');
      // filter out superadmins
      const list = res.data.data.filter(u => u.user_type !== 'superuseradmin');
      setEmployees(list);
    } catch (err) {
      console.error(err);
    }
  };

  // Fetch current coordinates of today's attendance logs for live markers
  const fetchLiveLocations = async () => {
    if (isViewingHistory) return;
    try {
      const today = new Date().toISOString().split('T')[0];
      const res = await axios.get(`/api/attendance?from=${today}&to=${today}`);
      
      // Clear existing live markers
      Object.values(liveMarkersRef.current).forEach(marker => {
        if (mapInstanceRef.current.hasLayer(marker)) {
          mapInstanceRef.current.removeLayer(marker);
        }
      });
      liveMarkersRef.current = {};

      const logs = res.data;
      const bounds = [];
      const onlineIcon = window.L.divIcon({
        className: 'custom-div-icon',
        html: `<div style='background-color:#22c55e;width:15px;height:15px;border-radius:50%;border:2px solid white;box-shadow:0 0 5px rgba(0,0,0,0.5);'></div>`,
        iconSize: [15, 15],
        iconAnchor: [7, 7]
      });

      logs.forEach(log => {
        // If employee matches filter, and has active shift coordinates
        if (selectedUser !== 'all' && log.user?._id !== selectedUser) return;
        
        // Skip users who have already punched out
        if (log.punchOut) return;
        
        if (log.locationHistory && log.locationHistory.length > 0) {
          const latestPoint = log.locationHistory[log.locationHistory.length - 1];
          const latlng = [latestPoint.latitude, latestPoint.longitude];
          bounds.push(latlng);

          const updatedTime = new Date(latestPoint.capturedAt).toLocaleTimeString();
          const popupContent = `<b>${log.user?.username || 'Employee'}</b><br/>Status: Punched In<br/>Updated: ${updatedTime}`;

          const marker = window.L.marker(latlng, { icon: onlineIcon })
            .bindPopup(popupContent)
            .addTo(mapInstanceRef.current);
          
          liveMarkersRef.current[log.user?._id] = marker;
        }
      });

      if (bounds.length > 0 && mapInstanceRef.current) {
        mapInstanceRef.current.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Handle real-time socket events
  const handleLiveLocationUpdate = (data) => {
    const { userId, username, latitude, longitude, time } = data;
    if (selectedUserRef.current !== 'all' && userId !== selectedUserRef.current) return;

    const latlng = [latitude, longitude];
    const onlineIcon = window.L.divIcon({
      className: 'custom-div-icon',
      html: `<div style='background-color:#22c55e;width:15px;height:15px;border-radius:50%;border:2px solid white;box-shadow:0 0 5px rgba(0,0,0,0.5);'></div>`,
      iconSize: [15, 15],
      iconAnchor: [7, 7]
    });
    
    const popupContent = `<b>${username}</b><br/>Status: Punched In (Live)<br/>Updated: ${new Date(time).toLocaleTimeString()}`;

    if (liveMarkersRef.current[userId]) {
      liveMarkersRef.current[userId].setLatLng(latlng);
      liveMarkersRef.current[userId].setPopupContent(popupContent);
    } else {
      const marker = window.L.marker(latlng, { icon: onlineIcon })
        .bindPopup(popupContent)
        .addTo(mapInstanceRef.current);
      liveMarkersRef.current[userId] = marker;
    }

    // Centering if single user view
    if (selectedUser !== 'all') {
      mapInstanceRef.current.setView(latlng, 15);
    }
  };

  // Re-fetch when user dropdown changes in live mode
  useEffect(() => {
    if (!isViewingHistory && mapInstanceRef.current) {
      fetchLiveLocations();
    }
  }, [selectedUser, isViewingHistory]);

  const loadHistory = async () => {
    if (selectedUser === 'all') {
      alert('Please select a specific employee to view history.');
      return;
    }
    setLoading(true);
    setIsViewingHistory(true);

    // Clear live markers from map
    Object.values(liveMarkersRef.current).forEach(marker => {
      if (mapInstanceRef.current.hasLayer(marker)) {
        mapInstanceRef.current.removeLayer(marker);
      }
    });

    // Clear history layers
    historyLayerRef.current.clearLayers();

    try {
      const res = await axios.get(`/api/attendance?from=${historyDate}&to=${historyDate}`);
      const log = res.data.find(l => l.user?._id === selectedUser);

      if (!log || !log.locationHistory || log.locationHistory.length === 0) {
        alert('No location history found for this employee on the selected date.');
        setIsViewingHistory(false);
        fetchLiveLocations();
        return;
      }

      const points = [...log.locationHistory].sort((a, b) => new Date(a.capturedAt) - new Date(b.capturedAt));
      const latlngs = [];

      const startIcon = window.L.divIcon({
        className: 'custom-div-icon',
        html: `<div style='background-color:#10b981;color:white;width:24px;height:24px;border-radius:50%;border:2px solid white;box-shadow:0 0 10px rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:12px;'>A</div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
      });

      const endIcon = window.L.divIcon({
        className: 'custom-div-icon',
        html: `<div style='background-color:#ef4444;color:white;width:24px;height:24px;border-radius:50%;border:2px solid white;box-shadow:0 0 10px rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:12px;'>B</div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
      });

      points.forEach((pt, index) => {
        const latlng = [pt.latitude, pt.longitude];
        latlngs.push(latlng);
        const time = new Date(pt.capturedAt).toLocaleTimeString();

        if (index === 0) {
          // Point A
          window.L.marker(latlng, { icon: startIcon })
            .bindPopup(`<b>Start Shift (Point A)</b><br/>Time: ${time}`)
            .addTo(historyLayerRef.current);
        } else if (index === points.length - 1 && log.punchOut) {
          // Point B
          window.L.marker(latlng, { icon: endIcon })
            .bindPopup(`<b>End Shift (Point B)</b><br/>Time: ${time}`)
            .addTo(historyLayerRef.current);
        } else {
          // Intermediate dots
          window.L.circleMarker(latlng, {
            radius: 4,
            color: '#8b5cf6',
            fillColor: '#8b5cf6',
            fillOpacity: 0.9,
            weight: 1
          }).bindPopup(`Time: ${time}`).addTo(historyLayerRef.current);
        }
      });

      if (latlngs.length > 1) {
        window.L.polyline(latlngs, {
          color: '#3b82f6',
          weight: 4,
          opacity: 0.8,
          dashArray: '10, 10'
        }).addTo(historyLayerRef.current);
      }

      if (latlngs.length > 0) {
        mapInstanceRef.current.fitBounds(latlngs, { padding: [50, 50] });
      }
    } catch (err) {
      console.error(err);
      alert('Error fetching location history logs.');
    } finally {
      setLoading(false);
    }
  };

  const resetToLive = () => {
    setIsViewingHistory(false);
    historyLayerRef.current.clearLayers();
    setSelectedUser('all');
    fetchLiveLocations();
  };

  return (
    <div className="page-shell">
      <div className="toolbar items-end">
        <div className="flex flex-col gap-1 flex-1 min-w-[200px]">
          <label className="label-xs" htmlFor="livetracking-employee-select">Select Employee</label>
          <select
            id="livetracking-employee-select"
            value={selectedUser}
            onChange={(e) => setSelectedUser(e.target.value)}
            className="select-field text-xs"
          >
            <option value="all">All Active Employees (Live)</option>
            {employees.map(emp => (
              <option key={emp._id} value={emp._id}>{emp.username}</option>
            ))}
          </select>
        </div>

        <div className="flex flex-col gap-1 flex-1 min-w-[200px]">
          <label className="label-xs" htmlFor="livetracking-history-date">Date (For History Route)</label>
          <input
            id="livetracking-history-date"
            type="date"
            value={historyDate}
            onChange={(e) => setHistoryDate(e.target.value)}
            className="input-field text-xs"
          />
        </div>

        <div className="flex gap-2 w-full md:w-auto">
          <button
            id="livetracking-history-btn"
            onClick={loadHistory}
            disabled={loading}
            className="btn-primary btn-sm"
          >
            <Clock className="w-3.5 h-3.5" /> {loading ? 'Loading...' : 'View History Route'}
          </button>

          <button
            id="livetracking-live-btn"
            onClick={resetToLive}
            className="btn-secondary btn-sm"
          >
            <Radio className="w-3.5 h-3.5 text-success" /> Back to Live
          </button>
        </div>
      </div>

      <div className="card overflow-hidden h-[65vh] relative min-h-[500px]">
        <div ref={mapContainerRef} className="h-full w-full z-10" />
      </div>
    </div>
  );
};

export default LiveTracking;
