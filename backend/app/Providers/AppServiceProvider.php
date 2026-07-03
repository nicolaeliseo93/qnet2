<?php

namespace App\Providers;

use App\Authorization\FieldPermissionRepository;
use App\Models\Address;
use App\Models\Attachment;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\PersonalData;
use App\Models\Role;
use App\Models\User;
use App\Models\UserTablePreference;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so FieldPermissionRepository::forRoleIds() memoizes its
        // one query across the WHOLE request (spec 0006), even though
        // AuthorizationRegistry::resolve() may build several
        // ResourceAuthorization instances per request (e.g. the FormRequest's
        // EnforcesFieldPermissions AND the controller's permissions block).
        $this->app->singleton(FieldPermissionRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable morph aliases: the *_type columns (polymorphic relations plus
        // activity-log subject/causer) store these short strings instead of
        // fully-qualified class names. Defence in depth against morph-type
        // injection — enforceMorphMap makes any unmapped model throw rather than
        // silently persisting an arbitrary FQCN — and immunity to class
        // renames/moves. The 'user' alias matches the public attachable alias
        // already used by config/attachments.php.
        //
        // Because enforcement is strict, EVERY model that can appear in a morph
        // column (here: any audited subject/causer and every polymorphic owner)
        // must be listed.
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'personal_data' => PersonalData::class,
            'contact' => Contact::class,
            'address' => Address::class,
            'attachment' => Attachment::class,
            'user_table_preference' => UserTablePreference::class,
            'business_function' => BusinessFunction::class,
            'company' => Company::class,
            'operational_site' => OperationalSite::class,
            'employment_profile' => EmploymentProfile::class,
        ]);

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}
