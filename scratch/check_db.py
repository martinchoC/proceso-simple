import re

with open('casalucho_local.sql', 'r', encoding='utf-8', errors='ignore') as f:
    text = f.read()

found = re.findall(r'CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`', text, re.IGNORECASE)
print(f"Total tables: {len(found)}")
g_tables = [t for t in found if t.startswith('gestion__')]
print(f"Count of gestion__ tables: {len(g_tables)}")
print("Sample gestion__ tables in casalucho_local.sql:")
for t in sorted(g_tables):
    print(f"  {t}")
