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
    await new Promise(r => setTimeout(r, 2000));

    // Let's test what happens when we trace the click in JavaScript
    const trace = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            const targetLi = nav?.querySelectorAll(':scope > .nav-item')[1]; // Clientes y Vencimientos
            const link = targetLi?.querySelector('.nav-link');
            
            // Check adminlte object
            const hasAdminlte = typeof window.adminlte !== 'undefined';
            const adminlteKeys = hasAdminlte ? Object.keys(window.adminlte) : [];
            
            // Check what happens if we instantiate Treeview or call its logic manually
            let manualTest = null;
            try {
                if (window.adminlte && window.adminlte.Treeview) {
                    const tv = new window.adminlte.Treeview(targetLi, { accordion: true, animationSpeed: 300 });
                    tv.toggle();
                    manualTest = {
                        called: true,
                        isOpenNow: targetLi.classList.contains('menu-open')
                    };
                } else {
                    manualTest = { error: 'adminlte.Treeview not on window.adminlte', keys: adminlteKeys };
                }
            } catch(e) {
                manualTest = { error: e.message, stack: e.stack };
            }

            // Check what selector adminlte uses
            const matchesToggle = nav.matches('[data-lte-toggle="treeview"]');
            const lteAttr = nav.getAttribute('data-lte-toggle');

            return {
                hasAdminlte,
                adminlteKeys,
                matchesToggle,
                lteAttr,
                manualTest
            };
        })()`,
        returnByValue: true
    });
    console.log("Trace result:", trace.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}
run();
