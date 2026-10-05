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

    // Create a new tab
    const newTabRes = await fetch('http://localhost:9222/json/new?http://localhost/casalucho/scratch/set_session.php', { method: 'PUT' });
    const tabData = await newTabRes.json();
    console.log("Tab created:", tabData.id);

    const ws = new WebSocket(tabData.webSocketDebuggerUrl);

    let id = 1;
    const callbacks = new Map();
    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && callbacks.has(msg.id)) {
            callbacks.get(msg.id)(msg.result);
            callbacks.delete(msg.id);
        }
        if (msg.method === 'Runtime.consoleAPICalled') {
            console.log("BROWSER CONSOLE:", msg.params.type, msg.params.args.map(a => a.value));
        }
        if (msg.method === 'Runtime.exceptionThrown') {
            console.error("BROWSER EXCEPTION:", msg.params.exceptionDetails?.text, msg.params.exceptionDetails?.exception);
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
    console.log("Connected to Tab WS!");

    await send('Page.enable');
    await send('Runtime.enable');

    // Wait for redirect to finish
    await new Promise(r => setTimeout(r, 2000));

    const pageInfo = await send('Runtime.evaluate', {
        expression: `({
            url: window.location.href,
            title: document.title,
            hasNav: !!document.querySelector('#navigation'),
            navItemCount: document.querySelectorAll('#navigation .nav-item').length
        })`,
        returnByValue: true
    });
    console.log("Page info after session set:", pageInfo.result?.value);

    // Test menu treeview items
    const testMenu = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            if (!nav) return { error: 'No #navigation found' };
            
            const items = Array.from(nav.querySelectorAll(':scope > .nav-item'));
            const treeviews = items.map(li => {
                const link = li.querySelector('.nav-link');
                const text = link?.querySelector('p')?.innerText.trim() || '';
                const sub = li.querySelector('.nav-treeview');
                return {
                    text,
                    hasSubmenu: !!sub,
                    isMenuOpen: li.classList.contains('menu-open'),
                    subDisplay: sub ? window.getComputedStyle(sub).display : null,
                    subHeight: sub ? sub.offsetHeight : null
                };
            });

            // Find first item with submenu
            const targetLi = items.find(li => li.querySelector('.nav-treeview'));
            let clickTest = null;
            if (targetLi) {
                const link = targetLi.querySelector('.nav-link');
                const sub = targetLi.querySelector('.nav-treeview');
                const text = link?.querySelector('p')?.innerText.trim();
                
                // Before
                const before = {
                    isMenuOpen: targetLi.classList.contains('menu-open'),
                    display: window.getComputedStyle(sub).display,
                    height: sub.offsetHeight
                };

                // Dispatch click
                link.click();

                // Immediately after
                const afterSync = {
                    isMenuOpen: targetLi.classList.contains('menu-open'),
                    display: window.getComputedStyle(sub).display,
                    height: sub.offsetHeight,
                    inlineStyle: sub.getAttribute('style')
                };

                clickTest = { text, before, afterSync };
            }

            return { treeviews, clickTest };
        })()`,
        returnByValue: true
    });
    console.log("Menu click evaluation:", JSON.stringify(testMenu.result?.value, null, 2));

    // Wait 500ms for animation
    await new Promise(r => setTimeout(r, 600));

    const afterAnim = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            const targetLi = nav?.querySelector(':scope > .nav-item:has(.nav-treeview)');
            if (!targetLi) return null;
            const sub = targetLi.querySelector('.nav-treeview');
            return {
                isMenuOpen: targetLi.classList.contains('menu-open'),
                display: window.getComputedStyle(sub).display,
                height: sub.offsetHeight,
                inlineStyle: sub.getAttribute('style')
            };
        })()`,
        returnByValue: true
    });
    console.log("After animation (600ms):", afterAnim.result?.value);

    // Let's also test Módulo 2
    console.log("\n--- Testing Módulo 2 (Gestión) ---");
    await send('Page.navigate', { url: 'http://localhost/casalucho/modules/gestion/ventas_facturas.php?empresa_id=2&modulo_id=2' });
    await new Promise(r => setTimeout(r, 2000));

    const mod2Test = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            if (!nav) return { error: 'No #navigation in Modulo 2' };
            const items = Array.from(nav.querySelectorAll(':scope > .nav-item'));
            const closedTree = items.find(li => li.querySelector('.nav-treeview') && !li.classList.contains('menu-open'));
            if (!closedTree) {
                return {
                    status: 'No closed treeview found',
                    items: items.map(li => ({
                        text: li.querySelector('.nav-link p')?.innerText.trim(),
                        open: li.classList.contains('menu-open')
                    }))
                };
            }
            const link = closedTree.querySelector('.nav-link');
            const sub = closedTree.querySelector('.nav-treeview');
            const text = link?.querySelector('p')?.innerText.trim();
            const beforeDisplay = window.getComputedStyle(sub).display;

            link.click();

            return {
                clicked: text,
                beforeDisplay,
                afterSyncOpen: closedTree.classList.contains('menu-open'),
                afterSyncDisplay: window.getComputedStyle(sub).display,
                afterSyncInlineStyle: sub.getAttribute('style')
            };
        })()`,
        returnByValue: true
    });
    console.log("Mod 2 click:", mod2Test.result?.value);

    await new Promise(r => setTimeout(r, 600));
    const mod2After = await send('Runtime.evaluate', {
        expression: `(() => {
            const nav = document.querySelector('#navigation');
            const clickedLi = nav?.querySelector(':scope > .nav-item.menu-open');
            const sub = clickedLi?.querySelector('.nav-treeview');
            return {
                openItem: clickedLi?.querySelector('.nav-link p')?.innerText.trim(),
                display: sub ? window.getComputedStyle(sub).display : null,
                height: sub ? sub.offsetHeight : null,
                inlineStyle: sub?.getAttribute('style')
            };
        })()`,
        returnByValue: true
    });
    console.log("Mod 2 after animation:", mod2After.result?.value);

    ws.close();
    chrome.kill();
    process.exit(0);
}

run().catch(e => {
    console.error("FATAL:", e);
    process.exit(1);
});
