<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchaseService) {}

    public function __invoke(StorePurchaseRequest $request, User $user): JsonResponse
    {
        $result = $this->purchaseService->create(
            user: $user,
            payload: $request->validated(),
        );

        return new PurchaseResource($result['purchase'])
            ->response()
            ->setStatusCode(
                $result['was_created']
                    ? Response::HTTP_CREATED
                    : Response::HTTP_OK,
            );
    }
}
