<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BoostInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vizra:boost:install 
                            {--force : Overwrite existing guideline files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Vizra ADK guidelines for Laravel Boost integration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Installing Vizra ADK guidelines for Laravel Boost...');
        
        // Check if Laravel Boost is installed
        if (!$this->isBoostInstalled()) {
            $this->warn('Laravel Boost is not installed. Please install it first:');
            $this->line('composer require laravel/boost --dev');
            $this->line('php artisan boost:install');
            
            if (!$this->confirm('Do you want to continue anyway?', false)) {
                return Command::FAILURE;
            }
        }
        
        // Define source and destination paths
        $sourcePath = __DIR__ . '/../../../resources/boost/guidelines/vizra-adk';
        $destinationPath = base_path('.ai/guidelines/vizra-adk');
        
        // Check if guidelines already exist
        if (File::exists($destinationPath) && !$this->option('force')) {
            $this->warn('Vizra ADK guidelines already exist.');
            
            if (!$this->confirm('Do you want to overwrite them?', false)) {
                $this->info('Installation cancelled.');
                return Command::SUCCESS;
            }
        }
        
        // Create destination directory if it doesn't exist
        File::ensureDirectoryExists(dirname($destinationPath));
        
        // Copy guidelines
        $this->copyGuidelines($sourcePath, $destinationPath);
        
        // Show success message and next steps
        $this->newLine();
        $this->info('✅ Vizra ADK guidelines installed successfully!');
        $this->newLine();
        
        $this->info('📚 Installed Guidelines:');
        $this->table(
            ['Guideline', 'Description'],
            [
                ['agent-creation.blade.php', 'How to create Vizra ADK agents'],
                ['tool-creation.blade.php', 'Building tools for agents'],
                ['workflow-patterns.blade.php', 'Agent workflow orchestration'],
                ['memory-usage.blade.php', 'Memory and context management'],
                ['sub-agents.blade.php', 'Sub-agent delegation patterns'],
                ['best-practices.blade.php', 'Best practices and conventions'],
            ]
        );
        
        $this->newLine();
        $this->info('🎯 Next Steps:');
        $this->line('1. Your AI assistant (Claude, GitHub Copilot, etc.) can now generate Vizra ADK code');
        $this->line('2. Try asking: "Create a customer support agent with email tools"');
        $this->line('3. The AI will use the guidelines to generate proper Vizra ADK code');
        
        $this->newLine();
        $this->info('📖 Documentation:');
        $this->line('- Vizra ADK Docs: https://vizra.ai/docs');
        $this->line('- Laravel Boost: https://github.com/laravel/boost');
        
        return Command::SUCCESS;
    }
    
    /**
     * Check if Laravel Boost is installed.
     */
    protected function isBoostInstalled(): bool
    {
        // Check if the boost package is installed
        $composerJson = json_decode(File::get(base_path('composer.json')), true);
        
        $isInRequire = isset($composerJson['require']['laravel/boost']);
        $isInRequireDev = isset($composerJson['require-dev']['laravel/boost']);
        
        return $isInRequire || $isInRequireDev;
    }
    
    /**
     * Copy guidelines from source to destination.
     */
    protected function copyGuidelines(string $source, string $destination): void
    {
        // Ensure the destination directory exists
        File::ensureDirectoryExists($destination);
        
        // Copy all files and directories recursively
        $this->copyDirectory($source, $destination);
        
        $this->info("✓ Guidelines copied to: {$destination}");
    }
    
    /**
     * Recursively copy a directory.
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (!File::exists($source)) {
            $this->error("Source directory does not exist: {$source}");
            return;
        }
        
        // Create destination directory
        File::ensureDirectoryExists($destination);
        
        // Get all files and directories
        $items = File::allFiles($source);
        
        foreach ($items as $item) {
            $relativePath = str_replace($source, '', $item->getPathname());
            $destPath = $destination . $relativePath;
            
            // Ensure the directory exists
            File::ensureDirectoryExists(dirname($destPath));
            
            // Copy the file
            File::copy($item->getPathname(), $destPath);
            
            $this->line("  - Copied: " . basename($item->getPathname()));
        }
    }
    
    /**
     * Show a custom banner.
     */
    protected function showBanner(): void
    {
        $this->newLine();
        $this->line('  _   ___ _____ ___   ___   ___   ___  _  __');
        $this->line(' | | / (_)_  / / _ \ / _ \ / _ \ |   \| |/ /');
        $this->line(' | |/ / _ / / | |_) | |_| | |_| || |) | \  \ ');
        $this->line(' |___/___|___||_| \_\\\\__,_|\\__,_||___/|_|\_\\');
        $this->line('              Laravel Boost Integration');
        $this->newLine();
    }
}