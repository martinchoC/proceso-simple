<?php
$lines = file('C:/Users/devel/.gemini/antigravity-ide/brain/efea6006-64fe-473c-9b39-aa0f14d5b33b/.system_generated/logs/transcript.jsonl');
foreach ($lines as $i => $l) {
    $j = json_decode($l, true);
    if (($j['type'] ?? '') === 'USER_INPUT') {
        echo "Line " . ($i + 1) . " Step " . ($j['step_index'] ?? '') . ":\n";
        echo substr(trim($j['content'] ?? ''), 0, 300) . "\n---\n";
    }
}
