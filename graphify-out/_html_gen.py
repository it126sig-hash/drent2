import json
import networkx as nx
from networkx.readwrite import json_graph
from graphify.export import to_html
from pathlib import Path

data = json.loads(Path('graphify-out/graph.json').read_text())
G = json_graph.node_link_graph(data, edges='links')
labels_raw = json.loads(Path('graphify-out/.graphify_labels.json').read_text()) if Path('graphify-out/.graphify_labels.json').exists() else {}
analysis = json.loads(Path('graphify-out/.graphify_analysis.json').read_text())
communities = {int(k): v for k, v in analysis['communities'].items()}
labels = {int(k): v for k, v in labels_raw.items()}

if G.number_of_nodes() > 5000:
    print('Graph has {} nodes - over 5000 limit. Attempting HTML generation anyway...'.format(G.number_of_nodes()))

try:
    to_html(G, communities, 'graphify-out/graph.html', community_labels=labels or None, node_limit=5000)
    print('graph.html written - open in any browser, no server needed')
except Exception as e:
    print('HTML generation failed: {}'.format(e))
