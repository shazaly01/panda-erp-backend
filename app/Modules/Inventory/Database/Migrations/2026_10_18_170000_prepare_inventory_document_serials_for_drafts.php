<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تجهيز سطر المستند لحفظ بيانات أسطوانة جديدة قبل تأكيد الاستلام.
     *
     * هذا التعديل لا ينشئ أسطوانات ولا يغيّر الأرصدة أو حالات الأسطوانات.
     * تعديل خدمة الاستلام لاستخدام هذه الحقول يأتي في خطوة منفصلة.
     */
    public function up(): void
    {
        Schema::table('inventory_document_serials', function (Blueprint $table): void {
            // نغيّر السماح بالقيمة الفارغة فقط، مع إبقاء المفتاح الأجنبي
            // وقيد عدم التكرار الموجودين. لا نعيد إنشاء العلاقة بـ constrained().
            $table->foreignId('serial_id')
                ->nullable()
                ->comment('معرف الأسطوانة/السيريال المسجل بالنظام')
                ->change();

            // الرقم الموثق في سطر المستند، حتى قبل إنشاء سجل الأسطوانة.
            // طول الحقل مطابق لحقل serial_number في جدول inventory_serials.
            $table->string('serial_number', 255)
                ->nullable()
                ->after('serial_id')
                ->comment('رقم الأسطوانة المدخل في المسودة أو الموثق وقت الاستلام');

            // لقطة مستقلة للوزن الفارغ المستخدم في حساب صافي هذا المستند.
            $table->decimal('tare_weight', 15, 4)
                ->nullable()
                ->after('replaced_serial_id')
                ->comment('الوزن الفارغ الموثق للأسطوانة في هذا المستند (كجم)');
        });

        // نترك الحقول الجديدة فارغة في السجلات السابقة عمداً.
        // لا نفترض أن الوزن الحالي للأسطوانة هو وزنها وقت المستند القديم.
    }

    /**
     * إعادة البنية السابقة دون حذف مسودات أو اختراع روابط لأسطواناتها.
     *
     * لا يمكن جعل serial_id إلزامياً ما دامت هناك صفوف بلا أسطوانة مرتبطة.
     * الفحص يشمل الصفوف المحذوفة منطقياً لأنها ما زالت موجودة في الجدول.
     */
    public function down(): void
    {
        if (DB::table('inventory_document_serials')->whereNull('serial_id')->exists()) {
            throw new \RuntimeException(
                'تعذر التراجع عن هذه الهجرة: توجد صفوف في inventory_document_serials '
                . 'بدون serial_id، بما فيها الصفوف المحذوفة منطقياً. '
                . 'يجب معالجة هذه الصفوف قبل إعادة الحقل إلى إلزامي. '
                . 'لم تُحذف أي بيانات بواسطة هذه الهجرة.'
            );
        }

        Schema::table('inventory_document_serials', function (Blueprint $table): void {
            $table->foreignId('serial_id')
                ->nullable(false)
                ->comment('معرف الأسطوانة/السيريال المسجل بالنظام')
                ->change();

            $table->dropColumn(['serial_number', 'tare_weight']);
        });
    }
};
