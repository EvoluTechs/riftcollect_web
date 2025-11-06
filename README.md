# RiftCollect

RiftCollect est une application web (PHP + JS) pour la communauté francophone des collectionneurs du TCG Riftbound.

Fonctionnalités incluses:
- Parcourir la base des cartes (via proxy serveur + cache local)
- Gérer sa collection (quantités par carte)
- Statistiques (globales, par rareté, par set)
- Actus: extensions synchronisées depuis l'API
- Authentification email/mot de passe (sessions PHP)

Technos:
- Backend: PHP 8+, PDO (MySQL par défaut; SQLite possible), cURL
- Frontend: HTML5, Bootstrap 5, Vanilla JS (SPA avec hash routing)
- Déploiement: hébergement mutualisé OVH (copie de fichiers)

## Configuration

Renommez/éditez `inc/Config.php` si besoin ou utilisez des variables d'environnement côté OVH:

- `RIFT_API_BASE`: URL de l'API officielle Riftbound (ex: `https://api.riftbound.example.com/v1`)
- `RIFT_API_KEY`: Clé API si nécessaire (opcional)
- `RC_CDN_IMAGES`: Liste d'URLs d'images publiques (séparées par des virgules) utilisées en secours sur la page d'accueil si aucun visuel n'est présent dans `assets/img/riftbound/`.
	- Exemple: `https://cdn.rgpub.io/public/live/map/riftbound/latest/OGN/cards/OGN-310/full-desktop.jpg`
- `RC_DB_DRIVER`: `mysql` (défaut) ou `sqlite`
- `RC_SQLITE_FILE`: Chemin du fichier SQLite (défaut: `storage/riftcollect.sqlite`)
- `RC_MYSQL_HOST`, `RC_MYSQL_DB`, `RC_MYSQL_USER`, `RC_MYSQL_PASS`
- `RC_DB_PREFIX`: Préfixe des tables (défaut: `riftcollect_`)

Par défaut, l'app est configurée pour se connecter à une base MySQL OVH avec le préfixe `riftcollect_`. Vous pouvez surcharger ces valeurs via les variables d'environnement ci‑dessus.

Le dossier `storage/` doit être inscriptible par PHP (OVH: droits 705/775 selon offre).

## Déploiement OVH

1. Uploadez tous les fichiers dans le dossier `www/` de votre hébergement.
2. Assurez-vous que `storage/` est inscriptible (CHMOD 705/775) et reste non-accessible au web (un `.htaccess` est fourni).
3. Base de données MySQL: créez la base/n-usr OVH et, si besoin, configurez les variables:
	- RC_DB_DRIVER = mysql
	- RC_MYSQL_HOST, RC_MYSQL_DB, RC_MYSQL_USER, RC_MYSQL_PASS
	- RC_DB_PREFIX = riftcollect_
	Par défaut, l’app est déjà configurée pour l’accès OVH fourni et le préfixe `riftcollect_`.
4. Les tables seront créées automatiquement au premier appel (API/cron) si absentes.
5. Planifiez une tâche cron OVH pour appeler `/cron/check_updates.php` quotidiennement (maj des extensions).
6. Pour peupler les cartes sans API officielle, utilisez `/cron/scan_cdn_cards.php`.

### Vérification de la connexion BDD

- `api.php?action=db.health` — écrit/relit/supprime un enregistrement de test dans `riftcollect_translations` et renvoie l’état de la connexion.

## Endpoints API (simples)

- `api.php?action=health`
- `api.php?action=db.health`
- `api.php?action=register` (POST: email, password)
- `api.php?action=login` (POST: email, password)
- `api.php?action=logout`
- `api.php?action=me`
- `api.php?action=cards.list&q=&rarity=&set=&page=1&pageSize=30`
- `api.php?action=cards.detail&id=...`
- `api.php?action=cards.refresh`
- `api.php?action=collection.get`
- `api.php?action=collection.set` (POST: card_id, qty)
- `api.php?action=collection.bulkSet` (POST JSON: [{card_id, qty}])
- `api.php?action=stats.progress`
- `api.php?action=expansions.list`
- `api.php?action=subscribe` (POST: enabled=1|0)

## Scan CDN (sans API)

Script: `cron/scan_cdn_cards.php`

Paramètres via variables d'environnement OVH ou query string:
- `RC_CDN_BASE` (defaut: `https://cdn.rgpub.io/public/live/map/riftbound/latest`)
- `RC_CDN_SETS` (defaut: `OGN`) — liste séparée par virgules
- `RC_CDN_RANGE` (defaut: `1-500`) — ex: `1-300`, `1,2,10-20`
- `RC_CDN_EXT` (defaut: `full-desktop.jpg`)
- `RC_CDN_DELAY_MS` (defaut: `100`) — délai entre requêtes

Exemples:
- Navigateur: `/cron/scan_cdn_cards.php?sets=OGN&range=1-400`
- Cron OVH: toutes les nuits, avec env `RC_CDN_SETS=OGN` et `RC_CDN_RANGE=1-400`

Le script fait des HEAD sur les URLs du CDN et insère dans `cards_cache` les cartes trouvées (id=`SET-###`, image_url, set_code). Les métadonnées (nom, rareté) restent vides tant que l'API officielle n'est pas branchée.

## Migration de SQLite vers MySQL

Si vous avez déjà des données dans `storage/riftcollect.sqlite`, vous pouvez les migrer vers MySQL:

- Navigateur: `/cron/migrate_sqlite_to_mysql.php?src=storage/riftcollect.sqlite&dry=0`
- CLI: `php cron/migrate_sqlite_to_mysql.php src=storage/riftcollect.sqlite dry=0`

Options:
- `src`: chemin du fichier SQLite (défaut `storage/riftcollect.sqlite`)
- `dry`: 1 = simulation (aucune écriture)
- `limit`: nombre max de lignes par table (0 = sans limite)
- `tables`: liste de tables à migrer, ex: `users,cards_cache,collections`

Notes:
- Le script détecte les tables source avec ou sans préfixe.
- Les tables cibles sont celles préfixées (`riftcollect_*`) et sont créées automatiquement si absentes.

## Notes

- L'API Riftbound réelle n'étant pas documentée ici, les points d'accès et schémas sont des placeholders. Adaptez `Config::$API_BASE_URL` et les mappings dans `Database::syncCardsFromApi()` / `syncExpansionsFromApi()` selon la vraie API.
- La page d'accueil peut utiliser des visuels CDN via `RC_CDN_IMAGES` le temps d'obtenir une clé API, ou si vous ne souhaitez pas stocker d'images localement.
- Les mots de passe sont hashés (`password_hash`). Aucune réinitialisation par email n'est fournie par défaut.
- CORS: tout passe via `api.php` pour éviter les problèmes CORS et cacher la clé API.

## Développement local (optionnel)

- Placez ce dossier dans un serveur PHP local (WAMP/XAMPP) ou utilisez `php -S localhost:8000` dans le dossier (si disponible).

 
