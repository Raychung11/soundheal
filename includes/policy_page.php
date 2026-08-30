<?php
declare(strict_types=1);

/**
 * Shared renderer for /public/terms.php, /public/privacy.php and
 * /public/refund.php — keeps the policy chrome in one place.
 *
 * Caller sets:
 *   $policyKey  : 'terms' | 'privacy' | 'refund'
 *   then includes this file.
 */

$policyKey = $policyKey ?? 'terms';
$title     = (string) setting('legal_' . $policyKey . '_title', ucfirst($policyKey));
$updatedAt = (string) setting('legal_' . $policyKey . '_updated_at', '');
$body      = (string) setting('legal_' . $policyKey . '_body', '');

$pageTitle = $title;

require __DIR__ . '/header.php';
?>
<section class="max-w-3xl mx-auto px-6 py-20 md:py-28">
  <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Legal</p>
  <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-4 leading-[1.05]"><?= e($title) ?></h1>
  <?php if ($updatedAt !== ''): ?>
    <p class="mt-4 text-xs uppercase tracking-widest text-beige-100/50">Last updated · <?= e($updatedAt) ?></p>
  <?php endif; ?>

  <article class="policy-prose mt-12 text-beige-100/80 leading-[1.85] font-light text-base space-y-4">
    <?php
    // Admin-authored content — rendered as HTML. The admin form makes this clear.
    if ($body === '') {
        echo '<p class="text-beige-100/55 italic">This policy has not been published yet. Please check back shortly.</p>';
    } else {
        echo $body;
    }
    ?>
  </article>

  <p class="mt-16 text-xs text-beige-100/40 italic text-center">
    Questions? Email <a href="mailto:<?= e((string) setting('company_email', 'hello@jaemiesoundbath.com')) ?>" class="text-gold-400/80 hover:text-gold-300"><?= e((string) setting('company_email', 'hello@jaemiesoundbath.com')) ?></a>.
  </p>
</section>

<style>
.policy-prose h2 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; color: #e7d2a3; margin-top: 2rem; }
.policy-prose h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.25rem; color: #d8b97e; margin-top: 1.5rem; }
.policy-prose p  { margin: 0.75rem 0; }
.policy-prose ul, .policy-prose ol { padding-left: 1.5rem; margin: 0.75rem 0; }
.policy-prose ul { list-style: disc; }
.policy-prose ol { list-style: decimal; }
.policy-prose li { margin: 0.25rem 0; }
.policy-prose a  { color: #d8b97e; text-decoration: underline; text-underline-offset: 3px; }
.policy-prose a:hover { color: #e7d2a3; }
.policy-prose strong, .policy-prose b { color: #f6efe5; }
.policy-prose em, .policy-prose i { color: rgba(246, 239, 229, 0.7); }
</style>

<?php require __DIR__ . '/footer.php'; ?>
