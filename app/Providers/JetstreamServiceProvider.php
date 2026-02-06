<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Jetstream\AddOrganizationMember;
use App\Actions\Jetstream\CreateOrganization;
use App\Actions\Jetstream\DeleteOrganization;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteOrganizationMember;
use App\Actions\Jetstream\RemoveOrganizationMember;
use App\Actions\Jetstream\UpdateMemberRole;
use App\Actions\Jetstream\UpdateOrganization;
use App\Actions\Jetstream\ValidateOrganizationDeletion;
use App\Enums\Weekday;
use App\Permissions\CorePermissions;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Service\TimezoneService;
use Brick\Money\Currency;
use Brick\Money\ISOCurrencyProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole;
use Laravel\Jetstream\Actions\ValidateTeamDeletion;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateOrganization::class);
        Jetstream::updateTeamNamesUsing(UpdateOrganization::class);
        Jetstream::addTeamMembersUsing(AddOrganizationMember::class);
        Jetstream::inviteTeamMembersUsing(InviteOrganizationMember::class);
        Jetstream::removeTeamMembersUsing(RemoveOrganizationMember::class);
        Jetstream::deleteTeamsUsing(DeleteOrganization::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
        Jetstream::useTeamModel(Organization::class);
        Jetstream::useMembershipModel(Member::class);
        Jetstream::useTeamInvitationModel(OrganizationInvitation::class);
        app()->singleton(UpdateTeamMemberRole::class, UpdateMemberRole::class);
        app()->singleton(ValidateTeamDeletion::class, ValidateOrganizationDeletion::class);
        Fortify::registerView(function () {
            return Inertia::render('Auth/Register', [
                'terms_url' => config('auth.terms_url'),
                'privacy_policy_url' => config('auth.privacy_policy_url'),
                'newsletter_consent' => config('auth.newsletter_consent'),
            ]);
        });
        Gate::define('removeTeamMember', function (User $user, Organization $team) {
            return false;
        });
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions([]);

        // Core permissions (extracted per SF-08 modular pattern)
        CorePermissions::register();

        // Feature permissions will be registered here by each feature:
        // TimesheetApprovalPermissions::register();
        // ExpensePermissions::register();
        // BudgetPermissions::register();
        // InvoicePermissions::register();
        // CalendarPermissions::register();
        // KioskPermissions::register();
        // TimeOffPermissions::register();
        // SchedulingPermissions::register();
        // ReportingPermissions::register();
        // TeamPermissions::register();

        Jetstream::inertia()
            ->whenRendering(
                'Profile/Show',
                function (Request $request, array $data): array {
                    return array_merge($data, [
                        'timezones' => $this->app->get(TimezoneService::class)->getSelectOptions(),
                        'weekdays' => Weekday::toSelectArray(),
                    ]);
                }
            )
            ->whenRendering(
                'Teams/Show',
                function (Request $request, array $data): array {
                    /** @var Organization $teamModel */
                    $teamModel = $data['team'];
                    $owner = $teamModel->owner;

                    return array_merge($data, [
                        'team' => [
                            'id' => $teamModel->getKey(),
                            'name' => $teamModel->name,
                            'currency' => $teamModel->currency,
                            'owner' => [
                                'id' => $owner->getKey(),
                                'name' => $owner->name,
                                'email' => $owner->email,
                                'profile_photo_url' => $owner->profile_photo_url,
                            ],
                            'users' => $teamModel->users->map(function (User $user): array {
                                return [
                                    'id' => $user->getKey(),
                                    'name' => $user->name,
                                    'email' => $user->email,
                                    'profile_photo_url' => $user->profile_photo_url,
                                    'membership' => [
                                        'id' => $user->membership->id,
                                        'role' => $user->membership->role,
                                    ],
                                ];
                            }),
                            'team_invitations' => $teamModel->teamInvitations->map(function (OrganizationInvitation $invitation): array {
                                return [
                                    'id' => $invitation->getKey(),
                                    'email' => $invitation->email,
                                    'role' => $invitation->role,
                                ];
                            }),
                        ],
                        'currencies' => array_map(function (Currency $currency): string {
                            return $currency->getName();
                        }, ISOCurrencyProvider::getInstance()->getAvailableCurrencies()),
                    ]);
                }
            );
    }
}
