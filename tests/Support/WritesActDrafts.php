<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\ActDraft;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\ActDraftService;

/**
 * Redige un projet d'acte, comme le fait l'officier en production.
 *
 * POURQUOI CE TRAIT EXISTE. Depuis D-064, le maire ne signe pas un dossier :
 * il signe un PROJET etabli par l'officier. Les fixtures qui poussaient une
 * demande jusqu'a `awaiting_signature` par un appel direct au service de
 * transition sautaient donc une etape reelle, et douze tests sont tombes le
 * jour ou la regle est entree en vigueur — a juste titre.
 *
 * Ce trait appelle le MEME service que le controleur de l'officier. Fabriquer
 * un ActDraft a la main aurait contourne l'empreinte de contenu, c'est-a-dire
 * la seule chose qui rend cette lecture du diagramme sure.
 */
trait WritesActDrafts
{
    protected function redigeLeProjet(ReissuanceRequest $demande, User $officier): ActDraft
    {
        return app(ActDraftService::class)->draft($demande->refresh(), $officier);
    }
}
