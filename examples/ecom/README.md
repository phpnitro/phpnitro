# Ma Boutique — exemple e-commerce

Démonstration complète du framework : catalogue, panier, checkout, compte utilisateur, carte, biométrie, suivi de commande en direct. **Vérifié de bout en bout** (curl + captures d'écran) : inscription → ajout panier → commande → confirmation avec statut qui évolue tout seul.

## Lancer

```bash
cd examples/ecom
composer install --working-dir=ui
composer install --working-dir=backend
cd ui && php -S 127.0.0.1:8090 -t public public/router.php
```

Ouvre `http://127.0.0.1:8090/`.

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

`backend/src/Repository/` : `ProductRepository` (6 produits de démo auto-seedés), `OrderRepository` (statut calculé depuis le temps écoulé, pas de worker), `UserRepository` (comptes, mots de passe hashés). Toutes les routes UI appellent ces repositories **en mémoire** (pas de HTTP), même mécanisme que le framework principal — le backend est toujours disponible, implicitement.

## Limites de cet exemple

- Photos produits : images aléatoires ([picsum.photos](https://picsum.photos)) plutôt que des vraies photos produit — suffisant pour démontrer la mise en page, pas pour un vrai catalogue.
- Pas de vrai paiement (le bouton biométrique confirme localement, ne débite rien).
- Pas encore packagé en APK Android dédié — utilise le même mécanisme que le framework principal (`bin/phpx bundle:android` root, à adapter si tu veux packager spécifiquement cet exemple).
