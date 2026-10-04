import subprocess
import re

# Get live tables
cmd = ["C:\\xampp\\mysql\\bin\\mysql.exe", "-u", "root", "gestion_multipyme", "-e", "SHOW TABLES;"]
p = subprocess.run(cmd, capture_output=True, text=True)
live_tables = set(line.strip() for line in p.stdout.splitlines()[1:] if line.strip())

# Get casalucho_local.sql tables
with open('casalucho_local.sql', 'r', encoding='utf-8', errors='ignore') as f:
    text = f.read()

dump_tables = set(re.findall(r'CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`', text, re.IGNORECASE))

print(f"Total live tables: {len(live_tables)}")
print(f"Total dump tables: {len(dump_tables)}")

only_in_live = sorted(list(live_tables - dump_tables))
only_in_dump = sorted(list(dump_tables - live_tables))

print(f"\nTables ONLY in live MariaDB ({len(only_in_live)}):")
for t in only_in_live:
    print(f"  + {t}")

print(f"\nTables ONLY in casalucho_local.sql ({len(only_in_dump)}):")
for t in only_in_dump:
    print(f"  - {t}")
