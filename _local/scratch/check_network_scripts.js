const { spawn } = require('child_process');

async function run() {
    const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
        '--headless=new',
        '--remote-debugging-port=9222',
        '--user-data-dir=C:\\xampp\\htdocs\\casalucho\\scratch\\chrome-profile',
        '--no-first-run',
        '--no-default-browser-check'
    ]);
    await new Promise(r => setTimeout(r, 1200));

    const newTabRes = await fetch('http://localhost:9222/json/new?http://localhost/casalucho/scratch/set_session.php', { method: 'PUT' });
    const tabData = await newTabRes.json();
    const ws = new WebSocket(tabData.webSocketDebuggerUrl);

    let id = 1;
    const callbacks = new Map();
    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && callbacks.has(msg.id)) {
            callbacks.get(msg.id)(msg.result);
            callbacks.delete(msg.id);
        }
        if (msg.method === 'Network.responseReceived') {
            console.log("NET RESPONSE:", msg.params.response.status, msg.params.response.url);
        }
        if (msg.method === 'Network.loadingFailed') {
            console.error("NET FAILED:", msg.params.errorText, msg.params.requestId);
        }
    };
    function send(method, params = {}) {
        return new Promise(resolve => {
            const msgId = id++;
            callbacks.set(msgId, resolve);
            ws.send(JSON.stringify({ id: msgId, method, params }));
        });
    }
    await new Promise(r => ws.onopen = r);
    await send('Network.enable');
    await send('Page.enable');
    await send('Runtime.enable');

    await send('Page.navigate', { url: 'http://localhost/casalucho/modules/estudio_contable/dashboard_estudio.php?empresa_id=2&modulo_id=4' });
    await new Promise(r => setTimeout(r, 2500));

    const scripts = await send('Runtime.evaluate', {
        expression: `Array.from(document.querySelectorAll('script')).map(s => s.src || 'INLINE: ' + s.innerText.slice(0, 50))`,
        returnByValue: true
    });
    console.log("Scripts found in DOM:", scripts.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}
run();
