# Paiement

Sept gateways dans `packages/payments/src/` (namespace `Engine\Payments\`) et une intégration complète et testée dans [`examples/ecom`](../examples/ecom/README.md) (`CheckoutPage`). Cinq d'entre eux (Kkiapay, FedaPay, Feexpay, iZiChangePay, TresorPay) sont des **services**, pas des widgets : chaque classe expose `scriptTag(): Widget` (le `<script>` du SDK, à placer une fois), `payOnClick(...): string` (le déclencheur, attachable via `Button::make($label, onClick: ...)` à n'importe quel bouton) et, pour Kkiapay seulement, `onSuccess(string $action): Widget`. PayPal et Stripe Elements restent des widgets, pour des raisons documentées ci-dessous plutôt que par oubli.

Règle commune à toutes : un événement de succès côté client **n'est jamais une preuve de paiement**, juste un signal d'UI — la commande n'est créée qu'après une vérification serveur-à-serveur avec la clé **privée/secrète**.

## Niveau de confiance par gateway

| Gateway | Confiance | Ce qui est vérifié |
|---|---|---|
| **Kkiapay** (`Payments\Kkiapay`) | Élevée | Pattern d'origine — script SDK + `transaction_id` + endpoint de vérification documenté. Non testé contre un vrai compte sandbox. |
| **PayPal** (`Payments\PaypalButton`, widget) | Élevée | Vrai JS SDK (`paypal.Buttons()`), flux OAuth2 + capture server-side standard. Non testé contre une vraie app sandbox. Reste un widget : le SDK PayPal dessine lui-même son bouton dans un conteneur qu'il contrôle entièrement — pas d'`onclick` à extraire. |
| **FedaPay** (`Payments\Fedapay`) | Moyenne-élevée | Même forme que Kkiapay. Non testé. |
| **Stripe** (redirection, `Payments\StripeCheckout`) | Élevée sur le principe, non testé sur l'appel réel | Checkout hébergé, API REST directe, aucun SDK client — `Button::make($label, action: $action)` suffit. |
| **Stripe Elements** (`Payments\Stripe::cardElement()` + `::confirmPaymentOnClick()`) | Élevée sur le principe, non testé sur l'appel réel | Champ carte intégré, la carte reste dans un iframe géré par Stripe, jamais dans notre DOM/serveur. Activé automatiquement quand `STRIPE_PUBLIC_KEY` **et** `STRIPE_SECRET_KEY` sont renseignés. |
| **Feexpay, iZiChangePay, TresorPay** | Faible à très faible | Gabarits structurels seulement (URL de script et fonctions JS marquées `TODO`) — `CheckoutPage` refuse la transaction dès qu'une clé secrète est configurée plutôt que de faire semblant de vérifier. |

`examples/ecom/.env` documente les variables de chaque gateway ; `/checkout` choisit le **premier** gateway configuré (voir `CheckoutPage::selectPaymentWidgets()`) — rien de configuré = mode démo.

## Exemple (service)

```php
Column::make([
    Kkiapay::scriptTag(),
    Kkiapay::onSuccess(action: 'confirmKkiapay'),
    Button::make('Payer avec mon bouton perso', onClick: Kkiapay::payOnClick($key, $amount)),
])
```

## Pourquoi pas un simple formulaire carte bancaire ?

Aucune intégration ici ne laisse jamais une donnée de carte brute atteindre notre propre DOM/serveur. Un widget avec des `TextField` classiques pour numéro/CVV, postés vers notre propre serveur, mettrait cette donnée en **scope PCI-DSS SAQ D** (audit complet, segmentation réseau...) — une vraie régression de sécurité. `Stripe::cardElement()` garde la partie sensible hors de notre contrôle (iframe Stripe) ; seul un identifiant déjà confirmé transite par notre serveur, revérifié via `StripeCheckout::retrievePaymentIntent()`.

Pour ajouter un autre gateway : `packages/payments/src/Kkiapay.php`/`Fedapay.php` servent de modèle pour un service à SDK JS classique ; `StripeCheckout.php` pour un flux de redirection hébergé sans SDK client.

## Manque encore

Apple Pay / Google Pay natifs, gestion des remboursements, réception de webhooks asynchrones (le flux actuel est 100% synchrone côté client).
