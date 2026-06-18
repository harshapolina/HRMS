/**
 * Geolocation Tracker
 * Blocks a form submission until valid GPS coordinates are acquired.
 * 
 * Usage:
 * <form id="myForm">
 *   <button id="myBtn">Submit</button>
 * </form>
 * <script>
 *   initGeolocationTracker('myForm', 'myBtn');
 * </script>
 */
function initGeolocationTracker(formId, buttonId, officePos = null, options = {}) {
    const form = document.getElementById(formId);
    const btn = document.getElementById(buttonId);
    if (!form || !btn) return;
    const deferReverseGeocode = options.deferReverseGeocode === true;
    // Initially disable or preserve state
    btn.setAttribute('disabled', 'true');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-geo-alt"></i> Fetching Location...';
    // Request Location
    if (!navigator.geolocation) {
        alert("Geolocation is not supported by your browser. Please use a modern browser.");
        btn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Location Unsupported';
        return;
    }
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            // Update UI Location Display if it exists
            const locationDisplay = document.getElementById('userLocationDisplay');
            const locationText = document.getElementById('locationText');
            if (locationDisplay && locationText) {
                locationDisplay.style.display = 'flex';
                locationText.innerText = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`;

                if (!deferReverseGeocode) {
                    locationText.innerText = `Acquiring address...`;
                    fetchReverseGeocode(lat, lng, locationDisplay, locationText);
                } else {
                    scheduleDeferredReverseGeocode(lat, lng, locationDisplay, locationText, options.reverseGeocodeDelayMs || 30000);
                }
            }

            // Geofencing Check
            if (officePos && officePos.lat && officePos.lng) {
                const distance = getDistance(
                    lat, lng, 
                    parseFloat(officePos.lat), 
                    parseFloat(officePos.lng)
                );
                if (distance > officePos.radius) {
                    const distKm = (distance / 1000).toFixed(2);
                    alert(`Access Denied: You are ${distance.toFixed(0)}m away from the office. You must be within ${officePos.radius}m to punch.`);
                    btn.innerHTML = `<i class="bi bi-geo-fill"></i> Too Far (${distKm}km)`;
                    btn.setAttribute('disabled', 'true');
                    
                    if (locationDisplay) {
                        locationDisplay.style.background = 'rgba(239, 68, 68, 0.05)';
                        locationDisplay.style.color = '#ef4444';
                        locationDisplay.style.borderColor = 'rgba(239, 68, 68, 0.1)';
                    }
                    return;
                }
            }
            // Create or update hidden inputs
            updateHiddenInput(form, 'latitude', lat);
            updateHiddenInput(form, 'longitude', lng);
            // Enable button
            btn.removeAttribute('disabled');
            btn.innerHTML = originalText;
            console.log("Location acquired:", lat, lng);
        },
        (error) => {
            let errorMsg = "Please allow location access to continue.";
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg = "Location access denied. You must allow location permissions to Punch In/Out.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg = "Location information is unavailable.";
                    break;
                case error.TIMEOUT:
                    errorMsg = "The request to get user location timed out.";
                    break;
            }
            alert(errorMsg);
            btn.innerHTML = '<i class="bi bi-lock-fill"></i> Location Required';

            const locationDisplay = document.getElementById('userLocationDisplay');
            const locationText = document.getElementById('locationText');
            if (locationDisplay && locationText) {
                locationDisplay.style.display = 'flex';
                locationDisplay.style.background = 'rgba(239, 68, 68, 0.05)';
                locationDisplay.style.color = '#ef4444';
                locationDisplay.style.borderColor = 'rgba(239, 68, 68, 0.1)';
                locationText.innerText = "Location access required";
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}
/**
 * Haversine Formula to calculate distance in meters
 */
function getDistance(lat1, lon1, lat2, lon2) {
    const R = 6371e3; // Earth radius in metres
    const φ1 = lat1 * Math.PI/180;
    const φ2 = lat2 * Math.PI/180;
    const Δφ = (lat2-lat1) * Math.PI/180;
    const Δλ = (lon2-lon1) * Math.PI/180;
    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
              Math.cos(φ1) * Math.cos(φ2) *
              Math.sin(Δλ/2) * Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c; 
}
function updateHiddenInput(form, name, value) {
    let input = form.querySelector(`input[name="${name}"]`);
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        form.appendChild(input);
    }
    input.value = value;
}

function fetchReverseGeocode(lat, lng, locationDisplay, locationText) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.display_name) {
                const addr = data.address || {};
                const city = addr.city || addr.town || addr.village || addr.suburb || '';
                const state = addr.state || addr.country || '';
                locationText.innerText = city ? `${city}, ${state}` : data.display_name.split(',').slice(0, 3).join(',');
            } else {
                locationText.innerText = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`;
            }
        })
        .catch(() => {
            locationText.innerText = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`;
        });
}

function scheduleDeferredReverseGeocode(lat, lng, locationDisplay, locationText, delayMs) {
    setTimeout(() => {
        if (document.hidden) return;
        fetchReverseGeocode(lat, lng, locationDisplay, locationText);
    }, delayMs);
}

/**
 * Continuous Live Tracking using watchPosition
 * Sends location to Firebase for both real-time tracking and historical routes
 */
let liveTrackingWatcher = null;
let lastFirebaseUpdate = 0;
let lastPosition = { lat: 0, lng: 0 };
let currentPosition = { lat: 0, lng: 0 };
let currentSessionId = '';
let currentAccuracy = 0;

function startLiveTracking(userId, username, sessionId) {
    if (liveTrackingWatcher !== null) return; // Already running

    console.log("Starting live tracking for user:", userId);
    currentSessionId = sessionId;

    const indicator = document.getElementById('liveTrackingIndicator');
    if (indicator) indicator.style.display = 'flex';

    if (!navigator.geolocation) {
        console.error("Geolocation is not supported by your browser.");
        return;
    }

    const dbRef = firebase.database().ref('live_locations/' + userId);
    const dbHistoryRef = firebase.database().ref('location_history/' + userId + '/' + sessionId);

    liveTrackingWatcher = navigator.geolocation.watchPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            const speed = position.coords.speed || 0;
            const now = Date.now();
            const distance = getDistance(lat, lng, lastPosition.lat, lastPosition.lng);
            
            currentAccuracy = accuracy;
            currentPosition = { lat, lng };

            // Update current live location
            dbRef.set({
                latitude: lat,
                longitude: lng,
                accuracy: accuracy,
                speed: speed,
                updated_at: now,
                time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }),
                status: "online",
                employee_name: username
            });

            // Write to Firebase history if moved >= 50m OR 5 minutes have passed
            if (distance >= 50 || (now - lastFirebaseUpdate) >= 300000 || lastFirebaseUpdate === 0) {
                dbHistoryRef.push({
                    latitude: lat,
                    longitude: lng,
                    accuracy: accuracy,
                    speed: speed,
                    captured_at: now,
                    time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })
                });
                lastFirebaseUpdate = now;
                lastPosition = { lat, lng };
            }
        },
        (error) => {
            console.error("WatchPosition error:", error);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

function stopLiveTracking(userId, callback = null) {
    try {
        if (liveTrackingWatcher !== null) {
            navigator.geolocation.clearWatch(liveTrackingWatcher);
            liveTrackingWatcher = null;
        }
        lastFirebaseUpdate = 0;

        const indicator = document.getElementById('liveTrackingIndicator');
        if (indicator) indicator.style.display = 'none';

        if (userId && typeof firebase !== 'undefined' && typeof firebase.database === 'function') {
            let lat = (currentPosition && currentPosition.lat !== 0) ? currentPosition.lat : 0;
            let lng = (currentPosition && currentPosition.lng !== 0) ? currentPosition.lng : 0;
            let accuracy = currentAccuracy || 0;

            if (lat === 0 || lng === 0) {
                const latInput = document.querySelector('input[name="latitude"]');
                const lngInput = document.querySelector('input[name="longitude"]');
                if (latInput && lngInput && latInput.value && lngInput.value) {
                    lat = parseFloat(latInput.value);
                    lng = parseFloat(lngInput.value);
                }
            }

            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const localSessionId = currentSessionId || `${year}-${month}-${day}`;

            if (lat !== 0 && lng !== 0) {
                // 1. Post final location to history using standard Firebase SDK
                const dbHistoryRef = firebase.database().ref('location_history/' + userId + '/' + localSessionId);
                dbHistoryRef.push({
                    latitude: lat,
                    longitude: lng,
                    accuracy: accuracy,
                    speed: 0,
                    captured_at: Date.now(),
                    time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }),
                    is_punch_out: true
                });

                // 2. Delete live tracking marker using standard Firebase SDK
                firebase.database().ref('live_locations/' + userId).remove();
            } else {
                // Just delete live tracking marker using standard Firebase SDK
                firebase.database().ref('live_locations/' + userId).remove();
            }
        }
    } catch (globalErr) {
        console.error("Global error in stopLiveTracking:", globalErr);
    }

    // Call callback after 200ms delay to let the Firebase SDK send data over the WebSocket
    if (callback) {
        setTimeout(callback, 200);
    }
}

