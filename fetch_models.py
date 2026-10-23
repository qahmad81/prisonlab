#!/usr/bin/env python3
"""
Fetches available chat-capable models and their pricing from OpenRouter
using OPENROUTER_API_KEY from .env, and saves them to models.json.
"""
import os
import json
from datetime import datetime
import requests


def load_env(env_path=".env"):
    env = {}
    if os.path.exists(env_path):
        with open(env_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def fetch_and_save_models(output_path="models.json"):
    previous = {}
    if os.path.isfile(output_path):
        with open(output_path, encoding="utf-8") as saved:
            previous = json.load(saved)
    previous_models = {m['id']:m for m in previous.get('models', [])}
    env = load_env()
    api_key = env.get("OPENROUTER_API_KEY", "")
    headers = {"Accept": "application/json"}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"

    resp = requests.get("https://openrouter.ai/api/v1/models", headers=headers, timeout=25)
    resp.raise_for_status()
    raw_models = resp.json().get("data", [])

    chat_models = []
    excluded_substrings = ["jev-", "-decisions", "embed", "moderation", "safeguard", "lyria", "-image"]

    for m in raw_models:
        mid = m.get("id", "")
        if any(ex in mid.lower() for ex in excluded_substrings):
            continue

        arch = m.get("architecture") or {}
        in_mod = arch.get("input_modalities") or []
        out_mod = arch.get("output_modalities") or []

        if "text" not in in_mod or "text" not in out_mod:
            continue

        pricing = m.get("pricing") or {}
        try:
            p_prompt = float(pricing.get("prompt", 0))
            p_comp = float(pricing.get("completion", 0))
        except (ValueError, TypeError):
            p_prompt = -1.0
            p_comp = -1.0

        if p_prompt < 0 or p_comp < 0:
            continue

        chat_models.append({
            **{k:v for k,v in previous_models.get(mid,{}).items() if k in ['provider_slug','description_ar','usage_rank','usage_tokens_7d']},
            "id": mid,
            "created": m.get("created"),
            "canonical_slug": m.get("canonical_slug",mid),
            "top_provider": m.get("top_provider"),
            "expiration_date": m.get("expiration_date"),
            "name": m.get("name", mid),
            "description": m.get("description", ""),
            "context_length": m.get("context_length", 4096),
            "pricing": {
                "prompt_per_token": pricing.get("prompt", "0"),
                "completion_per_token": pricing.get("completion", "0"),
                "prompt_per_1m": round(p_prompt * 1_000_000, 4),
                "completion_per_1m": round(p_comp * 1_000_000, 4),
            },
            "architecture": {
                "modality": arch.get("modality", "text->text"),
                "input_modalities": in_mod,
                "output_modalities": out_mod,
            },
            "supported_parameters": m.get("supported_parameters", []),
            **({"reasoning":m["reasoning"]} if "reasoning" in m else {}),
        })

    chat_models.sort(key=lambda x: x["name"].lower())
    payload = {
        "updated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "count": len(chat_models),
        "models": chat_models,
        "discovery_meta": previous.get("discovery_meta", {}),
    }

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    print(f"Successfully saved {len(chat_models)} chat models to {output_path}")


if __name__ == "__main__":
    fetch_and_save_models()
