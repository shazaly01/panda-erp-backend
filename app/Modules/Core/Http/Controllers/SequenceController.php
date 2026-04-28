<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Sequence;
use App\Modules\Core\Http\Requests\UpdateSequenceRequest;
use App\Modules\Core\Http\Resources\SequenceResource;
use Illuminate\Http\JsonResponse;

class SequenceController extends Controller
{
    /**
     * عرض جميع إعدادات التسلسلات (ترقيم المستندات)
     */
    public function index(): JsonResponse
    {
        // التحقق من الصلاحيات عبر SequencePolicy
        $this->authorize('viewAny', Sequence::class);

        $sequences = Sequence::orderBy('id')->get();

        return response()->json(SequenceResource::collection($sequences));
    }

    /**
     * عرض إعداد تسلسل محدد
     */
    public function show(Sequence $sequence): JsonResponse
    {
        // التحقق من الصلاحيات
        $this->authorize('view', $sequence);

        return response()->json(new SequenceResource($sequence));
    }

    /**
     * تحديث إعدادات التسلسل (تغيير الصيغة أو التصفير)
     */
    public function update(UpdateSequenceRequest $request, Sequence $sequence): JsonResponse
    {
        // التحقق من الصلاحيات
        $this->authorize('update', $sequence);

        // التحديث باستخدام البيانات المفلترة فقط والمصرح بها من الـ Form Request
        $sequence->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث إعدادات الترقيم بنجاح.',
            'data'    => new SequenceResource($sequence)
        ]);
    }
}
