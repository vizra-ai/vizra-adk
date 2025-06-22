<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ManagePromptsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vizra:prompt 
                            {action : The action to perform (create, list, activate, export, import)}
                            {agent? : The agent name}
                            {version? : The version name}
                            {--file= : File path for import/export}
                            {--content= : Prompt content for create action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage agent prompt versions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createPrompt(),
            'list' => $this->listPrompts(),
            'activate' => $this->activatePrompt(),
            'export' => $this->exportPrompt(),
            'import' => $this->importPrompt(),
            default => $this->error("Unknown action: {$action}") || 1,
        };
    }

    /**
     * Create a new prompt version
     */
    protected function createPrompt(): int
    {
        $agent = $this->argument('agent');
        $version = $this->argument('version');

        if (! $agent || ! $version) {
            $this->error('Agent name and version are required for create action');

            return 1;
        }

        // Get content from option or ask interactively
        $content = $this->option('content');
        if (! $content) {
            $this->info("Enter the prompt content for {$agent} version {$version}:");
            $this->info('(Press Ctrl+D when done)');
            $content = '';
            while ($line = fgets(STDIN)) {
                $content .= $line;
            }
        }

        // Save to file
        $promptPath = config('vizra-adk.prompts.storage_path', resource_path('prompts'));
        $agentPath = "{$promptPath}/{$agent}";

        if (! File::exists($agentPath)) {
            File::makeDirectory($agentPath, 0755, true);
        }

        $filePath = "{$agentPath}/{$version}.md";
        File::put($filePath, trim($content));

        $this->info("Created prompt version {$version} for agent {$agent}");
        $this->line("File saved to: {$filePath}");

        // Optionally save to database
        if (config('vizra-adk.prompts.use_database', false)) {
            DB::table('agent_prompt_versions')->updateOrInsert(
                [
                    'agent_name' => $agent,
                    'version' => $version,
                ],
                [
                    'instructions' => trim($content),
                    'metadata' => json_encode(['source' => 'cli']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $this->info('Also saved to database');
        }

        return 0;
    }

    /**
     * List all prompt versions
     */
    protected function listPrompts(): int
    {
        $agent = $this->argument('agent');

        $headers = ['Agent', 'Version', 'Source', 'Active', 'Created'];
        $rows = [];

        // Get database prompts
        if (config('vizra-adk.prompts.use_database', false)) {
            $query = DB::table('agent_prompt_versions');
            if ($agent) {
                $query->where('agent_name', $agent);
            }

            $dbPrompts = $query->get();
            foreach ($dbPrompts as $prompt) {
                $rows[] = [
                    $prompt->agent_name,
                    $prompt->version,
                    'Database',
                    $prompt->is_active ? '✓' : '',
                    $prompt->created_at,
                ];
            }
        }

        // Get file prompts
        $promptPath = config('vizra-adk.prompts.storage_path', resource_path('prompts'));

        if ($agent) {
            $agents = [$agent];
        } else {
            $agents = File::exists($promptPath) ? File::directories($promptPath) : [];
            $agents = array_map('basename', $agents);
        }

        foreach ($agents as $agentName) {
            $agentPath = "{$promptPath}/{$agentName}";
            if (File::exists($agentPath)) {
                $files = File::files($agentPath);
                foreach ($files as $file) {
                    $version = $file->getFilenameWithoutExtension();

                    // Skip if already in database
                    $exists = collect($rows)->contains(function ($row) use ($agentName, $version) {
                        return $row[0] === $agentName && $row[1] === $version;
                    });

                    if (! $exists) {
                        $rows[] = [
                            $agentName,
                            $version,
                            'File',
                            $version === 'default' ? '✓' : '',
                            date('Y-m-d H:i:s', $file->getMTime()),
                        ];
                    }
                }
            }
        }

        if (empty($rows)) {
            $this->info('No prompt versions found');

            return 0;
        }

        $this->table($headers, $rows);

        return 0;
    }

    /**
     * Activate a prompt version
     */
    protected function activatePrompt(): int
    {
        if (! config('vizra-adk.prompts.use_database', false)) {
            $this->error('Database storage must be enabled to activate prompts');

            return 1;
        }

        $agent = $this->argument('agent');
        $version = $this->argument('version');

        if (! $agent || ! $version) {
            $this->error('Agent name and version are required for activate action');

            return 1;
        }

        // Deactivate all versions for this agent
        DB::table('agent_prompt_versions')
            ->where('agent_name', $agent)
            ->update(['is_active' => false]);

        // Activate the specified version
        $updated = DB::table('agent_prompt_versions')
            ->where('agent_name', $agent)
            ->where('version', $version)
            ->update(['is_active' => true]);

        if ($updated) {
            $this->info("Activated version {$version} for agent {$agent}");
        } else {
            $this->error("Version {$version} not found for agent {$agent}");

            return 1;
        }

        return 0;
    }

    /**
     * Export a prompt to file
     */
    protected function exportPrompt(): int
    {
        $agent = $this->argument('agent');
        $version = $this->argument('version');
        $file = $this->option('file');

        if (! $agent || ! $version) {
            $this->error('Agent name and version are required for export action');

            return 1;
        }

        $content = null;

        // Try database first
        if (config('vizra-adk.prompts.use_database', false)) {
            $prompt = DB::table('agent_prompt_versions')
                ->where('agent_name', $agent)
                ->where('version', $version)
                ->first();

            if ($prompt) {
                $content = $prompt->instructions;
            }
        }

        // Try file if not found in database
        if (! $content) {
            $promptPath = config('vizra-adk.prompts.storage_path', resource_path('prompts'));
            $filePath = "{$promptPath}/{$agent}/{$version}.md";

            if (File::exists($filePath)) {
                $content = File::get($filePath);
            }
        }

        if (! $content) {
            $this->error("Prompt version {$version} not found for agent {$agent}");

            return 1;
        }

        if ($file) {
            File::put($file, $content);
            $this->info("Exported to {$file}");
        } else {
            $this->line($content);
        }

        return 0;
    }

    /**
     * Import a prompt from file
     */
    protected function importPrompt(): int
    {
        $agent = $this->argument('agent');
        $version = $this->argument('version');
        $file = $this->option('file');

        if (! $agent || ! $version || ! $file) {
            $this->error('Agent name, version, and --file are required for import action');

            return 1;
        }

        if (! File::exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        $content = File::get($file);

        // Save to file system
        $promptPath = config('vizra-adk.prompts.storage_path', resource_path('prompts'));
        $agentPath = "{$promptPath}/{$agent}";

        if (! File::exists($agentPath)) {
            File::makeDirectory($agentPath, 0755, true);
        }

        $destPath = "{$agentPath}/{$version}.md";
        File::put($destPath, $content);

        $this->info("Imported prompt to {$destPath}");

        // Optionally save to database
        if (config('vizra-adk.prompts.use_database', false)) {
            DB::table('agent_prompt_versions')->updateOrInsert(
                [
                    'agent_name' => $agent,
                    'version' => $version,
                ],
                [
                    'instructions' => $content,
                    'metadata' => json_encode(['source' => 'import', 'file' => $file]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $this->info('Also saved to database');
        }

        return 0;
    }
}
