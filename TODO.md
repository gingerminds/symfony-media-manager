# Reste à faire — portage de `gingerminds/laravel-media-manager`

Point au 8 octobre 2026 (étape 6 commitée). Branche `build`.

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
| 5c | Déplacer et renommer un dossier | à faire |
| 6 | Media | ✅ commité |
| 7 | Sélection de medias (`media-select`) | à faire |
| 8 | Panier (basket) | à faire |
| 9 | Commandes de maintenance | à faire |
| 10 | Documentation | à faire |

---

## Étape 5c — Déplacer et renommer un dossier

- `FileLibrary::moveDirectory($path, $parent, $name)` couvre le renommage (même parent) et le déplacement :
  - chaque fichier passe par `relocate()` (fichier physique, `path`, purge Glide, remise en place si l'enregistrement échoue) ;
  - les sous-dossiers vides sont recréés et l'ancien dossier est supprimé ;
  - les références ne bougent pas : elles pointent vers l'UUID, jamais vers le chemin.
- Règles :
  - refus d'un déplacement dans le dossier lui-même ou dans un de ses descendants ;
  - refus si la cible existe déjà (pas de fusion) ;
  - la racine ne se déplace pas ;
  - disque par défaut seulement (les dossiers n'existent que là).
- Volume : plafond configurable du nombre de fichiers par requête (par exemple 1 000). Au-delà, renvoyer vers une commande console.
- Admin : « Renommer » et « Déplacer » sur le dossier courant, à côté de « Supprimer le dossier ». Le déplacement réutilise la boîte de dialogue avec l'arbre. Droit `edit files`, rien dans la modale du sélecteur.
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

## Étape 7 — Sélection de medias (`media-select`)

- Équivalent du composant Laravel `media-select` (`resources/js/components/media-select/`) : choisir des **medias** (pas des fichiers) dans un formulaire, par exemple un `MediaSelectType`.
- Équivalent de `MediaCollectionSyncer` : synchronisation d'une relation many-to-many de medias par « collection » (colonne de collection dans la table pivot, enum ou chaîne).
- Réutiliser au maximum le widget et la modale de la 5b, ou une liste de medias filtrable par catégorie : à concevoir.

## Étape 8 — Panier (basket)

- **Entités** :
  - `Basket` : `token` UUID unique, propriétaire (relation polymorphe en Laravel : à transposer, a priori vers l'utilisateur du core), `expires_at`, horodatage ;
  - pivot `basket_media`, en cascade côté panier et côté media.
- **API** :
  - `POST /baskets`, `GET/DELETE /baskets/{token}` ;
  - `POST /baskets/{token}/medias`, `DELETE /baskets/{token}/medias/{mediaId}` ;
  - `GET /baskets/{token}/download` : ZIP des fichiers, sur le disque de chaque fichier (YANMAR 1.9), avec `ZipArchiveException`.
- **Connexion** :
  - enrichissement de la réponse de login (`BasketLoginResponseEnricher`) : voir le point d'extension équivalent du core ;
  - `basket.claim_strategy` (`merge`, `replace`, `ignore`) pour le panier anonyme au login.
- **`basket.enabled: false` doit vraiment tout couper** : entités hors mapping, opérations API retirées, enrichissement du login désactivé.
- **Permissions** et voter (`BasketPolicy`).

## Étape 9 — Commandes de maintenance (plan YANMAR 1.8)

| Commande | Rôle |
|---|---|
| `files:hash [--force]` | Calcule le `hash` des lignes qui n'en ont pas. |
| `files:index [--path=]` | Crée les lignes `files` manquantes pour les fichiers présents sur le disque, sous la racine. |
| `files:deduplicate [--dry-run]` | Regroupe par `hash`, garde le plus ancien, appelle `FileLibrary::mergeDuplicates()`. Ne supprime le fichier physique que si plus aucune ligne ne partage son `path`. Affiche un rapport en tableau. |
| `files:relocate [--dry-run]` | Déplace les fichiers situés hors racine vers le premier niveau de `library.root`, ajoute un suffixe en cas de conflit, met à jour `path` et purge Glide. |
| `files:orphans [--delete] [--older-than=]` | Liste les fichiers sans usage, et les supprime en option. |

Le filtre « Doublons » de la bibliothèque ne sert qu'aux bases importées : un upload ne crée jamais de doublon. Il sera utile après `files:hash` sur une base Laravel migrée.

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
- Assets : rien à ajouter dans le projet. Le slot `admin_includes.head` du core charge la CSS et les contrôleurs, et `RelativeImportCompiler` rend les imports relatifs du bundle compatibles avec l'importmap.
- `FilePickerType` : options, exemples (`accept`, `multiple`, `as_id` pour les champs JSON), événement `gm-file-picker:change`.
- `README.md` à jour (dépendances, installation, migrations).

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
