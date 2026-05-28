<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskDraftResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'status'                => $this->status,
            'type'                  => $this->type,
            'title'                 => $this->title,
            'summary'               => $this->summary,
            'priority'              => $this->priority,
            'suggested_project'     => $this->suggested_project,
            'suggested_team'        => $this->suggested_team,
            'confidence'            => $this->confidence,
            'missing_information'   => $this->missing_information ?? [],
            'suggested_next_action' => $this->suggested_next_action,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'incoming_email' => $this->whenLoaded('incomingEmail', fn () => [
                'id'          => $this->incomingEmail->id,
                'from'        => $this->incomingEmail->from_address,
                'subject'     => $this->incomingEmail->subject,
                'received_at' => $this->incomingEmail->received_at,
            ]),
            'ai_evaluation' => $this->whenLoaded('aiEvaluation', fn () => [
                'id'            => $this->aiEvaluation->id,
                'provider'      => $this->aiEvaluation->provider,
                'model'         => $this->aiEvaluation->model,
                'status'        => $this->aiEvaluation->status,
                'error_message' => $this->aiEvaluation->error_message,
                'latency_ms'    => $this->aiEvaluation->latency_ms,
            ]),
            'approval_decisions' => $this->whenLoaded('approvalDecisions'),
        ];
    }
}
