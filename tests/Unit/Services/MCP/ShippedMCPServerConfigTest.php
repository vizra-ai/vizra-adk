<?php

/**
 * Guards the MCP server examples the package ships in config/vizra-adk.php.
 *
 * A host app that never publishes the config still inherits these defaults, and an
 * `npx` command needs no prior install - so an archived or vulnerable server package
 * named here is one env var away from running. These tests read the merged package
 * config rather than overriding it, which is what a host app actually gets.
 */
it('ships only the two transport examples', function () {
    expect(array_keys(config('vizra-adk.mcp_servers')))
        ->toBe(['filesystem', 'github_http']);
});

it('names no archived MCP server package', function () {
    $args = [];

    foreach (config('vizra-adk.mcp_servers') as $server) {
        $args = array_merge($args, $server['args'] ?? []);
    }

    foreach ([
        '@modelcontextprotocol/server-github',
        '@modelcontextprotocol/server-postgres',
        '@modelcontextprotocol/server-brave-search',
        '@modelcontextprotocol/server-slack',
    ] as $package) {
        expect($args)->not->toContain($package);
    }
});
