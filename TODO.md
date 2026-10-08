# Reste à faire — portage de `gingerminds/laravel-media-manager`

Point au 8 octobre 2026 : le portage est terminé. Branche `build`.

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
| 10 | Documentation (`README.md`, `docs/`) | ✅ commité |

---

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
- Modifications locales non commitées : `composer.json` / `composer.lock` (dépôt local du bundle, core 1.6), `config/bundles.php`, `config/packages/flysystem.yaml`, `config/routes/gingerminds_media_manager.yaml`, migrations `Version20261007091812`, `Version20261007150534` (medias) et `Version20261008082627` (paniers).

## Hors périmètre

- Projet YANMAR (reste en Laravel).
- `gingerminds/laravel-cms` et son futur portage (dépendra de multisite).
