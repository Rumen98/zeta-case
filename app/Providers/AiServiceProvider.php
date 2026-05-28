<?php

namespace App\Providers;

use App\Services\Ai\Contracts\EmailParserInterface;
use App\Services\Ai\MockEmailParser;
use App\Services\Ai\OpenAiEmailParser;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmailParserInterface::class, function () {
            return match (config('services.ai.parser', 'mock')) {
                'openai' => new OpenAiEmailParser(
                    (string) config('services.ai.openai.api_key'),
                    (string) config('services.ai.openai.model', 'gpt-4o-mini'),
                ),
                default => new MockEmailParser(),
            };
        });
    }
}
