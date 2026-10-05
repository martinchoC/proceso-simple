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

    const clickTest = await send('Runtime.evaluate', {
        expression: `(() => {
            const links = Array.from(document.querySelectorAll('#navigation .nav-item > .nav-link'));
            const stockLink = links.find(l => l.innerText.includes('Stock'));
            if (!stockLink) return 'NO_STOCK_LINK';
            const li = stockLink.closest('.nav-item');
            const sub = li.querySelector('.nav-treeview');
            const beforeDisplay = window.getComputedStyle(sub).display;
            const beforeMenuOpen = li.classList.contains('menu-open');

            stockLink.click();

            return {
                before: { menuOpen: beforeMenuOpen, display: beforeDisplay },
                afterSync: { menuOpen: li.classList.contains('menu-open'), display: window.getComputedStyle(sub).display }
            };
        })()`,
        returnByValue: true
    });
    console.log("Stock click test in Modulo 2:", clickTest.result?.value);

    await new Promise(r => setTimeout(r, 500));

    const afterWait = await send('Runtime.evaluate', {
        expression: `(() => {
            const links = Array.from(document.querySelectorAll('#navigation .nav-item > .nav-link'));
            const stockLink = links.find(l => l.innerText.includes('Stock'));
            const li = stockLink?.closest('.nav-item');
            const sub = li?.querySelector('.nav-treeview');
            return {
                menuOpen: li?.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub?.offsetHeight
            };
        })()`,
        returnByValue: true
    });
    console.log("Stock after wait 500ms:", afterWait.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}
run();
