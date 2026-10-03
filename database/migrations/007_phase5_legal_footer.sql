-- =====================================================
-- Phase 5 migration: legal pages + footer / company details
-- All values land in site_settings so the admin can edit them
-- from /admin/legal_settings.php and /admin/footer_settings.php.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES

-- ----- Legal: Terms of Service -----
('legal_terms_title',
 'Terms of Service',
 'string'),
('legal_terms_updated_at',
 DATE_FORMAT(NOW(), '%Y-%m-%d'),
 'string'),
('legal_terms_body',
 '<p><em>Template — please review with qualified legal counsel before publishing.</em></p>
<h2>1. Acceptance</h2>
<p>By creating an account, booking a session, or using SoundHeal services, you agree to these Terms of Service. If you do not agree, please refrain from using the platform.</p>
<h2>2. Membership and Access</h2>
<p>Free trials and paid memberships grant access to the SoundHeal audio library and member pricing on in-person sessions, subject to availability. Membership is personal and non-transferable.</p>
<h2>3. Bookings</h2>
<p>Reservations are confirmed only after payment is received. Capacity is limited; seats are held for thirty minutes during checkout.</p>
<h2>4. Cancellation</h2>
<p>Members may cancel a reservation up to twelve hours before the session begins. Cancellations after this window are non-refundable unless covered by our Refund Policy.</p>
<h2>5. Conduct</h2>
<p>SoundHeal is a held space. Disruptive behaviour, recording without consent, or any conduct that threatens the safety of facilitators or guests may result in removal without refund.</p>
<h2>6. Health Disclaimer</h2>
<p>SoundHeal experiences are wellness offerings and are not medical treatment. They do not diagnose, treat, cure, or prevent any disease. Please consult qualified healthcare professionals for any medical or mental-health concerns.</p>
<h2>7. Intellectual Property</h2>
<p>All audio recordings, written content, branding and imagery on this platform are the property of SoundHeal or its licensors. Personal listening is permitted; redistribution or commercial use is not.</p>
<h2>8. Limitation of Liability</h2>
<p>To the maximum extent permitted by applicable law, SoundHeal shall not be liable for indirect, incidental or consequential damages arising from your use of the platform or attendance at sessions.</p>
<h2>9. Changes to Terms</h2>
<p>We may update these Terms from time to time. The effective date appears at the top of this page. Material changes will be communicated by email or on-site notice.</p>
<h2>10. Contact</h2>
<p>Questions about these Terms can be sent to <a href="mailto:hello@soundheal.local">hello@soundheal.local</a>.</p>',
 'text'),

-- ----- Legal: Privacy Policy -----
('legal_privacy_title',
 'Privacy Policy',
 'string'),
('legal_privacy_updated_at',
 DATE_FORMAT(NOW(), '%Y-%m-%d'),
 'string'),
('legal_privacy_body',
 '<p><em>Template — please review with qualified legal counsel and ensure alignment with applicable data-protection laws (e.g. PDPA Malaysia, GDPR).</em></p>
<h2>1. Who We Are</h2>
<p>SoundHeal (<em>“we”, “us”</em>) operates this platform and is the data controller for personal information you provide.</p>
<h2>2. Information We Collect</h2>
<ul>
  <li>Account details: name, email, phone, hashed password.</li>
  <li>Booking and payment records (we never store full payment-card numbers — these are handled by our payment provider, Billplz).</li>
  <li>Usage data: pages viewed, audio plays, device + browser metadata, hashed IP address.</li>
  <li>Marketing campaign tags (UTM parameters) you arrive with.</li>
  <li>Content you submit through contact forms, the AI concierge, or referrals.</li>
</ul>
<h2>3. How We Use It</h2>
<p>To provide and improve the service, send transactional emails (welcome, booking confirmations, password reset), deliver wellness recommendations through Aria, run anonymous analytics, and — only with your consent — send occasional marketing communications.</p>
<h2>4. Sharing</h2>
<p>We share only what is necessary with: our payment provider (Billplz) for transactions, our email provider for transactional mail, and our infrastructure provider (Hostinger) for hosting. We do not sell your personal information.</p>
<h2>5. Cookies</h2>
<p>We use first-party cookies for session security, CSRF protection and referral attribution. No third-party advertising cookies are set.</p>
<h2>6. Retention</h2>
<p>Account data is kept while your account is active and for a reasonable period after closure to comply with tax and audit requirements.</p>
<h2>7. Your Rights</h2>
<p>You can request access, correction, deletion or export of your data by emailing <a href="mailto:privacy@soundheal.local">privacy@soundheal.local</a>. We will respond within 30 days.</p>
<h2>8. Children</h2>
<p>SoundHeal is intended for adults aged 18 and over. We do not knowingly collect personal data from minors.</p>
<h2>9. Updates</h2>
<p>We may revise this policy. The effective date appears at the top of this page.</p>',
 'text'),

-- ----- Legal: Refund Policy -----
('legal_refund_title',
 'Refund Policy',
 'string'),
('legal_refund_updated_at',
 DATE_FORMAT(NOW(), '%Y-%m-%d'),
 'string'),
('legal_refund_body',
 '<p><em>Template — please review with qualified legal counsel and align with your payment provider terms.</em></p>
<h2>1. Sessions</h2>
<p>Reservations cancelled at least <strong>12 hours</strong> before the session start time are eligible for a full refund. Cancellations within 12 hours are non-refundable, but the booking value may be applied as a credit toward a future session, subject to availability.</p>
<h2>2. Cancelled or Rescheduled Sessions</h2>
<p>If SoundHeal cancels a session, all affected bookings will receive a full refund within seven (7) business days. If a session is rescheduled, you may transfer your seat to the new date or request a refund.</p>
<h2>3. Memberships</h2>
<p>Memberships are billed in advance for the cycle you select (monthly, quarterly, yearly). Once a billing cycle has begun, the membership fee is non-refundable except where required by law. You can cancel auto-renewal at any time from <em>My Membership</em>; your access continues to the end of the current cycle.</p>
<h2>4. Free Trials</h2>
<p>Free trials do not require payment, so no refund is necessary if you choose not to continue.</p>
<h2>5. How Refunds Are Processed</h2>
<p>Approved refunds are returned to the original payment method via Billplz. Allow up to seven (7) business days for the refund to appear on your statement.</p>
<h2>6. Requests</h2>
<p>To request a refund, please email <a href="mailto:billing@soundheal.local">billing@soundheal.local</a> with your booking reference and a brief note. We aim to respond within two business days.</p>',
 'text'),

-- ----- Footer: company details -----
('company_name',           'SoundHeal',                                     'string'),
('company_legal_name',     'SoundHeal Wellness Sdn. Bhd.',                  'string'),
('company_registration_no','202401012345 (1234567-X)',                      'string'),
('company_tagline',        'Wellness Operating System',                     'string'),
('company_address_line1',  'No. 12, Jalan Sanctuary',                       'string'),
('company_address_line2',  'Bangsar',                                       'string'),
('company_city',           'Kuala Lumpur 59100',                            'string'),
('company_country',        'Malaysia',                                      'string'),
('company_phone',          '+60 3-1234 5678',                               'string'),
('company_email',          'hello@soundheal.local',                         'string'),
('company_billing_email',  'billing@soundheal.local',                       'string'),
('company_privacy_email',  'privacy@soundheal.local',                       'string'),

-- Social URLs (leave blank to hide the icon).
('company_social_instagram','',  'string'),
('company_social_facebook', '',  'string'),
('company_social_tiktok',   '',  'string'),
('company_social_youtube',  '',  'string'),
('company_social_whatsapp', '',  'string'),

-- Footer micro-copy
('footer_about_blurb',
 'A sanctuary for sound, breath and stillness — held with intention, designed for the modern soul.',
 'text'),
('footer_show_company_block', '1', 'bool'),
('footer_show_policy_links',  '1', 'bool')

ON DUPLICATE KEY UPDATE `key` = `key`;
