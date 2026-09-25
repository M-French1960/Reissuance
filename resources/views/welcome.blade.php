{{--
    PAGE D'ACCUEIL (D-072, D-078).

    ELLE NE PASSE PAS PAR layouts/app.blade.php, ET C'EST VOLONTAIRE. La
    maquette retenue porte son propre en-tête et son propre pied de page ; la
    faire entrer dans la coquille des écrans de travail aurait imposé deux
    en-têtes superposés. Elle charge donc tokens.css et home.css, et rien
    d'autre : le visiteur n'a besoin d'aucune règle de app.css.

    CE QUE LA MAQUETTE LIVRÉE PROMETTAIT ET QUI N'A PAS ÉTÉ REPRIS :

      - ses styles et son script étaient EN LIGNE, et ses polices venaient de
        Google. La politique de sécurité déclare `style-src 'self'`,
        `script-src 'self'` et `font-src 'self'` : la page se serait affichée
        entièrement nue, sans script. Tout a été sorti dans des fichiers
        servis depuis ce serveur, polices comprises ;
      - un champ de suivi public avec un numéro d'exemple. Le suivi d'un
        dossier n'est pas public, et le format réel des références n'est pas
        celui de la maquette. Remplacé par un renvoi vers la connexion ;
      - « Vous êtes prévenu par SMS et e-mail ». L'application n'envoie
        AUCUN SMS : les canaux sont la base et le courriel ;
      - « une déclaration de perte délivrée par le commissariat ». Le
        formulaire ne demande que deux pièces : une pièce d'identité et une
        photo du demandeur ;
      - actes de mariage et de décès marqués « Bientôt ». Personne n'a promis
        de date ; ils sont donnés pour ce qu'ils sont, non traités ;
      - deux réponses de FAQ laissées en « [À compléter] », visibles par le
        citoyen ;
      - un sélecteur de langue « visuel uniquement ». Remplacé par le vrai
        composant du service ;
      - des liens de pied de page vers Confidentialité, Contact et Conditions
        d'utilisation : trois pages qui n'existent pas.

    ET CE QU'ELLE N'AVAIT PAS : le bandeau de démonstration. Tant que le
    prestataire de signature est l'adaptateur de démonstration, les actes
    produits portent « sans valeur juridique ». Le dire ici, et pas à la fin
    du parcours (D-025, §10 du brief).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>{{ __('home.title') }} | {{ __('common.brand') }}</title>
    <meta name="description" content="{{ __('home.meta_description') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    <script src="{{ asset('js/home.js') }}" defer></script>
    <script src="{{ asset('js/language-switch.js') }}" defer></script>
</head>
<body class="home">
    <a class="skip-link" href="#contenu">{{ __('common.skip_to_content') }}</a>

    <header class="home-header">
        <div class="home-wrap">
            <nav class="home-nav" aria-label="{{ __('common.main_navigation') }}">
                <a class="home-brand" href="{{ route('home') }}" aria-current="page">
                    <svg class="home-brand__mark" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
                        <circle cx="16" cy="16" r="15" stroke="currentColor" stroke-width="2" opacity="0.5"/>
                        <path d="M10 22V10h7a4 4 0 0 1 0 8h-7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="home-brand__name">{{ __('common.brand') }}</span>
                </a>

                <ul class="home-nav__links" data-home-menu>
                    <li><a href="#documents">{{ __('home.nav_documents') }}</a></li>
                    <li><a href="#etapes">{{ __('home.nav_steps') }}</a></li>
                    <li><a href="#preparer">{{ __('home.nav_checklist') }}</a></li>
                    <li><a href="#questions">{{ __('home.nav_faq') }}</a></li>
                </ul>

                <div class="home-nav__actions">
                    <x-language-switcher />
                    <a class="home-btn home-btn--ghost" href="{{ route('login') }}">{{ __('common.sign_in') }}</a>
                    <a class="home-btn home-btn--primary" href="{{ route('register') }}">{{ __('common.create_account') }}</a>

                    <button type="button" class="home-menu-toggle"
                            data-home-menu-toggle
                            aria-expanded="false"
                            aria-label="{{ __('home.menu_open') }}"
                            data-label-open="{{ __('home.menu_open') }}"
                            data-label-close="{{ __('home.menu_close') }}">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true" focusable="false">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </nav>
        </div>
    </header>

    @unless ($signatureEngage)
        {{-- Avant le héros, et non après : c'est la première chose à lire. --}}
        <aside class="home-banner" aria-labelledby="demo-titre">
            <div class="home-wrap home-banner__box">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true" focusable="false">
                    <path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <div>
                    <p class="home-banner__title" id="demo-titre">{{ __('home.demo_title') }}</p>
                    <p>{!! __('home.demo_body', ['mention' => '<strong>'.e(__('home.demo_mention')).'</strong>']) !!}</p>
                </div>
            </div>
        </aside>
    @endunless

    <main id="contenu" tabindex="-1">
        <section class="home-hero">
            <span class="home-orb home-orb--1" aria-hidden="true"></span>
            <span class="home-orb home-orb--2" aria-hidden="true"></span>

            <div class="home-wrap home-hero__grid">
                <div>
                    <h1>{{ __('home.hero_title') }}</h1>
                    <p class="home-hero__lead">{{ __('home.hero_lead') }}</p>

                    <div class="home-hero__cta">
                        <a class="home-btn home-btn--primary" href="{{ route('register') }}">{{ __('home.hero_start') }}</a>
                        <a class="home-btn home-btn--ghost" href="#etapes">{{ __('home.hero_steps') }}</a>
                    </div>

                    {{--
                        LA MAQUETTE METTAIT ICI UN CHAMP DE SUIVI PUBLIC.

                        Il n'existe pas de route publique de suivi, et il ne
                        doit pas en exister : une référence est devinable, et
                        un suivi public dirait à qui la saisit le nom, le
                        centre et l'état du dossier d'un inconnu.
                    --}}
                    <div class="home-track">
                        <strong class="home-track__label">{{ __('home.track_label') }}</strong>
                        <a class="home-btn home-btn--ghost" href="{{ route('login') }}">{{ __('home.track_action') }}</a>
                        <p class="home-track__hint">{{ __('home.track_body') }}</p>
                    </div>
                </div>

                <div class="home-doc-stage">
                    {{--
                        Illustration, et RIEN D'AUTRE. Aucun nom, aucun centre,
                        aucune date : les champs portent leur propre légende
                        (« Votre nom », « Le centre que vous choisissez »).
                        Inventer une titulaire camerounaise plausible aurait
                        mis en scène une personne qui peut exister (§13).
                    --}}
                    <article class="home-doc" aria-labelledby="exemple-titre">
                        <div class="home-doc__head">
                            <div>
                                <p class="home-doc__title" id="exemple-titre">{{ __('home.mock_title') }}</p>
                                <p class="home-doc__sub">{{ __('home.mock_sub') }}</p>
                            </div>
                            <svg class="home-doc__seal" viewBox="0 0 52 52" fill="none" aria-hidden="true" focusable="false">
                                <circle cx="26" cy="26" r="24" stroke="currentColor" stroke-width="1.5" opacity="0.55"/>
                                <circle cx="26" cy="26" r="18" stroke="currentColor" stroke-width="1" opacity="0.35"/>
                                <path d="m19 26 5 5 10-10" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>

                        <div class="home-doc__lines">
                            <div class="home-doc__line">
                                <span>{{ __('home.mock_holder_label') }}</span>
                                <span>{{ __('home.mock_holder_value') }}</span>
                            </div>
                            <div class="home-doc__line">
                                <span>{{ __('home.mock_center_label') }}</span>
                                <span>{{ __('home.mock_center_value') }}</span>
                            </div>
                            <div class="home-doc__line">
                                <span>{{ __('home.mock_reason_label') }}</span>
                                <span>{{ __('home.mock_reason_value') }}</span>
                            </div>
                        </div>

                        <ol class="home-timeline">
                            <li class="is-done">
                                <span class="home-dot" aria-hidden="true">1</span>
                                <span><strong>{{ __('home.mock_1') }}</strong><small>{{ __('home.mock_1_note') }}</small></span>
                            </li>
                            <li class="is-done">
                                <span class="home-dot" aria-hidden="true">2</span>
                                <span><strong>{{ __('home.mock_2') }}</strong><small>{{ __('home.mock_2_note') }}</small></span>
                            </li>
                            <li class="is-current">
                                <span class="home-dot" aria-hidden="true">3</span>
                                <span><strong>{{ __('home.mock_3') }}</strong><small>{{ __('home.mock_3_note') }}</small></span>
                            </li>
                            <li>
                                <span class="home-dot" aria-hidden="true">4</span>
                                <span><strong>{{ __('home.mock_4') }}</strong><small>{{ __('home.mock_4_note') }}</small></span>
                            </li>
                        </ol>

                        <div class="home-doc__badge">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true" focusable="false">
                                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M10 21h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            {{ __('home.mock_badge') }}
                        </div>
                    </article>

                    <p class="home-doc-stage__caption">{{ __('home.mock_caption') }}</p>
                </div>
            </div>
        </section>

        <section class="home-section home-section--tinted" id="documents" aria-labelledby="documents-titre">
            <div class="home-wrap">
                <h2 id="documents-titre">{{ __('home.docs_title') }}</h2>
                <p class="home-section__intro">{{ __('home.docs_intro') }}</p>

                <div class="home-docs">
                    <article class="home-doc-card home-glass home-doc-card--featured">
                        <span class="home-status home-status--live">{{ __('home.docs_available') }}</span>
                        <div class="home-doc-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none">
                                <path d="M6 3h8l4 4v14H6V3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M14 3v4h4M9 13h6M9 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3>{{ __('home.docs_birth_title') }}</h3>
                        <p>{{ __('home.docs_birth_body') }}</p>
                        <a class="home-btn home-btn--primary" href="{{ route('register') }}">{{ __('home.docs_birth_cta') }}</a>
                    </article>

                    <article class="home-doc-card home-glass">
                        <span class="home-status home-status--off">{{ __('home.docs_unavailable') }}</span>
                        <div class="home-doc-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none">
                                <path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3>{{ __('home.docs_marriage_title') }}</h3>
                        <p>{{ __('home.docs_marriage_body') }}</p>
                    </article>

                    <article class="home-doc-card home-glass">
                        <span class="home-status home-status--off">{{ __('home.docs_unavailable') }}</span>
                        <div class="home-doc-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none">
                                <path d="M12 21V8M7 12h10M12 3a2.5 2.5 0 0 0 0 5 2.5 2.5 0 0 0 0-5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3>{{ __('home.docs_death_title') }}</h3>
                        <p>{{ __('home.docs_death_body') }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="home-section" id="etapes" aria-labelledby="etapes-titre">
            <div class="home-wrap">
                <h2 id="etapes-titre">{{ __('home.steps_title') }}</h2>
                <p class="home-section__intro">{{ __('home.steps_intro') }}</p>

                <ol class="home-steps">
                    @foreach ([1, 2, 3, 4] as $numero)
                        <li class="home-step home-glass">
                            <span class="home-step__num" aria-hidden="true">{{ $numero }}</span>
                            <h3>{{ __("home.step{$numero}_title") }}</h3>
                            <p>{{ __("home.step{$numero}_body") }}</p>
                            <span class="home-step__who">{{ __("home.step{$numero}_who") }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="home-section home-section--tinted" id="preparer" aria-labelledby="preparer-titre">
            <div class="home-wrap home-checklist-wrap">
                <div>
                    <h2 id="preparer-titre">{{ __('home.bring_title') }}</h2>
                    <p class="home-section__intro">{{ __('home.bring_intro') }}</p>

                    <aside class="home-aside home-glass">
                        <h3>{{ __('home.aside_title') }}</h3>
                        <p>{{ __('home.aside_body') }}</p>
                        <a class="home-btn home-btn--solid" href="{{ route('register') }}">{{ __('home.aside_cta') }}</a>
                    </aside>
                </div>

                <ul class="home-checklist home-glass">
                    @foreach (['id', 'photo', 'details', 'email'] as $element)
                        <li>
                            <span class="home-checklist__tick" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                                    <path d="m5 13 5 5L20 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span>
                                <strong>{{ __("home.bring_{$element}_title") }}</strong>
                                <span class="home-checklist__desc">{{ __("home.bring_{$element}_desc") }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="home-wrap">
                <p class="home-section__intro">{{ __('home.bring_note') }}</p>
            </div>
        </section>

        <section class="home-section" aria-labelledby="confiance-titre">
            <div class="home-wrap">
                <h2 id="confiance-titre">{{ __('home.trust_title') }}</h2>
                <p class="home-section__intro">{{ __('home.trust_intro') }}</p>

                <div class="home-trust">
                    <div class="home-trust__item home-glass">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" aria-hidden="true" focusable="false">
                            <rect x="4" y="10" width="16" height="11" rx="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <h3>{{ __('home.trust1_title') }}</h3>
                        <p>{{ __('home.trust1_body') }}</p>
                    </div>

                    <div class="home-trust__item home-glass">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" aria-hidden="true" focusable="false">
                            <path d="M4 18c3-5 6 1 9-3s4 1 7-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M4 21h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <h3>{{ __('home.trust2_title') }}</h3>
                        <p>{{ __('home.trust2_body') }}</p>
                    </div>

                    <div class="home-trust__item home-glass">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3>{{ __('home.trust3_title') }}</h3>
                        <p>{{ __('home.trust3_body') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-section home-section--tinted" id="questions" aria-labelledby="questions-titre">
            <div class="home-wrap">
                <h2 id="questions-titre">{{ __('home.faq_title') }}</h2>
                <p class="home-section__intro">{{ __('home.faq_intro') }}</p>

                {{--
                    Aucune réponse n'est laissée à compléter. Celle du délai
                    dit qu'aucun délai n'est annoncé, et pourquoi : la question
                    est ouverte côté administration (docs/COMPLIANCE_OPEN_QUESTIONS.md,
                    question D8). Afficher un chiffre inventé aurait été pire
                    qu'un blanc.
                --}}
                <div class="home-faq">
                    @foreach ([1, 2, 3, 4, 5] as $question)
                        <details class="home-glass">
                            <summary>{{ __("home.faq_q{$question}") }}</summary>
                            <p>{{ __("home.faq_a{$question}") }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="home-section" aria-labelledby="final-titre">
            <div class="home-wrap">
                <div class="home-final">
                    <div>
                        <h2 id="final-titre">{{ __('home.final_title') }}</h2>
                        <p>{{ __('home.final_body') }}</p>
                    </div>
                    <a class="home-btn home-btn--primary" href="{{ route('register') }}">{{ __('home.final_cta') }}</a>
                </div>
            </div>
        </section>
    </main>

    {{--
        PIED DE PAGE : uniquement des liens qui mènent quelque part.

        La maquette en proposait douze, répartis en quatre colonnes, dont
        Confidentialité, Contact et Conditions d'utilisation. Ces trois pages
        n'existent pas, et un lien mort en pied de page d'un service public
        est une promesse cassée. Trois colonnes, six liens, tous vivants.
    --}}
    <footer class="home-footer">
        <div class="home-wrap">
            <div class="home-footer__grid">
                <div>
                    <span class="home-brand home-footer__brand">
                        <span class="home-brand__name">{{ __('common.brand') }}</span>
                    </span>
                    <p class="home-footer__tag">{{ __('home.footer_tag') }}</p>
                </div>

                <div>
                    <h2>{{ __('home.footer_service') }}</h2>
                    <ul>
                        <li><a href="{{ route('register') }}">{{ __('home.footer_birth') }}</a></li>
                        <li><a href="{{ route('login') }}">{{ __('home.footer_track') }}</a></li>
                        <li><a href="{{ route('login') }}">{{ __('home.footer_signin') }}</a></li>
                    </ul>
                </div>

                <div>
                    <h2>{{ __('home.footer_help') }}</h2>
                    <ul>
                        <li><a href="#questions">{{ __('home.nav_faq') }}</a></li>
                        <li><a href="#preparer">{{ __('home.nav_checklist') }}</a></li>
                        <li><a href="{{ route('health') }}">{{ __('home.footer_status') }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="home-footer__bottom">
                <span>{{ __('home.footer_year', ['year' => now()->year]) }}</span>
                <span>{{ __('common.footer') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
