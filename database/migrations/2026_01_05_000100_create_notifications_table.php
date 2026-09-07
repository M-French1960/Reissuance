<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications applicatives.
 *
 * Le canal `database` sert le centre de notifications dans l'interface : c'est
 * le seul canal dont on soit sur qu'il arrive. Le courriel part en plus, quand
 * une adresse existe et que le serveur repond.
 *
 * Ce que cette table NE contient PAS : le motif d'un rejet, ni aucun element
 * du dossier. Elle porte la reference et le changement d'etat, et renvoie a la
 * demande. Le detail se lit connecte (garde-fou n6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Le centre de notifications trie par date et compte les non lues.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
