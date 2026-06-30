<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Upcoming Sessions';
// Bump the page title if filtered to an experience (set after we know).


// Optional filter — when arriving from an Experience card the URL is
// /public/events.php?experience=<slug>, and we narrow the listing to
// just that experience's sessions. We resolve the slug to an id so the
// query stays simple and indexed.
$filterExperience = null;
$filterSlug = trim((string) input('experience', ''));
if ($filterSlug !== '') {
    $xStmt = db()->prepare(
        "SELECT id, title, description, cover_image
           FROM experiences WHERE slug = :s AND status = 'active' LIMIT 1"
    );
    $xStmt->execute([':s' => $filterSlug]);
    $filterExperience = $xStmt->fetch() ?: null;
}

// Fetch templates (recurring) + non-recurring future sessions; auto-created
// child instances of recurring templates are excluded — the expansion
// helper will resolve seats_taken for each occurrence by looking up the
// child if one already exists.
$eventsSql =
    "SELECT e.* FROM events e
      WHERE e.status = 'published'
        AND e.parent_event_id IS NULL
        AND (e.recurrence = 'daily' OR e.starts_at >= NOW())"
    . ($filterExperience ? " AND e.experience_id = :xid" : "")
    . " ORDER BY e.starts_at ASC";
$eventsStmt = db()->prepare($eventsSql);
if ($filterExperience) $eventsStmt->execute([':xid' => (int) $filterExperience['id']]);
else $eventsStmt->execute();
$rawEvents = $eventsStmt->fetchAll();

if ($filterExperience) {
    $pageTitle = (string) $filterExperience['title'] . ' · Sessions';
    $pageType  = 'article';
    // Use the experience's own cover + description for the WhatsApp /
    // Facebook share preview, so the link doesn't fall back to the
    // generic site image.
    if (!empty($filterExperience['cover_image'])) {
        $pageImage = (string) $filterExperience['cover_image'];
    }
    $xDesc = trim((string) ($filterExperience['description'] ?? ''));
    $xDesc = (string) preg_replace('/\s+/', ' ', $xDesc);
    if (function_exists('mb_strlen') && mb_strlen($xDesc) > 200) {
        $xDesc = mb_substr($xDesc, 0, 197) . '…';
    }
    if ($xDesc !== '') $pageDescription = $xDesc;
}

// Expand recurring templates 60 days out so the calendar has dots on
// future dates. One-off events further out pass through unchanged.
$events = expand_event_occurrences($rawEvents, 60);

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
  <h1 class="font-serif text-5xl text-beige-100 mt-4"><?= $filterExperience ? e($filterExperience['title']) : 'Upcoming sessions' ?></h1>
  <p class="mt-6 max-w-2xl text-beige-100/70 leading-relaxed">Reserve your seat. Choose the comforts you want — we'll hold space for you either way.</p>

  <?php if ($filterExperience): ?>
    <div class="mt-6 inline-flex items-center gap-3 px-4 py-2 rounded-full border border-gold-500/30 bg-gold-500/5 text-xs text-beige-100/75">
      <span>Filtered to <strong class="text-gold-400"><?= e($filterExperience['title']) ?></strong> sessions</span>
      <a href="<?= url('/public/events.php') ?>" class="text-beige-100/55 hover:text-gold-400 underline-offset-4 hover:underline">Show all →</a>
    </div>
  <?php endif; ?>

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
    <!-- Coming up — a visual lead-in showing the next 3 sessions so the
         page attracts before it filters. -->
    <h2 class="mt-12 font-serif text-2xl text-beige-100">Coming up next</h2>
    <div class="mt-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach (array_slice($events, 0, 3) as $fe):
        $feCover    = !empty($fe['cover_image']) ? media_src((string) $fe['cover_image']) : '';
        $feIsOcc    = !empty($fe['_template_id']);
        $feReserve  = $feIsOcc
            ? '/member/book_event.php?event_id=' . (int) $fe['_template_id'] . '&date=' . urlencode((string) $fe['_occurrence_date'])
            : '/member/book_event.php?event_id=' . (int) $fe['id'];
        $feRemain   = max(0, (int) $fe['capacity'] - (int) $fe['seats_taken']);
        $feSold     = $feRemain <= 0;
        $feFromPrice = min((float) $fe['price_member'], (float) $fe['price_public']);
      ?>
        <article class="group relative rounded-3xl overflow-hidden border border-white/5 bg-navy-900/40 hover:border-gold-500/30 transition flex flex-col">
          <div class="relative aspect-[4/3] bg-gradient-to-br from-navy-800 to-navy-950 overflow-hidden">
            <?php if ($feCover): ?>
              <img src="<?= e($feCover) ?>" alt="<?= e($fe['title']) ?>" loading="lazy"
                   onerror="this.style.display='none'"
                   class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-[1.04]">
              <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-navy-950/20 to-transparent"></div>
            <?php else: ?>
              <span class="absolute inset-0 flex items-center justify-center font-serif text-5xl text-gold-400/30">◯</span>
            <?php endif; ?>
            <?php if ($feSold): ?>
              <span class="absolute top-3 right-3 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full bg-navy-950/80 text-beige-100/70 border border-white/10">Fully held</span>
            <?php else: ?>
              <span class="absolute top-3 right-3 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full bg-navy-950/70 text-gold-400 border border-gold-500/30 backdrop-blur">
                <?= $feRemain ?> seat<?= $feRemain === 1 ? '' : 's' ?> left
              </span>
            <?php endif; ?>
          </div>
          <div class="p-5 flex flex-col gap-3 flex-1">
            <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/80"><?= e(format_datetime($fe['starts_at'], 'D, d M · g:i A')) ?></p>
            <h3 class="font-serif text-xl text-beige-100 leading-tight"><?= e($fe['title']) ?></h3>
            <?php if (!empty($fe['subtitle'])): ?>
              <p class="text-xs text-beige-100/60 line-clamp-2"><?= e($fe['subtitle']) ?></p>
            <?php endif; ?>
            <div class="mt-auto flex items-end justify-between gap-2 pt-3 border-t border-white/5">
              <div>
                <p class="text-[10px] uppercase tracking-widest text-beige-100/50">From</p>
                <p class="font-serif text-xl text-gold-400"><?= e(format_money($feFromPrice)) ?></p>
              </div>
              <?php if ($feSold): ?>
                <button class="px-4 py-2 rounded-full bg-navy-800 text-beige-100/40 cursor-not-allowed text-sm" disabled>Held</button>
              <?php else: ?>
                <a href="<?= url($feReserve) ?>"
                   class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-sm whitespace-nowrap">Reserve →</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <h2 class="mt-14 font-serif text-2xl text-beige-100">All sessions</h2>

    <div class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between" data-events-toolbar>
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

    <!-- Inline month-grid calendar — dates with sessions are clickable
         and show a small gold dot. Prev / next month buttons let the
         visitor browse further out without scrolling a long strip. -->
    <div class="mt-6" x-data="sessionCalendar(<?= htmlspecialchars(json_encode($sessionsByDay), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($initialDay), ENT_QUOTES) ?>)" x-cloak>
      <div class="flex items-center gap-2 mb-3 flex-wrap">
        <button type="button" @click="setDay('')"
                :class="!activeDay ? 'border-gold-500/50 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-gold-500/30 hover:text-gold-400'"
                class="px-4 py-1.5 rounded-full border text-xs uppercase tracking-[0.2em] transition">
          All upcoming
        </button>
        <span class="text-xs text-beige-100/40 ml-1" x-show="activeDay" x-cloak>
          Showing <span x-text="formattedSelected"></span>
        </span>
      </div>

      <div class="border border-white/5 rounded-2xl bg-navy-900/40 p-4 sm:p-5 max-w-md">
        <div class="flex items-center justify-between mb-3">
          <button type="button" @click="prevMonth()"
                  class="p-2 rounded-full text-beige-100/70 hover:text-gold-400 hover:bg-white/5 transition" aria-label="Previous month">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
          </button>
          <p class="font-serif text-lg text-beige-100" x-text="monthLabel"></p>
          <button type="button" @click="nextMonth()"
                  class="p-2 rounded-full text-beige-100/70 hover:text-gold-400 hover:bg-white/5 transition" aria-label="Next month">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
          </button>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-[10px] uppercase tracking-widest text-beige-100/45 mb-1.5">
          <template x-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d">
            <div x-text="d"></div>
          </template>
        </div>

        <div class="grid grid-cols-7 gap-1">
          <template x-for="(cell, idx) in cells" :key="idx">
            <button type="button"
                    @click="cell.hasSession && setDay(cell.iso)"
                    :disabled="!cell.hasSession"
                    :class="cellClass(cell)"
                    class="relative h-10 sm:h-11 rounded-lg text-sm transition">
              <span x-show="!cell.filler" x-text="cell.day"></span>
              <span x-show="cell.hasSession"
                    class="absolute bottom-1.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-gold-400"></span>
            </button>
          </template>
        </div>

        <p class="text-[10px] text-beige-100/40 mt-3 text-center">Dates with a · have sessions. Tap to filter the list below.</p>
      </div>
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
// Alpine state for the inline month calendar. Receives the map of
// dates-with-sessions from PHP and dispatches a custom event whenever
// the visitor picks a date, which the vanilla filter below listens to.
function sessionCalendar(sessionDays, initial) {
  return {
    sessionDays: sessionDays || {},
    activeDay: initial || '',
    viewYear: null,
    viewMonth: null,
    monthNames: ['January','February','March','April','May','June','July','August','September','October','November','December'],
    init() {
      const seed = this.activeDay && this.sessionDays[this.activeDay]
        ? new Date(this.activeDay + 'T00:00:00')
        : new Date();
      this.viewYear = seed.getFullYear();
      this.viewMonth = seed.getMonth();
      this.dispatch();
    },
    get monthLabel() { return this.monthNames[this.viewMonth] + ' ' + this.viewYear; },
    get formattedSelected() {
      if (!this.activeDay) return '';
      const d = new Date(this.activeDay + 'T00:00:00');
      return d.toLocaleDateString('en-GB', {weekday:'short', day:'numeric', month:'short'});
    },
    get cells() {
      const pad = (n) => String(n).padStart(2, '0');
      const firstDay = new Date(this.viewYear, this.viewMonth, 1);
      const startOffset = firstDay.getDay();
      const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
      const cells = [];
      for (let i = 0; i < startOffset; i++) cells.push({ filler: true, day: '', iso: '' });
      for (let d = 1; d <= daysInMonth; d++) {
        const iso = `${this.viewYear}-${pad(this.viewMonth + 1)}-${pad(d)}`;
        cells.push({ filler: false, day: d, iso, hasSession: !!this.sessionDays[iso] });
      }
      while (cells.length % 7 !== 0) cells.push({ filler: true, day: '', iso: '' });
      return cells;
    },
    cellClass(cell) {
      if (cell.filler) return 'opacity-0 pointer-events-none';
      if (cell.iso === this.activeDay) return 'bg-gold-500 text-navy-950 font-medium shadow-[0_4px_20px_-8px_rgba(201,164,106,0.5)]';
      if (cell.hasSession) return 'bg-navy-950/60 text-beige-100 hover:bg-navy-800 cursor-pointer';
      return 'text-beige-100/25 cursor-not-allowed';
    },
    prevMonth() { if (--this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; } },
    nextMonth() { if (++this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; } },
    setDay(day) { this.activeDay = day; this.dispatch(); },
    dispatch() {
      window.dispatchEvent(new CustomEvent('events:setDay', { detail: this.activeDay }));
    },
  };
}

(function () {
  const search   = document.querySelector('[data-events-search]');
  const filters  = document.querySelectorAll('[data-events-filters] button');
  const cards    = Array.from(document.querySelectorAll('[data-event]'));
  const empty    = document.querySelector('[data-events-empty]');
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

  window.addEventListener('events:setDay', (e) => {
    activeDay = e.detail || '';
    apply();
  });

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
