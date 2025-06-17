<?php

namespace Vizra\VizraADK\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Memory\AgentMemory;

/**
 * Example tool that manages user profiles using agent memory
 */
class UserProfileTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'manage_user_profile',
            'description' => 'Update or retrieve user profile information from memory',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'description' => 'Action to perform: update_fact, add_preference, get_profile',
                        'enum' => ['update_fact', 'add_preference', 'get_profile']
                    ],
                    'key' => [
                        'type' => 'string',
                        'description' => 'The key/name for the fact or preference'
                    ],
                    'value' => [
                        'type' => 'string', 
                        'description' => 'The value to store (not needed for get_profile)'
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Category for preferences (optional)',
                        'default' => 'general'
                    ]
                ],
                'required' => ['action']
            ]
        ];
    }
    
    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $action = $arguments['action'];
        
        switch ($action) {
            case 'update_fact':
                if (!isset($arguments['key']) || !isset($arguments['value'])) {
                    return json_encode([
                        'success' => false,
                        'error' => 'Both key and value are required for update_fact'
                    ]);
                }
                
                $memory->addFact("{$arguments['key']}: {$arguments['value']}", 1.0);
                
                return json_encode([
                    'success' => true,
                    'message' => "Fact stored: {$arguments['key']} = {$arguments['value']}"
                ]);
                
            case 'add_preference':
                if (!isset($arguments['value'])) {
                    return json_encode([
                        'success' => false,
                        'error' => 'Value is required for add_preference'
                    ]);
                }
                
                $category = $arguments['category'] ?? 'general';
                $memory->addPreference($arguments['value'], $category);
                
                return json_encode([
                    'success' => true,
                    'message' => "Preference added to {$category}: {$arguments['value']}"
                ]);
                
            case 'get_profile':
                $summary = $memory->getSummary();
                $facts = $memory->getFacts()->pluck('content')->toArray();
                $preferences = $memory->getPreferences()->groupBy(function($item) {
                    return $item->metadata['category'] ?? 'general';
                })->map(function($group) {
                    return $group->pluck('content')->toArray();
                })->toArray();
                
                return json_encode([
                    'success' => true,
                    'profile' => [
                        'summary' => $summary,
                        'facts' => $facts,
                        'preferences' => $preferences
                    ]
                ]);
                
            default:
                return json_encode([
                    'success' => false,
                    'error' => "Unknown action: {$action}"
                ]);
        }
    }
}