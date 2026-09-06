<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();

            // Chiffre par cast Eloquent. Non interrogeable : voir national_id_hash.
            $table->text('national_id_number')->nullable();

            // Index aveugle HMAC-SHA256 (docs/DATA_MODEL.md 3).
            // Permet la recherche par numero EXACT et la detection de doublons.
            // Ne permet ni recherche partielle, ni tri.
            $table->string('national_id_hash', 64)->nullable()->unique();

            $table->string('phone', 32)->nullable();
            $table->string('address')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_profiles');
    }
};
