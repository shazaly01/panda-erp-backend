<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\Api\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function __construct()
    {
        // تطبيق الصلاحيات تلقائيًا على كل الدوال
        $this->authorizeResource(Company::class, 'company');
    }

 public function index(): AnonymousResourceCollection
    {
        $query = Company::query();

        if (request()->filled('search')) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('tax_number', 'like', "%{$search}%")
                  ->orWhere('license_number', 'like', "%{$search}%");
            });
        }

        // التعديل: تطبيق الترتيب دائماً في النهاية، سواء كان هناك بحث أم لا
        $companies = $query->latest()->paginate(15);

        return CompanyResource::collection($companies);
    }


    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());
        return response()->json([
            'message' => 'Company created successfully.',
            'data' => CompanyResource::make($company),
        ], Response::HTTP_CREATED);
    }

    public function show(Company $company): CompanyResource
    {
        // يمكن إرجاع الـ Resource مباشرة هنا
        return CompanyResource::make($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());
        return response()->json([
            'message' => 'Company updated successfully.',
            'data' => CompanyResource::make($company->fresh()),
        ]);
    }

    public function destroy(Company $company): Response
    {
        $company->delete();
        return response()->noContent(); // ->noContent() هي الطريقة الأحدث والأكثر تعبيرًا
    }
}
