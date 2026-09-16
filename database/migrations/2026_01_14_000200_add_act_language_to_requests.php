<?php

declare(strict_types=1);

use App\Support\Locales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The language the certificate is issued in, frozen at submission.
 *
 * WHY IT IS STORED RATHER THAN READ AT PRINT TIME. A certificate is signed
 * once, and its content fingerprint binds the signature to the exact text.
 * If the language were read from the session at download time, the same
 * signed certificate would come back in French for one reader and English for
 * another, and the fingerprint would no longer match what the mayor signed.
 *
 * NULL means a request submitted before this column existed. Those fall back
 * to the platform default rather than pretending to a choice nobody made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reissuance_requests', function (Blueprint $table): void {
            $table->string('act_language', 5)->nullable()->after('reason');
        });

        $codes = implode(', ', array_map(
            fn (string $c): string => "'".$c."'",
            array_keys(Locales::SUPPORTED),
        ));

        DB::statement(
            "ALTER TABLE reissuance_requests ADD CONSTRAINT requests_act_language_supported_check
             CHECK (act_language IS NULL OR act_language IN ({$codes}))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reissuance_requests DROP CONSTRAINT requests_act_language_supported_check');

        Schema::table('reissuance_requests', function (Blueprint $table): void {
            $table->dropColumn('act_language');
        });
    }
};
