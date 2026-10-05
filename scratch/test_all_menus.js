const { spawn } = require('child_process');

async function run() {
    console.log("Starting Chrome headless...");
    const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
        '--headless=new',
        '--remote-debugging-port=9222',
        '--user-data-dir=C:\\xampp\\htdocs\\casalucho\\scratch\\chrome-profile',
        '--no-first-run',
        '--no-default-browser-check'
    ]);

    await new Promise(r => setTimeout(r, 1200));

    const newTabRes = await fetch('http://localhost:9222/json/new?http://localhost/casalucho/scratch/set_session.php?target=/casalucho/modules/estudio_contable/dashboard_estudio.php?empresa_id=2%26modulo_id=4', { method: 'PUT' });
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

    // Test Estudio Contable (Modulo 4)
    console.log("--- Testing Modulo 4 (Estudio Contable) ---");
    const mod4Click = await send('Runtime.evaluate', {
        expression: `(() => {
            const links = Array.from(document.querySelectorAll('#navigation a.nav-link'));
            const targetLink = links.find(a => a.innerText.includes('Clientes y Vencimientos'));
            if (!targetLink) return { error: 'Link not found', links: links.map(a => a.innerText.trim()) };
            
            const li = targetLink.closest('.nav-item');
            const sub = li.querySelector('.nav-treeview');
            const before = {
                menuOpen: li.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub ? sub.offsetHeight : null
            };

            targetLink.click();

            const afterSync = {
                menuOpen: li.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null
            };

            return { target: 'Clientes y Vencimientos', before, afterSync };
        })()`,
        returnByValue: true
    });
    console.log("Modulo 4 Click result:", mod4Click.result?.value);

    await new Promise(r => setTimeout(r, 500));

    const mod4After = await send('Runtime.evaluate', {
        expression: `(() => {
            const links = Array.from(document.querySelectorAll('#navigation a.nav-link'));
            const targetLink = links.find(a => a.innerText.includes('Clientes y Vencimientos'));
            const li = targetLink?.closest('.nav-item');
            const sub = li?.querySelector('.nav-treeview');
            return {
                menuOpen: li?.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub ? sub.offsetHeight : null,
                childLinks: Array.from(sub?.querySelectorAll('a') || []).map(a => a.innerText.trim())
            };
        })()`,
        returnByValue: true
    });
    console.log("Modulo 4 After 500ms:", mod4After.result?.value);

    // Test Modulo 2 (Gestión)
    console.log("\n--- Testing Modulo 2 (Gestión) ---");
    await send('Page.navigate', { url: 'http://localhost/casalucho/modules/gestion/ventas_facturas.php?empresa_id=2&modulo_id=2' });
    await new Promise(r => setTimeout(r, 2000));

    const mod2Click = await send('Runtime.evaluate', {
        expression: `(() => {
            const links = Array.from(document.querySelectorAll('#navigation a.nav-link'));
            const targetLink = links.find(a => a.innerText.includes('Stock'));
            if (!targetLink) return { error: 'Link not found', links: links.map(a => a.innerText.trim()) };
            
            const li = targetLink.closest('.nav-item');
            const sub = li.querySelector('.nav-treeview');
            const before = {
                menuOpen: li.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub ? sub.offsetHeight : null
            };

            targetLink.click();

            return { target: 'Stock', before, afterSync: { menuOpen: li.classList.contains('menu-open'), display: window.getComputedStyle(sub).display } };
        })()`,
        returnByValue: true
    });
    console.log("Modulo 2 Click result:", mod2Click.result?.value);

    await new Promise(r => setTimeout(r, 500));

    const mod2After = await send('Runtime.evaluate', {
        expression: `(() => {
            const links = Array.from(document.querySelectorAll('#navigation a.nav-link'));
            const targetLink = links.find(a => a.innerText.includes('Stock'));
            const li = targetLink?.closest('.nav-item');
            const sub = li?.querySelector('.nav-treeview');
            return {
                menuOpen: li?.classList.contains('menu-open'),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub ? sub.offsetHeight : null,
                childLinks: Array.from(sub?.querySelectorAll('a') || []).map(a => a.innerText.trim())
            };
        })()`,
        returnByValue: true
    });
    console.log("Modulo 2 After 500ms:", mod2After.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}

run().catch(e => {
    console.error("FATAL:", e);
    process.exit(1);
});
