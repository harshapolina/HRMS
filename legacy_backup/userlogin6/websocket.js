console.log("WebSocket script running");

const ws = new WebSocket('ws://searchhomesindia.in:65003/');

ws.onopen = function() {
    console.log('WebSocket connection established');
};

ws.onmessage = function(event) {
    console.log('Received message:', event.data);

    try {
        const data = JSON.parse(event.data);
        if (data && data.action === 'update' && data.userId) {
            updateSessionData(data.userId);
        }
    } catch (e) {
        console.error('Error parsing message as JSON:', e);
    }
};

ws.onclose = function() {
    console.log('WebSocket connection closed');
};

function updateSessionData(userId) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_session.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log('Session updated successfully');
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                updatePage(response.data);
            }
        }
    };
    xhr.send(JSON.stringify({ userId: userId }));
}

function updatePage(data) {
    // Update the DOM elements with new session data here
    document.getElementById('username').textContent = data.username;
    // Update other DOM elements as necessary
}
