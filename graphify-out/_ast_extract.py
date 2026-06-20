import sys, json
from graphify.extract import collect_files, extract
from pathlib import Path

incremental = json.loads(Path('graphify-out/.graphify_incremental.json').read_text())
code_files = incremental.get('new_files', {}).get('code', [])
file_paths = []
for f in code_files:
    p = Path(f)
    if p.is_dir():
        file_paths.extend(collect_files(p))
    elif p.exists():
        file_paths.append(p)

if file_paths:
    result = extract(file_paths)
    Path('graphify-out/.graphify_ast.json').write_text(json.dumps(result, indent=2))
    print('AST: {} nodes, {} edges'.format(len(result['nodes']), len(result['edges'])))
else:
    Path('graphify-out/.graphify_ast.json').write_text(json.dumps({'nodes':[],'edges':[],'input_tokens':0,'output_tokens':0}))
    print('No code files - skipping AST extraction')
