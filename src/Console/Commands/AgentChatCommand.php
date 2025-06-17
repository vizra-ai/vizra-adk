<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Vizra\VizraADK\Facades\Agent;
use Illuminate\Support\Str; // For generating session ID
use Symfony\Component\Console\Input\InputArgument;
use Vizra\VizraADK\Exceptions\AgentNotFoundException;
use Vizra\VizraADK\Exceptions\ToolExecutionException;
use Vizra\VizraADK\Exceptions\AgentConfigurationException;

class AgentChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vizra:chat {agent_name : The name of the agent to chat with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chat with a specified agent in real-time. Type "exit" or "quit" to end the chat.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $agentName = $this->argument('agent_name');
        $this->info("Attempting to start chat with agent: <comment>{$agentName}</comment>");

        if (!Agent::hasAgent($agentName)) {
            $this->error("Agent '{$agentName}' is not registered or found.");
            $this->line("Please ensure the agent is registered, typically in a ServiceProvider using Agent::build() or Agent::define().");
            return Command::FAILURE;
        }

        // Generate a unique session ID for this chat session
        $sessionId = Str::uuid()->toString();
        $this->info("Chat session started with ID: <comment>{$sessionId}</comment>. Type 'exit' or 'quit' to end.");
        $this->newLine();

        while (true) {
            $input = $this->ask("You");

            if (strtolower($input) === 'exit' || strtolower($input) === 'quit') {
                $this->info("Exiting chat with agent: {$agentName}.");
                break;
            }

            if (empty($input)) {
                continue;
            }

            try {
                $this->line("<options=bold>Agent {$agentName}:</>");
                // Wrap agent's response for better readability
                $response = Agent::run($agentName, $input, $sessionId);

                if (is_string($response)) {
                    $this->line($response);
                } elseif (is_array($response) || is_object($response)) {
                    // If the response is an array or object, pretty print it.
                    $this->line(json_encode($response, JSON_PRETTY_PRINT));
                } else {
                    $this->line((string) $response);
                }

            } catch (AgentNotFoundException $e) {
                $this->error("Agent '{$agentName}' could not be found or loaded during the chat.");
                $this->line("Detail: " . $e->getMessage());
                return Command::FAILURE;
            } catch (ToolExecutionException $e) {
                $this->error("A tool required by the agent '{$agentName}' failed to execute.");
                $this->line("Detail: " . $e->getMessage());
                // Optionally, you might want to allow the chat to continue or terminate
            } catch (AgentConfigurationException $e) {
                $this->error("Agent configuration error for '{$agentName}'.");
                $this->line("Detail: " . $e->getMessage());
                return Command::FAILURE;
            } catch (\Throwable $e) {
                $this->error("An unexpected error occurred while interacting with agent '{$agentName}'.");
                $this->line("Detail: " . $e->getMessage());
                // Depending on the severity, you might want to terminate or allow continuation
            }
            $this->newLine(); // Add a new line for spacing after agent's response
        }

        return Command::SUCCESS;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [
            ['agent_name', InputArgument::REQUIRED, 'The name of the agent class to chat with.'],
        ];
    }
}
