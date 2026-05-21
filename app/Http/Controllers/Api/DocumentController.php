<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    /**
     * كلاس الخدمة المركزية لإدارة مستندات المنشأة
     */
    protected DocumentService $documentService;

    /**
     * حقن الخدمة وتطبيق الصلاحيات التلقائية عبر الـ Constructor
     */
    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;

        // تطبيق سياسة الصلاحيات (Spatie Policy) تلقائياً على الموديل
        $this->authorizeResource(Document::class, 'document');
    }

    /**
     * عرض قائمة المستندات مع الفلترة الديناميكية المتقدمة للأرشيف
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Document::class);

        // جلب الاستعلام مع تحميل العلاقة المتعددة الأوجه لتجنب مشكلة الـ N+1 Query
        $query = Document::with('documentable');

        // 1. الفلترة بناءً على الجهة التابعة لها المستند داخل الـ ERP
        if (request()->has('target_id') && request()->has('target_type')) {
            $targetType = request('target_type');
            $modelType = match($targetType) {
                'company' => \App\Models\Company::class,
                'project' => \App\Models\Project::class,
                'employee' => \App\Modules\HR\Models\Employee::class,
                'contract' => \App\Modules\HR\Models\Contract::class,
            };

            $query->where('documentable_id', request('target_id'))
                  ->where('documentable_type', $modelType);
        }

        // 2. الفلترة بناءً على تصنيف نوع المستند الموحد (Enum) لسهولة البحث والفرز
        if (request()->has('document_type')) {
            $query->where('document_type', request('document_type'));
        }

        $documents = $query->latest()->paginate(20);
        return DocumentResource::collection($documents);
    }

    /**
     * استقبال ومعالجة طلب أرشفة وثيقة جديدة وتأمينها
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        // استدعاء الخدمة المخصصة لتنفيذ المنطق البرمجي المعقد والـ Transaction
        $document = $this->documentService->uploadDocument(
            $request->file('file'),
            $request->name,
            DocumentType::from($request->document_type),
            $request->target_type,
            (int) $request->target_id,
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'تمت أرشفة المستند بنجاح وتأمينه وفق معايير الحماية العالمية.',
            'data' => DocumentResource::make($document),
        ], Response::HTTP_CREATED);
    }

    /**
     * عرض تفاصيل مستند معين مع جهته المربوط بها
     */
    public function show(Document $document): DocumentResource
    {
        $document->load('documentable');
        return DocumentResource::make($document);
    }

    /**
     * حذف مستند برمجياً (Soft Delete) وحماية السجل من الضياع
     */
    public function destroy(Document $document): Response
    {
        $this->documentService->deleteDocument($document);
        return response()->noContent();
    }



    /**
     * تحميل الملف إجبارياً للمستخدم (Force Download with Original Name)
     * المعيار العالمي لمنع المتصفح من فتح ملفات الـ PDF والصور في تبويب جديد عند طلب التحميل
     */
    public function download(Document $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // التحقق من صلاحية العرض والتحميل للمستند
        $this->authorize('view', $document);

        // جلب المسار الفعلي للملف على السيرفر بناءً على القرص المخزن فيه
        $storagePath = Storage::disk($document->disk)->path($document->file_path);

        // إرجاع استجابة تحميل فوري وتحميل الملف باسمه التجاري المسجل بالنظام وبامتداده الأصلي
        return response()->download($storagePath, $document->name . '.' . $document->extension);
    }
}
