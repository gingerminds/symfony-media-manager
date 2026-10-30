# Refactoring — points à corriger

Issu de l'audit du 27/10/2026 (portage Laravel → Symfony). L'adaptation au core 1.10 est faite. Aucun projet n'utilise encore le bundle : les BC breaks sont acceptés.

Légende — effort : S (≤ ½ j), M (1–3 j), L (> 3 j) ; [H] hypothèse à vérifier.

## 2. Sécurité

| Constat | Où | Correction | Effort |
|---|---|---|---|
| Aucun contrôle d'accès sur `GET /api/files/{id}`, pas de fichier privé — **reporté par décision** | `src/Entity/File/File.php:17-37` | `security:` ou voter ; disque privé + `UriSigner` | M |

## 3. Réimplémentations de ce que Symfony fait déjà

| Constat | Où | Natif | Effort |
|---|---|---|---|
| Admin des fichiers en SPA JSON : ≈ 1 400 lignes de JS, `innerHTML` et échappement manuel, 12 routes JSON | `assets/file_browser/*`, `templates/components/file/_browser.html.twig:17-25`, `FileLibraryController` / `FileLibraryActionController` | Twig + Turbo Frames + `symfony/ux-dropzone` ; pagination de la bibliothèque (`LibraryQuery`, `FileRepository::paginateLibrary`) alignée sur le contrat des listes du core au passage | L |
| Payloads parsés à la main ; processeur aiguillé sur une chaîne `$action` qui avale les exceptions | `FileLibraryActionController.php:57-180`, `src/Basket/ApiPlatform/BasketProcessor.php:55,126-135` | DTO + `#[MapRequestPayload]` ; `input:` et un processeur par opération | M |
| `Uuid::isValid()` + `find()` écrits deux fois | `ShowFileController.php:33`, `AbstractFileLibraryController.php:57` | `#[MapEntity]` / value resolver | S |
| Taille et MIME validés à la main | `FileStorage::store()` | `Assert\File` + `#[MapUploadedFile]` | S |
| Purge des paniers par cron | `src/Basket/Command/PurgeBasketsCommand.php` | Symfony Scheduler | S |
| Services câblés à la main (591 lignes) ; extension Twig non paresseuse | `config/services/*.php`, `src/Twig/MediaManagerExtension.php` | `#[AsTwigFunction]` comme le core | S/M |
| `RelativeImportCompiler` + enregistrement inline des contrôleurs Stimulus | `src/AssetMapper/RelativeImportCompiler.php`, `templates/admin/_head.html.twig` | package UX (`symfony.controllers`) ou entrypoint importmap [H] | M |

## 4. Bugs et mauvais code

| Constat | Où | Correction | Effort |
|---|---|---|---|
| MIME inventé (`application/docx`) stocké puis renvoyé comme `Content-Type` | `src/File/MimeTypeNormalizer.php:23-37`, `ShowFileController.php:30`, `LibraryQuery.php:22` | stocker le vrai MIME, filtrer par famille, migrer les données | M |
| `GET /download` supprime le panier (effet de bord sur un GET) | `src/Basket/Controller/DownloadBasketController.php:52` | suppression par DELETE ou TTL | S |
| Le ZIP copie chaque fichier en temporaire avant de zipper | `src/Basket/BasketArchiver.php:69-89` | zip en `StreamedResponse` | M |
| Suppression : disque avant base, un flush par fichier | `FileStorage.php:86-94` | suppression disque en `postFlush` ou via Messenger | S |
| Filtre « orphelins » en `NOT IN` de tous les ids ; parcours complet de table | `src/File/FileLibrary.php:79`, `src/File/Reference/JsonFieldFileReferenceSource.php:45-63` | `NOT EXISTS` | M |
| `hasDirectories` par sous-dossier (N `ListObjects` sur S3) ; 4 allers-retours storage par réponse fichier | `FileLibraryController.php:162-167`, `FileResponseFactory` | ETag sur `hash` / `updatedAt` lu en base | M |
| Deux scans identiques des métadonnées Doctrine | `src/Media/MediaUsageCounter.php:57-80`, `src/File/Reference/DoctrineAssociationReferenceSource.php:104-127` | factoriser | S |

## 5. Doublons à remonter dans le core

- `assets/file_browser/modal.js` (empilement des modales Bootstrap) : à déplacer dans le core s'il sert ailleurs [H].

## 6. Surchargeabilité

| Blocage | Où | Piste | Effort |
|---|---|---|---|
| Aucun événement avant / après upload, suppression, déplacement, renommage (antivirus, purge CDN, optimisation d'image) | `FileStorage`, `FileLibrary` | événements dispatchés | M |
| `FileResponseFactory`, `PathGuard` (politique de nommage), `DiskRegistry` : `final readonly`, sans interface, injectés par type concret | `src/Http/FileResponseFactory.php:17`, `src/File/PathGuard.php:13` | interfaces + `#[AsAlias]` (X-Sendfile, redirection S3, règle de nommage) | S/M |
| `#[ApiResource]` sur les entités concrètes, `fromClass: Basket::class` en dur | `src/Entity/File/File.php`, `src/Basket/Entity/Basket.php:36-70` | ressource déclarée par config, même mécanisme que le core | M |


## 7. Code mort

Paramètre `gingerminds_media_manager.files_rate_limit` jamais lu (`GingermindsMediaManagerBundle.php:132`) ; `storage.disks` et `basket.enabled` lus seulement par les tests ; `FileReferenceRegistry::isUsed()` sans appelant ; `AbstractMediaLink`, `MediaCollectionSyncer`, `MediaCollectionTransformer` sans usage (ni cms ni skeleton) ; `LibraryStartPathProviderInterface` à une seule implémentation (à garder si documenté, cf. `TODO.md` « Dossiers par site »).
