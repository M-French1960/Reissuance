<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfficialUserRequest;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\Commune;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Portail administrateur : gouvernance des comptes uniquement.
 *
 * Aucune methode de ce controleur ne donne acces au contenu d'un dossier
 * d'identite. C'est l'application du 4.2 du brief : separation de la
 * gouvernance et des donnees personnelles.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['center:id,name', 'commune:id,name'])
            ->when($request->filled('recherche'), function ($query) use ($request): void {
                $term = trim((string) $request->input('recherche'));
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('statut'), fn ($q) => $q->where('status', $request->input('statut')))
            ->orderBy('role')
            ->orderBy('name')
            // Pagination systematique : aucune collection non bornee (8.5).
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'centers' => CivilStatusCenter::with('commune:id,name')->orderBy('name')->get(),
            'communes' => Commune::orderBy('name')->get(),
        ]);
    }

    public function store(StoreOfficialUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);

        $user = DB::transaction(function () use ($data, $role, $request): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Mot de passe aleatoire jamais communique : le compte est
                // active par le lien de definition envoye a son titulaire.
                // L'administrateur ne choisit jamais un mot de passe (4.2).
                'password' => Hash::make(Str::random(64)),
                'role' => $role->value,
                // Cree « pending » : son titulaire peut se connecter pour
                // poser sa 2FA, et rien d'autre. Il ne devient actif que par
                // une action explicite d'un administrateur, une fois la 2FA
                // confirmee.
                'status' => 'pending',
                'civil_status_center_id' => $role === UserRole::Officer ? $data['civil_status_center_id'] : null,
                'commune_id' => $role === UserRole::Mayor ? $data['commune_id'] : null,
            ]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'actor_role' => UserRole::Admin->value,
                'action' => 'account.created',
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'reason' => "Rôle : {$role->label()}",
                'ip_address' => $request->ip(),
            ]);

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Compte créé pour {$user->name}. Un lien d'activation lui a été envoyé.");
    }

    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('changeStatus', $user);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,active,suspended,disabled'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.min' => "Indiquez le motif de ce changement : il figurera au journal d'audit.",
        ]);

        // Un compte officiel ne peut etre active qu'une fois sa 2FA posee.
        // La contrainte en base le refuserait ; on rend le message utilisable.
        if ($validated['status'] === 'active'
            && $user->role->requiresTwoFactor()
            && $user->two_factor_confirmed_at === null) {
            return back()->withErrors([
                'status' => "Ce compte ne peut pas être activé tant que sa double authentification n'est pas configurée par son titulaire.",
            ]);
        }

        $previous = $user->status;

        DB::transaction(function () use ($user, $validated, $previous, $request): void {
            $user->forceFill(['status' => $validated['status']])->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'actor_role' => UserRole::Admin->value,
                'action' => 'account.status_changed',
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'from_status' => $previous,
                'to_status' => $validated['status'],
                'reason' => $validated['reason'],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', "Statut de {$user->name} mis à jour.");
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $this->authorize('triggerPasswordReset', $user);

        Password::sendResetLink(['email' => $user->email]);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => UserRole::Admin->value,
            'action' => 'account.password_reset_requested',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', "Un lien de réinitialisation a été envoyé à {$user->name}.");
    }

    public function reassign(Request $request, User $user): RedirectResponse
    {
        $this->authorize('reassign', $user);

        $validated = $request->validate([
            'civil_status_center_id' => ['nullable', 'integer', 'exists:civil_status_centers,id'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $before = $user->role === UserRole::Officer
            ? "centre #{$user->civil_status_center_id}"
            : "commune #{$user->commune_id}";

        DB::transaction(function () use ($user, $validated, $before, $request): void {
            $user->forceFill($user->role === UserRole::Officer
                ? ['civil_status_center_id' => $validated['civil_status_center_id']]
                : ['commune_id' => $validated['commune_id']])->save();

            $after = $user->role === UserRole::Officer
                ? "centre #{$user->civil_status_center_id}"
                : "commune #{$user->commune_id}";

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'actor_role' => UserRole::Admin->value,
                'action' => 'account.reassigned',
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'reason' => "{$before} → {$after}. {$validated['reason']}",
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', "Rattachement de {$user->name} modifié.");
    }
}
