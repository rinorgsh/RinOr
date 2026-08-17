const eur = new Intl.NumberFormat('fr-BE', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const eurCompact = new Intl.NumberFormat('fr-BE', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

/**
 * Les montants circulent en centimes entiers depuis le serveur : le formatage
 * est le seul endroit où l'on repasse en euros.
 */
export function formatCents(cents, { compact = false } = {}) {
    const value = (Number(cents) || 0) / 100;

    return compact ? eurCompact.format(value) : eur.format(value);
}

/** Montant signé, pour un solde ou un delta. */
export function formatSignedCents(cents, options = {}) {
    const n = Number(cents) || 0;
    const sign = n > 0 ? '+' : n < 0 ? '−' : '';

    return sign + formatCents(Math.abs(n), options);
}

/** Convertit une saisie utilisateur ("12,50") en nombre exploitable. */
export function parseAmount(input) {
    if (input === null || input === undefined || input === '') {
        return '';
    }

    const cleaned = String(input).replace(/\s/g, '').replace(',', '.');
    const n = Number(cleaned);

    return Number.isFinite(n) ? n : '';
}

export function centsToInput(cents) {
    return ((Number(cents) || 0) / 100).toFixed(2);
}

export function useMoney() {
    return { formatCents, formatSignedCents, parseAmount, centsToInput };
}
