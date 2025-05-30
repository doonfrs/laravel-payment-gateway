<?php

namespace Trinavo\PaymentGateway\Console;

use Illuminate\Console\Command;

class InstallPaymentGatewayCommand extends Command
{
    protected $signature = 'payment-gateway:install 
                           {--force : Force overwrite of existing files}
                           {--silent : Run in silent mode with default options}';

    protected $description = 'Install Laravel Payment Gateway assets and configurations';

    public function handle()
    {
        $this->info('Installing Laravel Payment Gateway...');

        // 1. Add tailwind source path
        $this->addTailwindSourcePath();

        // 2. Publish config
        $this->publishConfig();

        // 3. Publish views (only if --force or not silent mode and confirmed)
        if ($this->option('force') ||
            (! $this->option('silent') && $this->confirm('Would you like to publish the views?', false))) {
            $this->publishViews();
        }

        // 4. Publish translations (only if --force or not silent mode and confirmed)
        if ($this->option('force') ||
            (! $this->option('silent') && $this->confirm('Would you like to publish the translations?', false))) {
            $this->publishTranslations();

            $this->info('✓ Translations published to lang/vendor/payment-gateway directory');
            $this->info('  To customize translations, edit the JSON files in this directory.');
        }

        // 5. Run migrations (default in silent mode)
        if ($this->option('silent') || $this->confirm('Would you like to run migrations?', true)) {
            $this->call('migrate', $this->option('silent') ? ['--quiet' => true] : []);
        }

        $this->info('Laravel Payment Gateway has been installed successfully!');
        $this->info('To access the payment gateway, visit: /payment-gateway');
    }

    protected function addTailwindSourcePath()
    {
        $cssPath = base_path('resources/css/app.css');
        $sourceLine = "@source '../../vendor/trinavo/laravel-payment-gateway/resources/**/*.php';";

        if (file_exists($cssPath)) {
            $css = file_get_contents($cssPath);
            if (strpos($css, $sourceLine) === false) {
                file_put_contents($cssPath, $css."\n".$sourceLine);
                if (! $this->option('silent')) {
                    $this->info('✓ Source line added to app.css');
                }
            } elseif (! $this->option('silent')) {
                $this->info('✓ Source line already exists in app.css');
            }
        } elseif (! $this->option('silent')) {
            $this->error('× app.css not found! Make sure your application is using Tailwind CSS.');
        }
    }

    protected function publishConfig()
    {
        $params = ['--provider' => 'Trinavo\\PaymentGateway\\Providers\\PaymentGatewayServiceProvider'];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        if ($this->option('silent')) {
            $params['--quiet'] = true;
        }

        $this->call('vendor:publish', array_merge($params, ['--tag' => 'config']));
    }

    protected function publishViews()
    {
        $params = ['--provider' => 'Trinavo\\PaymentGateway\\Providers\\PaymentGatewayServiceProvider'];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        if ($this->option('silent')) {
            $params['--quiet'] = true;
        }

        $this->call('vendor:publish', array_merge($params, ['--tag' => 'payment-gateway-views']));
    }

    protected function publishTranslations()
    {
        $params = ['--provider' => 'Trinavo\\PaymentGateway\\Providers\\PaymentGatewayServiceProvider'];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        if ($this->option('silent')) {
            $params['--quiet'] = true;
        }

        $this->call('vendor:publish', array_merge($params, ['--tag' => 'payment-gateway-translations']));
    }
}
