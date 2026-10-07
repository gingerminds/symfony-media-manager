# Reste à faire — portage de `gingerminds/laravel-media-manager`

Point au 7 octobre 2026. Branche `build`.

## Où on en est

| Étape | Contenu | État |
|---|---|---|
| 0 | Squelette du bundle, application de test, CI | ✅ commité |
| 1 | Entité `File`, stockage Flysystem (disques nommés), `FileStorage`, `PathGuard`, `MimeTypeNormalizer` | ✅ commité |
| 2 | Presets Glide, `GET /api/files/{id}` et `/{preset}`, cache, `gingerminds:media:cache:clear` | ✅ commité |
| 3 | `MediaCategory` : arbre, CRUD admin avec réordonnancement, API (`/tree` compris) | ✅ commité |
| 4 | `FileLibrary` et registre des références (`FileReferenceRegistry`) | ✅ commité |
| 5a | Page « Bibliothèque de fichiers », endpoints JSON, assets admin (slot `head` du core 1.6) | ✅ commité |
| 5b | `FilePickerType` et modale de sélection | ⏳ fait, **à commiter** |
| 6 | Media | à faire |
| 7 | Sélection de medias (`media-select`) | à faire |
| 8 | Panier (basket) | à faire |
| 9 | Commandes de maintenance | à faire |
| 10 | Documentation | à faire |

---

## Étape 5b — à commiter

- Fait :
  - `FilePickerType` (options `accept`, `multiple`, `as_id`, `preview_preset`, `start_path`) ;
  - widget en cartes, réordonnables en multiple ;
  - modale `GET /admin/files/picker` ;
  - filtre `accept[]` de `browse` et `MimeTypePatterns`.
- Testé en fonctionnel avec l'`Article` de l'application de test, et dans le skeleton avec des widgets injectés à la main.
- Le test dans un vrai formulaire du skeleton se fera à l'étape 6, avec le formulaire Media.

## Étape 6 — Media

À concevoir puis valider avant de coder. Ce que contient le package Laravel :

- **Entité `Media`** (table `medias`), sur le modèle `Interface` + `BaseMedia` (MappedSuperclass) + `Media`, surchargeable via `resources.media` :
  - `name`, `created_at` / `updated_at` ;
  - `file` (vers `files`, obligatoire) ;
  - `thumbnail` (vers `files`, optionnel, image) ;
  - `category` (vers `media_categories`) ;
  - les colonnes historiques `file_name`, `mime_type` et `size` : à reprendre ou à dériver du `File` (à décider, en pensant à l'import Laravel).
- **Clés étrangères vers `files` en `RESTRICT`** (plan YANMAR). Le Laravel a un `thumbnail_id … cascadeOnDelete()`, à ne pas reproduire.
- **Admin CRUD** (`medias`, permissions `view|edit|delete medias`, entrée dans le menu Médiathèque) :
  - formulaire avec `FilePickerType` pour `file` et pour `thumbnail` (`accept: ['image/*']`) ;
  - liste avec filtres (catégorie, recherche).
- **Suppression d'un media** : on supprime la ligne, jamais le fichier (YANMAR 1.5). Le fichier reste dans la bibliothèque, éventuellement orphelin.
- **API** :
  - `GetCollection` et `Get`, avec les groupes `media:list` / `media:read` (et `basket:read`) ;
  - propriétés `file_reference` et `file_size` ;
  - provider dédié (`MediaProvider`) ;
  - `file` renvoie l'UUID quel que soit le type, jamais le `path` brut (YANMAR 1.5) ;
  - cache : `CacheCascade` depuis `MediaCategory` (déjà déclaré côté catégorie : `['media']`).
- **`gingerminds:media:cache:clear`** : ajouter l'option `--media`.
- **Registre des références** : vérifier que `Media.file` et `Media.thumbnail` sont bien vus par la source Doctrine. Le registre doit alors bloquer la suppression d'un fichier utilisé, et le lien d'édition doit pointer vers le media.
- **Bug Laravel à ne pas reproduire** : `MediaController::create()` ne transmet pas `$mediaCategory` à la vue.
- **Tests** : CRUD, API, cache, blocage de suppression du fichier.
- **Dans le skeleton** :
  - migration ;
  - vrai formulaire avec le sélecteur ;
  - suppression bloquée d'un fichier utilisé ;
  - fusion de doublons avec mise à jour des références.

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
