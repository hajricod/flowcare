<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlowCare Queue SSE Demo</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #18212f;
            --muted: #5b687a;
            --line: #d7dde7;
            --ok: #0c7a43;
            --warn: #af6a00;
            --err: #a11f1f;
            --accent: #0f4cbd;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(1200px 600px at 15% -10%, #dce8ff 0%, transparent 60%),
                        radial-gradient(900px 500px at 110% 10%, #e8fff2 0%, transparent 55%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 24px;
        }

        .wrap {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
            margin-bottom: 16px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 26px;
        }

        p {
            margin: 0;
            color: var(--muted);
        }

        .controls {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 13px;
            color: var(--muted);
        }

        .field input {
            height: 38px;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 0 10px;
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        button {
            border: 0;
            border-radius: 10px;
            height: 38px;
            padding: 0 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-start { background: var(--accent); color: #fff; }
        .btn-stop { background: #e9edf5; color: #223147; }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .stat {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
            background: #fbfcff;
        }

        .stat .k {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .stat .v {
            font-size: 26px;
            font-weight: 700;
        }

        .status-ok { color: var(--ok); }
        .status-warn { color: var(--warn); }
        .status-err { color: var(--err); }

        pre {
            margin: 0;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            background: #0f172a;
            color: #d6e2ff;
            min-height: 220px;
            max-height: 360px;
            overflow: auto;
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 800px) {
            .controls { grid-template-columns: 1fr 1fr; }
            .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Queue SSE Demo</h1>
        <p>Open a live stream for one branch and watch queue updates in real time.</p>

        <div class="controls">
            <div class="field">
                <label for="branch">Branch ID</label>
                <input id="branch" value="br_muscat_001">
            </div>
            <div class="field">
                <label for="interval">Interval (sec)</label>
                <input id="interval" type="number" min="1" max="10" value="2">
            </div>
            <div class="field">
                <label for="duration">Duration (sec)</label>
                <input id="duration" type="number" min="10" max="300" value="60">
            </div>
            <div class="field">
                <label for="autostart">Auto reconnect</label>
                <input id="autostart" type="number" min="0" max="1" value="1">
            </div>
        </div>

        <div class="actions">
            <button class="btn-start" id="startBtn">Start Stream</button>
            <button class="btn-stop" id="stopBtn">Stop Stream</button>
        </div>
    </div>

    <div class="card stats">
        <div class="stat">
            <div class="k">Connection Status</div>
            <div id="status" class="v status-warn">IDLE</div>
        </div>
        <div class="stat">
            <div class="k">Live Queue Number</div>
            <div id="queue" class="v">-</div>
        </div>
        <div class="stat">
            <div class="k">Updated At</div>
            <div id="updated" class="v" style="font-size:16px; font-weight:600;">-</div>
        </div>
    </div>

    <div class="card">
        <pre id="log"></pre>
    </div>
</div>

<script>
    let source = null;

    const statusEl = document.getElementById('status');
    const queueEl = document.getElementById('queue');
    const updatedEl = document.getElementById('updated');
    const logEl = document.getElementById('log');

    const branchEl = document.getElementById('branch');
    const intervalEl = document.getElementById('interval');
    const durationEl = document.getElementById('duration');
    const autostartEl = document.getElementById('autostart');

    function log(message) {
        const line = `[${new Date().toISOString()}] ${message}`;
        logEl.textContent += line + "\n";
        logEl.scrollTop = logEl.scrollHeight;
    }

    function setStatus(text, cls) {
        statusEl.textContent = text;
        statusEl.className = `v ${cls}`;
    }

    function closeStream() {
        if (source) {
            source.close();
            source = null;
            setStatus('CLOSED', 'status-warn');
            log('Connection closed by client.');
        }
    }

    function openStream() {
        closeStream();

        const branch = branchEl.value.trim();
        const interval = Math.min(10, Math.max(1, Number(intervalEl.value || 2)));
        const duration = Math.min(300, Math.max(10, Number(durationEl.value || 60)));

        if (!branch) {
            setStatus('ERROR', 'status-err');
            log('Branch ID is required.');
            return;
        }

        const url = `/api/branches/${encodeURIComponent(branch)}/queue/stream?interval=${interval}&duration=${duration}`;
        source = new EventSource(url);

        setStatus('CONNECTING', 'status-warn');
        log(`Opening stream: ${url}`);

        source.onopen = () => {
            setStatus('CONNECTED', 'status-ok');
            log('SSE connection established.');
        };

        source.addEventListener('queue.update', (event) => {
            const payload = JSON.parse(event.data);
            queueEl.textContent = String(payload.live_queue_number);
            updatedEl.textContent = payload.timestamp;
            log(`queue.update => live_queue_number=${payload.live_queue_number}`);
        });

        source.addEventListener('queue.end', () => {
            setStatus('ENDED', 'status-warn');
            log('queue.end received from server.');
            source.close();
            source = null;

            if (Number(autostartEl.value || 1) === 1) {
                log('Auto reconnecting in 1 second...');
                setTimeout(openStream, 1000);
            }
        });

        source.onerror = () => {
            setStatus('RETRYING', 'status-warn');
            log('SSE connection issue, browser will retry automatically.');
        };
    }

    document.getElementById('startBtn').addEventListener('click', openStream);
    document.getElementById('stopBtn').addEventListener('click', closeStream);
</script>
</body>
</html>
