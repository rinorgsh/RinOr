# RinOr

> Là où part ton or.

Mini app web de comptabilité personnelle. Le nom est le prénom : **Rin** + **or**.

## Ce qu'elle fait

| Menu | Rôle |
| --- | --- |
| **Tableau** | Rentrées et sorties du mois, tendance 6 mois, où part l'argent, ce qui rapporte le plus, prochains prélèvements, tâches en attente |
| **Dépenses** | Journal daté, une ligne par achat, groupé par jour |
| **Rentrées** | Journal daté de tout ce qui entre |
| **Factures** | Ce qu'on te doit : statut, échéance, retard. Encaisser crée la rentrée |
| **Abonnements** | Ce qui se prélève seul, mensuel ou annuel, avec la charge fixe mensualisée |
| **Caisses** | Argent mis de côté : on y met en disant d'où ça vient, on en sort en disant pourquoi |
| **À faire** | Tâches avec statut (à faire → en cours → terminé) et priorité |
| **Catégories** | Pour répondre à « dans quoi part mon argent » et « d'où il vient » |

## Deux règles structurantes

**Les montants sont stockés en centimes entiers** (`amount_cents`), jamais en
flottants : additionner des `float` sur de l'argent finit toujours par produire
un écart d'un centime. Le trait `App\Concerns\HasAmount` expose un attribut
`amount` en euros pour les formulaires et l'affichage.

**Une facture encaissée crée sa rentrée.** Marquer « payée » génère
automatiquement l'écriture correspondante (montant TTC), reliée par
`incomes.invoice_id`. Rouvrir la facture la retire. Sans ce lien il faudrait
ressaisir le montant dans Rentrées, et cette double saisie est exactement ce
qu'on finit par ne plus faire.

**Les abonnements ne créent pas d'écritures de dépense.** Ils sont comptés à
part comme *charge fixe*, ramenés au mois (un annuel compte pour 1/12). Le
total des sorties du mois = dépenses saisies + charge fixe. C'est ce qui permet
de voir séparément ce qui est subi et ce qui est décidé.

## Stack

Laravel 13 · Inertia 3 · Vue 3.5 · Tailwind 4 · Vite 8 · SQLite · PHPUnit

Polices : Instrument Serif (titres) · Instrument Sans (UI) · IBM Plex Mono
(montants, chiffres tabulaires alignés).

## Design

Direction « ledger chaud » : surfaces noir chaud et bone, neutres sable,
titres en serif éditorial. **La couleur est réservée au sens** — sens de
l'argent, séries de graphique, statut de tâche — jamais décorative. L'ambre
du logotype est la seule exception, et c'est de l'identité.

Les palettes de graphique sont validées par script (bandes de luminosité,
plancher de chroma, séparation daltonienne, contraste) dans les deux modes.
Thème clair/sombre : `data-theme` est stampé sur `<html>` avant le premier
paint, donc pas de flash et une seule source de vérité pour CSS et Tailwind.

Mobile d'abord : barre d'onglets en bas dans la zone du pouce, bouton flottant
d'ajout, formulaires en feuille qui monte, encoche iOS gérée, champs à 16px
pour éviter le zoom automatique.

## Démarrer

```bash
composer dev      # serveur + vite (php artisan dev)
```

## Données

```bash
php artisan migrate:fresh --force
php artisan app:create-user               # AVANT le seeder : il attribue les données à un compte
php artisan db:seed --force               # catégories, abonnements, rentrées, caisses, tâches
php artisan db:seed --class=DemoSeeder    # + dépenses fictives, pour voir le tableau peuplé
php artisan app:clear-demo                # retire uniquement les données [démo]
```

L'ordre compte : le seeder attribue tout au premier compte, il ne fait donc
rien s'il n'y en a aucun.

Le seeder principal contient de **vraies** données reprises de l'ancien Excel :
20 abonnements (montants TTC), les rentrées encaissées en 2026, deux caisses
(dont une réserve TVA) et six tâches issues des anomalies repérées.

## Accès et cloisonnement

**Chaque compte ne voit que ses propres données.** Les sept tables portent un
`user_id`, et le trait `App\Concerns\BelongsToUser` applique deux garanties :

1. Un **scope global** filtre toute requête sur l'utilisateur connecté. C'est
   une liste blanche : on n'écrit jamais `where('user_id', ...)` dans un
   contrôleur, parce qu'il suffit de l'oublier une fois pour exposer les
   finances de quelqu'un d'autre. La liaison implicite de route en hérite : l'id
   d'un autre utilisateur renvoie 404, pas 403.
2. `user_id` est **imposé à la création**, jamais lu depuis le formulaire.

Attention aux règles de validation `unique` et `exists` : elles interrogent la
table directement et **échappent au scope**. Elles sont explicitement cadrées
sur `user_id` dans `CategoryController` (unicité du nom par compte) et partout
où `category_id` est validé (sinon on rattache une écriture à la catégorie
d'autrui en devinant son id). `IsolationTest` verrouille ces deux cas.

### Inscription

Ouverte sur `/inscription`, **compte actif immédiatement — pas de vérification
par e-mail**. Mot de passe : 12 caractères minimum, confirmé. Le compte et ses
14 catégories de départ sont créés dans la même transaction, donc un échec ne
laisse jamais de compte à moitié créé.

Deux conséquences de l'absence de vérification, à connaître :

- **L'adresse n'est jamais prouvée.** Un compte peut être créé avec l'adresse
  de quelqu'un d'autre. Si une réinitialisation de mot de passe par e-mail est
  ajoutée un jour, il faudra la cadrer.
- **La limitation de débit est la seule barrière** contre la création en masse :
  5 inscriptions par heure et par IP (`throttle:5,60` sur la route POST).

Le premier compte, ou un compte administrateur, peut aussi se créer hors ligne :

```bash
php artisan app:create-user                       # interactif
echo 'mot-de-passe' | php artisan app:create-user --email=toi@exemple.be
```

Le mot de passe se lit sur stdin en non-interactif plutôt qu'en argument : un
argument atterrirait dans l'historique du shell et dans la liste des processus.

Toutes les routes sont derrière `auth`. Les tentatives de connexion sont
limitées à 5 par minute et par couple e-mail + IP, et le message d'erreur est
identique que l'adresse existe ou non.

### Reprise de données existantes

Si la migration de cloisonnement a tourné avant qu'un compte existe, les lignes
sont orphelines et invisibles. Une fois le compte créé :

```bash
php artisan app:claim-data toi@exemple.be
```

Les catégories orphelines qui portent le même nom qu'une catégorie par défaut
sont **fusionnées** : les écritures sont repointées, le doublon disparaît.

## Installation sur l'écran d'accueil (PWA)

L'app s'installe et s'ouvre en plein écran, sans barre d'adresse.

Ce qui est en place : `manifest.webmanifest` (`display: standalone`), icônes
192/512 + une **maskable** pour Android, `apple-touch-icon` 180 (iOS ignore les
icônes du manifeste), `theme-color` clair et sombre pour la barre d'état,
`viewport-fit=cover` + retraits `env(safe-area-inset-*)`, rebond élastique
désactivé, champs à 16px pour éviter le zoom au focus, raccourcis d'appui long
(Dépense / Rentrée / Caisses).

Un bandeau explique le geste au premier lancement : bouton d'installation réel
sur Android (`beforeinstallprompt`), instructions Partager → « Sur l'écran
d'accueil » sur iOS, qui n'a pas d'API d'installation.

**Pas de service worker.** L'app exige donc le réseau : c'est un choix assumé
pour garder le code simple. Conséquence à connaître : Chrome sur Android ne
proposera pas la bannière d'installation native sans service worker (le bouton
du bandeau, lui, fonctionne). Sur iOS, aucun impact.

### Déployer sur Forge

Trois pièges, tous liés au `.gitignore` :

1. `public/build` n'est pas versionné → le build tourne sur le serveur.
2. `database/database.sqlite` n'est pas versionné → il faut le créer au premier
   déploiement (et il survit aux suivants).
3. Sans HTTPS, pas d'installation propre → SSL → Let's Encrypt dans Forge.

**Avant le premier déploiement**, authentifie Composer sur le serveur. Sans ça,
il télécharge les archives depuis `codeload.github.com` de façon anonyme et
GitHub finit par répondre `HTTP 429` au milieu de l'installation.

Forge lie un `auth.json` dans chaque release (« Linking auth.json file » dans le
log). C'est là qu'il faut mettre le token — à la racine du site, pas dans une
release :

```bash
# /home/forge/rinor.on-forge.com/auth.json
{
    "github-oauth": {
        "github.com": "TON_TOKEN"
    }
}
```

Un token *classic* **sans aucun scope** suffit : pour télécharger des paquets
publics, il ne sert qu'à identifier l'appelant et lever le plafond (60 → 5 000
requêtes/heure). Ne lui donne pas `repo`.

Vérification :

```bash
composer diagnose | grep -i github        # doit signaler un token présent
```

Et pour ne pas taper le plafond en rafale :

```bash
echo 'COMPOSER_MAX_PARALLEL_HTTP=6' | sudo tee -a /etc/environment
```

### SQLite et le déploiement « zero downtime »

**À faire avant le premier déploiement réussi, sinon tu perds tes données.**

En mode zero downtime, Forge crée un dossier par release
(`.../releases/75612865`) et fait pointer un lien symbolique dessus. Tout
fichier écrit dans le dossier de l'application disparaît donc au déploiement
suivant : un `touch database/database.sqlite` recréerait une base **vide** à
chaque déploiement.

La base doit vivre hors des releases :

```bash
mkdir -p /home/forge/rinor.on-forge.com/shared
touch /home/forge/rinor.on-forge.com/shared/database.sqlite
chmod 664 /home/forge/rinor.on-forge.com/shared/database.sqlite
```

Puis, dans le `.env` du site :

```dotenv
DB_DATABASE=/home/forge/rinor.on-forge.com/shared/database.sqlite
```

Laravel lit `env('DB_DATABASE', database_path('database.sqlite'))` : un chemin
absolu court-circuite entièrement le dossier de release. Le script de
déploiement n'a alors plus rien à faire côté fichier de base.

(MySQL, que Forge provisionne par défaut, évite ce piège par construction. Si
tu préfères, c'est un changement de `DB_*` et rien d'autre.)

### Script de déploiement

```bash
cd $FORGE_SITE_PATH

# Pas de --prefer-dist : c'est lui qui interdit à Composer de se rabattre sur
# un git clone quand le téléchargement d'une archive échoue. Le script par
# défaut de Forge le met, et transforme un HTTP 429 passager en déploiement
# raté.
composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force

# public/build n'est pas versionné : sans ces deux lignes, le site se charge
# sans CSS ni JS, ce qui ressemble à un bug de l'app alors qu'il ne manque que
# le build.
npm ci
npm run build

php artisan optimize                    # config + routes + vues en cache
$FORGE_PHP artisan queue:restart
```

En zero downtime, Forge fait lui-même le `git clone` de la release : pas de
`git pull` dans le script.

`.env` de production :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ton-domaine.be          # sert à générer les URLs d'assets
SESSION_SECURE_COOKIE=true              # le cookie ne part qu'en HTTPS
DB_CONNECTION=sqlite
```

Puis, une seule fois : `php artisan app:create-user`.

SQLite suffit largement pour un seul utilisateur. Si tu préfères MySQL (Forge
en provisionne un par défaut), c'est un changement de `DB_*` et rien d'autre.

## Tests

```bash
php artisan test        # 59 tests
```

`IsolationTest` décrit huit manières concrètes dont les finances d'un compte
pourraient fuiter chez un autre : listes, liaison de route en lecture et en
écriture, `category_id` emprunté, `user_id` injecté par le formulaire, totaux du
tableau de bord, unicité des catégories, suppression en cascade.

Le reste couvre : chaque page derrière `auth`, l'absence de route d'inscription,
l'identité des messages d'erreur de connexion, la limitation des tentatives, le
manifeste et les dimensions réelles de chaque icône déclarée, l'arithmétique en
centimes, l'impossibilité de vider une caisse au-delà de son solde, la survie
des écritures à la suppression d'une catégorie, et un mois de dashboard vide ou
avec un paramètre `?month=` bricolé.

## Pas encore branché

- Rentrées récurrentes (un salaire se ressaisit chaque mois à la main).
- Entité « client » et marge par client — aujourd'hui le client est un simple
  libellé sur la facture, avec autocomplétion.
- TVA trimestrielle : le montant collecté est affiché, mais rien ne calcule
  encore ce qu'il faut provisionner avant chaque échéance.
- Saisie hors ligne avec synchronisation différée.
- Export CSV / clôture annuelle.
- 2FA — le mot de passe est aujourd'hui la seule barrière.
- Vérification d'e-mail et réinitialisation de mot de passe.
- **Sauvegardes.** Le SQLite est un fichier unique et n'a aucune stratégie de
  sauvegarde. À traiter avant d'ouvrir l'inscription à d'autres personnes : tu
  deviens responsable de traitement RGPD dès que tu héberges leurs finances.
- Ziggy : les URLs sont partagées via Inertia (`nav`) plutôt que codées en dur.
