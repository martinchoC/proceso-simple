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

    const newTabRes = await fetch('http://localhost:9222/json/new?http://localhost/casalucho/scratch/set_session.php?target=/casalucho/modules/estudio_contable/estudio_clientes.php?empresa_id=2%26modulo_id=4', { method: 'PUT' });
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

    console.log("Testing estudio_clientes.php...");
    const clientesTest = await send('Runtime.evaluate', {
        expression: `(() => {
            const btnGroup = document.querySelector('.card-header .btn-group');
            const btns = btnGroup ? Array.from(btnGroup.querySelectorAll('button')).map(b => ({
                id: b.id,
                title: b.title,
                icon: b.querySelector('i')?.className,
                classes: b.className
            })) : [];

            // Check if default .dt-buttons is in the page
            const dtButtons = document.querySelectorAll('.dt-buttons');

            // Test clicking Excel button
            let excelTriggered = false;
            if (window.tablaClientes) {
                // hook into button
                const btn = document.querySelector('#btnExportarExcel');
                if (btn) {
                    btn.click();
                    excelTriggered = true;
                }
            }

            return {
                hasBtnGroup: !!btnGroup,
                buttonsCount: btns.length,
                buttons: btns,
                hasVisibleDtButtons: Array.from(dtButtons).some(b => b.offsetWidth > 0 && b.offsetHeight > 0),
                excelTriggered
            };
        })()`,
        returnByValue: true
    });
    console.log("estudio_clientes test:", JSON.stringify(clientesTest.result?.value, null, 2));

    // Test vencimientos_tablero.php
    console.log("\nTesting vencimientos_tablero.php...");
    await send('Page.navigate', { url: 'http://localhost/casalucho/modules/estudio_contable/vencimientos_tablero.php?empresa_id=2&modulo_id=4' });
    await new Promise(r => setTimeout(r, 2500));

    const vencTest = await send('Runtime.evaluate', {
        expression: `(() => {
            const btnGroup = document.querySelector('.card-header .btn-group');
            const btns = btnGroup ? Array.from(btnGroup.querySelectorAll('button')).map(b => ({
                id: b.id,
                title: b.title,
                icon: b.querySelector('i')?.className
            })) : [];
            return {
                hasBtnGroup: !!btnGroup,
                buttonsCount: btns.length,
                buttons: btns
            };
        })()`,
        returnByValue: true
    });
    console.log("vencimientos_tablero test:", JSON.stringify(vencTest.result?.value, null, 2));

    ws.close();
    chrome.kill();
    process.exit(0);
}

run().catch(e => {
    console.error("FATAL:", e);
    process.exit(1);
});
