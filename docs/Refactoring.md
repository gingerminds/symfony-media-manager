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
| Picker des médias en JSON : recherche `GET /admin/medias/search`, résultats construits en JS (`innerHTML`) | `assets/controllers/media_picker_controller.js`, `MediaController::search()` | même rendu serveur que la bibliothèque (Turbo frame) | M |

## 7. Code mort — à trancher

- Collections de médias (`AbstractMediaLink`, `MediaLinkInterface`, `MediaCollectionSyncer`, `MediaCollectionTransformer`, options `collection` / `link_factory` du `MediaSelectType`) : portées de Laravel et documentées (`docs/Forms.md`), mais utilisées ni par le cms ni par le skeleton.

## Plus tard

- `LibraryStartPathProviderInterface` (une seule implémentation) : gardé, documenté, pour les dossiers par site (`TODO.md`).
