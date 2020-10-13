class Yii2WebSockets {
    /**
     * Container for Websocket instance.
     * @type {WebSocket}
     * @public
     */
    socket;

    /**
     * One time connection token.
     * @type {string}
     * @pivate
     */
    connectToken;

    /**
     * User`s auth token.
     * @type {string}
     * @pivate
     */
    loginToken;

    /**
     * Url for connections.
     * @type {string}
     * @public
     */
    savedUrl = '';

    /**
     * Port for connection.
     * @type {string}
     * @public
     */
    savedPort = '';

    /**
     * Socket mode for connections. ws, wss.
     * @type {string}
     * @public
     */
    savedSocketMode = '';

    /**
     * Route for socket connection.
     * @type {string}
     * @public
     */
    savedRoute = '';

    /**
     * Array with actions handlers.
     * @type {array}
     * @private
     */
    actions = [];

    /**
     * User`s implementation of onOpen event.
     * @type {function}
     * @private
     */
    onOpen;

    /**
     * User`s implementation of onClose event.
     * @type {function}
     * @private
     */
    onClose;

    /**
     * Class constructor.
     * @constructor
     */
    constructor() {
        this.loginToken = $('meta[name=login-token]').attr("content");
        this.connectToken = $('meta[name=socket-token]').attr("content");
    }

    /**
     * Connect to websocket.
     * @constructor
     * @param {string} url - Url for websocket connection.
     * @param {string, int} port - port for connection. Default - 80.
     * @param {string} socketMode - Socket mode. ws or wss supported. Default - ws.
     * @param {string} route - Route to sockets.
     */
    connect(url, port= '80', socketMode = 'ws', route = '') {
        if(url !== '') {
            if(typeof port === 'number' || typeof port === 'string') {
                if(typeof socketMode === 'string') {
                    if (socketMode === 'ws' || socketMode === 'wss') {
                        if (typeof this.socket === 'undefined') {
                            this.socket = new WebSocket(socketMode + '://' + url + ':' + port+ '/' + route +'?login-token='+this.loginToken+'&connect-token='+this.connectToken);
                            this.savedUrl = url;
                            this.savedPort = port;
                            this.savedSocketMode = socketMode;
                            this.savedRoute = route;
                            this.socket.onopen = (e) => this._onOpen(e);
                            this.socket.onmessage = (e) => this._onMessage(e);
                            this.socket.onclose = (e) => this._onClose(e);
                            this.socket.onerror = (e) => this._onError(e);
                        } else {
                            console.warn('Socket already connected.');
                        }
                    } else {
                        return new TypeError('Parameter "socketMode" must be a string. ' + typeof socketMode + ' received.');
                    }
                } else {
                    return new TypeError('Parameter "socketMode" must be a string. ' + typeof socketMode + ' received.');
                }
            } else {
                return new TypeError('Parameter "port" must be a string. ' + typeof port + ' received.');
            }
        } else {
            return new TypeError('Parameter "url" can not be empty.');
        }
    }

    /**
     * Reconnect to websocket with previous credentials.
     * @constructor
     */
    reconnect() {
        if (this.socket.readyState === WebSocket.CLOSED) {
            if (this.savedUrl !== '') {
                if (typeof this.savedPort === 'number' || typeof this.savedPort === 'string') {
                    if (typeof this.savedSocketMode === 'string') {
                        if (this.savedSocketMode === 'ws' || this.savedSocketMode === 'wss') {
                            let ws_el = this;

                            $.ajax({
                                method: "POST",
                                url: requestTokenUrl+'request-socket-token',
                                type: "html",
                            }).done(function (data) {
                                if(data !== '') {
                                    ws_el.connectToken = data;
                                    ws_el.socket = new WebSocket(ws_el.savedSocketMode + '://' + ws_el.savedUrl + ':' + ws_el.savedPort+ '/' + ws_el.savedRoute +'?login-token='+ws_el.loginToken+'&connect-token='+ws_el.connectToken);
                                    ws_el.socket.onopen = (e) => ws_el._onOpen(e);
                                    ws_el.socket.onmessage = (e) => ws_el._onMessage(e);
                                    ws_el.socket.onclose = (e) => ws_el._onClose(e);
                                    ws_el.socket.onerror = (e) => ws_el._onError(e);
                                }
                            }).fail(function (jqXHR, textStatus) {
                                console.log(jqXHR);
                                sendErrorAlert(jqXHR);
                                ws_el.reconnect();
                            });
                        } else {
                            return new TypeError('Parameter "socketMode" must be a string. ' + typeof this.savedSocketMode + ' received.');
                        }
                    } else {
                        return new TypeError('Parameter "socketMode" must be a string. ' + typeof this.savedSocketMode + ' received.');
                    }
                } else {
                    return new TypeError('Parameter "port" must be a string. ' + typeof this.savedPort + ' received.');
                }
            } else {
                return new TypeError('Parameter "url" can not be empty.');
            }
        } else {
            return new Error('Socket ');
        }
    }

    /**
     * Initial function for socket connection.
     * @constructor
     */
    _onOpen(event) {
        console.log('Socket connection established.');
        if(typeof this.onOpen === 'function') {
            this.onOpen(event);
        }
    }

    /**
     * Wrapping for raw messages from server.
     * @constructor
     */
    _onMessage(e) {
        try {
            let data = JSON.parse(e.data);
            if(data.status == 1) {
                if("action" in data) {
                    if (data.action in this.actions) {
                        this.actions[data.action](data.data);
                    } else {
                        this.socket.onerror(new Error('Server calling for unknown action "'+data.action+'".'));
                    }
                } else {
                    this.socket.onerror(new Error('No "action" field provided in server\'s message.'));
                }
            } else if(data.status == 2) {
                this.socket.onerror(new Error(data.data.errorText));
            }
        } catch (error) {
            console.log(error);
            console.log(e.data);
        }
        //this.socket.send('{"type":"message", "text": "Im connected!"}');
    }

    /**
     * Handling of close event for socket connection. Reconnection if needed.
     * @constructor
     */
    _onClose (event) {
        if (event.wasClean) {
            console.log('Соединение закрыто чисто');
        } else {
            if(event.code === 1000) {
                console.log('Соединение закрыто чисто');
            } else {
                console.log('Соединение закрыто с проблемой'); // например, "убит" процесс сервера
            }
            console.log('Код: ' + event.code + ' причина: ' + event.reason);
            //console.log(event);
            if (event.code === 1006) {
                setTimeout(ws.reconnect(), 1000);
            }
        }
        if(typeof this.onClose === 'function') {
            this.onClose(event);
        }
    }

    /**
     * Handling of error events.
     * @constructor
     */
    _onError (error) {
        console.log("Ошибка: " + error.message);
    };

    /**
     * Wrapper function for socket sending.
     * @constructor
     * @param {string} action - Action name.
     * @param {object} data - Data payload for sending. Should be a valid JS object for JSON parsing.
     */
    socketSend(action, data= {}) {
        if (typeof action === 'string') {
            if (typeof data === 'object') {
                this.socket.send('{"action":"' + action + '", "data": ' + JSON.stringify(data) + '}');
                console.log('Socket message send');
            } else {
                return new TypeError('Parameter "data" must be a object. ' + typeof action + ' received.');
            }
        } else {
            return new TypeError('Parameter "action" must be a string. ' + typeof action + ' received.');
        }
    }

    /**
     * Bind new action for server calling.
     * @constructor
     * @param {string} name - Action name.
     * @param {function} action - Callback function for action handling.
     */
    addAction(name, action) {
        this.actions[name] = (e) => action(e);
    }
}
const ws = new Yii2WebSockets();
