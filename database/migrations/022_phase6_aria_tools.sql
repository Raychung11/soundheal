-- =====================================================
-- Phase 6.x: Aria's super-assistant settings
--
--   business_hours        — free-text, shown in Aria's get_business_info
--                            and surfaced in the footer.
--   ai_tools_enabled      — kill switch for tool calling (in case OpenAI
--                            errors and we want to fall back to plain
--                            chat without redeploying).
--   ai_max_tool_calls     — bound on tool-calling loop iterations per
--                            turn (cost + latency safety net).
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('business_hours',     'Tuesday – Sunday · 10am – 8pm (closed Mondays)', 'string'),
    ('ai_tools_enabled',   '1',                                              'bool'),
    ('ai_max_tool_calls',  '4',                                              'int')
ON DUPLICATE KEY UPDATE `key` = `key`;
