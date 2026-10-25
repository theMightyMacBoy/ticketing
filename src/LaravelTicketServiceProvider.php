<?php

namespace Coderflex\LaravelTicket;

use Coderflex\LaravelTicket\Commands\EvaluateSlasCommand;
use Coderflex\LaravelTicket\Observers\MessageObserver;
use Coderflex\LaravelTicket\Observers\TicketObserver;
use Coderflex\LaravelTicket\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTicketServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-ticket')
            ->hasConfigFile('laravel_ticket')
            ->hasCommand(EvaluateSlasCommand::class)
            ->hasMigrations(
                'create_tickets_table',
                'create_messages_table',
                'create_categories_table',
                'create_labels_table',
                'create_category_ticket_table',
                'create_label_ticket_table',
                'add_assigned_to_column_into_tickets_table',
                'install_enterprise_itsm',
            );
    }

    public function packageBooted(): void
    {
        $ticket = config('laravel_ticket.models.ticket');
        $message = config('laravel_ticket.models.message');

        Gate::policy($ticket, TicketPolicy::class);

        $ticket::observe(TicketObserver::class);
        $message::observe(MessageObserver::class);
    }
}
