// Live TV player. Plays HLS (.m3u8) with hls.js where the browser lacks native
// support, and shows a retry panel if the broadcast server does not respond.

export async function startLivePlayer(video) {
    const status = document.getElementById('stream-status');
    const fallback = document.getElementById('stream-fallback');
    const retry = document.getElementById('retry-stream');
    const source = video.dataset.hls;
    const liveText = video.dataset.liveText;
    const Hls = source && !video.canPlayType('application/vnd.apple.mpegurl')
        ? (await import('hls.js')).default
        : null;

    const MAX_RECONNECTS = 5;

    let hls;
    let connectionTimer;
    let reconnects = 0;

    const setStatus = (message) => { status.textContent = message; };
    const clearConnectionTimer = () => window.clearTimeout(connectionTimer);
    const showFallback = () => {
        clearConnectionTimer();
        fallback.hidden = false;
        setStatus('Unable to connect to the live stream.');
    };
    const beginConnectionTimer = () => {
        clearConnectionTimer();
        connectionTimer = window.setTimeout(showFallback, 15000);
    };
    const play = () => video.play().then(() => {
        clearConnectionTimer();
        fallback.hidden = true;
        setStatus(liveText);
    }).catch(() => {
        clearConnectionTimer();
        setStatus('LIVE - Press Play to start');
    });

    video.addEventListener('playing', () => setStatus(liveText));
    video.addEventListener('waiting', () => {
        setStatus('Buffering…');
        beginConnectionTimer();
    });
    video.addEventListener('error', showFallback);

    const start = () => {
        fallback.hidden = true;
        setStatus('Connecting to live stream...');
        beginConnectionTimer();

        if (!Hls) {
            if (source) {
                video.src = source;
            }
            video.load();
            video.addEventListener('loadedmetadata', play, { once: true });
            return;
        }

        if (!Hls.isSupported()) {
            clearConnectionTimer();
            setStatus('Your browser does not support HLS live streaming.');
            return;
        }

        hls?.destroy();
        reconnects = 0;
        hls = new Hls({
            enableWorker: true,
            lowLatencyMode: true,
            backBufferLength: 30,
            manifestLoadingTimeOut: 10000,
            levelLoadingTimeOut: 10000,
            fragLoadingTimeOut: 15000,
        });
        hls.loadSource(source);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, play);
        hls.on(Hls.Events.FRAG_LOADED, () => { reconnects = 0; });
        hls.on(Hls.Events.ERROR, (event, data) => {
            if (!data.fatal) {
                return;
            }

            // Network drops: keep reconnecting a few times before offering a manual retry.
            if (data.type === Hls.ErrorTypes.NETWORK_ERROR && reconnects < MAX_RECONNECTS) {
                reconnects++;
                setStatus('Stream connection problem. Reconnecting...');
                beginConnectionTimer();
                hls.startLoad();
            } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                hls.recoverMediaError();
            } else if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                showFallback();
            } else {
                // Anything else: rebuild the player from scratch after a short pause.
                hls.destroy();
                setStatus('Stream connection problem. Reconnecting...');
                window.setTimeout(start, 3000);
            }
        });
    };

    retry.addEventListener('click', start);
    start();
}
