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

    const items = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            return Array.from(nav.querySelectorAll(':scope > .nav-item')).map(li => {
                const link = li.querySelector('.nav-link');
                const sub = li.querySelector('.nav-treeview');
                return {
                    text: link ? link.querySelector('p')?.innerText.trim() : '',
                    href: link ? link.getAttribute('href') : '',
                    menuOpen: li.classList.contains('menu-open'),
                    hasSub: !!sub,
                    subDisplay: sub ? window.getComputedStyle(sub).display : null
                };
            });
        })()`,
        returnByValue: true
    });
    console.log("Nav items in Modulo 2:", items.result?.value);

    // Let's click on the first item with hasSub that is NOT menuOpen
    const clickItem = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            const targetLi = Array.from(nav.querySelectorAll(':scope > .nav-item')).find(li => li.querySelector('.nav-treeview') && !li.classList.contains('menu-open'));
            if (!targetLi) return 'NO_CLOSED_TREEVIEW';
            const link = targetLi.querySelector('.nav-link');
            const text = link.querySelector('p')?.innerText.trim();
            const sub = targetLi.querySelector('.nav-treeview');
            
            const beforeDisplay = window.getComputedStyle(sub).display;
            link.click();
            return {
                text,
                beforeDisplay,
                afterSyncMenuOpen: targetLi.classList.contains('menu-open'),
                afterSyncDisplay: window.getComputedStyle(sub).display
            };
        })()`,
        returnByValue: true
    });
    console.log("Click closed item in Modulo 2:", clickItem.result?.value);

    await new Promise(r => setTimeout(r, 600));

    const afterWait = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            const targetLi = Array.from(nav.querySelectorAll(':scope > .nav-item')).find(li => li.querySelector('.nav-treeview') && li.classList.contains('menu-open'));
            const sub = targetLi?.querySelector('.nav-treeview');
            return {
                text: targetLi?.querySelector('.nav-link p')?.innerText.trim(),
                menuOpen: targetLi?.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub?.offsetHeight
            };
        })()`,
        returnByValue: true
    });
    console.log("After animation wait:", afterWait.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}
run();
