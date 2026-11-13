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
    await send('Network.enable');
    await send('Page.enable');
    await send('Runtime.enable');

    await new Promise(r => setTimeout(r, 2500));

    const res = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            const hasAdminlte = typeof window.adminlte !== 'undefined';
            const scripts = Array.from(document.querySelectorAll('script')).map(s => s.src || s.innerText.slice(0, 30));
            
            // Try clicking Stock (which is closed)
            const items = Array.from(nav.querySelectorAll(':scope > .nav-item'));
            const stockLi = items.find(li => li.querySelector('.nav-link p')?.innerText.trim().startsWith('Stock'));
            const link = stockLi?.querySelector('.nav-link');
            const sub = stockLi?.querySelector('.nav-treeview');
            
            const before = {
                menuOpen: stockLi?.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null
            };

            if (link) link.click();

            const afterSync = {
                menuOpen: stockLi?.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null
            };

            return {
                url: window.location.href,
                hasAdminlte,
                scriptsCount: scripts.length,
                before,
                afterSync
            };
        })()`,
        returnByValue: true
    });
    console.log("Modulo 2 test:", res.result?.value);

    // Wait 500ms
    await new Promise(r => setTimeout(r, 600));

    const afterWait = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            const items = Array.from(nav.querySelectorAll(':scope > .nav-item'));
            const stockLi = items.find(li => li.querySelector('.nav-link p')?.innerText.trim().startsWith('Stock'));
            const sub = stockLi?.querySelector('.nav-treeview');
            return {
                menuOpen: stockLi?.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub?.offsetHeight
            };
        })()`,
        returnByValue: true
    });
    console.log("After wait 600ms:", afterWait.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}
run();
