<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('action', 30);
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
