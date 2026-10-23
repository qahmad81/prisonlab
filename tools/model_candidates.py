"""Prepare a review-only chat catalog diff. Never edits the approved catalog or policy."""
import argparse
import datetime as dt
import json
from pathlib import Path
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


def prepare(raw, baseline, policy):
    known = {m['id']: m for m in baseline['models']}
    approved = {mid for pool in policy['jury'].values() for mid in pool['models']}
    approved.update(mid for pins in policy['shortlist_overrides'].values() for mid in pins.values())
    candidates, changes = [], []
    for m in raw.get('data', []):
        arch = m.get('architecture') or {}
        mid = m.get('id', '')
        if 'text' not in arch.get('input_modalities', []) or 'text' not in arch.get('output_modalities', []):
            continue
        if any(x in mid.lower() for x in ['embed', 'moderation', 'jev-', '-decisions', 'safeguard']):
            continue
        pricing = m.get('pricing') or {}
        try:
            prompt, completion = float(pricing['prompt']) * 1e6, float(pricing['completion']) * 1e6
        except (KeyError, TypeError, ValueError):
            continue
        if min(prompt, completion) < 0:
            continue
        row = {'id': mid, 'name': m.get('name', mid), 'created': m.get('created'),
               'publisher': mid.lstrip('~').split('/')[0], 'context_length': m.get('context_length'),
               'prompt_per_1m': prompt, 'completion_per_1m': completion,
               'description': m.get('description', ''), 'alias': mid.startswith('~'),
               'variant': ':' in mid, 'currently_approved_pool_or_pin': mid in approved}
        if mid not in known:
            candidates.append(row)
        else:
            old = known[mid].get('pricing', {})
            if any(abs(float(old.get(k, -1)) - row[k]) > .000001 for k in ['prompt_per_1m', 'completion_per_1m']):
                changes.append(row)
    current = {m.get('id') for m in raw.get('data', [])}
    return {'new_chat_models': sorted(candidates, key=lambda m: m.get('created') or 0, reverse=True),
            'price_changes': changes, 'approved_missing': sorted(approved - current),
            'review_required': True, 'source': 'https://openrouter.ai/api/v1/models'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--catalog', type=Path, help='Optional saved official response for offline review')
    parser.add_argument('--output', type=Path, default=ROOT / '.model-candidates')
    args = parser.parse_args()
    if args.catalog:
        raw = json.loads(args.catalog.read_text(encoding='utf-8'))
    else:
        with urllib.request.urlopen('https://openrouter.ai/api/v1/models', timeout=30) as response:
            raw = json.load(response)
    result = prepare(raw, json.loads((ROOT / 'models.json').read_text(encoding='utf-8')),
                     json.loads((ROOT / 'lib/model-policy.json').read_text(encoding='utf-8')))
    stamp = dt.datetime.now(dt.timezone.utc).strftime('%Y%m%d-%H%M%S')
    folder = args.output / stamp
    folder.mkdir(parents=True, exist_ok=False)
    (folder / 'catalog.json').write_text(json.dumps(raw, ensure_ascii=False, indent=2), encoding='utf-8')
    (folder / 'review.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    lines = ['# Chat model candidates — operator review required', '',
             'No approved models or judge pools were changed. Prices are USD per million tokens.', '']
    for label, field in [('New chat-capable models', 'new_chat_models'), ('Changed prices', 'price_changes')]:
        lines += ['## ' + label, '', '| Model | Input | Output | Context |', '|---|---:|---:|---:|']
        for row in result[field]:
            lines.append(f"| `{row['id'].replace('|', '')}` | {row['prompt_per_1m']:.6g} | {row['completion_per_1m']:.6g} | {row['context_length']} |")
        lines.append('')
    lines += ['## Approved IDs absent from current source', '', *['- `' + mid + '`' for mid in result['approved_missing']]]
    (folder / 'review.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
    print(json.dumps({'folder': str(folder), 'new_models': len(result['new_chat_models']),
                      'price_changes': len(result['price_changes']), 'approved_missing': result['approved_missing']}))


if __name__ == '__main__':
    main()
