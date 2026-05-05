<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_shopping_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('list_name')->default('default');
            $table->string('category')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->nullable(); // kg, pz, L, etc.
            $table->boolean('is_bought')->default(false);
            $table->timestamp('bought_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_shopping_items');
    }
};
