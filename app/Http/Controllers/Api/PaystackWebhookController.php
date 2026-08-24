<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaystackWebhookRequest;
use App\Services\PaystackWebhookService;
use Illuminate\Http\JsonResponse;

class PaystackWebhookController extends Controller
{
    public function __construct(private readonly PaystackWebhookService $paystackWebhookService) {}

    public function __invoke(PaystackWebhookRequest $request): JsonResponse
    {
        $this->paystackWebhookService->handle($request->validated());

        return response()->json(['status' => true]);
    }
}
