<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة.
     */
    public function up(): void
    {
        // 1. إسقاط قيود المفاتيح الأجنبية السابقة لتعديل خصائص الأعمدة بأمان
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_unit_id']);
        });

        // 2. تعديل الأعمدة لتصبح nullable وإضافة حقول الصنف والوحدة الحرة
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->string('item_name', 255)->nullable()->after('product_id');

            $table->unsignedBigInteger('product_unit_id')->nullable()->change();
            $table->string('unit_name', 100)->nullable()->after('product_unit_id');

            $table->foreign('product_id')
                ->references('id')
                ->on('inventory_products')
                ->nullOnDelete();

            $table->foreign('product_unit_id')
                ->references('id')
                ->on('inventory_product_units')
                ->nullOnDelete();
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        // 1. إسقاط المفاتيح الأجنبية المعدلة
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_unit_id']);
        });

        // 2. التراجع عن التعديلات وحذف الأعمدة المضافة
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->dropColumn(['item_name', 'unit_name']);

            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->unsignedBigInteger('product_unit_id')->nullable(false)->change();

            $table->foreign('product_id')
                ->references('id')
                ->on('inventory_products')
                ->restrictOnDelete();

            $table->foreign('product_unit_id')
                ->references('id')
                ->on('inventory_product_units')
                ->restrictOnDelete();
        });
    }
};