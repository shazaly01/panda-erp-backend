<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Accounting\Http\Requests\StoreJournalEntryRequest;
use App\Modules\Accounting\Http\Requests\UpdateJournalEntryRequest;
use App\Modules\Accounting\Http\Resources\JournalEntryResource;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\DTO\JournalEntryDetailDto;

class JournalEntryController extends Controller
{
    public function __construct(
        protected JournalEntryService $service
    ) {}

    public function index()
    {
        $this->authorize('viewAny', JournalEntry::class);

        $entries = JournalEntry::with(['details', 'creator'])
            ->latest('date')
            ->paginate(20);

        return JournalEntryResource::collection($entries);
    }

    public function show(JournalEntry $journalEntry)
    {
        $this->authorize('view', $journalEntry);

        // تحميل العلاقات لعرض الأسماء
        $journalEntry->load(['details.account', 'details.costCenter', 'creator']);

        return new JournalEntryResource($journalEntry);
    }

  /**
     * إنشاء قيد جديد
     */
    public function store(StoreJournalEntryRequest $request, JournalEntryService $service): JsonResponse
    {
        // 1. تحويل مصفوفة التفاصيل إلى كائنات DTO
        $detailsDto = collect($request->details)->map(function ($item) {
            return new JournalEntryDetailDto(
                account_id:     $item['account_id'],
                debit:          (float) $item['debit'],
                credit:         (float) $item['credit'],
                cost_center_id: $item['cost_center_id'] ?? null,

                // [تم التصحيح]: استخدام description ليتطابق مع الـ Request والـ DTO
                description:    $item['description'] ?? null,

                // (اختياري) إذا كنت ترسل هذه البيانات من الفرونت إند
                party_type:     $item['party_type'] ?? null,
                party_id:       isset($item['party_id']) ? (string) $item['party_id'] : null
            );
        })->toArray();

        // 2. إنشاء الـ DTO الرئيسي
        $dto = new JournalEntryDto(
            date:        $request->date,
            description: $request->description,
            details:     $detailsDto,
            // أضفنا العملة هنا للتأكد من تمريرها
            currency_id: $request->currency_id ?? null
        );

        // 3. التنفيذ
        $entry = $service->createEntry($dto);

        return response()->json([
            'message' => 'تم إنشاء القيد بنجاح',
            'data'    => new JournalEntryResource($entry)
        ], 201);
    }

    /**
     * ترحيل القيد
     */
    public function post(JournalEntry $journalEntry, JournalEntryService $service): JsonResponse
    {
        // التحقق من الصلاحية (يمكنك نقلها لـ Policy لاحقاً)
        // $this->authorize('post', $journalEntry);

        $postedEntry = $service->postEntry($journalEntry);

        return response()->json([
            'message' => 'تم ترحيل القيد بنجاح',
            'data'    => new JournalEntryResource($postedEntry)
        ]);
    }

   public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry, JournalEntryService $service)
    {
        // تحويل البيانات إلى DTO (نفس منطق store تماماً)
        $detailsDto = collect($request->details)->map(function ($item) {
            return new JournalEntryDetailDto(
                account_id:     $item['account_id'],
                debit:          (float) $item['debit'],
                credit:         (float) $item['credit'],
                cost_center_id: $item['cost_center_id'] ?? null,
                description:    $item['description'] ?? null,
                party_type:     $item['party_type'] ?? null,
                party_id:       isset($item['party_id']) ? (string) $item['party_id'] : null
            );
        })->toArray();

        $dto = new JournalEntryDto(
            date:        $request->date,
            description: $request->description,
            details:     $detailsDto,
            currency_id: $request->currency_id ?? null
        );

        $updatedEntry = $service->updateEntry($journalEntry, $dto);

        return response()->json([
            'message' => 'تم تحديث القيد بنجاح',
            'data'    => new JournalEntryResource($updatedEntry),
        ]);
    }

    public function destroy(JournalEntry $journalEntry)
    {
        $this->authorize('delete', $journalEntry);
        $this->service->deleteEntry($journalEntry);

        return response()->json([
            'message' => 'تم حذف القيد بنجاح',
        ]);
    }
}
