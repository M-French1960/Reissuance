<?php

declare(strict_types=1);

use App\Support\Locales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The language an account is served in.
 *
 * Kept on the account rather than in the session alone: an officer who set
 * French once should not have to set it again at a shared counter, and a
 * citizen who reads English should get English in their notification e-mails,
 * which are sent by a queued job with no session to read.
 *
 * NULL means "never chosen": the session, then the browser, then the platform
 * default decide. That is not the same as having picked the default, and the
 * column keeps the difference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('status');
        });

        $codes = implode(', ', array_map(
            fn (string $c): string => "'".$c."'",
            array_keys(Locales::SUPPORTED),
        ));

        // A language code the application does not serve would render an
        // account in no language at all. The database refuses it.
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_locale_supported_check
             CHECK (locale IS NULL OR locale IN ({$codes}))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_locale_supported_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
