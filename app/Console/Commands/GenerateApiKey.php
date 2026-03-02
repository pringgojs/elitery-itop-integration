<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apikey:generate {--name=} {--description=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new API Key';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('What is the name of this API Key?');
        $description = $this->option('description') ?? $this->ask('Description (optional)', '');

        // Generate a unique API key
        $apiKey = 'sk_' . Str::random(32);

        // Create the API key in database
        $createdKey = ApiKey::create([
            'key' => $apiKey,
            'name' => $name,
            'description' => $description ?: null,
            'is_active' => true,
        ]);

        $this->info('✓ API Key generated successfully!');
        $this->newLine();
        $this->table(
            ['ID', 'Name', 'Key', 'Created At'],
            [[
                $createdKey->id,
                $createdKey->name,
                $createdKey->key,
                $createdKey->created_at->format('Y-m-d H:i:s'),
            ]]
        );
        $this->newLine();
        $this->line('<comment>Usage in request header:</comment>');
        $this->line('X-API-Key: ' . $apiKey);

        return 0;
    }
}
