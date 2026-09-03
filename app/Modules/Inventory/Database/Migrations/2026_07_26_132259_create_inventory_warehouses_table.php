<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل ملف الهجرة
     */
    public function up(): void
    {
        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    /**
     * التراجع عن ملف الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouses');
    }
};