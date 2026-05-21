<?php

namespace App\Services\Ai;

/**
 * Chooses Gemini vs OpenAI based on config and available API keys.
 */
class AiProviderResolver
{
    public function preferred(): ?string
    {
        $configured = strtolower((string) config('ai.provider', 'auto'));

        if ($configured === 'gemini') {
            return $this->canUseGemini() ? 'gemini' : null;
        }

        if ($configured === 'openai') {
            return $this->canUseOpenAi() ? 'openai' : null;
        }

        if ($this->canUseGemini()) {
            return 'gemini';
        }

        if ($this->canUseOpenAi()) {
            return 'openai';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function fallbackOrder(): array
    {
        $primary = $this->preferred();

        if ($primary === 'gemini') {
            return array_values(array_filter(['gemini', $this->canUseOpenAi() ? 'openai' : null]));
        }

        if ($primary === 'openai') {
            return array_values(array_filter(['openai', $this->canUseGemini() ? 'gemini' : null]));
        }

        return [];
    }

    public function canUseGemini(): bool
    {
        return config('gemini.enabled') && ! empty(config('gemini.api_key'));
    }

    public function canUseOpenAi(): bool
    {
        return config('openai.enabled') && ! empty(config('openai.api_key'));
    }
}
