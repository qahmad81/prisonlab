"""Refresh factual catalog metadata and 7-day OpenRouter usage; never runs inference."""
from pathlib import Path
import collections,json,math,sys,urllib.request
from datetime import datetime,timedelta,timezone
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from fetch_models import load_env

root=Path(__file__).resolve().parents[1]
def enrich():
    source=root/'models.json'; catalog=json.loads(source.read_text(encoding='utf-8'));env=load_env(str(root/'.env'))
    with urllib.request.urlopen('https://openrouter.ai/api/v1/models',timeout=30) as r:market=json.load(r)['data']
    by_id={m['id']:m for m in market};usage=collections.Counter();meta={}
    end=datetime.now(timezone.utc).date()-timedelta(days=1);start=end-timedelta(days=6)
    try:
        req=urllib.request.Request(f'https://openrouter.ai/api/v1/datasets/rankings-daily?start_date={start}&end_date={end}',headers={'Authorization':'Bearer '+env.get('OPENROUTER_API_KEY','')})
        with urllib.request.urlopen(req,timeout=30) as r:ranks=json.load(r)
        meta=ranks['meta']
        for row in ranks['data']:
            if row['model_permaslug']!='other':usage[row['model_permaslug']]+=int(row['total_tokens'])
    except Exception as exc:print('Usage ranking unavailable; date metadata still refreshed:',type(exc).__name__)
    ordered={slug:i+1 for i,(slug,_) in enumerate(usage.most_common())}
    for m in catalog['models']:
        raw=by_id.get(m['id'])
        if not raw: m['catalog_unavailable']=True;continue
        m.pop('catalog_unavailable',None)
        for key in ['created','canonical_slug','top_provider','expiration_date','context_length']:
            m[key]=raw.get(key)
        slug=raw.get('canonical_slug',m['id'])
        if ':' in m['id'] and ':' not in slug:slug=m['id']
        m['usage_rank']=ordered.get(slug) or ordered.get(m['id'])
        m['usage_tokens_7d']=usage.get(slug,usage.get(m['id'],0))
    catalog['discovery_meta']={'updated_at':datetime.now(timezone.utc).isoformat(),'ranking_meta':meta,
        'ranking_source':'https://openrouter.ai/rankings','license':'CC BY 4.0','window_days':7}
    source.write_text(json.dumps(catalog,ensure_ascii=False,indent=2),encoding='utf-8')
    print('Enriched',len(catalog['models']),'models;',sum(bool(m.get('usage_rank')) for m in catalog['models']),'matched usage rankings.')
if __name__=='__main__':enrich()
