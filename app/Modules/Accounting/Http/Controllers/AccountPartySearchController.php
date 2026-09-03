<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\AccountPartySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountPartySearchController extends Controller
{
    public function __construct(
        protected AccountPartySearchService $searchService
    ) {}

    /**
     * البحث الموحد في الحسابات والأطراف المساعدة
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');
        
        $typesInput = $request->input('types');
        $allowedTypes = [];

        if (is_array($typesInput)) {
            $allowedTypes = $typesInput;
        } elseif (is_string($typesInput) && !empty($typesInput)) {
            $allowedTypes = explode(',', $typesInput);
        }

        $limit = $request->integer('limit_per_type', 10);

        $results = $this->searchService->search($query, $allowedTypes, $limit);

        return response()->json([
            'data' => $results,
        ]);
    }

    /**
     * جلب قائمة الأنواع المدعومة حالياً في البحث
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => $this->searchService->getAvailableTypes(),
        ]);
    }
}