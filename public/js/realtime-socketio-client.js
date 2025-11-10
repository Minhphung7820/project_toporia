/**
 * Toporia Realtime Socket.IO Client
 *
 * JavaScript client library for connecting to Toporia Realtime Socket.IO Gateway.
 * Compatible with Socket.IO v4 protocol.
 *
 * Features:
 * - Auto-reconnection
 * - Room/channel management
 * - Event acknowledgments
 * - Namespace support
 * - Connection state tracking
 *
 * Usage:
 * ```javascript
 * // CDN (Socket.IO client required)
 * <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
 * <script src="/js/realtime-socketio-client.js"></script>
 *
 * // Connect
 * const realtime = new ToporiaRealtime('http://localhost:3000');
 *
 * // Join room
 * realtime.join('chat.room.1');
 *
 * // Listen for events
 * realtime.on('message.sent', (data) => {
 *     console.log('Message:', data);
 * });
 *
 * // Send event
 * realtime.emit('message.sent', { text: 'Hello!' });
 * ```
 */

class ToporiaRealtime {
    /**
     * Create Toporia Realtime client.
     *
     * @param {string} url Server URL (e.g., 'http://localhost:3000')
     * @param {object} options Socket.IO options
     */
    constructor(url, options = {}) {
        this.url = url;
        this.socket = null;
        this.rooms = new Set();
        this.listeners = new Map();
        this.connected = false;

        // Default options
        this.options = {
            transports: ['websocket', 'polling'],
            reconnection: true,
            reconnectionAttempts: Infinity,
            reconnectionDelay: 1000,
            reconnectionDelayMax: 5000,
            timeout: 20000,
            ...options
        };

        this.init();
    }

    /**
     * Initialize Socket.IO connection.
     */
    init() {
        if (typeof io === 'undefined') {
            throw new Error('Socket.IO client library not loaded. Include: https://cdn.socket.io/4.5.4/socket.io.min.js');
        }

        this.socket = io(this.url, this.options);

        // Connection events
        this.socket.on('connect', () => {
            this.connected = true;
            console.log('[Toporia Realtime] Connected');

            // Rejoin rooms after reconnection
            this.rejoinRooms();

            this.emit('connected', { id: this.socket.id });
        });

        this.socket.on('disconnect', (reason) => {
            this.connected = false;
            console.log('[Toporia Realtime] Disconnected:', reason);
            this.emit('disconnected', { reason });
        });

        this.socket.on('connect_error', (error) => {
            console.error('[Toporia Realtime] Connection error:', error);
            this.emit('error', { error: error.message });
        });

        // Reconnection events
        this.socket.on('reconnect', (attempt) => {
            console.log('[Toporia Realtime] Reconnected after', attempt, 'attempts');
            this.emit('reconnected', { attempt });
        });

        this.socket.on('reconnect_attempt', (attempt) => {
            console.log('[Toporia Realtime] Reconnection attempt', attempt);
        });

        this.socket.on('reconnect_error', (error) => {
            console.error('[Toporia Realtime] Reconnection error:', error);
        });

        this.socket.on('reconnect_failed', () => {
            console.error('[Toporia Realtime] Reconnection failed');
            this.emit('reconnect_failed', {});
        });
    }

    /**
     * Join a room/channel.
     *
     * @param {string} room Room name
     * @param {function} callback Acknowledgment callback
     * @returns {Promise}
     */
    async join(room, callback) {
        return new Promise((resolve, reject) => {
            this.socket.emit('join', { room }, (response) => {
                if (response && !response.error) {
                    this.rooms.add(room);
                    console.log('[Toporia Realtime] Joined room:', room);

                    if (callback) callback(response);
                    resolve(response);
                } else {
                    console.error('[Toporia Realtime] Failed to join room:', room, response);

                    if (callback) callback(response);
                    reject(response);
                }
            });
        });
    }

    /**
     * Leave a room/channel.
     *
     * @param {string} room Room name
     * @param {function} callback Acknowledgment callback
     * @returns {Promise}
     */
    async leave(room, callback) {
        return new Promise((resolve, reject) => {
            this.socket.emit('leave', { room }, (response) => {
                if (response && !response.error) {
                    this.rooms.delete(room);
                    console.log('[Toporia Realtime] Left room:', room);

                    if (callback) callback(response);
                    resolve(response);
                } else {
                    console.error('[Toporia Realtime] Failed to leave room:', room, response);

                    if (callback) callback(response);
                    reject(response);
                }
            });
        });
    }

    /**
     * Listen for an event.
     *
     * @param {string} event Event name
     * @param {function} callback Event handler
     * @returns {this}
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);

            // Register Socket.IO listener
            this.socket.on(event, (data) => {
                const handlers = this.listeners.get(event) || [];
                handlers.forEach(handler => handler(data));
            });
        }

        this.listeners.get(event).push(callback);
        return this;
    }

    /**
     * Listen for an event once.
     *
     * @param {string} event Event name
     * @param {function} callback Event handler
     * @returns {this}
     */
    once(event, callback) {
        const wrapper = (data) => {
            callback(data);
            this.off(event, wrapper);
        };

        return this.on(event, wrapper);
    }

    /**
     * Remove event listener.
     *
     * @param {string} event Event name
     * @param {function} callback Specific handler to remove (optional)
     * @returns {this}
     */
    off(event, callback) {
        if (!this.listeners.has(event)) {
            return this;
        }

        if (callback) {
            const handlers = this.listeners.get(event);
            const index = handlers.indexOf(callback);
            if (index > -1) {
                handlers.splice(index, 1);
            }
        } else {
            this.listeners.delete(event);
            this.socket.off(event);
        }

        return this;
    }

    /**
     * Emit an event.
     *
     * @param {string} event Event name
     * @param {*} data Event data
     * @param {function} callback Acknowledgment callback (optional)
     * @returns {this}
     */
    emit(event, data, callback) {
        if (callback) {
            this.socket.emit(event, data, callback);
        } else {
            this.socket.emit(event, data);
        }
        return this;
    }

    /**
     * Disconnect from server.
     */
    disconnect() {
        this.socket.disconnect();
        this.connected = false;
    }

    /**
     * Reconnect to server.
     */
    reconnect() {
        this.socket.connect();
    }

    /**
     * Check if connected.
     *
     * @returns {boolean}
     */
    isConnected() {
        return this.connected && this.socket.connected;
    }

    /**
     * Get connection ID.
     *
     * @returns {string}
     */
    getId() {
        return this.socket.id;
    }

    /**
     * Get joined rooms.
     *
     * @returns {Array}
     */
    getRooms() {
        return Array.from(this.rooms);
    }

    /**
     * Rejoin all rooms (after reconnection).
     */
    async rejoinRooms() {
        const promises = Array.from(this.rooms).map(room => {
            return this.join(room).catch(err => {
                console.error('[Toporia Realtime] Failed to rejoin room:', room, err);
            });
        });

        await Promise.all(promises);
    }

    /**
     * Get raw Socket.IO socket instance.
     *
     * @returns {Socket}
     */
    getSocket() {
        return this.socket;
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ToporiaRealtime;
}

// Usage examples (commented out)
/*
// Example 1: Simple chat
const realtime = new ToporiaRealtime('http://localhost:3000');

realtime.on('connected', () => {
    console.log('Connected! ID:', realtime.getId());

    // Join chat room
    realtime.join('chat.room.1');
});

realtime.on('message.sent', (data) => {
    console.log(`${data.user}: ${data.text}`);
    displayMessage(data);
});

// Send message
function sendMessage(text) {
    realtime.emit('message.sent', {
        user: currentUser.name,
        text: text,
        timestamp: Date.now()
    });
}

// Example 2: Multiple rooms
const realtime = new ToporiaRealtime('http://localhost:3000');

// Join multiple rooms
await realtime.join('notifications');
await realtime.join('presence.online');
await realtime.join('chat.room.1');

// Listen for events
realtime.on('notification.new', (data) => {
    showNotification(data.title, data.body);
});

realtime.on('user.joined', (data) => {
    updateUserList(data.user_id, 'online');
});

// Example 3: Event acknowledgments
realtime.emit('typing.started', { room: 'chat.room.1' }, (ack) => {
    console.log('Server acknowledged:', ack);
});

// Example 4: Reconnection handling
realtime.on('reconnected', ({ attempt }) => {
    console.log('Reconnected after', attempt, 'attempts');
    // Refresh data, resync state, etc.
});

realtime.on('disconnected', ({ reason }) => {
    console.log('Disconnected:', reason);
    showOfflineIndicator();
});

// Example 5: Custom namespace
const chat = new ToporiaRealtime('http://localhost:3000/chat');
const notifications = new ToporiaRealtime('http://localhost:3000/notifications');

chat.on('message', handleChatMessage);
notifications.on('alert', handleNotification);
*/
