const { spawn } = require('child_process');

async function run() {
    const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
        '--headless=new',
        '--remote-debugging-port=9222',
        '--user-data-dir=C:\\xampp\\htdocs\\casalucho\\scratch\\chrome-profile',
        '--no-first-run',
        '--no-default-browser-check'
    ]);
    await new Promise(r => setTimeout(r, 1000));

    const newTabRes = await fetch('http://localhost:9222/json/new?http://localhost/casalucho/login.php', { method: 'PUT' });
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
    await new Promise(r => setTimeout(r, 1500));

    // What's the password in the database for pablo?
    // Let's check login page elements
    const formInfo = await send('Runtime.evaluate', {
        expression: `(() => {
            return {
                url: window.location.href,
                inputs: Array.from(document.querySelectorAll('input')).map(i => ({ name: i.name, id: i.id, type: i.type })),
                buttons: Array.from(document.querySelectorAll('button')).map(b => ({ text: b.innerText, type: b.type }))
            };
        })()`,
        returnByValue: true
    });
    console.log("Form info:", formInfo.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}
run();
