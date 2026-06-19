<?php
require_once dirname(__DIR__) . '/env_loader.php';
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    header('Location: /');
    exit;
}

$skip_superadmin_css = true;
include __DIR__ . '/htmlopen.php'; 
include __DIR__ . '/header.php'; 
?>
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />

<div class="content">
<div class="contentinside">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-12">
          <!-- LIVE TRACKING UI START -->
          <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
          <style>
              /* ---- Page-level Layout ---- */
              .lt-page-wrapper {
                  padding: 0;
              }

              .lt-page-header {
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                  margin-bottom: 24px;
                  padding: 0 4px;
              }
              .lt-page-header h2 {
                  color: #1e293b;
                  margin: 0;
                  font-weight: 800;
                  font-family: 'Lexend Deca', 'Inter', sans-serif;
                  font-size: 1.6rem;
                  display: flex;
                  align-items: center;
                  gap: 10px;
              }
              .lt-page-header h2 i { font-size: 1.3rem; }
              .lt-page-header p {
                  margin: 4px 0 0;
                  font-size: 0.82rem;
                  color: #64748b;
                  font-weight: 500;
              }

              /* ---- Controls Bar (flat, inline) ---- */
              .lt-controls {
                  display: flex;
                  flex-wrap: wrap;
                  gap: 16px;
                  align-items: flex-end;
                  margin-bottom: 24px;
              }

              .lt-control-group {
                  display: flex;
                  flex-direction: column;
                  gap: 6px;
                  flex: 1;
                  min-width: 180px;
              }

              .lt-control-group label {
                  font-size: 0.72rem;
                  font-weight: 700;
                  color: #64748b;
                  text-transform: uppercase;
                  letter-spacing: 0.5px;
              }

              .lt-control-group select,
              .lt-control-group input {
                  padding: 10px 14px;
                  border: 1px solid #e2e8f0;
                  border-radius: 10px;
                  outline: none;
                  font-family: inherit;
                  font-size: 0.9rem;
                  font-weight: 500;
                  width: 100%;
                  box-sizing: border-box;
                  background: #ffffff;
                  color: #1e293b;
                  transition: border-color 0.2s ease, box-shadow 0.2s ease;
              }
              .lt-control-group select:focus,
              .lt-control-group input:focus {
                  border-color: #008080;
                  box-shadow: 0 0 0 3px rgba(0, 128, 128, 0.08);
              }

              .lt-btn {
                  border: none;
                  padding: 10px 24px;
                  border-radius: 10px;
                  cursor: pointer;
                  font-weight: 700;
                  font-size: 0.88rem;
                  transition: all 0.2s ease;
                  white-space: nowrap;
                  display: inline-flex;
                  align-items: center;
                  gap: 8px;
                  height: 42px;
              }
              .lt-btn-primary {
                  background: #008080;
                  color: #ffffff;
              }
              .lt-btn-primary:hover {
                  background: #0f766e;
                  transform: translateY(-1px);
              }
              .lt-btn-secondary {
                  background: #1e293b;
                  color: #ffffff;
              }
              .lt-btn-secondary:hover {
                  background: #0f172a;
                  transform: translateY(-1px);
              }

              /* ---- Map ---- */
              .lt-map-wrap {
                  height: calc(100vh - 180px);
                  min-height: 500px;
                  border-radius: 14px;
                  overflow: hidden;
                  border: 1px solid #e2e8f0;
              }
              #map { height: 100%; width: 100%; }

              /* ---- Responsive ---- */
              @media (max-width: 768px) {
                  .lt-controls {
                      display: grid;
                      grid-template-columns: 1fr 1fr;
                      gap: 12px;
                      margin-bottom: 16px;
                  }
                  .lt-control-group {
                      min-width: unset;
                      flex: none;
                      width: 100%;
                  }
                  .lt-btn {
                      width: 100%;
                      justify-content: center;
                      height: 42px;
                      padding: 10px 12px;
                      font-size: 0.82rem;
                  }
                  .lt-map-wrap {
                      height: calc(100vh - 220px);
                      min-height: 450px;
                  }
                  .lt-page-header h2 {
                      font-size: 1.25rem;
                  }
              }

              /* ===========================
                 DARK MODE — Charcoal Monochrome
                 =========================== */
              body.dark-mode .lt-page-header h2 { color: #ffffff; }
              body.dark-mode .lt-page-header p { color: #aaaaaa; }

              body.dark-mode .lt-control-group label { color: #aaaaaa; }

              body.dark-mode .lt-control-group select,
              body.dark-mode .lt-control-group input {
                  background-color: #262626 !important;
                  color: #ffffff !important;
                  border-color: #333333 !important;
              }
              body.dark-mode .lt-control-group select:focus,
              body.dark-mode .lt-control-group input:focus {
                  border-color: #008080 !important;
                  box-shadow: 0 0 0 3px rgba(0, 128, 128, 0.15);
              }
              body.dark-mode .lt-control-group select {
                  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
                  background-repeat: no-repeat !important;
                  background-position: right 0.75rem center !important;
                  background-size: 16px 12px !important;
                  appearance: none !important;
                  -webkit-appearance: none !important;
                  padding-right: 2.5rem !important;
              }
              body.dark-mode .lt-control-group select option {
                  background-color: #1e1e1e !important;
                  color: #ffffff !important;
              }

              body.dark-mode .lt-btn-primary {
                  background: #008080;
                  color: #ffffff;
              }
              body.dark-mode .lt-btn-primary:hover { background: #0f766e; }

              body.dark-mode .lt-btn-secondary {
                  background: #2a2a2a;
                  color: #ffffff;
                  border: 1px solid #3d3d3d;
              }
              body.dark-mode .lt-btn-secondary:hover { background: #333333; }

              body.dark-mode .lt-map-wrap {
                  border-color: #333333;
              }

              /* Map tiles & popups dark mode */
              body.dark-mode .leaflet-tile-pane {
                  filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
              }
              body.dark-mode .leaflet-popup-content-wrapper,
              body.dark-mode .leaflet-popup-tip {
                  background-color: #1e1e1e;
                  color: #ffffff;
              }
              body.dark-mode .leaflet-popup-content b { color: #ffffff; }
              body.dark-mode .leaflet-control-zoom a {
                  background-color: #262626;
                  color: #ffffff;
                  border-color: #333333;
              }
              body.dark-mode .leaflet-control-zoom a:hover {
                  background-color: #333333;
              }
          </style>

          <div class="lt-page-wrapper">
              <div class="lt-controls">
                  <div class="lt-control-group">
                      <label>Select Employee</label>
                      <select id="employeeSelect">
                          <option value="all">All Active Employees (Live)</option>
                      </select>
                  </div>
                  <div class="lt-control-group">
                      <label>Date (For History)</label>
                      <input type="date" id="historyDate" value="<?php echo date('Y-m-d'); ?>">
                  </div>
                  <button class="lt-btn lt-btn-primary" onclick="loadHistory()">
                      <i class="bi bi-clock-history"></i> View History Route
                  </button>
                  <button class="lt-btn lt-btn-secondary" onclick="resetToLive()">
                      <i class="bi bi-broadcast"></i> Back to Live
                  </button>
              </div>

              <div class="lt-map-wrap">
                  <div id="map"></div>
              </div>
          </div>

          <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
          <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
          <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
          <script>
              const firebaseConfig = {
                  apiKey: "<?php echo getenv('FIREBASE_API_KEY') ?: 'AIzaSyBg7-DeKj7PQ3h6Q1Nf3YBLxDEWN3J5_8U'; ?>",
                  authDomain: "<?php echo getenv('FIREBASE_AUTH_DOMAIN') ?: 'hrms-live-tracking.firebaseapp.com'; ?>",
                  databaseURL: "<?php echo getenv('FIREBASE_DATABASE_URL') ?: 'https://hrms-live-tracking-default-rtdb.asia-southeast1.firebasedatabase.app'; ?>",
                  projectId: "<?php echo getenv('FIREBASE_PROJECT_ID') ?: 'hrms-live-tracking'; ?>",
                  storageBucket: "<?php echo getenv('FIREBASE_STORAGE_BUCKET') ?: 'hrms-live-tracking.firebasestorage.app'; ?>",
                  messagingSenderId: "<?php echo getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '578036127062'; ?>",
                  appId: "<?php echo getenv('FIREBASE_APP_ID') ?: '1:578036127062:web:bfff8efff0c627d4f072f7'; ?>",
                  measurementId: "<?php echo getenv('FIREBASE_MEASUREMENT_ID') ?: 'G-RX2SWP5RKK'; ?>"
              };
              if (!firebase.apps.length) { firebase.initializeApp(firebaseConfig); }
              const db = firebase.database();

              const map = L.map('map').setView([20.5937, 78.9629], 5);
              L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

              let liveMarkers = {};
              let historyLayer = L.layerGroup().addTo(map);
              let isViewingHistory = false;

              const onlineIcon = L.divIcon({ className: 'custom-div-icon', html: `<div style='background-color:#22c55e;width:15px;height:15px;border-radius:50%;border:2px solid white;box-shadow:0 0 5px rgba(0,0,0,0.5);'></div>`, iconSize: [15, 15], iconAnchor: [7, 7] });
              fetch('action.php?fetch_users_json=1').then(res => res.json()).then(data => {
                  const select = document.getElementById('employeeSelect');
                  data.forEach(emp => { 
                      let opt = document.createElement('option'); 
                      opt.value = emp.username; 
                      opt.textContent = emp.username; 
                      opt.setAttribute('data-id', emp.id);
                      select.appendChild(opt); 
                  });
              });

              function isUserLive(user) {
                  return user && user.status === 'online' && user.latitude && user.longitude;
              }

              function removeLiveMarker(uid) {
                  if (liveMarkers[uid] && map.hasLayer(liveMarkers[uid])) {
                      map.removeLayer(liveMarkers[uid]);
                  }
                  delete liveMarkers[uid];
              }

              function renderLiveLocations(data) {
                  if (!data || isViewingHistory) return;
 
                  const select = document.getElementById('employeeSelect');
                  const selectedOpt = select.options[select.selectedIndex];
                  const selectedUsername = selectedOpt ? selectedOpt.value : 'all';
                  const selectedId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
                  const activeUids = new Set();
 
                  for (let uid in data) {
                      if (selectedUsername !== 'all' && uid !== selectedUsername && uid !== selectedId) continue;
                      if (isUserLive(data[uid])) activeUids.add(uid);
                  }

                  for (let uid in liveMarkers) {
                      if (!activeUids.has(uid)) removeLiveMarker(uid);
                  }

                  let bounds = [];
                  for (let uid of activeUids) {
                      const user = data[uid];
                      const latlng = [user.latitude, user.longitude];
                      bounds.push(latlng);
                      const updatedTime = user.time || (user.updated_at ? new Date(user.updated_at).toLocaleTimeString() : 'N/A');
                      const popupContent = `<b>${user.employee_name || 'Employee'}</b><br>Status: Live<br>Speed: ${Math.round((user.speed || 0) * 3.6)} km/h<br>Updated: ${updatedTime}`;

                      if (liveMarkers[uid]) {
                          liveMarkers[uid].setLatLng(latlng);
                          liveMarkers[uid].setIcon(onlineIcon);
                          liveMarkers[uid].setPopupContent(popupContent);
                          if (!map.hasLayer(liveMarkers[uid])) liveMarkers[uid].addTo(map);
                      } else {
                          liveMarkers[uid] = L.marker(latlng, { icon: onlineIcon }).bindPopup(popupContent).addTo(map);
                      }
                  }

                  if (bounds.length > 0) {
                      map.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
                  }
              }

              db.ref('live_locations').on('value', (snapshot) => {
                  if (isViewingHistory) return;
                  renderLiveLocations(snapshot.val());
              });

              document.getElementById('employeeSelect').addEventListener('change', () => {
                  if (!isViewingHistory) {
                      db.ref('live_locations').once('value').then(snapshot => renderLiveLocations(snapshot.val()));
                  }
              });

              function loadHistory() {
                  const select = document.getElementById('employeeSelect');
                  const selectedOpt = select.options[select.selectedIndex];
                  if (!selectedOpt) return;
                  const empUsername = selectedOpt.value;
                  const empId = selectedOpt.getAttribute('data-id');
                  const date = document.getElementById('historyDate').value;
                  if (empUsername === 'all') { alert('Please select a specific employee to view history.'); return; }
                  isViewingHistory = true;
                  for (let uid in liveMarkers) { removeLiveMarker(uid); }
                  historyLayer.clearLayers();
                  
                  // Read directly from Firebase Realtime Database
                  db.ref('location_history/' + empUsername + '/' + date).once('value').then(snapshot => {
                      const dataVal = snapshot.val();
                      if (!dataVal && empId) {
                          // Fall back to querying by numeric ID for old history logs
                          return db.ref('location_history/' + empId + '/' + date).once('value').then(fallbackSnap => {
                              return fallbackSnap.val();
                          });
                      }
                      return dataVal;
                  }).then(dataVal => {
                      const data = [];
                      if (dataVal) {
                          for (let key in dataVal) {
                              data.push(dataVal[key]);
                          }
                      }
                      
                      // Sort by captured_at to ensure chronological order
                      data.sort((a, b) => a.captured_at - b.captured_at);

                      if (data.length === 0) {
                          isViewingHistory = false;
                          alert('No location history found for this employee on the selected date.');
                          return;
                      }                      let latlngs = [];
                      
                      // Format current local date as YYYY-MM-DD to check if viewing today
                      const d = new Date();
                      const year = d.getFullYear();
                      const month = String(d.getMonth() + 1).padStart(2, '0');
                      const day = String(d.getDate()).padStart(2, '0');
                      const todayLocal = `${year}-${month}-${day}`;
                      const isToday = (date === todayLocal);
                      
                      // Custom Icons for A (Start) and B (End)
                      const startIcon = L.divIcon({ className: 'custom-div-icon', html: `<div style='background-color:#10b981;color:white;width:24px;height:24px;border-radius:50%;border:2px solid white;box-shadow:0 0 10px rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:12px;'>A</div>`, iconSize: [24, 24], iconAnchor: [12, 12] });
                      const endIcon = L.divIcon({ className: 'custom-div-icon', html: `<div style='background-color:#ef4444;color:white;width:24px;height:24px;border-radius:50%;border:2px solid white;box-shadow:0 0 10px rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:12px;'>B</div>`, iconSize: [24, 24], iconAnchor: [12, 12] });
 
                      data.forEach((point, index) => {
                          let latlng = [point.latitude, point.longitude];
                          latlngs.push(latlng);
                          let time = point.time || (point.captured_at ? new Date(point.captured_at).toLocaleTimeString() : 'N/A');
                          
                          if (index === 0) {
                              // Point A (Start)
                              L.marker(latlng, { icon: startIcon }).bindPopup(`<b>Start Location (Point A)</b><br>Time: ${time}`).addTo(historyLayer);
                          } else if (point.is_punch_out === true || point.is_punch_out === 'true' || point.is_punch_out === 1 || point.is_punch_out === '1' || (!isToday && index === data.length - 1)) {
                              // Point B (End) - Drawn if it is explicitly a punch out point, or if we are viewing a past date and it's the last point
                              L.marker(latlng, { icon: endIcon }).bindPopup(`<b>End Location (Point B)</b><br>Time: ${time}`).addTo(historyLayer);
                          } else {
                          // Intermediate tracking points
                              L.circleMarker(latlng, { radius: 4, color: '#8b5cf6', fillColor: '#8b5cf6', fillOpacity: 0.9, weight: 1 }).bindPopup(`Time: ${time}`).addTo(historyLayer);
                          }
                      });
                      
                      if (latlngs.length > 1) { 
                          // Movie style dashed line connecting A -> B
                          L.polyline(latlngs, {color: '#3b82f6', weight: 4, opacity: 0.8, dashArray: '10, 10'}).addTo(historyLayer); 
                      }
                      
                      if (latlngs.length > 0) {
                          map.fitBounds(latlngs, {padding: [50, 50]});
                      }
                  }).catch(err => {
                      isViewingHistory = false;
                      console.error("Tracking rendering error:", err);
                      alert('An error occurred while drawing the history map: ' + err.message);
                  });
              }

              function resetToLive() {
                  isViewingHistory = false;
                  historyLayer.clearLayers();
                  document.getElementById('employeeSelect').value = 'all';
                  db.ref('live_locations').once('value').then(snapshot => renderLiveLocations(snapshot.val()));
              }
          </script>
          <!-- LIVE TRACKING UI END -->
      </div>
    </div>
  </div>
</div>
</div>
<?php include __DIR__ . '/htmlclose.php'; ?>
