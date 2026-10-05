<?php
$lines = file('C:/Users/devel/.gemini/antigravity-ide/brain/efea6006-64fe-473c-9b39-aa0f14d5b33b/.system_generated/logs/transcript.jsonl');
$j = json_decode($lines[169], true);
file_put_contents('scratch/step_169_prompt.txt', $j['content'] ?? '');
echo "Saved, length: " . strlen($j['content'] ?? '') . "\n";
