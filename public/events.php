<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Upcoming Sessions';

// Match the capacity-hold logic in member/book_event.php — pending
// bookings hold a seat until they pay, so they count toward the
// public "seats remaining" total.
$events = db()->query(
    "SELECT e.*,
            (SELECT COALESCE(SUM(quantity), 0)
               FROM event_bookings b
              WHERE b.event_id = e.id
                AND b.status IN ('pending','paid','attended')) AS seats_taken
     FROM events e
     WHERE e.status = 'published' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC"
)->fetchAll();

// Distinct categories for the filter chips.
$categories = array_values(array_unique(array_filter(array_map(
    fn($e) => trim((string) ($e['category'] ?? '')), $events
))));

// Event structured data (schema.org/Event) for Google rich results.
$ldBase = rtrim((string) config('app.url'), '/');
$eventsLd = [];
foreach ($events as $e) {
    $img = !empty($e['cover_image']) ? media_src((string) $e['cover_image']) : '';
    if ($img !== '' && !str_starts_with($img, 'http')) $img = $ldBase . '/' . ltrim($img, '/');
    $available = ((int) $e['capacity'] - (int) $e['seats_taken']) > 0;
    $eventsLd[] = array_filter([
        '@context'            => 'https://schema.org',
        '@type'               => 'Event',
        'name'                => $e['title'],
        'startDate'           => date('c', strtotime((string) $e['starts_at'])),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'description'         => (string) ($e['subtitle'] ?: ($e['description'] ?? '')),
        'image'               => $img ?: null,
        'location'            => !empty($e['location']) ? [
            '@type'   => 'Place',
            'name'    => $e['location'],
            'address' => $e['location'],
        ] : null,
        'organizer'           => ['@type' => 'Organization', 'name' => brand_name(), 'url' => $ldBase],
        'offers'              => [
            '@type'         => 'Offer',
            'price'         => number_format((float) $e['price_public'], 2, '.', ''),
            'priceCurrency' => 'MYR',
            'availability'  => $available ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'url'           => $ldBase . '/public/events.php#event-' . (int) $e['id'],
        ],
    ], fn($v) => $v !== null && $v !== '');
}

require __DIR__ . '/../includes/header.php';
?>
<?php if ($eventsLd): ?>
<script type="application/ld+json"><?= json_encode($eventsLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<section class="max-w-6xl mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Calendar</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4">Upcoming sessions</h1>
  <p class="mt-6 max-w-2xl text-beige-100/70 leading-relaxed">Reserve your seat. Members enjoy quiet pricing and priority booking on every session.</p>

  <?php if ($events): ?>
    <div class="mt-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between" data-events-toolbar>
      <label class="relative md:max-w-xs w-full">
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-beige-100/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="search" data-events-search placeholder="Search sessions…"
               class="w-full rounded-full bg-navy-900/60 border border-white/10 pl-11 pr-4 py-2.5 text-sm text-beige-100 placeholder:text-beige-100/40 focus:border-gold-500/50 focus:outline-none">
      </label>
      <?php if (count($categories) > 1): ?>
        <div class="flex flex-wrap gap-2" data-events-filters>
          <button type="button" data-cat="" class="text-xs uppercase tracking-[0.2em] px-4 py-2 rounded-full border border-gold-500/40 bg-gold-500/10 text-gold-400 transition" aria-pressed="true">All</button>
          <?php foreach ($categories as $cat): ?>
            <button type="button" data-cat="<?= e(strtolower($cat)) ?>" class="text-xs uppercase tracking-[0.2em] px-4 py-2 rounded-full border border-white/10 text-beige-100/60 hover:border-gold-500/40 hover:text-gold-400 transition capitalize" aria-pressed="false"><?= e($cat) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$events): ?>
    <div class="mt-16 border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="font-serif text-2xl text-beige-100/80">New sessions are being woven into the calendar.</p>
      <p class="mt-3 text-beige-100/60">Sign up to be notified when the next one opens.</p>
      <a href="<?= url('/public/register.php') ?>" class="inline-block mt-8 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Notify me</a>
    </div>
  <?php else: ?>
    <div class="mt-16 grid gap-8">
      <?php foreach ($events as $event):
        $remaining = max(0, (int)$event['capacity'] - (int)$event['seats_taken']);
        $soldOut = $remaining <= 0;
        $coverSrc = !empty($event['cover_image']) ? media_src((string) $event['cover_image']) : '';
        $catVal = strtolower(trim((string) ($event['category'] ?? '')));
        $searchText = strtolower(trim(($event['title'] ?? '') . ' ' . ($event['subtitle'] ?? '') . ' '
            . ($event['description'] ?? '') . ' ' . ($event['facilitator'] ?? '') . ' ' . ($event['location'] ?? '')));
        $shareUrl = $ldBase . '/public/events.php#event-' . (int) $event['id'];
        $shareUrlEnc = rawurlencode($shareUrl);
        $shareTextEnc = rawurlencode($event['title'] . ' · ' . brand_name());
      ?>
        <article id="event-<?= (int)$event['id'] ?>"
                 data-event data-cat="<?= e($catVal) ?>" data-search="<?= e($searchText) ?>"
                 class="group grid md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] overflow-hidden border border-white/5 rounded-3xl bg-navy-900/40 hover:border-gold-500/30 transition">

          <!-- Cover -->
          <div class="relative aspect-[4/3] md:aspect-auto md:min-h-[300px] bg-gradient-to-br from-navy-800 to-navy-950 overflow-hidden">
            <?php if ($coverSrc): ?>
              <img src="<?= e($coverSrc) ?>" alt="<?= e($event['title']) ?>"
                   onerror="this.style.display='none'"
                   loading="lazy"
                   class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-[1.03]">
              <div class="absolute inset-0 bg-gradient-to-t md:bg-gradient-to-r from-navy-950/85 via-navy-950/20 to-transparent"></div>
            <?php else: ?>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="font-serif text-6xl text-gold-400/30">◯</span>
              </div>
            <?php endif; ?>

            <?php if (!empty($event['category'])): ?>
              <span class="absolute top-4 left-4 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full border border-gold-500/40 bg-navy-950/60 text-gold-400 backdrop-blur capitalize">
                <?= e($event['category']) ?>
              </span>
            <?php endif; ?>

            <?php if ($soldOut): ?>
              <span class="absolute top-4 right-4 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full bg-navy-950/80 text-beige-100/70 border border-white/10">Fully held</span>
            <?php else: ?>
              <span class="absolute top-4 right-4 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full bg-navy-950/70 text-gold-400 border border-gold-500/30 backdrop-blur">
                <?= $remaining ?> seat<?= $remaining === 1 ? '' : 's' ?> left
              </span>
            <?php endif; ?>
          </div>

          <!-- Content + price -->
          <div class="p-6 md:p-10 flex flex-col gap-6">
            <div>
              <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80"><?= e(format_datetime($event['starts_at'], 'l, d M Y · g:i A')) ?></p>
              <h2 class="font-serif text-3xl md:text-4xl text-beige-100 mt-3 leading-tight"><?= e($event['title']) ?></h2>
              <?php if (!empty($event['subtitle'])): ?>
                <p class="text-beige-100/75 mt-3 leading-relaxed"><?= e($event['subtitle']) ?></p>
              <?php endif; ?>
              <?php if (!empty($event['description'])): ?>
                <p class="text-sm text-beige-100/60 mt-4 leading-[1.85] line-clamp-4"><?= nl2br(e($event['description'])) ?></p>
              <?php endif; ?>

              <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-xs text-beige-100/55">
                <?php if (!empty($event['location'])): ?>
                  <span class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-gold-400/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 22s7-7.16 7-12a7 7 0 1 0-14 0c0 4.84 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <?= e($event['location']) ?>
                  </span>
                <?php endif; ?>
                <?php if (!empty($event['facilitator'])): ?>
                  <span class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-gold-400/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.5"/><path d="M5 21c1-4 4-6 7-6s6 2 7 6"/></svg>
                    <?= e($event['facilitator']) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="flex items-center gap-2 text-[11px] text-beige-100/45">
              <span class="uppercase tracking-[0.25em]">Share</span>
              <a href="https://wa.me/?text=<?= $shareTextEnc ?>%20<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
                 class="px-2.5 py-1 rounded-full border border-white/10 hover:border-gold-500/40 hover:text-gold-400 transition">WhatsApp</a>
              <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
                 class="px-2.5 py-1 rounded-full border border-white/10 hover:border-gold-500/40 hover:text-gold-400 transition">Facebook</a>
              <button type="button" data-copy-link="<?= e($shareUrl) ?>"
                 class="px-2.5 py-1 rounded-full border border-white/10 hover:border-gold-500/40 hover:text-gold-400 transition">Copy link</button>
            </div>

            <div class="mt-auto flex items-end justify-between gap-4 pt-5 border-t border-white/5">
              <div class="space-y-1">
                <div class="flex items-baseline gap-3">
                  <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Public</span>
                  <span class="font-serif text-xl text-beige-100"><?= e(format_money((float)$event['price_public'])) ?></span>
                </div>
                <div class="flex items-baseline gap-3">
                  <span class="text-[10px] uppercase tracking-widest text-gold-400/80">Member</span>
                  <span class="font-serif text-xl text-gold-400"><?= e(format_money((float)$event['price_member'])) ?></span>
                </div>
              </div>
              <?php if ($soldOut): ?>
                <button class="px-6 py-3 rounded-full bg-navy-800 text-beige-100/40 cursor-not-allowed text-sm" disabled>Fully held</button>
              <?php else: ?>
                <a href="<?= url('/member/book_event.php?event_id=' . (int)$event['id']) ?>"
                   class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition shadow-[0_8px_30px_-12px_rgba(201,164,106,0.5)] text-sm">
                  Reserve →
                </a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p data-events-empty class="hidden mt-12 text-center text-beige-100/55 italic">No sessions match your search. Try a different word or category.</p>
  <?php endif; ?>
</section>

<script>
(function () {
  const search  = document.querySelector('[data-events-search]');
  const filters = document.querySelectorAll('[data-events-filters] button');
  const cards   = Array.from(document.querySelectorAll('[data-event]'));
  const empty   = document.querySelector('[data-events-empty]');
  let activeCat = '';

  function apply() {
    const q = (search?.value || '').trim().toLowerCase();
    let shown = 0;
    cards.forEach((card) => {
      const matchesCat  = !activeCat || card.dataset.cat === activeCat;
      const matchesText = !q || (card.dataset.search || '').includes(q);
      const show = matchesCat && matchesText;
      card.classList.toggle('hidden', !show);
      if (show) shown++;
    });
    if (empty) empty.classList.toggle('hidden', shown !== 0);
  }

  search?.addEventListener('input', apply);
  filters.forEach((btn) => btn.addEventListener('click', () => {
    activeCat = btn.dataset.cat || '';
    filters.forEach((b) => {
      const on = b === btn;
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.classList.toggle('border-gold-500/40', on);
      b.classList.toggle('bg-gold-500/10', on);
      b.classList.toggle('text-gold-400', on);
      b.classList.toggle('border-white/10', !on);
      b.classList.toggle('text-beige-100/60', !on);
    });
    apply();
  }));

  document.querySelectorAll('[data-copy-link]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const url = btn.dataset.copyLink;
      try { await navigator.clipboard.writeText(url); }
      catch (e) {
        const t = document.createElement('textarea');
        t.value = url; document.body.appendChild(t); t.select();
        document.execCommand('copy'); t.remove();
      }
      const original = btn.textContent;
      btn.textContent = 'Copied ✓';
      setTimeout(() => { btn.textContent = original; }, 1600);
    });
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
