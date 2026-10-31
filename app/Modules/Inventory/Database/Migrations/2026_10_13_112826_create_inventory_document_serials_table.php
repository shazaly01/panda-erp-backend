<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_document_serials', function (Blueprint $table) {
            $table->id();

            // الأسطوانة المعنية بالحركة
            $table->foreignId('serial_id')
                ->constrained('inventory_serials')
                ->cascadeOnDelete()
                ->comment('معرف الأسطوانة/السيريال المسجل بالنظام');

            // الربط متعدد الأشكال مع بند المستند (استلام، صرف، تحويل، إلخ)
            $table->string('document_item_type', 150)->comment('فئة موديل سطر السند المرجعي');
            $table->unsignedBigInteger('document_item_id')->comment('معرف سطر السند المرجعي');

            // في حال كانت الأسطوانة الواردة بديلة عن أسطوانة قديمة تم التنازل عنها للمورد
            $table->foreignId('replaced_serial_id')
                ->nullable()
                ->constrained('inventory_serials')
                ->nullOnDelete()
                ->comment('معرف الأسطوانة القديمة المستبدلة إن وجدت');

            // قراءات الميزان الموثقة لحظة توثيق السند
            $table->decimal('gross_weight', 15, 4)
                ->nullable()
                ->comment('الوزن الإجمالي القائم المسجل على الميزان أثناء حركة السند (كجم)');

            $table->decimal('net_weight', 15, 4)
                ->nullable()
                ->comment('صافي وزن الغاز المحسوب لحظة حركة السند (كجم)');

            $table->text('notes')->nullable()->comment('ملاحظات الفحص أو حالة الأسطوانة أثناء الحركة');

            $table->timestamps();
            $table->softDeletes();

            // الفهارس والقيود لضمان سرعة الاستعلام ومنع تكرار نفس السيريال في نفس السطر
            $table->index(['document_item_type', 'document_item_id'], 'inv_doc_serials_morph_idx');
            $table->unique(['document_item_type', 'document_item_id', 'serial_id'], 'inv_doc_serials_unique');
            $table->index('replaced_serial_id', 'inv_doc_serials_replaced_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_document_serials');
    }
};