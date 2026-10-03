-- =====================================================
-- Phase 6.x: Aria as a sitewide floating chat widget
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('aria_widget_enabled',  '1',                                                            'bool'),
    ('aria_widget_greeting', 'Hi — I''m Aria. Ask me about sessions, pricing, or anything else.', 'string')
ON DUPLICATE KEY UPDATE `key` = `key`;
