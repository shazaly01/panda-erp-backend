<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct()
    {
        // تطبيق الصلاحيات تلقائيًا
        // ملاحظة: authorizeResource لا تدعم index بشكل جيد مع الفلترة، لذا سنتحقق منها يدويًا
        $this->authorizeResource(Document::class, 'document');
    }

   public function index(): AnonymousResourceCollection
{
    $this->authorize('viewAny', Document::class);

    // 1. استخدام العلاقة الجديدة documentable
    $query = Document::with('documentable');

    // 2. الفلترة بناءً على المعطيات الجديدة القادمة من الواجهة
    if (request()->has('target_id') && request()->has('target_type')) {
        $modelType = request('target_type') === 'company'
            ? \App\Models\Company::class
            : \App\Models\Project::class;

        $query->where('documentable_id', request('target_id'))
              ->where('documentable_type', $modelType);
    }

    $documents = $query->latest()->paginate(20);
    return DocumentResource::collection($documents);
}

   public function store(StoreDocumentRequest $request): JsonResponse
{
    $path = $request->file('file')->store('documents', 'public');

    // تحديد الموديل بناءً على النوع المرسل
    $modelType = $request->target_type === 'company'
        ? \App\Models\Company::class
        : \App\Models\Project::class;

    $document = Document::create([
        'name' => $request->name,
        'file_path' => $path,
        'documentable_id' => $request->target_id,
        'documentable_type' => $modelType,
    ]);

    return response()->json([
        'message' => 'Document uploaded successfully.',
        'data' => DocumentResource::make($document),
    ], Response::HTTP_CREATED);
}


 public function show(Document $document): DocumentResource
{
    // تحميل العلاقة متعددة الأوجه بدلاً من الشركة فقط
    $document->load('documentable');
    return DocumentResource::make($document);
}

    // لا نحتاج لدالة update، لأن تحديث مستند هو عملية حذف ثم رفع

   public function destroy(Document $document): Response
    {
        // نكتفي بحذف السجل برمجياً (Soft Delete)
        // يتم وضع تاريخ في عمود deleted_at
        // يبقى الملف الفعلي آمناً في السيرفر لاستعادته في أي وقت
        $document->delete();

        return response()->noContent();
    }
}
