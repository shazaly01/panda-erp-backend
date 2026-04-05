<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_custodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');

            $table->string('item_name', 150); // مثال: لابتوب ديل، سيارة كورولا
            $table->string('reference_number', 100)->nullable(); // السيريال نمبر أو رقم اللوحة

            $table->date('received_date'); // تاريخ الاستلام
            $table->date('return_date')->nullable(); // تاريخ الإرجاع الفعلي

            $table->enum('status', ['with_employee', 'returned', 'lost', 'damaged'])->default('with_employee');

            // قيمة العهدة: يتم استخدامها لخصمها من الراتب إذا تم إتلافها أو فقدانها
            $table->decimal('estimated_value', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_custodies');
    }
};
