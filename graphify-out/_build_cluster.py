import json
import networkx as nx
from networkx.readwrite import json_graph
from graphify.cluster import cluster, score_all
from graphify.analyze import god_nodes, surprising_connections, suggest_questions
from graphify.report import generate
from graphify.export import to_json
from pathlib import Path

# Load the merged graph we just saved
data = json.loads(Path('graphify-out/graph.json').read_text())
G = json_graph.node_link_graph(data, edges='links')

# Load detection + extraction for report context
detection = json.loads(Path('graphify-out/.graphify_incremental.json').read_text())
# Normalize detection for report generator
detection_norm = {
    'total_files': detection.get('total_files', 0),
    'total_words': detection.get('total_words', detection.get('new_total', 0)),
    'needs_graph': True,
    'warning': None,
    'files': detection.get('files', {}),
}
extract = json.loads(Path('graphify-out/.graphify_extract.json').read_text())
tokens = {'input': extract.get('input_tokens', 0), 'output': extract.get('output_tokens', 0)}

# Cluster
communities = cluster(G)
cohesion = score_all(G, communities)
gods = god_nodes(G)
surprises = surprising_connections(G, communities)
labels = {cid: 'Community ' + str(cid) for cid in communities}
questions = suggest_questions(G, communities, labels)

# Generate report with placeholder labels
report = generate(G, communities, cohesion, labels, gods, surprises, detection_norm, tokens, 'frontend', suggested_questions=questions)
Path('graphify-out/GRAPH_REPORT.md').write_text(report, encoding='utf-8')

# Re-export graph.json with community data
to_json(G, communities, 'graphify-out/graph.json', force=True)

analysis = {
    'communities': {str(k): v for k, v in communities.items()},
    'cohesion': {str(k): v for k, v in cohesion.items()},
    'gods': gods,
    'surprises': surprises,
    'questions': questions,
}
Path('graphify-out/.graphify_analysis.json').write_text(json.dumps(analysis, indent=2))

if G.number_of_nodes() == 0:
    print('ERROR: Graph is empty')
else:
    print('Graph: {} nodes, {} edges, {} communities'.format(
        G.number_of_nodes(), G.number_of_edges(), len(communities)))
    print('God nodes: {}'.format([g['label'] for g in gods[:5]]))
