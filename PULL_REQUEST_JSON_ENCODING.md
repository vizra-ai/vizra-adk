# Fix: Prevent Double-Encoding of JSON Content in AgentMessage

## Problem

When AI providers (like Anthropic Claude) return tool results as JSON strings, the `AgentMessage` model's JSON cast was double-encoding the content, resulting in triple-encoded data:

1. **First encoding**: Tool returns JSON response
2. **Second encoding**: AI provider wraps in text block structure
3. **Third encoding**: Laravel's JSON cast encodes the string again

This resulted in database content like:
```
"\"[{\\\"type\\\":\\\"text\\\",\\\"text\\\":\\\"{...}\\\"...}]\""
```

## Solution

Added a `setContentAttribute()` mutator that intelligently handles content before the JSON cast is applied:

- Detects if incoming value is already a valid JSON string
- If so, decodes it once and re-encodes it (preventing double-encoding)
- If not (array/object), lets JSON cast handle it normally
- Fully backwards compatible with existing behavior

## Changes

### Modified Files
- `src/Models/AgentMessage.php`
  - Added `setContentAttribute()` mutator
  - Added `isJson()` helper method

## Testing

Tested with MCP tools returning JSON responses:

**Before:**
```json
"\"[{\\\"type\\\":\\\"text\\\",\\\"text\\\":\\\"{\\\\n    \\\\\\\"success\\\\\\\": true...}\\\"}]\""
```

**After:**
```json
[{"type":"text","text":"{\n    \"success\": true,\n    \"items\": [...]}"}]
```

## Backwards Compatibility

✅ **Fully backwards compatible**
- Arrays/objects continue to work as before
- Plain strings continue to work as before
- Only JSON strings are handled specially
- Existing data remains readable (getter still works)

## Benefits

1. **No more triple-encoding**: Content stored with correct encoding level
2. **Cleaner data**: Database content is more readable and debuggable
3. **Better performance**: No need for recursive JSON decoding on read
4. **Standards compliant**: Aligns with how AI providers return tool results

## Use Case

This fix is particularly important for applications using:
- MCP (Model Context Protocol) tools
- Custom tools that return structured JSON data
- Any AI provider that wraps tool results in text blocks

---

**Related Issue**: N/A (proactive fix)
**Breaking Changes**: None
**Migration Required**: No
