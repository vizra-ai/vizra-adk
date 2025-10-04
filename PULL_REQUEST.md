# Add SSE Response Parsing for HTTP MCP Servers

## Problem

The current HTTP MCP client implementation only supports plain JSON responses. However, some MCP servers (like [Context7](https://mcp.context7.com)) return responses in Server-Sent Events (SSE) format, even for non-streaming requests.

### Example

When connecting to Context7 MCP server, the response looks like:
```
event: message
data: {"jsonrpc":"2.0","id":1,"result":{...}}
```

The existing code expected plain JSON:
```json
{"jsonrpc":"2.0","id":1,"result":{...}}
```

This caused the client to fail with "Invalid JSON-RPC response format" errors.

Additionally, Context7 requires the `Accept: text/event-stream` header to be present, otherwise it returns a `406 Not Acceptable` error:
```json
{
  "jsonrpc": "2.0",
  "error": {
    "code": -32000,
    "message": "Not Acceptable: Client must accept both application/json and text/event-stream"
  }
}
```

## Solution

This PR adds support for SSE-formatted responses while maintaining backward compatibility with plain JSON responses.

### Changes

1. **Updated Accept Header** (`buildHeaders()`)
   - Changed from `Accept: application/json` to `Accept: application/json, text/event-stream`
   - This signals to the server that we can handle both response formats
   - Required by servers like Context7 that enforce this via 406 errors

2. **Added Response Parser** (`parseResponse()`)
   - Detects response format (SSE vs plain JSON)
   - Routes to appropriate parser

3. **Added SSE Parser** (`parseSSEResponse()`)
   - Extracts JSON-RPC data from SSE `data:` lines
   - Handles multi-line SSE responses
   - Gracefully skips malformed lines

### How It Works

```php
// Detects SSE format by checking for "event:" or "data:" prefix
if (str_starts_with(trim($body), 'event:') || str_starts_with(trim($body), 'data:')) {
    return $this->parseSSEResponse($body);
}

// Falls back to plain JSON parsing
return json_decode($body, true);
```

## Testing

Tested with:
- ✅ Context7 MCP Server (SSE format): https://mcp.context7.com/mcp
- ✅ Local JSON MCP Server (plain JSON format)

Both work correctly with the same client code.

## Backward Compatibility

- ✅ Existing JSON-only MCP servers continue to work
- ✅ No breaking changes to the API
- ✅ SSE support is automatic based on response format

## Example Configuration

```php
'mcp_servers' => [
    'context-7' => [
        'transport' => 'http',
        'url' => 'https://mcp.context7.com/mcp',
        'timeout' => 120,
        'enabled' => true,
    ],
],
```

## Related

This aligns with the MCP HTTP transport specification which allows servers to use SSE for responses: https://spec.modelcontextprotocol.io/specification/architecture/#transports
