<?php

namespace App\Http\Controllers;

use App\Services\Orchestration\PromptBuilder;
use Illuminate\Http\Request;

class AgentSystemMessageController extends Controller
{
    public function show(int $agentNumber)
    {
        if ($agentNumber < 0 || $agentNumber > 12) {
            abort(404);
        }
        $builder = PromptBuilder::fromConfig();

        // Check if this is an override (override stored as persona, not full system prompt)
        $overridePath = $builder->getAgentPersonaOverridePath();
        $overrides = file_exists($overridePath) ? (json_decode(file_get_contents($overridePath), true) ?? []) : [];
        $isOverride = isset($overrides[$agentNumber]);

        // Return the full system prompt (persona + CoT for agents 5, 7, 8) so the portal
        // displays exactly what the agent receives as its system role instruction.
        $current = $builder->buildSystemPrompt($agentNumber);

        return response()->json([
            'agent_number' => $agentNumber,
            'system_message' => $current,
            'is_override' => $isOverride,
        ]);
    }

    public function update(Request $request, int $agentNumber)
    {
        if ($agentNumber < 0 || $agentNumber > 12) {
            abort(404);
        }
        $validated = $request->validate([
            'system_message' => 'required|string|min:10|max:5000',
        ]);

        $builder = PromptBuilder::fromConfig();
        $overridePath = $builder->getAgentPersonaOverridePath();
        $overrides = file_exists($overridePath) ? (json_decode(file_get_contents($overridePath), true) ?? []) : [];
        $overrides[$agentNumber] = trim($validated['system_message']);
        file_put_contents($overridePath, json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return response()->json(['ok' => true, 'agent_number' => $agentNumber]);
    }

    public function reset(int $agentNumber)
    {
        if ($agentNumber < 0 || $agentNumber > 12) {
            abort(404);
        }
        $builder = PromptBuilder::fromConfig();
        $overridePath = $builder->getAgentPersonaOverridePath();
        if (file_exists($overridePath)) {
            $overrides = json_decode(file_get_contents($overridePath), true) ?? [];
            unset($overrides[$agentNumber]);
            file_put_contents($overridePath, json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        return response()->json(['ok' => true]);
    }
}
