<?php
$f = fopen('C:/Users/devel/.gemini/antigravity-ide/brain/efea6006-64fe-473c-9b39-aa0f14d5b33b/.system_generated/logs/transcript_full.jsonl', 'r');
while (($line = fgets($f)) !== false) {
    if (strpos($line, '"step_index":169') !== false) {
        $data = json_decode($line, true);
        file_put_contents('scratch/step_169_full.txt', $data['content'] ?? '');
        echo "Found step 169 in transcript_full. Length: " . strlen($data['content'] ?? '') . "\n";
        break;
    }
}
fclose($f);
