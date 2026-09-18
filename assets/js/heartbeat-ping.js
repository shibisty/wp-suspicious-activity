(function () {
    if (typeof WP_SAD_Heartbeat === 'undefined') {
        return;
    }

    var endpoint = WP_SAD_Heartbeat.endpoint;
    var interval = parseInt(WP_SAD_Heartbeat.interval, 10) || 60000;

    function ping() {
        if (document.visibilityState !== 'visible') {
            return;
        }
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true
        }).catch(function () {
            // мовчки ігноруємо мережеві помилки пінгу — це не критично
        });
    }

    ping();
    setInterval(ping, interval);
})();
