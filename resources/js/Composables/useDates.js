const dayMonth = new Intl.DateTimeFormat('fr-BE', { day: '2-digit', month: 'short' });
const full = new Intl.DateTimeFormat('fr-BE', { day: '2-digit', month: 'long', year: 'numeric' });

/** Les dates arrivent en 'YYYY-MM-DD' : on les parse en local, jamais en UTC. */
function toDate(iso) {
    if (!iso) return null;
    const [y, m, d] = String(iso).split('-').map(Number);

    return Number.isFinite(y) ? new Date(y, m - 1, d) : null;
}

export function formatDay(iso) {
    const d = toDate(iso);

    return d ? dayMonth.format(d) : '';
}

export function formatFull(iso) {
    const d = toDate(iso);

    return d ? full.format(d) : '';
}

/** « dans 12 jours », « il y a 3 jours », « aujourd'hui ». */
export function relativeDays(iso) {
    const d = toDate(iso);
    if (!d) return '';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const days = Math.round((d - today) / 86400000);

    if (days === 0) return "aujourd'hui";
    if (days === 1) return 'demain';
    if (days === -1) return 'hier';

    return days > 0 ? `dans ${days} jours` : `il y a ${Math.abs(days)} jours`;
}

export function useDates() {
    return { formatDay, formatFull, relativeDays };
}
