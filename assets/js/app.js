// SoundHeal global JS hooks. Kept intentionally minimal — pages use Alpine.js inline.

document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss flash messages after 6 seconds.
    document.querySelectorAll('.flash').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-6px)';
            setTimeout(() => el.remove(), 700);
        }, 6000);
    });
});
