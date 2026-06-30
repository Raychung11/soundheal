<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Upcoming Sessions';

// Fetch templates (recurring) + non-recurring future sessions; auto-created
// child instances of recurring templates are excluded — the expansion
// helper will resolve seats_taken for each occurrence by looking up the
// child if one already exists.
$rawEvents = db()->query(
    "SELECT e.* FROM events e
      WHERE e.status = 'published'
        AND e.parent_event_id IS NULL
        AND (e.recurrence = 'daily' OR e.starts_at >= NOW())
      ORDER BY e.starts_at ASC"
)->fetchAll();

$events = expand_event_occurrences($rawEvents, 14);

// Distinct categories for the filter chips.
$categories = array_values(array_unique(array_filter(array_map(
    fn($e) => trim((string) ($e['category'] ?? '')), $events
))));

// Per-event social-share metadata. When ?event=ID (and optional &date=Y-m-d
// for a recurring occurrence) is present, override the page-level Open
// Graph tags so the share preview shows that specific event's title,
// description and cover image — not the generic calendar meta.
$shareEventId = (int) input('event', 0);
$shareDate    = (string) input('date', '');
if ($shareEventId > 0) {
    foreach ($events as $e) {
        $isMatch = !empty($e['_template_id'])
            ? ((int) $e['_template_id'] === $shareEventId
                && ($shareDate === '' || $e['_occurrence_date'] === $shareDate))
            : ((int) $e['id'] === $shareEventId);
        if (!$isMatch) continue;
        $pageTitle = (string) $e['title'];
        $desc = trim((string) ($e['subtitle'] ?? ''));
        if ($desc === '') $desc = trim((string) ($e['description'] ?? ''));
        $desc = (string) preg_replace('/\s+/', ' ', $desc);
        if (function_exists('mb_strlen') && mb_strlen($desc) > 200) {
            $desc = mb_substr($desc, 0, 197) . '…';
        }
        if ($desc !== '') $pageDescription = $desc;
        if (!empty($e['cover_image'])) $pageImage = (string) $e['cover_image'];
        $pageType = 'article';
        break;
    }
}

// Event structured data (schema.org/Event) for Google rich results.
$ldBase = rtrim((string) config('app.url'), '/');
$eventsLd = [];
foreach ($events as $e) {
    $img = !empty($e['cover_image']) ? media_src((string) $e['cover_image']) : '';
    if ($img !== '' && !str_starts_with($img, 'http')) $img = $ldBase . '/' . ltrim($img, '/');
    $available = ((int) $e['capacity'] - (int) $e['seats_taken']) > 0;
    $isOcc = !empty($e['_template_id']);
    $ldUrl = $isOcc
        ? $ldBase . '/public/events.php?event=' . (int) $e['_template_id'] . '&date=' . urlencode((string) $e['_occurrence_date'])
        : $ldBase . '/public/events.php?event=' . (int) $e['id'];
    $eventsLd[] = array_filter([
        '@context'            => 'https://schema.org',
        '@type'               => 'Event',
        'name'                => $e['title'],
        'startDate'           => date('c', strtotime((string) $e['starts_at'])),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'description'         => (string) ($e['subtitle'] ?: ($e['description'] ?? '')),
        'image'               => $img ?: null,
        'url'                 => $ldUrl,
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
            'url'           => $ldUrl,
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
  <p class="mt-6 max-w-2xl text-beige-100/70 leading-relaxed">Reserve your seat. Choose the comforts you want — we'll hold space for you either way.</p>

  <?php
  // Group occurrences by day for the cinema-style date strip.
  $sessionsByDay = [];
  foreach ($events as $e) {
      $d = substr((string) $e['starts_at'], 0, 10);
      $sessionsByDay[$d] = ($sessionsByDay[$d] ?? 0) + 1;
  }
  $daysList   = array_keys($sessionsByDay);
  $initialDay = $daysList[0] ?? '';
  ?>

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

    <!-- Date strip (cinema-style) -->
    <div class="mt-6 flex gap-2 overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0" data-day-strip>
      <?php foreach ($daysList as $day):
        $dayDate = new DateTimeImmutable($day);
        $count = $sessionsByDay[$day] ?? 0;
        $active = $day === $initialDay;
      ?>
        <button type="button" data-day="<?= e($day) ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>"
                class="shrink-0 w-[72px] px-3 py-3 rounded-2xl border text-center transition
                       <?= $active ? 'border-gold-500/50 bg-gold-500/10 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/70 hover:border-gold-500/30 hover:text-gold-400' ?>">
          <p class="text-[10px] uppercase tracking-widest opacity-70"><?= e($dayDate->format('D')) ?></p>
          <p class="font-serif text-2xl mt-0.5"><?= e($dayDate->format('d')) ?></p>
          <p class="text-[9px] uppercase tracking-widest opacity-70"><?= e($dayDate->format('M')) ?></p>
          <p class="text-[10px] mt-1 opacity-80"><?= (int) $count ?> sess<?= $count === 1 ? '' : 'n' ?></p>
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$events): ?>
    <div class="mt-16 border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="font-serif text-2xl text-beige-100/80">New sessions are being woven into the calendar.</p>
      <p class="mt-3 text-beige-100/60">Sign up to be notified when the next one opens.</p>
      <a href="<?= url('/public/register.php') ?>" class="inline-block mt-8 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Notify me</a>
    </div>
  <?php else: ?>
    <div class="mt-8 space-y-3">
      <?php foreach ($events as $event):
        $remaining = max(0, (int)$event['capacity'] - (int)$event['seats_taken']);
        $soldOut = $remaining <= 0;
        $catVal = strtolower(trim((string) ($event['category'] ?? '')));
        $dayVal = substr((string) $event['starts_at'], 0, 10);
        $searchText = strtolower(trim(($event['title'] ?? '') . ' ' . ($event['subtitle'] ?? '') . ' '
            . ($event['description'] ?? '') . ' ' . ($event['facilitator'] ?? '') . ' ' . ($event['location'] ?? '')));
        $isRecurringOcc = !empty($event['_template_id']);
        $cardKey = $isRecurringOcc
            ? 'event-' . (int) $event['_template_id'] . '-' . str_replace('-', '', (string) $event['_occurrence_date'])
            : 'event-' . (int) $event['id'];
        $shareEventParam = $isRecurringOcc ? (int) $event['_template_id'] : (int) $event['id'];
        $shareDateParam  = $isRecurringOcc ? '&date=' . urlencode((string) $event['_occurrence_date']) : '';
        $reserveUrl = $isRecurringOcc
            ? '/member/book_event.php?event_id=' . (int) $event['_template_id'] . '&date=' . urlencode((string) $event['_occurrence_date'])
            : '/member/book_event.php?event_id=' . (int) $event['id'];
        $shareUrl = $ldBase . '/public/events.php?event=' . $shareEventParam . $shareDateParam . '#' . $cardKey;
        $shareUrlEnc = rawurlencode($shareUrl);
        $shareTextEnc = rawurlencode($event['title'] . ' · ' . brand_name());
        $startTs = strtotime((string) $event['starts_at']);
      ?>
        <article id="<?= e($cardKey) ?>"
                 data-event data-day="<?= e($dayVal) ?>" data-cat="<?= e($catVal) ?>" data-search="<?= e($searchText) ?>"
                 class="border border-white/5 rounded-2xl bg-navy-900/40 hover:border-gold-500/30 transition">
          <div class="grid grid-cols-[auto_1fr_auto] gap-3 sm:gap-5 items-center p-4 sm:p-5">

            <!-- Time -->
            <div class="text-center w-14 sm:w-16">
              <p class="font-serif text-xl sm:text-2xl text-gold-400 leading-none"><?= e(date('g:i', $startTs)) ?></p>
              <p class="text-[10px] uppercase tracking-widest text-beige-100/55 mt-1"><?= e(date('A', $startTs)) ?></p>
            </div>

            <!-- Title + meta -->
            <div class="min-w-0">
              <h3 class="font-serif text-base sm:text-lg text-beige-100 leading-tight truncate"><?= e($event['title']) ?></h3>
              <?php if (!empty($event['subtitle']) || !empty($event['location'])): ?>
                <p class="text-xs text-beige-100/55 mt-1 truncate">
                  <?php if (!empty($event['subtitle'])): ?><?= e($event['subtitle']) ?><?php endif; ?>
                  <?php if (!empty($event['location']) && !empty($event['subtitle'])): ?> · <?php endif; ?>
                  <?php if (!empty($event['location'])): ?><?= e($event['location']) ?><?php endif; ?>
                </p>
              <?php endif; ?>
              <p class="text-xs mt-1.5">
                <?php if ($soldOut): ?>
                  <span class="text-beige-100/45">Fully held</span>
                <?php else: ?>
                  <span class="text-gold-400"><?= $remaining ?> seat<?= $remaining === 1 ? '' : 's' ?> left</span>
                <?php endif; ?>
                <span class="text-beige-100/35"> · </span>
                <?php
                  $rowAName = trim((string) ($event['package_a_label'] ?? '')) ?: 'Comfort';
                  $rowBName = trim((string) ($event['package_b_label'] ?? '')) ?: 'BYO';
                ?>
                <span class="text-beige-100/70"><?= e($rowBName) ?> <?= e(format_money((float)$event['price_member'])) ?></span>
                <span class="text-beige-100/35"> · </span>
                <span class="text-beige-100/70"><?= e($rowAName) ?> <?= e(format_money((float)$event['price_public'])) ?></span>
              </p>
            </div>

            <!-- Actions -->
            <div class="shrink-0 flex items-center gap-2">
              <div x-data="{ open: false }" @click.outside="open=false" class="relative">
                <button type="button" @click="open=!open" aria-label="Share"
                        class="p-2 rounded-full border border-white/10 text-beige-100/55 hover:border-gold-500/40 hover:text-gold-400 transition">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8 11 8-4M8 13l8 4"/></svg>
                </button>
                <div x-show="open" x-cloak
                     class="absolute right-0 top-full mt-2 z-20 min-w-[170px] rounded-2xl border border-white/10 bg-navy-900 shadow-2xl p-2 text-sm">
                  <a href="https://wa.me/?text=<?= $shareTextEnc ?>%20<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
                     class="block px-3 py-2 rounded-lg hover:bg-white/5 text-beige-100">WhatsApp</a>
                  <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
                     class="block px-3 py-2 rounded-lg hover:bg-white/5 text-beige-100">Facebook</a>
                  <button type="button" data-copy-link="<?= e($shareUrl) ?>"
                          class="w-full text-left px-3 py-2 rounded-lg hover:bg-white/5 text-beige-100">Copy link</button>
                </div>
              </div>
              <?php if ($soldOut): ?>
                <button class="px-3 sm:px-5 py-2 rounded-full bg-navy-800 text-beige-100/40 cursor-not-allowed text-sm whitespace-nowrap" disabled>Held</button>
              <?php else: ?>
                <a href="<?= url($reserveUrl) ?>"
                   class="px-3 sm:px-5 py-2 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-sm whitespace-nowrap">
                  Reserve →
                </a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p data-events-empty class="hidden mt-12 text-center text-beige-100/55 italic">No sessions match your search. Try a different word, category or date.</p>
  <?php endif; ?>
</section>

<script>
(function () {
  const search     = document.querySelector('[data-events-search]');
  const filters    = document.querySelectorAll('[data-events-filters] button');
  const dayChips   = document.querySelectorAll('[data-day-strip] button');
  const cards      = Array.from(document.querySelectorAll('[data-event]'));
  const empty      = document.querySelector('[data-events-empty]');
  let activeCat = '';
  let activeDay = <?= json_encode($initialDay) ?>;

  function apply() {
    const q = (search?.value || '').trim().toLowerCase();
    let shown = 0;
    cards.forEach((card) => {
      const matchesCat  = !activeCat || card.dataset.cat === activeCat;
      const matchesText = !q || (card.dataset.search || '').includes(q);
      const matchesDay  = !activeDay || card.dataset.day === activeDay;
      const show = matchesCat && matchesText && matchesDay;
      card.classList.toggle('hidden', !show);
      if (show) shown++;
    });
    if (empty) empty.classList.toggle('hidden', shown !== 0);
  }

  function setPressed(btn, on) {
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.classList.toggle('border-gold-500/50', on);
    btn.classList.toggle('bg-gold-500/10', on);
    btn.classList.toggle('text-gold-400', on);
    btn.classList.toggle('border-white/10', !on);
    btn.classList.toggle('text-beige-100/70', !on);
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

  dayChips.forEach((btn) => btn.addEventListener('click', () => {
    activeDay = btn.dataset.day || '';
    dayChips.forEach((b) => setPressed(b, b === btn));
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

  apply();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
