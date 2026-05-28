<?php

namespace App\Services\Ai\Data;

// Immutable DTO returned by every EmailParserInterface implementation.
// Every field is nullable so a parser can admit "I don't know" via
// missingInformation rather than fabricating values.
final class ParsedEmailResult
{
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly ?string $priority,
        public readonly ?string $suggestedProject,
        public readonly ?string $suggestedTeam,
        public readonly ?float  $confidence,
        public readonly array   $missingInformation = [],
        public readonly ?string $suggestedNextAction = null,
        public readonly array   $rawOutput = [],
        public readonly ?int    $latencyMs = null,
        public readonly ?string $model = null,
    ) {}

    public function toDraftAttributes(): array
    {
        return [
            'type'                  => $this->type,
            'title'                 => $this->title,
            'summary'               => $this->summary,
            'priority'              => $this->priority,
            'suggested_project'     => $this->suggestedProject,
            'suggested_team'        => $this->suggestedTeam,
            'confidence'            => $this->confidence,
            'missing_information'   => $this->missingInformation,
            'suggested_next_action' => $this->suggestedNextAction,
        ];
    }
}
