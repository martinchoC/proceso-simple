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

    const newTabRes = await fetch('http://localhost:9222/json/new?http://localhost/casalucho/scratch/set_session.php?target=/casalucho/modules/gestion/ventas_facturas.php?empresa_id=2%26modulo_id=2', { method: 'PUT' });
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
    };
    function send(method, params = {}) {
        return new Promise(resolve => {
            const msgId = id++;
            callbacks.set(msgId, resolve);
            ws.send(JSON.stringify({ id: msgId, method, params }));
        });
    }
    await new Promise(r => ws.onopen = r);
    await send('Page.enable');
    await send('Runtime.enable');
    await new Promise(r => setTimeout(r, 2500));

    const aside = await send('Runtime.evaluate', {
        expression: `(() => {
            const sidebar = document.querySelector('.sidebar-wrapper');
            return {
                html: sidebar ? sidebar.innerHTML : 'NO_SIDEBAR_WRAPPER',
                bodyText: document.body.innerText.slice(0, 300)
            };
        })()`,
        returnByValue: true
    });
    console.log("Sidebar HTML:", aside.result?.value?.html?.slice(0, 1500));
    console.log("Body text:", aside.result?.value?.bodyText);

    ws.close();
    chrome.kill();
    process.exit(0);
}
run();
