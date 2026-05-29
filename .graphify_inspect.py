import json
from pathlib import Path

a = json.loads(Path('.graphify_analysis.json').read_text())
extraction = json.loads(Path('.graphify_extract.json').read_text())

# Build id -> (label, source_file) lookup
nodes = {n['id']: n for n in extraction['nodes']}
comms = {int(k): v for k, v in a['communities'].items()}

# Sort by size
sorted_comms = sorted(comms.items(), key=lambda x: -len(x[1]))

for cid, members in sorted_comms[:60]:
    labels = []
    sources = set()
    for nid in members:
        n = nodes.get(nid, {})
        lbl = n.get('label', nid)
        src = n.get('source_file', '')
        labels.append(lbl)
        if src:
            # show top folders
            parts = src.replace('\\\\', '/').split('/')
            if len(parts) > 2:
                sources.add('/'.join(parts[:3]))
            else:
                sources.add(src)
    print(f"=== Community {cid} ({len(members)} nodes) ===")
    print("  sources:", sorted(list(sources))[:6])
    print("  labels:", labels[:15])
    print()
