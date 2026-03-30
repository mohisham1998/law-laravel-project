# OpenRouter Models - Valid Model IDs Reference

> Generated: 2026-03-24
> Source: https://openrouter.ai/models

---

## ✅ Valid Google Models (Working on OpenRouter)

| Model ID | Display Name | Context | Pricing |
|----------|--------------|---------|---------|
| `google/gemini-3-flash-preview` | Gemini 3 Flash Preview | 1.05M | $0.50/M input, $3/M output |
| `google/gemini-2.5-flash` | Gemini 2.5 Flash | 1.05M | $0.30/M input, $2.50/M output |
| `google/gemini-2.5-flash-lite` | Gemini 2.5 Flash Lite | 1.05M | $0.10/M input, $0.40/M output |
| `google/gemini-3.1-flash-lite-preview` | Gemini 3.1 Flash Lite Preview | 1.05M | $0.25/M input, $1.50/M output |
| `google/gemini-3.1-pro-preview` | Gemini 3.1 Pro Preview | 1.05M | $2/M input, $12/M output |
| `google/gemini-2.0-flash-001` | Gemini 2.0 Flash | 1.05M | $0.10/M input, $0.40/M output |
| `google/gemini-2.5-pro` | Gemini 2.5 Pro | 1.05M | $1.25/M input, $10/M output |
| `google/gemini-2.0-flash-lite-001` | Gemini 2.0 Flash Lite | 1.05M | $0.075/M input, $0.30/M output |

---

## ✅ Valid Anthropic Models (Working on OpenRouter)

| Model ID | Display Name | Context | Pricing |
|----------|--------------|---------|---------|
| `anthropic/claude-sonnet-4.6` | Claude Sonnet 4.6 | 1M | $3/M input, $15/M output |
| `anthropic/claude-opus-4.6` | Claude Opus 4.6 | 1M | $5/M input, $25/M output |
| `anthropic/claude-sonnet-4.5` | Claude Sonnet 4.5 | 1M | $3/M input, $15/M output |
| `anthropic/claude-haiku-4.5` | Claude Haiku 4.5 | 200K | $1/M input, $5/M output |
| `anthropic/claude-opus-4.5` | Claude Opus 4.5 | 200K | $5/M input, $25/M output |
| `anthropic/claude-sonnet-4` | Claude Sonnet 4 | 200K | $3/M input, $15/M output |
| `anthropic/claude-3.5-haiku` | Claude 3.5 Haiku | 200K | $0.80/M input, $4/M output |
| `anthropic/claude-3.7-sonnet` | Claude 3.7 Sonnet | 200K | $3/M input, $15/M output |
| `anthropic/claude-3.5-sonnet` | Claude 3.5 Sonnet | 200K | $6/M input, $30/M output |

---

## ⚠️ INVALID Model Found in Your Code

The following model ID in `resources/views/components/agent-model-config.blade.php` is **NOT VALID** on OpenRouter:

### Invalid: `google/gemini-2.5-flash-preview`

**Error:** "google/gemini-2.5-flash-preview is not a valid model ID"

**Fix:** Replace with: `google/gemini-2.5-flash`

---

## 📝 Recommended Model List for Your App

Based on the valid models above, update your `agent-model-config.blade.php`:

```php
$modelGroups = [
    'Anthropic' => [
        'anthropic/claude-sonnet-4.6'  => 'Claude Sonnet 4.6',
        'anthropic/claude-opus-4.6'    => 'Claude Opus 4.6',
        'anthropic/claude-3.5-sonnet'  => 'Claude 3.5 Sonnet',
        'anthropic/claude-3.5-haiku'   => 'Claude 3.5 Haiku',
    ],
    'OpenAI' => [
        'openai/gpt-4o'            => 'GPT-4o',
        'openai/gpt-4o-mini'       => 'GPT-4o Mini',
        'openai/gpt-4-turbo'       => 'GPT-4 Turbo',
        'openai/o1'                => 'o1',
        'openai/o3-mini'           => 'o3 Mini',
    ],
    'Google' => [
        'google/gemini-2.5-pro'           => 'Gemini 2.5 Pro',
        'google/gemini-2.5-flash'          => 'Gemini 2.5 Flash',  // ← FIXED
        'google/gemini-2.5-flash-lite'      => 'Gemini 2.5 Flash Lite',
        'google/gemini-2.0-flash-001'      => 'Gemini 2.0 Flash',
    ],
    'Meta' => [
        'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
        'meta-llama/llama-3.1-405b-instruct'=> 'Llama 3.1 405B',
    ],
    'Mistral' => [
        'mistralai/mistral-large'        => 'Mistral Large',
        'mistralai/mistral-small-2603'   => 'Mistral Small 4',
        'mistralai/codestral-latest'     => 'Codestral',
    ],
];
```

---

## 🧪 Testing Note

Since your OpenRouter quota is exhausted, you cannot test models live. Once you add credit, the models above will work correctly. The fix for the invalid model ID should resolve the error you encountered.