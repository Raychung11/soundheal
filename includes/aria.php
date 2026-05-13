<?php
declare(strict_types=1);

/**
 * Aria — Calm Wellness Concierge helpers.
 *
 *   ai_config()             Merges env defaults (config/ai.php) with
 *                           admin-managed site_settings (ai_*). Mirrors
 *                           payment_config()'s pattern so values can live
 *                           in either place; the DB wins when populated.
 *
 *   aria_system_prompt()    Builds the full system prompt for an OpenAI
 *                           call: persona + admin's editable prompt + a
 *                           live "Currently available" inventory
 *                           (active experiences, active class packs).
 *                           Refreshed on every call, so disabling an
 *                           experience in admin flows through to Aria
 *                           without redeploying.
 *
 *   aria_fallback()         The scripted reply used when no API key is
 *                           configured (or OpenAI errors). Also DB-aware
 *                           so it doesn't recommend offerings that have
 *                           been disabled.
 */

if (!function_exists('ai_config')) {

    function ai_config(): array
    {
        $env = $GLOBALS['ai_config'] ?? config('ai');

        $apiKey      = trim((string) setting('ai_openai_api_key', '')) ?: (string) ($env['openai']['api_key'] ?? '');
        $model       = trim((string) setting('ai_model', '')) ?: (string) ($env['openai']['model'] ?? 'gpt-4o-mini');
        $temperature = (string) setting('ai_temperature', '');
        if ($temperature === '') $temperature = '0.6';

        $personaName     = trim((string) setting('ai_persona_name', '')) ?: (string) ($env['persona']['name'] ?? 'Aria');
        $personaRole     = trim((string) setting('ai_persona_role', '')) ?: (string) ($env['persona']['role'] ?? 'Calm Wellness Concierge');
        $personaPrompt   = (string) setting('ai_system_prompt', '');     // empty handled by aria_system_prompt()
        $includeLive     = (bool) setting('ai_include_live_offerings', true);

        return [
            'provider' => $env['provider'] ?? 'openai',
            'openai'   => [
                'api_key' => $apiKey,
                'model'   => $model,
                'base_url' => (string) ($env['openai']['base_url'] ?? 'https://api.openai.com/v1'),
            ],
            'temperature' => (float) $temperature,
            'persona' => [
                'name'          => $personaName,
                'role'          => $personaRole,
                'system_prompt' => $personaPrompt,
            ],
            'include_live_offerings' => $includeLive,
        ];
    }

    /**
     * Build the full system prompt for an OpenAI chat call. Combines:
     *   - brand-aware preamble (persona name + role + brand)
     *   - admin-editable rules of conduct (settings.ai_system_prompt)
     *   - a "Currently available" inventory drawn from the DB
     *
     * The brand_name() reference means Aria automatically uses the live
     * brand (e.g. "jaemie sound bath"), not a baked-in string.
     */
    function aria_system_prompt(): string
    {
        $cfg   = ai_config();
        $brand = function_exists('brand_name') ? brand_name() : 'our sanctuary';
        $name  = $cfg['persona']['name'] ?: 'Aria';
        $role  = $cfg['persona']['role'] ?: 'Calm Wellness Concierge';

        $rules = trim((string) $cfg['persona']['system_prompt']);
        if ($rules === '') {
            $rules = "Voice: soft, reassuring, warm. Speak in short breathing sentences.\n"
                   . "Never give medical advice. Close wellbeing replies with a gentle disclaimer.";
        }

        $preamble = sprintf(
            "You are %s, the %s for %s.",
            $name, $role, $brand
        );

        $inventory = $cfg['include_live_offerings'] ? aria_live_offerings_block() : '';

        return trim(implode("\n\n", array_filter([$preamble, $rules, $inventory])));
    }

    /**
     * Returns a markdown-ish block listing active experiences and class
     * packs so Aria can recommend accurately. Empty string if nothing is
     * active (in which case the prompt simply omits the inventory).
     */
    function aria_live_offerings_block(): string
    {
        $lines = ['Currently available (only recommend from this list):'];

        try {
            $exps = db()->query(
                "SELECT title, duration, description FROM experiences
                  WHERE status = 'active' ORDER BY sort_order ASC, title ASC"
            )->fetchAll();
        } catch (Throwable $e) {
            $exps = [];
        }

        if ($exps) {
            $lines[] = '';
            $lines[] = 'Experiences (in-person sessions):';
            foreach ($exps as $exp) {
                $bits = ['- ' . trim((string) $exp['title'])];
                if (!empty($exp['duration'])) $bits[] = '(' . trim((string) $exp['duration']) . ')';
                $line = implode(' ', $bits);
                if (!empty($exp['description'])) {
                    $line .= ' — ' . trim((string) $exp['description']);
                }
                $lines[] = $line;
            }
        } else {
            $lines[] = '';
            $lines[] = 'Experiences: none scheduled right now. If a guest asks about in-person sessions, gently say new offerings are being prepared.';
        }

        try {
            $packs = function_exists('active_packs') ? active_packs() : [];
        } catch (Throwable $e) {
            $packs = [];
        }

        if ($packs) {
            $lines[] = '';
            $lines[] = 'Pricing — class packs:';
            foreach ($packs as $p) {
                $total  = (int) $p['paid_credits'] + (int) $p['bonus_credits'];
                $price  = function_exists('format_money') ? format_money((float) $p['price']) : ('RM ' . number_format((float) $p['price'], 2));
                $bonus  = (int) $p['bonus_credits'] > 0 ? sprintf(' (%d paid + %d bonus)', (int) $p['paid_credits'], (int) $p['bonus_credits']) : '';
                $valid  = (int) $p['validity_days'] > 0 ? sprintf(', %d-day validity', (int) $p['validity_days']) : '';
                $lines[] = sprintf('- %s — %s for %d credits%s%s. One credit per session.',
                    (string) $p['name'], $price, $total, $bonus, $valid);
            }
            $lines[] = '- Single offline classes can also be booked at the per-session price shown on each event page.';
        }

        return implode("\n", $lines);
    }

    /**
     * Soft scripted reply used when no API key is set or OpenAI errors.
     * Picks the first active experience so we never recommend something
     * the admin has disabled.
     */
    function aria_fallback(string $message): string
    {
        try {
            $first = db()->query(
                "SELECT title, description FROM experiences
                  WHERE status = 'active' ORDER BY sort_order ASC, title ASC LIMIT 1"
            )->fetch();
        } catch (Throwable $e) {
            $first = null;
        }

        $opening = "Thank you for sharing that with me. Take a slow breath in… and out.";
        if ($first) {
            $suggestion = sprintf("If you're looking for stillness, our %s is a gentle place to begin.",
                (string) $first['title']);
        } else {
            $suggestion = "We're between offerings just now — please check the experiences page in a few days, or leave us a quiet note via the contact form.";
        }
        $disclaimer = "This is not medical advice. Please consult qualified professionals for medical or mental health concerns.";

        return $opening . "\n\n" . $suggestion . "\n\n" . $disclaimer;
    }
}
