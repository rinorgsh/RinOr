# Accountable

Mini app web de comptabilité personnelle. **Stack uniquement — aucune logique métier n'est encore implémentée.**

## Stack

| Couche | Choix | Version |
| --- | --- | --- |
| Backend | Laravel | 13.25 |
| Runtime | PHP | 8.3 |
| Pont serveur/client | Inertia.js (`inertiajs/inertia-laravel`) | 3.3 |
| Frontend | Vue 3 (`@inertiajs/vue3`) | 3.5 |
| CSS | Tailwind CSS (`@tailwindcss/vite`) | 4 |
| Build | Vite | 8 |
| Icônes | `@lucide/vue` | 1 |
| Base de données | SQLite (`database/database.sqlite`) | — |
| Tests | PHPUnit | — |

## Démarrer

```bash
composer dev     # serveur + vite + queue en une commande (php artisan dev)
```

Ou séparément :

```bash
php artisan serve
npm run dev
```

## Structure front

```
resources/js/
├── app.js          # point d'entrée Inertia
├── Pages/          # une page = une route Inertia (resolve via import.meta.glob)
├── Layouts/
├── Components/
└── Composables/
```

`resources/views/app.blade.php` est la vue racine montée par Inertia
(`HandleInertiaRequests::$rootView = 'app'`).

L'alias `@` pointe sur `resources/js`.

## Pas encore branché

Volontairement absent, à ajouter quand le besoin sera défini :

- authentification (pas de starter kit, pas de Breeze)
- Ziggy (`@routes`) pour les routes nommées côté Vue
- SSR Inertia
- modèles, migrations et écrans métier
