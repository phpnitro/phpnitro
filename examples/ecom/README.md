# Ma Boutique — exemple e-commerce

Démonstration complète du framework : catalogue, panier, checkout, compte utilisateur, carte, biométrie, suivi de commande en direct. **Vérifié de bout en bout** (curl + captures d'écran) : inscription → ajout panier → commande → confirmation avec statut qui évolue tout seul.

## Lancer

```bash
cd examples/ecom
composer install --working-dir=lib/pages
composer install --working-dir=lib/backend
cd lib/pages && php -S 127.0.0.1:8090 -t public public/router.php
```

Ouvre `http://127.0.0.1:8090/`.

## Android

APK dédié (package `com.mobile.ecom`, distinct de la démo racine — les deux s'installent en même temps) : voir `android/README.md`. **Vérifié sur device réel** (Infinix X6532) : catalogue affiché, données servies par le PHP embarqué sur le téléphone.

```bash
bash bundle-android.sh
cd android && gradle :app:assembleDebug
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

## Ce que ça démontre

| Écran | Route | Widgets / fonctionnalités |
|---|---|---|
| Catalogue | `/` | Grille de produits (`Column` en mode grid), `LinkWrap`, `Image`, produits stockés en SQLite (Doctrine DBAL) |
| Détail produit | `/product/{id}` | `MapView` (retrait en magasin), `Button` avec action, `SingleScrollView` |
| Panier | `/cart` | `ListView`, `Form` + champ caché pour retirer un article, état partagé entre écrans (`Cart`, session) |
| Paiement | `/checkout` | `Form`, `TextField`, `SelectBox`, `Checkbox`, `FingerprintButton` (WebAuthn), validation + redirection programmatique |
| Confirmation | `/order/{id}` | `StreamBuilder` — le statut de la commande progresse tout seul (confirmée → en préparation → expédiée → livrée) sans rechargement de page |
| Connexion / Inscription | `/login`, `/register` | `Form`, mots de passe hashés (`password_hash`/`password_verify`), session |
| Compte | `/account` | `ThemeToggle`, déconnexion, protégé par état de session |

## Backend

`lib/backend/src/Repository/` : `ProductRepository` (6 produits de démo auto-seedés), `OrderRepository` (statut calculé depuis le temps écoulé, pas de worker), `UserRepository` (comptes, mots de passe hashés). Toutes les routes UI appellent ces repositories **en mémoire** (pas de HTTP), même mécanisme que le framework principal — le backend est toujours disponible, implicitement. `lib/pages/` et `lib/backend/` dépendent des packages partagés du framework (`phpnitro/ui`, `phpnitro/database`, dans `packages/` à la racine du dépôt) via des path repositories — aucun code de framework dupliqué dans cet exemple.

## Limites de cet exemple

- Photos produits : images aléatoires ([picsum.photos](https://picsum.photos)) plutôt que des vraies photos produit — suffisant pour démontrer la mise en page, pas pour un vrai catalogue.
- Pas de vrai paiement (le bouton biométrique confirme localement, ne débite rien).
- Les images produits mettent quelques secondes à charger sur le device réel (réseau du téléphone) — comportement normal, pas un bug.
