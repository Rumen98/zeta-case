<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncomingEmailRequest;
use App\Http\Resources\TaskDraftResource;
use App\Services\Exceptions\DuplicateEmailException;
use App\Services\IncomingEmailService;

class IncomingEmailController extends Controller
{
    public function __construct(private IncomingEmailService $emails) {}

    public function store(StoreIncomingEmailRequest $request)
    {
        try {
            $draft = $this->emails->ingest($request->validated());
        } catch (DuplicateEmailException $e) {
            return response()->json([
                'error'             => 'duplicate_email',
                'message'           => 'This email has already been ingested.',
                'incoming_email_id' => $e->existingIncomingEmailId,
            ], 409);
        }

        return TaskDraftResource::make($draft->load(['incomingEmail', 'aiEvaluation']))
            ->response()
            ->setStatusCode(201);
    }
}
