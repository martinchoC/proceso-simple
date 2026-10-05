import os
import re
import json

models = {}
gestion_dir = 'modules/gestion'

for filename in sorted(os.listdir(gestion_dir)):
    if filename.endswith('_model.php'):
        path = os.path.join(gestion_dir, filename)
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        # Find functions
        funcs = re.findall(r'function\s+([a-zA-Z0-9_]+)\s*\(', content)
        # Find tables mentioned
        tables = set(re.findall(r'(?:FROM|INTO|UPDATE|JOIN)\s+([a-zA-Z0-9_]+)', content, re.IGNORECASE))
        tables = {t for t in tables if t.startswith('gestion__') or t.startswith('conf__')}
        
        models[filename] = {
            'lines': len(content.splitlines()),
            'functions': funcs,
            'tables': sorted(list(tables))
        }

print(f"Total models analyzed: {len(models)}")
for m, info in sorted(models.items(), key=lambda x: x[1]['lines'], reverse=True)[:25]:
    print(f"{m:<35} | {info['lines']:>5} lines | {len(info['functions']):>2} funcs | Tables: {', '.join(info['tables'][:3])}...")

with open('scratch/models_summary.json', 'w', encoding='utf-8') as f:
    json.dump(models, f, indent=2, ensure_ascii=False)
