import json
from pathlib import Path
from networkx.readwrite import json_graph
from graphify.cluster import score_all
from graphify.analyze import god_nodes, surprising_connections, suggest_questions
from graphify.report import generate

data = json.loads(Path('graphify-out/graph.json').read_text())

import networkx as nx
G = json_graph.node_link_graph(data, edges='links')

analysis = json.loads(Path('graphify-out/.graphify_analysis.json').read_text())
communities = {int(k): v for k, v in analysis['communities'].items()}
cohesion = {int(k): v for k, v in analysis['cohesion'].items()}

# Load for report
incremental = json.loads(Path('graphify-out/.graphify_incremental.json').read_text())
detection_norm = {
    'total_files': incremental.get('total_files', 0),
    'total_words': incremental.get('total_words', 0),
    'needs_graph': True,
    'warning': None,
    'files': incremental.get('files', {}),
}
extract = json.loads(Path('graphify-out/.graphify_extract.json').read_text())
tokens = {'input': extract.get('input_tokens', 0), 'output': extract.get('output_tokens', 0)}

# Community labels based on node sampling above
labels = {
    0: "Vue Runtime (iOS Build)",
    1: "Vue Components Bundle",
    2: "PrimeVue UI Components",
    3: "Rich Text Editor (iOS)",
    4: "Invoice Terms Editor",
    5: "Booking Android Bundle",
    6: "Vue Setup Hooks (iOS)",
    7: "Vue Core Runtime",
    8: "Autocomplete/Booking iOS",
    9: "DataTable Column Bundle",
    10: "Booking Detail Logic",
    11: "Transaction Report Android",
    12: "ProseMirror Schema (iOS)",
    13: "Transaction Report iOS",
    14: "ProseMirror Marks (iOS)",
    15: "Date Utility Bundle",
    16: "ProseMirror Node iOS",
    17: "ProseMirror State iOS",
    18: "ProseMirror Schema Editor",
    19: "ProseMirror Node Editor",
    20: "Android UI Components",
    21: "Dashboard Android Bundle",
    22: "ProseMirror Pos Editor",
    23: "Tiptap Extensions iOS",
    24: "Tiptap Extensions Editor",
    25: "html2canvas Rendering",
    26: "Booking Create Form",
    27: "Transaction Report Bundle",
    28: "Transaction Chart Bundle",
    29: "Autocomplete iOS Bundle",
    44: "Booking Cancellation Flow",
    109: "Receipt Print Utility",
}

# Fill remaining with generic labels
for cid in communities:
    if cid not in labels:
        labels[cid] = "Community " + str(cid)

gods = analysis['gods']
surprises = analysis['surprises']
questions = suggest_questions(G, communities, labels)

report = generate(G, communities, cohesion, labels, gods, surprises, detection_norm, tokens, 'frontend', suggested_questions=questions)
Path('graphify-out/GRAPH_REPORT.md').write_text(report, encoding='utf-8')
Path('graphify-out/.graphify_labels.json').write_text(json.dumps({str(k): v for k, v in labels.items()}))
print('Report updated with community labels')
