<?php

namespace App\Http\Controllers;

use App\Services\Tv\TvReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ReconciliationController extends Controller
{
    public function __construct(
        protected TvReconciliationService $reconciliationService
    ) {}

    public function sync(): JsonResponse|RedirectResponse
    {
        $results = $this->reconciliationService->reconcileAll();

        if (request()->wantsJson()) {
            return response()->json([
                'status' => 'ok',
                'reconciled' => count($results),
                'results' => $results,
            ]);
        }

        return back();
    }
}
