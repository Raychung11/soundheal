-- =====================================================
-- Phase 5 follow-up: Updated legal documents for
-- jaemiesoundbath / JLC Management Sdn. Bhd.
-- Aligned with Malaysian PDPA 2010 + Consumer Protection Act 1999.
-- Idempotent — re-running rewrites with the latest version.
-- =====================================================

-- =====================
--  TERMS OF SERVICE
-- =====================
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
('legal_terms_title',      'Terms of Service',                'string'),
('legal_terms_updated_at', DATE_FORMAT(NOW(), '%Y-%m-%d'),    'string'),
('legal_terms_body',
'<p><em>Please review this document with qualified legal counsel before publishing. The text below is a starter tailored for jaemiesoundbath and Malaysian operation; it is not a substitute for legal advice.</em></p>

<h2>1. Who we are</h2>
<p>These Terms of Service (<strong>“Terms”</strong>) govern your access to and use of the jaemiesoundbath platform, sessions and audio library (collectively, the <strong>“Service”</strong>). The Service is operated by <strong>JLC Management Sdn. Bhd.</strong> (the <strong>“Company”</strong>, <strong>“we”</strong>, <strong>“us”</strong>) under the brand <em>jaemiesoundbath</em>. By creating an account, booking a session or otherwise using the Service, you agree to be bound by these Terms.</p>

<h2>2. Eligibility</h2>
<p>You must be at least eighteen (18) years old to register an account or book a session. By using the Service you represent that you meet this requirement.</p>

<h2>3. Accounts</h2>
<p>You are responsible for keeping your account credentials confidential and for all activity that occurs under your account. Notify us at <a href="mailto:hello@jaemiesoundbath.com">hello@jaemiesoundbath.com</a> immediately if you suspect unauthorised use.</p>

<h2>4. Memberships and free trials</h2>
<ul>
  <li>Memberships are billed in advance for the cycle you select (monthly, quarterly or yearly). Member pricing applies to in-person sessions and the audio library.</li>
  <li>Free trials are offered without payment and convert to a paid membership only if you choose to subscribe. We may cap or modify trial lengths from time to time.</li>
  <li>Memberships are personal, non-transferable and not redeemable for cash.</li>
</ul>

<h2>5. Sessions and bookings</h2>
<ul>
  <li>Reservations are confirmed only after payment has been received in full.</li>
  <li>Seats are limited and allocated on a first-come basis. Pending reservations are held for thirty (30) minutes during checkout.</li>
  <li>Please arrive ten minutes before the session begins. Late arrivals may be refused entry to preserve the integrity of the experience for other guests.</li>
</ul>

<h2>6. Cancellations and rescheduling</h2>
<p>You may cancel a reservation up to twelve (12) hours before the session begins for a full refund. Cancellations within twelve (12) hours are non-refundable but, at our discretion, may be applied as a credit toward a future session. If we cancel or reschedule a session, please refer to the <a href="/public/refund.php">Refund Policy</a>.</p>

<h2>7. Conduct in the sanctuary</h2>
<p>jaemiesoundbath is a held container. To protect that space we ask that you:</p>
<ul>
  <li>Silence mobile devices for the duration of the session.</li>
  <li>Refrain from recording, photographing or live-streaming without prior written consent.</li>
  <li>Disclose any relevant health conditions or pregnancy at the time of booking so we can guide you safely.</li>
</ul>
<p>We reserve the right to refuse entry or remove any guest whose conduct disturbs the safety or experience of others, without refund.</p>

<h2>8. Health and wellness disclaimer</h2>
<p>Our offerings are wellness experiences and <strong>are not medical treatment</strong>. Sound healing, breathwork, meditation and audio content do not diagnose, treat, cure or prevent any medical or psychological condition. Please consult a qualified healthcare professional before participating if you have any cardiac, respiratory, neurological, pregnancy-related or mental-health condition. By attending, you accept the inherent risks of these practices.</p>

<h2>9. Payments</h2>
<p>Payments are processed by Billplz Sdn. Bhd. We do not store your full payment-card details. All amounts are quoted in Malaysian Ringgit (MYR) and are inclusive of applicable Service Tax unless stated otherwise.</p>

<h2>10. Intellectual property</h2>
<p>All audio recordings, written content, branding, logos and imagery on the Service are the property of JLC Management Sdn. Bhd. or its licensors and are protected by Malaysian and international copyright laws. You are granted a personal, non-exclusive, non-transferable, revocable licence to access and stream the audio library for personal listening only. Any redistribution, commercial use, mirroring or scraping is prohibited.</p>

<h2>11. AI concierge</h2>
<p>Aria, our AI wellness concierge, provides supportive suggestions only. Aria does not provide medical, psychological or financial advice. Conversations may be reviewed in anonymised form to improve the Service. Do not share information you would not want stored.</p>

<h2>12. Referrals</h2>
<p>The member-refer-member programme rewards both parties with additional trial-access days when a referred friend successfully registers. Rewards are non-cash, non-transferable and may be modified or withdrawn at our discretion. Self-referrals, automated referrals or abuse of the programme may result in revocation of rewards.</p>

<h2>13. Termination</h2>
<p>You may close your account at any time by emailing <a href="mailto:hello@jaemiesoundbath.com">hello@jaemiesoundbath.com</a>. We may suspend or terminate your access if you breach these Terms or use the Service in a manner that harms us or another guest.</p>

<h2>14. Limitation of liability</h2>
<p>To the maximum extent permitted by Malaysian law, the Company, its directors, employees and facilitators shall not be liable for indirect, incidental, special or consequential damages arising from your use of the Service or attendance at a session. Our total liability for any claim shall not exceed the amount you paid to us in the twelve (12) months preceding the claim.</p>

<h2>15. Governing law and dispute resolution</h2>
<p>These Terms are governed by the laws of Malaysia. Any dispute shall first be addressed by good-faith discussion; failing resolution, the courts of Kuala Lumpur shall have exclusive jurisdiction.</p>

<h2>16. Changes</h2>
<p>We may amend these Terms from time to time. Material changes will be notified by email or on-site banner. Continued use of the Service after the effective date constitutes acceptance.</p>

<h2>17. Contact</h2>
<p>JLC Management Sdn. Bhd. (operating as jaemiesoundbath)<br>Email: <a href="mailto:hello@jaemiesoundbath.com">hello@jaemiesoundbath.com</a></p>',
 'text')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `value_type` = VALUES(`value_type`);

-- =====================
--  PRIVACY POLICY
-- =====================
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
('legal_privacy_title',      'Privacy Policy',                'string'),
('legal_privacy_updated_at', DATE_FORMAT(NOW(), '%Y-%m-%d'),  'string'),
('legal_privacy_body',
'<p><em>Please review this document with qualified legal counsel before publishing. The text below is a starter tailored to Malaysia''s Personal Data Protection Act 2010 (PDPA) and is not a substitute for legal advice.</em></p>

<h2>1. Who is responsible for your data</h2>
<p><strong>JLC Management Sdn. Bhd.</strong>, operating as <strong>jaemiesoundbath</strong>, is the data controller for personal information collected through this Service. You can reach our data-protection contact at <a href="mailto:privacy@jaemiesoundbath.com">privacy@jaemiesoundbath.com</a>.</p>

<h2>2. Information we collect</h2>
<ul>
  <li><strong>Account details</strong> — name, email, phone number, hashed password, optional avatar.</li>
  <li><strong>Booking and membership records</strong> — sessions you have reserved, attended or cancelled.</li>
  <li><strong>Payment metadata</strong> — transaction reference, amount, status. Full card numbers are processed and stored by Billplz; we never see them.</li>
  <li><strong>Wellness interactions</strong> — moods you share with Aria (our AI concierge), audio plays and check-in history.</li>
  <li><strong>Usage data</strong> — page paths visited, referring website, user-agent string, hashed IP address, UTM campaign tags. Sessions are identified by a hashed identifier for unique-visitor counts.</li>
  <li><strong>Cookies</strong> — first-party only, for sign-in, CSRF protection and referral attribution.</li>
  <li><strong>Communications</strong> — messages you send via contact and corporate-inquiry forms.</li>
</ul>

<h2>3. How we use it</h2>
<ul>
  <li>To provide and deliver the Service (account access, bookings, audio streaming, the AI concierge).</li>
  <li>To send transactional emails: welcome, email verification, password reset, booking confirmations and refund notices.</li>
  <li>To improve the Service through anonymised analytics.</li>
  <li>To detect, prevent and respond to fraud, abuse or security issues.</li>
  <li>To send occasional marketing communications <em>only</em> with your consent — you may withdraw consent at any time.</li>
</ul>

<h2>4. Legal basis (where applicable)</h2>
<p>We process personal data under one or more of the following bases: your consent, performance of a contract with you (your membership or booking), our legitimate interests in running a safe and well-functioning service, and compliance with our legal obligations.</p>

<h2>5. Sharing</h2>
<p>We share only the minimum necessary with carefully selected processors:</p>
<ul>
  <li><strong>Billplz Sdn. Bhd.</strong> — to process payments.</li>
  <li><strong>Hostinger</strong> — to host the platform and store database records.</li>
  <li><strong>OpenAI</strong> — to power Aria''s replies. Conversations are sent in API mode and are not used to train external models.</li>
  <li><strong>Email provider</strong> — to deliver transactional mail.</li>
</ul>
<p>We do not sell your personal information. We may disclose information when required to do so by Malaysian law or a court order.</p>

<h2>6. Cookies</h2>
<p>We use first-party cookies for session security (signed-in state, CSRF token) and referral attribution (a 30-day cookie carrying a share code). We do not use third-party advertising cookies.</p>

<h2>7. Retention</h2>
<p>Account data is retained while your account is active and for a reasonable period after closure (typically seven years) to comply with tax, accounting and audit obligations. Audio-play logs are retained for up to twenty-four months for service-improvement purposes.</p>

<h2>8. Your rights under PDPA 2010</h2>
<p>You have the right to:</p>
<ul>
  <li>Request a copy of the personal data we hold about you.</li>
  <li>Correct inaccurate or incomplete data.</li>
  <li>Withdraw consent for marketing communications.</li>
  <li>Request deletion of your account and associated data, subject to our retention obligations.</li>
  <li>Lodge a complaint with the Personal Data Protection Department of Malaysia (Jabatan Perlindungan Data Peribadi) if you believe we have failed to meet our obligations.</li>
</ul>
<p>To exercise any of these rights, email <a href="mailto:privacy@jaemiesoundbath.com">privacy@jaemiesoundbath.com</a>. We will respond within thirty (30) days.</p>

<h2>9. Security</h2>
<p>We protect your data with industry-standard measures: HTTPS in transit, PHP password hashing (bcrypt), hardened sessions, role-based access control, prepared SQL statements and audit logging of administrative actions. No system is perfectly secure, but we work hard to keep yours safe.</p>

<h2>10. Children</h2>
<p>jaemiesoundbath is intended for adults aged 18 and over. We do not knowingly collect personal data from minors. If you believe a minor has provided us with personal data, please contact us so we can remove it.</p>

<h2>11. International transfers</h2>
<p>Some of our processors (e.g. OpenAI) operate outside Malaysia. Where this is the case, we ensure transfers are protected by contractual safeguards that meet PDPA standards.</p>

<h2>12. Changes</h2>
<p>We may update this policy. The effective date appears at the top of the page. Material changes will be communicated by email or on-site notice.</p>

<h2>13. Contact</h2>
<p>JLC Management Sdn. Bhd. (operating as jaemiesoundbath)<br>Email: <a href="mailto:privacy@jaemiesoundbath.com">privacy@jaemiesoundbath.com</a></p>',
 'text')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `value_type` = VALUES(`value_type`);

-- =====================
--  REFUND POLICY
-- =====================
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
('legal_refund_title',      'Refund Policy',                  'string'),
('legal_refund_updated_at', DATE_FORMAT(NOW(), '%Y-%m-%d'),   'string'),
('legal_refund_body',
'<p><em>Please review this document with qualified legal counsel before publishing. The text below is a starter aligned with the Malaysian Consumer Protection Act 1999 and Billplz settlement terms; it is not a substitute for legal advice.</em></p>

<h2>1. Overview</h2>
<p>We want your experience with jaemiesoundbath to feel calm from booking to follow-through. This policy explains how refunds work for our two main offerings: <strong>individual sessions</strong> and <strong>memberships</strong>.</p>

<h2>2. Session refunds</h2>
<ul>
  <li><strong>More than 12 hours before the session</strong> — full refund, no questions asked. Cancel from <em>My Bookings</em> or email us.</li>
  <li><strong>Within 12 hours of the session</strong> — non-refundable, but the booking value can be applied as a credit toward a future session of equal or lesser value, subject to availability and to be used within ninety (90) days.</li>
  <li><strong>No-shows</strong> — treated as a within-12-hour cancellation. We are unable to refund or credit no-shows.</li>
</ul>

<h2>3. Sessions cancelled or rescheduled by us</h2>
<p>If we cancel a session, every affected booking will receive a <strong>full refund</strong> within seven (7) business days, automatically. If we reschedule, you may transfer your seat to the new date at no cost, or request a full refund.</p>

<h2>4. Memberships</h2>
<ul>
  <li>Memberships are billed in advance for the cycle you choose. Once a billing cycle has begun, the membership fee is <strong>non-refundable</strong> except where required by law.</li>
  <li>You can cancel auto-renewal at any time from <em>My Membership</em>; access continues uninterrupted to the end of the current cycle.</li>
  <li>If a membership is cancelled within the first seven (7) days of the first ever subscription and no member-tier session has been attended, we will provide a full refund on request as a goodwill exception.</li>
</ul>

<h2>5. Free trials</h2>
<p>Free trials do not require payment, so no refund is necessary if you decide not to continue. Your trial simply ends on the date shown in your dashboard.</p>

<h2>6. Referral rewards</h2>
<p>Referral trial-day rewards have no cash value and are not refundable when a referee account is closed or refunded.</p>

<h2>7. How refunds are processed</h2>
<p>Approved refunds are returned to the original payment method via our payment provider, Billplz Sdn. Bhd. Allow up to seven (7) business days for the refunded amount to appear on your statement; settlement timing depends on your card issuer or bank.</p>

<h2>8. How to request a refund</h2>
<p>Email <a href="mailto:billing@jaemiesoundbath.com">billing@jaemiesoundbath.com</a> with:</p>
<ul>
  <li>Your booking or membership reference (e.g. <code>SH-XXXXXXXX</code>);</li>
  <li>The date of the session or charge;</li>
  <li>A brief note describing the reason for your request.</li>
</ul>
<p>We aim to acknowledge within two (2) business days and to resolve most requests within seven (7) business days.</p>

<h2>9. Statutory rights</h2>
<p>Nothing in this policy is intended to limit or waive any rights you have under the Malaysian Consumer Protection Act 1999 or other applicable consumer-protection law.</p>

<h2>10. Contact</h2>
<p>JLC Management Sdn. Bhd. (operating as jaemiesoundbath)<br>Email: <a href="mailto:billing@jaemiesoundbath.com">billing@jaemiesoundbath.com</a></p>',
 'text')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `value_type` = VALUES(`value_type`);
