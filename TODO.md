# Reste à faire — portage de `gingerminds/laravel-media-manager`

Point au 8 octobre 2026 (étapes 5c à 9 commitées). Branche `build`.

## Où on en est

| Étape | Contenu | État |
|---|---|---|
| 0 | Squelette du bundle, application de test, CI | ✅ commité |
| 1 | Entité `File`, stockage Flysystem (disques nommés), `FileStorage`, `PathGuard`, `MimeTypeNormalizer` | ✅ commité |
| 2 | Presets Glide, `GET /api/files/{id}` et `/{preset}`, cache, `gingerminds:media:cache:clear` | ✅ commité |
| 3 | `MediaCategory` : arbre, CRUD admin avec réordonnancement, API (`/tree` compris) | ✅ commité |
| 4 | `FileLibrary` et registre des références (`FileReferenceRegistry`) | ✅ commité |
| 5a | Page « Bibliothèque de fichiers », endpoints JSON, assets admin (slot `head` du core 1.6) | ✅ commité |
| 5b | `FilePickerType` et modale de sélection | ✅ commité |
| 5c | Sélection, déplacement, renommage et suppression de dossiers | ✅ commité |
| 6 | Media | ✅ commité |
| 7 | Sélection de medias (`media-select`) | ✅ commité |
| 8 | Panier (basket) | ✅ commité |
| 9 | Commandes de maintenance | ✅ commité |
| 10 | Documentation | à faire |

---

## Étape 5c — Dossiers : sélection, déplacement, renommage, suppression (commitée)

- `FileLibrary::moveDirectory($path, $parent, $name)` couvre le renommage (même parent) et le déplacement :
  - chaque fichier passe par `relocate()` (fichier physique, `path`, purge Glide, remise en place si l'enregistrement échoue) ;
  - les sous-dossiers vides sont recréés et l'ancien dossier est supprimé ;
  - les références ne bougent pas : elles pointent vers l'UUID, jamais vers le chemin.
- Règles :
  - refus d'un déplacement dans le dossier lui-même ou dans un de ses descendants ;
  - refus si la cible existe déjà (pas de fusion) ;
  - la racine ne se déplace pas ;
  - disque par défaut seulement (les dossiers n'existent que là).
- Volume : `library.max_directory_move` (1 000 par défaut, 0 sans limite). Au-delà, l'admin renvoie vers `gingerminds:media:directory:move <path> [<parent>] [--name=]`.
- Admin :
  - des cases à cocher sur les dossiers ; on sélectionne soit des dossiers, soit des fichiers, jamais les deux ;
  - avec des dossiers cochés, la barre propose Renommer (un seul dossier), Déplacer et Supprimer (dossiers vides seulement) à la place des actions de fichiers ;
  - plus de boutons sur le dossier courant ;
  - droit `edit files`, rien dans la modale du sélecteur.
- Points de doc : un `start_path` ou un `LibraryStartPathProviderInterface` qui vise un dossier par son nom ne le suit pas. Penser plus tard aux dossiers par site (multisite).
- Tests : renommage, déplacement, cycles, conflit, retour arrière, plafond.

## Étape 6 — Media (commitée)

- Contenu :
  - entité `Media`, avec `code` obligatoire et unique (ajouté par rapport au Laravel) ;
  - CRUD admin ;
  - API `/api/media` ;
  - `--media` sur `cache:clear` ;
  - refus de supprimer une catégorie qui contient des medias.
- Choix :
  - la catégorie est optionnelle ;
  - aucune colonne historique (`file_name`, `mime_type` et `size` ont déjà été supprimées par le Laravel) ;
  - `file` vaut toujours l'UUID.
- Corrigé en même temps :
  - la recherche de la bibliothèque ne porte que sur le nom des fichiers, et pas les dossiers ;
  - le filtre de type de la modale est limité aux types de `accept`.
- À vérifier dans le skeleton (migration `Version20261007150534`) :
  - vrai formulaire avec le sélecteur ;
  - suppression bloquée d'un fichier utilisé ;
  - fusion de doublons.
- Le groupe `basket:read` sur les champs du media est reporté à l'étape 8.

## Étape 7 — Sélection de medias (commitée)

- `MediaSelectType` :
  - options `multiple`, `as_id`, `categories` (codes, sous-catégories comprises ; un seul code verrouille le filtre), `per_page`, `collection` + `link_factory` ;
  - le widget reprend les cartes du sélecteur de fichiers.
- Modale partagée `GET /admin/medias/picker`, alimentée par `GET /admin/medias/search` (endpoint admin, pas l'API publique).
- Collections ordonnées :
  - `AbstractMediaLink` (`media` en RESTRICT, `collection`, `position`), étendu par le projet ;
  - `MediaCollectionSyncer` (chaîne ou enum).
- Suppression d'un media utilisé refusée (`MediaUsageCounter`, qui compte les associations Doctrine vers `MediaInterface`).
- Pas d'indication de langue (propre aux projets Laravel) : ajouter un point d'extension si un projet en a besoin.
- Testée dans le skeleton avec un formulaire de démonstration temporaire sur le dashboard (retiré depuis).

## Étape 8 — Panier (commitée)

- Tout le panier est dans `src/Basket`, hors de `src/Entity` : API Platform scanne ce dossier même quand la fonctionnalité est désactivée.
- Propriétaire : l'utilisateur du core (`owner_id`, `CASCADE`).
- ZIP : noms d'origine, « nom (2).ext » en cas de doublon.
- Durée de vie : `basket.ttl` (30 jours) pour les paniers invités seulement, et purge par `gingerminds:media:basket:purge`.
- Comportements repris du Laravel :
  - `POST /baskets` connecté remplace le panier de l'utilisateur ;
  - le téléchargement supprime le panier.
- `basket.enabled: false` vérifié par un test dans un environnement dédié (`test_no_basket`).

## Étape 9 — Commandes de maintenance (commitée)

- Commandes `gingerminds:media:files:hash`, `index`, `deduplicate`, `relocate` et `orphans` ; leur logique est dans `src/File/Maintenance`.
- `index` indexe tous les fichiers, quel que soit leur type.
- `relocate` traite tous les disques.
- Le filtre « Doublons » de la bibliothèque devient utile après `files:hash` sur une base importée.

## Étape 10 — Documentation

Pages à écrire : configuration, services, composants (types de formulaire, widget), bibliothèque de fichiers, migration depuis Laravel. Points à ne pas oublier :

- Une entité `File` surchargée doit redéclarer `#[ORM\Index(name: 'files_hash_index', columns: ['hash'])]` et son `#[ApiResource]` : Doctrine ignore les index d'une MappedSuperclass.
- `files_rate_limit` n'accepte pas de placeholder d'environnement. `%env()%` fonctionne pour `storage.default_disk`, `library.root` et `library.max_upload_size`.
- `images.driver: gd` nécessite le support WebP de GD (`default_format: webp`).
- Une base Laravel avec des codes de catégorie en double doit être nettoyée avant import (`code` est unique).
- Les medias Laravel sans `file_id` doivent être supprimés ou complétés avant import (`file_id` est obligatoire).
- Les medias Laravel n'ont pas de `code` (obligatoire et unique) : en générer un à l'import (par exemple `media-{id}`).
- Ne jamais mettre `onDelete: CASCADE` sur une relation vers `files`.
- Les références stockées en JSON n'ont pas d'intégrité en base. Leur recherche par `LIKE` ne fonctionne pas sur un `jsonb` Postgres.
- Le droit `delete files` est créé par `gingerminds:permissions:sync` mais n'est pas utilisé : toutes les actions d'écriture relèvent de `edit files`.
- `FileLibraryController` (lectures) est surchargeable via `resources.file.controller`. Les écritures se changent en décorant le service `gingerminds_media_manager.controller.admin.file_library_action`.
- `LibraryStartPathProviderInterface` : aliaser l'interface pour changer le dossier d'ouverture.
- Déplacer ou renommer un dossier : un `start_path` ou un `LibraryStartPathProviderInterface` qui vise ce dossier par son nom ne le suit pas. Au-delà de `library.max_directory_move`, passer par la commande.
- Assets : rien à ajouter dans le projet. Le slot `admin_includes.head` du core charge la CSS et les contrôleurs, et `RelativeImportCompiler` rend les imports relatifs du bundle compatibles avec l'importmap.
- `FilePickerType` : options, exemples (`accept`, `multiple`, `as_id` pour les champs JSON), événement `gm-file-picker:change`.
- `MediaSelectType` et collections de medias :
  - exemple d'entité de lien (`AbstractMediaLink`, relation propriétaire en `CASCADE`, `cascade: ['persist']` et `orphanRemoval` côté entité) ;
  - options `collection` et `link_factory` ;
  - une relation directe vers `MediaInterface` doit être en `RESTRICT`.
- Panier :
  - API ;
  - règles d'accès ;
  - `claim_strategy` ;
  - `ttl` et purge (cron) ;
  - import Laravel : `owner_type` de l'utilisateur vers `owner_id`, les autres propriétaires ignorés ;
  - `basket.enabled: false`.
- `README.md` à jour (dépendances, installation, migrations).
- Import d'une base Laravel, dans l'ordre :
  1. déclarer les disques Laravel ;
  2. nettoyer les codes de catégorie en double et les medias sans fichier, donner un `code` aux medias ;
  3. lancer `files:relocate`, `files:hash`, `files:deduplicate`, puis `files:orphans`.

## Plus tard

- **Dossiers par site** (après la migration, quand `gingerminds/symfony-multisite` sera installé dans le skeleton) :
  - brancher `LibraryStartPathProviderInterface` sur le site courant ;
  - voir s'il faut confiner la bibliothèque et le sélecteur au dossier du site.
- **Option `reject` du `FilePickerType`** (liste noire de types) : seulement si un vrai besoin apparaît, la liste blanche `accept` est la règle.
- **Première ouverture d'un dossier d'images** : génération Glide des miniatures (≈ 5,6 s pour 48 images au premier affichage, 0,3 s ensuite). Piste possible : générer `thumbnail` à l'upload, ou prévoir une commande de préchauffage.

## Skeleton (`gingerminds-symfony-skeleton`)

- Données du stress test à supprimer quand tes vérifications seront finies :
  - dossier `stress-test/` (162 dossiers, 905 fichiers) ;
  - « image picker.png » à la racine et « image de test.png » dans `stress-test/pagination`.
- Modifications locales non commitées : `composer.json` / `composer.lock` (dépôt local du bundle, core 1.6), `config/bundles.php`, `config/packages/flysystem.yaml`, `config/routes/gingerminds_media_manager.yaml`, migration `Version20261007091812`.

## Hors périmètre

- Projet YANMAR (reste en Laravel).
- `gingerminds/laravel-cms` et son futur portage (dépendra de multisite).
