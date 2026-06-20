import json
import networkx as nx
from networkx.readwrite import json_graph
from graphify.build import build_from_json
from graphify.export import to_json
from pathlib import Path

# Backup old graph
old_path = Path('graphify-out/graph.json')
if old_path.exists():
    import shutil
    shutil.copy(old_path, 'graphify-out/.graphify_old.json')

# Load new extraction
new_extraction = json.loads(Path('graphify-out/.graphify_extract.json').read_text())

# Build new graph from new extraction
G_new = build_from_json(new_extraction)

# Try to load existing graph and merge
if old_path.exists():
    existing_data = json.loads(old_path.read_text())
    G_existing = json_graph.node_link_graph(existing_data, edges='links')
    
    # Remove deleted nodes (nodes from deleted files)
    incremental = json.loads(Path('graphify-out/.graphify_incremental.json').read_text())
    deleted_files = set(incremental.get('deleted_files', []))
    
    # Prune nodes whose source_file is in deleted_files
    nodes_to_remove = []
    for nid, ndata in G_existing.nodes(data=True):
        src = ndata.get('source_file', '')
        # Normalize: compare basename patterns
        for df in deleted_files:
            if df.replace('\\', '/') in src.replace('\\', '/') or src.replace('\\', '/') in df.replace('\\', '/'):
                nodes_to_remove.append(nid)
                break
    
    G_existing.remove_nodes_from(nodes_to_remove)
    print('Pruned {} nodes from deleted files'.format(len(nodes_to_remove)))
    
    # Merge new nodes/edges into existing
    G_existing.update(G_new)
    G_final = G_existing
else:
    G_final = G_new

print('Final graph: {} nodes, {} edges'.format(G_final.number_of_nodes(), G_final.number_of_edges()))

# Save merged extraction for downstream steps
to_json(G_final, {}, 'graphify-out/graph.json', force=True)
print('Saved merged graph.json')
