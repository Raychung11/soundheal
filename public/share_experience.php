<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Share your experience';
$pageDescription = 'Tell us how your sound bath experience felt. Approved reflections may appear on our site.';

$errors = [];
$done   = false;

if (is_post()) {
    csrf_verify();

    $name   = trim((string) input('author_name', ''));
    $title  = trim((string) input('author_title', ''));
    $quote  = trim((string) input('quote', ''));
    $rating = max(1, min(5, (int) input('rating', 5)));

    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        $errors[] = 'Please share the name we can credit (2–150 characters).';
    }
    if (mb_strlen($title) > 150) {
        $errors[] = 'The title is a little long — keep it under 150 characters.';
    }
    if (mb_strlen($quote) < 10 || mb_strlen($quote) > 1000) {
        $errors[] = 'Please share a few words about your experience (10–1000 characters).';
    }

    $throttleKey = 'testimonial:' . client_ip();
    if (!$errors && !throttle($throttleKey, 3, 3600)) {
        $errors[] = 'Thank you — you have already shared with us recently. Please try again later.';
    }

    if (!$errors) {
        // Submitted unpublished — an admin reviews and publishes from the BOS.
        db()->prepare(
            "INSERT INTO testimonials (author_name, author_title, quote, rating, is_published)
             VALUES (:n, :t, :q, :r, 0)"
        )->execute([':n' => $name, ':t' => $title !== '' ? $title : null, ':q' => $quote, ':r' => $rating]);
        audit_log('testimonial.submit', 'testimonials', (int) db()->lastInsertId());
        $done = true;
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-2xl mx-auto px-6 py-20 md:py-28">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Your reflection</p>
  <h1 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4 leading-tight">Share your experience</h1>

  <?php if ($done): ?>
    <div class="mt-10 border border-gold-500/30 rounded-3xl p-8 bg-navy-900/50 text-center">
      <p class="font-serif text-2xl text-beige-100">Thank you for trusting us with your words.</p>
      <p class="mt-3 text-beige-100/70">Our team will gently review your reflection before it appears on the site.</p>
      <a href="<?= url('/public/index.php') ?>" class="inline-block mt-8 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Back to home</a>
    </div>
  <?php else: ?>
    <p class="mt-5 text-beige-100/70 leading-relaxed">If a session moved something in you, we would be honoured to hear it. Approved reflections may be shared on our site to help others take their first quiet step.</p>

    <?php foreach ($errors as $err): ?>
      <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="mt-10 space-y-6">
      <?= csrf_field() ?>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Your name</span>
        <input name="author_name" required maxlength="150" value="<?= e((string) input('author_name', '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Title or location <span class="text-beige-100/30">(optional)</span></span>
        <input name="author_title" maxlength="150" placeholder="e.g. Member since 2025, Kuala Lumpur" value="<?= e((string) input('author_title', '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">How was your experience?</span>
        <select name="rating" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= (int) input('rating', 5) === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></option>
          <?php endfor; ?>
        </select>
      </label>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Your words</span>
        <textarea name="quote" rows="5" required minlength="10" maxlength="1000"
                  class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e((string) input('quote', '')) ?></textarea>
      </label>

      <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Share my reflection</button>
      <p class="text-center text-[11px] text-beige-100/40">Submitted reflections are reviewed before publishing. We never share your details without consent.</p>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
