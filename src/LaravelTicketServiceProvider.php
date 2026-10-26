<?php

namespace Coderflex\LaravelTicket;

use Coderflex\LaravelTicket\Commands\EvaluateSlasCommand;
use Coderflex\LaravelTicket\Commands\FetchMailCommand;
use Coderflex\LaravelTicket\Commands\PurgeMailCommand;
use Coderflex\LaravelTicket\Mail\GraphMailboxReader;
use Coderflex\LaravelTicket\Mail\MailboxReader;
use Coderflex\LaravelTicket\Observers\MessageObserver;
use Coderflex\LaravelTicket\Observers\TicketObserver;
use Coderflex\LaravelTicket\Policies\TicketPolicy;
use Illuminate\Console\Scheduling\Schedule;
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
            ->hasViews('laravel-ticket')
            ->hasCommands(
                EvaluateSlasCommand::class,
                FetchMailCommand::class,
                PurgeMailCommand::class,
            )
            ->hasMigrations(
                'create_tickets_table',
                'create_messages_table',
                'create_categories_table',
                'create_labels_table',
                'create_category_ticket_table',
                'create_label_ticket_table',
                'add_assigned_to_column_into_tickets_table',
                'install_enterprise_itsm',
                'add_support_mailboxes',
                'add_departments',
            );
    }

    public function packageRegistered(): void
    {
        $this->app->bind(MailboxReader::class, GraphMailboxReader::class);
    }

    public function packageBooted(): void
    {
        $ticket = config('laravel_ticket.models.ticket');
        $message = config('laravel_ticket.models.message');

        Gate::policy($ticket, TicketPolicy::class);

        $ticket::observe(TicketObserver::class);
        $message::observe(MessageObserver::class);

        $this->app->booted(function () {
            if (! config('laravel_ticket.mail.schedule')) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);

            $schedule->command('tickets:fetch-mail')
                ->everyMinute()
                ->withoutOverlapping();

            $schedule->command('tickets:purge-mail')
                ->dailyAt('00:00')
                ->timezone('UTC')
                ->withoutOverlapping();
        });
    }
}
