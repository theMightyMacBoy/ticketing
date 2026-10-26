<?php

namespace Coderflex\LaravelTicket\Tests;

use Coderflex\LaravelTicket\LaravelTicketServiceProvider;
use Coderflex\LaravelTicket\Tests\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '6rE9Nz59bGRbeMATftriyQjrpF7DcOQm');

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Coderflex\\LaravelTicket\\Tests\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelTicketServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('auth.providers.users.model', User::class);
        config()->set('laravel_ticket.mail.schedule', false);

        $migrations = [
            include __DIR__.'/../database/migrations/create_tickets_table.php.stub',
            include __DIR__.'/../database/migrations/create_categories_table.php.stub',
            include __DIR__.'/../database/migrations/create_messages_table.php.stub',
            include __DIR__.'/../database/migrations/create_labels_table.php.stub',
            include __DIR__.'/../database/migrations/add_assigned_to_column_into_tickets_table.php.stub',

            // Many to Many tables
            include __DIR__.'/../database/migrations/create_label_ticket_table.php.stub',
            include __DIR__.'/../database/migrations/create_category_ticket_table.php.stub',

            // Tests Migration
            include __DIR__.'/Database/Migrations/create_users_table.php',
            include __DIR__.'/../database/migrations/install_enterprise_itsm.php.stub',
            include __DIR__.'/../database/migrations/add_support_mailboxes.php.stub',
            include __DIR__.'/../database/migrations/add_departments.php.stub',
        ];

        collect($migrations)->each(fn ($migration) => $migration->up());
    }
}
