<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\NavigationItem;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\HtmlSanitizer;
use App\Support\Navigation;
use App\Support\SiteSettings;
use App\View\Composers\SiteComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SiteSettings::class);
        $this->app->scoped(Navigation::class);
        $this->app->singleton(HtmlSanitizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        Password::defaults(fn () => Password::min(12)->letters()->numbers()->max(128));

        $this->configureRateLimiting();
        $this->registerAuditLogging();

        View::composer(['components.layout', 'components.site-header', 'components.site-footer'], SiteComposer::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by('contact-minute:'.$request->ip()),
            Limit::perDay(20)->by('contact-day:'.$request->ip()),
        ]);

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by('search:'.$request->ip()));
    }

    /**
     * Sensitive administrative actions are written to the activity log.
     * Content workflow, media and settings changes are logged by their
     * services; sign-ins and changes to users, menus and taxonomy are logged here.
     */
    private function registerAuditLogging(): void
    {
        $logger = fn () => $this->app->make(ActivityLogger::class);

        Event::listen(function (Login $event) use ($logger): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                $logger()->log('auth.login', 'Signed in', $event->user, [], $event->user);
            }
        });

        Event::listen(function (Logout $event) use ($logger): void {
            if ($event->user instanceof User) {
                $logger()->log('auth.logout', 'Signed out', $event->user, [], $event->user);
            }
        });

        Event::listen(function (Failed $event) use ($logger): void {
            $email = (string) ($event->credentials['email'] ?? '');
            $logger()->log('auth.failed', 'Failed sign-in attempt', null, ['email' => Str::limit($email, 190, '')]);
        });

        Event::listen(function (PasswordReset $event) use ($logger): void {
            if ($event->user instanceof User) {
                $logger()->log('auth.password_reset', 'Password reset', $event->user, [], $event->user);
            }
        });

        foreach ([User::class, NavigationItem::class, Category::class, Tag::class] as $model) {
            foreach (['created', 'updated', 'deleted'] as $event) {
                $model::$event(function (Model $record) use ($logger, $event): void {
                    if (! auth()->check()) {
                        return;
                    }

                    $changed = array_values(array_diff(
                        array_keys($record->getChanges()),
                        ['updated_at', 'password', 'remember_token', 'last_login_at'],
                    ));

                    if ($event === 'updated' && $changed === [] && ! $record->wasChanged('password')) {
                        return;
                    }

                    $label = Str::headline(class_basename($record));
                    $name = $record->getAttribute('name') ?? $record->getAttribute('label') ?? '#'.$record->getKey();

                    $logger()->log(
                        Str::snake(class_basename($record)).'.'.$event,
                        ucfirst($event).' '.Str::lower($label).' "'.$name.'"',
                        $record,
                        array_filter([
                            'changed' => $changed ?: null,
                            'password_changed' => $record->wasChanged('password') ?: null,
                            'role' => $record instanceof User ? $record->role?->value : null,
                        ]),
                    );
                });
            }
        }
    }
}
