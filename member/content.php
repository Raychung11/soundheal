<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Audio Sanctuary';
$user = current_user();

// Trial state for the banner — gracefully degrade if migration 003 hasn't run.
$trialEndsAt = null;
$trialActive = false;
$trialHoursLeft = 0;
$trialDaysLeft  = 0;
try {
    $trialRow = db()->prepare("SELECT trial_ends_at FROM users WHERE id = :u");
    $trialRow->execute([':u' => $user['id']]);
    $trialEndsAt = $trialRow->fetchColumn() ?: null;
    $trialActive = $trialEndsAt && strtotime($trialEndsAt) > time();
    $trialHoursLeft = $trialActive ? max(0, (int) round((strtotime($trialEndsAt) - time()) / 3600)) : 0;
    $trialDaysLeft  = (int) ceil($trialHoursLeft / 24);
} catch (Throwable $e) {
    error_log('[content.php] trial check skipped: ' . $e->getMessage());
}

try {
    $hasMemberAccess = user_has_member_access();
} catch (Throwable $e) {
    error_log('[content.php] member-access check fell back to membership-only: ' . $e->getMessage());
    $stmt = db()->prepare("SELECT 1 FROM memberships WHERE user_id = :u AND status = 'active' LIMIT 1");
    $stmt->execute([':u' => $user['id']]);
    $hasMemberAccess = (bool) $stmt->fetchColumn();
}

// All published tracks. Premium tracks remain visible (locked overlay).
$tracks = db()->query(
    "SELECT id, slug, title, description, type, file_path, cover_image, duration_seconds, access, created_at
     FROM wellness_content
     WHERE is_published = 1
     ORDER BY created_at DESC"
)->fetchAll();

// Filter optionally by mood from dashboard (?mood=tired etc.). Map mood → preferred type.
$mood = preg_replace('/[^a-z]/', '', strtolower((string) input('mood', '')));
$moodToType = [
    'tired'       => 'sleep',
    'heavy'       => 'meditation',
    'overwhelmed' => 'breathing',
    'anxious'     => 'breathing',
    'peaceful'    => 'meditation',
    'curious'     => 'audio',
    'open'        => 'audio',
];
$initialType = $moodToType[$mood] ?? 'all';

// Recent plays for "continue listening". Skip silently if content_plays
// table isn't there yet (phase 2 migration not applied).
$recentTracks = [];
try {
    $recent = db()->prepare(
        "SELECT c.id, c.title, c.type, c.duration_seconds, c.cover_image, c.access,
                MAX(p.played_at) AS last_played
         FROM content_plays p
         JOIN wellness_content c ON c.id = p.content_id
         WHERE p.user_id = :u AND c.is_published = 1
         GROUP BY c.id
         ORDER BY last_played DESC
         LIMIT 4"
    );
    $recent->execute([':u' => $user['id']]);
    $recentTracks = $recent->fetchAll();
} catch (Throwable $e) {
    error_log('[content.php] continue-listening skipped: ' . $e->getMessage());
}

// Pick a featured track — the latest non-premium-locked one for this viewer.
$featured = null;
foreach ($tracks as $t) {
    if ($t['access'] !== 'premium' || $hasMemberAccess) {
        $featured = $t;
        break;
    }
}

// Build the JS-side track list.
$tracksForJs = array_map(function ($t) use ($hasMemberAccess) {
    $locked = ($t['access'] === 'premium' && !$hasMemberAccess);
    return [
        'id'       => (int) $t['id'],
        'title'    => (string) $t['title'],
        'desc'     => (string) ($t['description'] ?? ''),
        'type'     => (string) $t['type'],
        'access'   => (string) $t['access'],
        'duration' => (int) $t['duration_seconds'],
        'cover'    => $t['cover_image'] ? media_src((string) $t['cover_image']) : '',
        'src'      => url('/api/stream_content.php?id=' . (int) $t['id']),
        'locked'   => $locked,
    ];
}, $tracks);

$typeLabels = [
    'all'        => 'All',
    'audio'      => 'Sound',
    'meditation' => 'Meditation',
    'sleep'      => 'Sleep',
    'breathing'  => 'Breath',
    'article'    => 'Reading',
];

require __DIR__ . '/../includes/header.php';
?>

<script>
  window.__SH_LIBRARY = {
    tracks: <?= json_encode($tracksForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    initialFeatured: <?= $featured ? (int) $featured['id'] : 'null' ?>,
    initialType: <?= json_encode($initialType) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    logUrl: <?= json_encode(url('/api/log_play.php')) ?>
  };
</script>

<div x-data="library()" x-init="init()" class="pb-32">

  <section class="max-w-6xl mx-auto px-6 pt-12 md:pt-16">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Audio sanctuary</p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-4">A library for stillness</h1>
    <p class="mt-4 text-beige-100/70 max-w-2xl leading-relaxed">Curated tracks for meditation, sleep and breath. Press play, dim the lights, and let the frequencies do the work.</p>

    <?php if ($trialActive): ?>
      <div class="mt-6 inline-flex items-center gap-3 px-4 py-2 rounded-full border border-gold-500/30 bg-gold-500/5 text-gold-400 text-xs uppercase tracking-widest">
        <span class="w-1.5 h-1.5 rounded-full bg-gold-400 animate-pulse"></span>
        Free trial · <?= $trialDaysLeft >= 1 ? $trialDaysLeft . ' day' . ($trialDaysLeft === 1 ? '' : 's') : $trialHoursLeft . ' hours' ?> remaining
      </div>
    <?php endif; ?>
  </section>

  <?php if ($featured): ?>
    <section class="max-w-6xl mx-auto px-6 mt-12">
      <article class="relative overflow-hidden rounded-[2rem] border border-white/5 bg-navy-900/40">
        <div class="absolute inset-0 opacity-30 pointer-events-none">
          <?php $featuredCover = $featured['cover_image'] ? media_src((string) $featured['cover_image']) : ''; ?>
          <?php if ($featuredCover): ?>
            <img src="<?= e($featuredCover) ?>" alt="" class="w-full h-full object-cover">
          <?php else: ?>
            <div class="w-full h-full bg-gradient-to-br from-gold-500/30 via-navy-800 to-navy-950"></div>
          <?php endif; ?>
          <div class="absolute inset-0 bg-gradient-to-r from-navy-950/95 via-navy-950/70 to-navy-950/30"></div>
        </div>

        <div class="relative grid md:grid-cols-2 gap-8 p-8 md:p-12">
          <div>
            <p class="text-[11px] uppercase tracking-[0.4em] text-gold-400/70">Featured · <span class="capitalize"><?= e($featured['type']) ?></span></p>
            <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-3 leading-tight"><?= e($featured['title']) ?></h2>
            <?php if (!empty($featured['description'])): ?>
              <p class="mt-4 text-beige-100/75 leading-relaxed"><?= e($featured['description']) ?></p>
            <?php endif; ?>

            <div class="mt-8 flex items-center gap-4">
              <button @click="playTrack(<?= (int) $featured['id'] ?>)" type="button"
                      class="h-16 w-16 rounded-full border border-gold-500/50 bg-gold-500 text-navy-950 hover:bg-gold-400 transition flex items-center justify-center text-2xl shadow-[0_8px_30px_-10px_rgba(201,164,106,0.5)]">
                <span x-show="!(playing && currentId === <?= (int) $featured['id'] ?>)">▶</span>
                <span x-show="playing && currentId === <?= (int) $featured['id'] ?>" class="text-base">❚❚</span>
              </button>
              <div>
                <p class="text-sm text-beige-100/80"><?= max(1, (int) round(((int) $featured['duration_seconds']) / 60)) ?> min</p>
                <p class="text-xs text-beige-100/50 mt-1">Wear headphones if you can</p>
              </div>
            </div>
          </div>

          <div class="hidden md:flex items-center justify-center">
            <div class="relative h-56 w-56">
              <div class="absolute inset-0 rounded-full border border-gold-500/20" :class="(playing && currentId === <?= (int) $featured['id'] ?>) ? 'animate-ping-slow' : ''"></div>
              <div class="absolute inset-6 rounded-full border border-gold-500/30"></div>
              <div class="absolute inset-12 rounded-full border border-gold-500/40 flex items-center justify-center font-serif text-gold-400/60 text-sm tracking-widest uppercase">
                Sound · <?= e($featured['type']) ?>
              </div>
            </div>
          </div>
        </div>
      </article>
    </section>
  <?php endif; ?>

  <?php if ($recentTracks): ?>
    <section class="max-w-6xl mx-auto px-6 mt-16">
      <div class="flex items-end justify-between">
        <h2 class="font-serif text-2xl text-gold-400">Continue listening</h2>
        <p class="text-xs text-beige-100/40 uppercase tracking-widest">Last 4</p>
      </div>
      <div class="mt-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($recentTracks as $r): ?>
          <button type="button" @click="playTrack(<?= (int) $r['id'] ?>)"
                  class="group text-left rounded-2xl border border-white/5 bg-navy-900/40 hover:border-gold-500/30 hover:bg-navy-900/70 transition p-4 flex gap-4 items-center">
            <div class="relative h-14 w-14 rounded-xl overflow-hidden bg-gradient-to-br from-navy-800 to-navy-950 flex-shrink-0 flex items-center justify-center border border-white/5">
              <?php $rc = $r['cover_image'] ? media_src((string) $r['cover_image']) : ''; ?>
              <?php if ($rc): ?>
                <img src="<?= e($rc) ?>" alt="" class="w-full h-full object-cover">
              <?php else: ?>
                <span class="text-gold-400/40 font-serif text-xl">◯</span>
              <?php endif; ?>
              <span class="absolute inset-0 flex items-center justify-center bg-navy-950/60 opacity-0 group-hover:opacity-100 transition text-gold-400">▶</span>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-beige-100 truncate"><?= e($r['title']) ?></p>
              <p class="text-xs text-beige-100/50 capitalize"><?= e($r['type']) ?> · <?= max(1, (int) round(((int)$r['duration_seconds'])/60)) ?> min</p>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="max-w-6xl mx-auto px-6 mt-16">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex flex-wrap gap-2">
        <?php foreach ($typeLabels as $key => $label): ?>
          <button type="button" @click="filterType = '<?= $key ?>'"
                  :class="filterType === '<?= $key ?>' ? 'bg-gold-500 text-navy-950' : 'border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400'"
                  class="px-4 py-1.5 rounded-full text-sm transition"><?= e($label) ?></button>
        <?php endforeach; ?>
      </div>

      <div class="flex flex-wrap gap-2">
        <button type="button" @click="filterDuration = 'all'"
                :class="filterDuration === 'all' ? 'bg-gold-500/20 text-gold-400 border border-gold-500/40' : 'border border-white/10 text-beige-100/60'"
                class="px-3 py-1 rounded-full text-xs uppercase tracking-widest transition">Any</button>
        <button type="button" @click="filterDuration = 'short'"
                :class="filterDuration === 'short' ? 'bg-gold-500/20 text-gold-400 border border-gold-500/40' : 'border border-white/10 text-beige-100/60'"
                class="px-3 py-1 rounded-full text-xs uppercase tracking-widest transition">&lt; 5m</button>
        <button type="button" @click="filterDuration = 'medium'"
                :class="filterDuration === 'medium' ? 'bg-gold-500/20 text-gold-400 border border-gold-500/40' : 'border border-white/10 text-beige-100/60'"
                class="px-3 py-1 rounded-full text-xs uppercase tracking-widest transition">5–15m</button>
        <button type="button" @click="filterDuration = 'long'"
                :class="filterDuration === 'long' ? 'bg-gold-500/20 text-gold-400 border border-gold-500/40' : 'border border-white/10 text-beige-100/60'"
                class="px-3 py-1 rounded-full text-xs uppercase tracking-widest transition">&gt; 15m</button>
      </div>
    </div>

    <input x-model="search" type="search" placeholder="Search the library…"
           class="w-full rounded-full bg-navy-900/60 border border-white/5 px-5 py-3 text-sm focus:border-gold-500/50 focus:outline-none">
  </section>

  <section class="max-w-6xl mx-auto px-6 mt-10">
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <template x-for="track in visibleTracks" :key="track.id">
        <article class="group rounded-3xl border border-white/5 bg-navy-900/40 hover:border-gold-500/30 transition overflow-hidden flex flex-col">
          <div class="relative aspect-[4/3] bg-gradient-to-br from-navy-800 to-navy-950 overflow-hidden">
            <template x-if="track.cover">
              <img :src="track.cover" alt="" class="w-full h-full object-cover transition group-hover:scale-105">
            </template>
            <template x-if="!track.cover">
              <div class="w-full h-full flex items-center justify-center">
                <span class="font-serif text-5xl text-gold-400/30">◯</span>
              </div>
            </template>

            <button type="button"
                    x-show="!track.locked"
                    @click="playTrack(track.id)"
                    class="absolute bottom-3 right-3 h-12 w-12 rounded-full bg-gold-500 text-navy-950 flex items-center justify-center text-lg shadow-[0_8px_30px_-10px_rgba(201,164,106,0.5)] hover:bg-gold-400 transition">
              <span x-show="!(playing && currentId === track.id)">▶</span>
              <span x-show="playing && currentId === track.id">❚❚</span>
            </button>

            <span x-show="track.locked"
                  class="absolute top-3 right-3 text-[10px] uppercase tracking-widest px-2 py-1 rounded-full bg-navy-950/80 text-gold-400 border border-gold-500/30">
              Premium
            </span>
            <span x-show="playing && currentId === track.id" class="absolute bottom-3 left-3 inline-flex items-center gap-1.5">
              <span class="w-1 h-3 bg-gold-400 rounded animate-pulse"></span>
              <span class="w-1 h-5 bg-gold-400 rounded animate-pulse" style="animation-delay:0.15s"></span>
              <span class="w-1 h-2 bg-gold-400 rounded animate-pulse" style="animation-delay:0.3s"></span>
              <span class="w-1 h-4 bg-gold-400 rounded animate-pulse" style="animation-delay:0.45s"></span>
            </span>
          </div>

          <div class="p-5 flex-1 flex flex-col">
            <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/70 capitalize" x-text="track.type"></p>
            <h3 class="font-serif text-xl text-beige-100 mt-1.5" x-text="track.title"></h3>
            <p class="text-sm text-beige-100/60 mt-2 leading-relaxed line-clamp-3" x-show="track.desc" x-text="track.desc"></p>
            <div class="mt-auto pt-4 flex items-center justify-between text-xs text-beige-100/40">
              <span x-text="`${Math.max(1, Math.round(track.duration / 60))} min`"></span>
              <template x-if="track.locked">
                <a :href="'<?= url('/public/membership.php') ?>'" class="text-gold-400 hover:text-gold-300">Upgrade →</a>
              </template>
              <template x-if="!track.locked">
                <button type="button" @click="playTrack(track.id)" class="text-gold-400/80 hover:text-gold-300">
                  <span x-show="!(playing && currentId === track.id)">Play →</span>
                  <span x-show="playing && currentId === track.id">Now playing</span>
                </button>
              </template>
            </div>
          </div>
        </article>
      </template>
    </div>

    <div x-show="visibleTracks.length === 0" class="mt-16 text-center border border-white/5 rounded-3xl p-16 bg-navy-900/40">
      <p class="font-serif text-2xl text-beige-100/80">Nothing matches that quiet search.</p>
      <p class="text-sm text-beige-100/50 mt-2">Try a different mood, or clear the filters.</p>
      <button type="button" @click="filterType='all'; filterDuration='all'; search=''" class="mt-6 px-5 py-2 rounded-full bg-gold-500/20 text-gold-400 hover:bg-gold-500/30 transition text-sm">Clear filters</button>
    </div>
  </section>

  <!-- Sticky mini-player -->
  <div x-show="currentId !== null" x-cloak x-transition.opacity
       class="fixed bottom-0 inset-x-0 z-40 border-t border-white/5 bg-navy-950/90 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
      <button @click="toggle()" class="h-12 w-12 rounded-full bg-gold-500 text-navy-950 flex items-center justify-center flex-shrink-0">
        <span x-show="!playing">▶</span>
        <span x-show="playing">❚❚</span>
      </button>
      <div class="min-w-0 flex-1">
        <p class="text-sm text-beige-100 truncate" x-text="currentTrack ? currentTrack.title : ''"></p>
        <div class="mt-1.5 h-1 bg-white/10 rounded-full overflow-hidden">
          <div class="h-full bg-gradient-to-r from-gold-500 to-gold-300" :style="`width: ${progress}%`"></div>
        </div>
        <div class="flex items-center justify-between text-[10px] text-beige-100/50 mt-1 uppercase tracking-widest">
          <span x-text="formatTime(currentTime)"></span>
          <span x-text="formatTime(durationSec)"></span>
        </div>
      </div>
      <button @click="stop()" class="text-beige-100/40 hover:text-beige-100 text-xs uppercase tracking-widest hidden sm:block">Close</button>
    </div>
  </div>
</div>

<script>
function library() {
  const opts = window.__SH_LIBRARY || { tracks: [] };
  return {
    tracks: opts.tracks || [],
    filterType: opts.initialType || 'all',
    filterDuration: 'all',
    search: '',

    audio: null,
    currentId: null,
    playing: false,
    progress: 0,
    currentTime: 0,
    durationSec: 0,

    init() {
      this.audio = new Audio();
      this.audio.preload = 'metadata';
      this.audio.addEventListener('timeupdate', () => {
        if (this.audio.duration) {
          this.progress = (this.audio.currentTime / this.audio.duration) * 100;
          this.currentTime = this.audio.currentTime;
          this.durationSec = this.audio.duration;
        }
      });
      this.audio.addEventListener('ended', () => { this.playing = false; this.progress = 0; });
      this.audio.addEventListener('pause', () => { this.playing = false; });
      this.audio.addEventListener('play',  () => { this.playing = true;  });

      if (opts.initialFeatured) {
        const t = this.tracks.find(x => x.id === opts.initialFeatured && !x.locked);
        if (t) {
          this.currentId = t.id;
          this.audio.src = t.src;
        }
      }
    },

    get currentTrack() {
      return this.tracks.find(t => t.id === this.currentId) || null;
    },

    get visibleTracks() {
      const q = this.search.trim().toLowerCase();
      return this.tracks.filter(t => {
        if (this.filterType !== 'all' && t.type !== this.filterType) return false;
        if (this.filterDuration === 'short'  && t.duration >= 5 * 60) return false;
        if (this.filterDuration === 'medium' && (t.duration < 5*60 || t.duration > 15*60)) return false;
        if (this.filterDuration === 'long'   && t.duration <= 15 * 60) return false;
        if (q && !t.title.toLowerCase().includes(q) && !t.desc.toLowerCase().includes(q)) return false;
        return true;
      });
    },

    playTrack(id) {
      const t = this.tracks.find(x => x.id === id);
      if (!t || t.locked) return;
      if (this.currentId === id) {
        this.toggle();
        return;
      }
      this.currentId = id;
      this.audio.src = t.src;
      this.audio.play().then(() => {
        this.playing = true;
        this.logPlay(id);
      }).catch(() => { this.playing = false; });
    },

    toggle() {
      if (!this.currentId) return;
      if (this.playing) { this.audio.pause(); }
      else { this.audio.play().then(() => this.playing = true).catch(() => {}); }
    },

    stop() {
      if (this.audio) { this.audio.pause(); this.audio.currentTime = 0; }
      this.currentId = null;
      this.playing = false;
      this.progress = 0;
    },

    formatTime(sec) {
      sec = Math.max(0, Math.floor(sec || 0));
      const m = Math.floor(sec / 60);
      const s = (sec % 60).toString().padStart(2, '0');
      return `${m}:${s}`;
    },

    logPlay(id) {
      fetch(opts.logUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': opts.csrf },
        body: JSON.stringify({ content_id: id, seconds: 0 }),
      }).catch(() => {});
    },
  };
}
</script>

<style>
@keyframes ping-slow { 0%, 60% { opacity: 0.6; transform: scale(1); } 100% { opacity: 0; transform: scale(1.4); } }
.animate-ping-slow { animation: ping-slow 2.4s ease-out infinite; }
[x-cloak] { display: none !important; }
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>
