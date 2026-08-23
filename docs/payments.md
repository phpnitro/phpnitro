# Paiement

`packages/payments/src/` — six gateways, chacun une classe `Engine\Payments\*` autonome, même idiome REST server-to-server (`file_get_contents()`/`stream_context_create()` — pas de `curl`, absent du PHP cross-compilé pour Android ; pas de SDK vendor non plus). Une classe crée une transaction/commande côté serveur du gateway et retourne l'URL d'une page de paiement **hébergée** — ouverte via [`Engine\Device\UrlLauncher`](device-and-native.md), le client complète le paiement sur cette page externe, jamais dans un formulaire de cette app. Aucun rendu de carte/widget intégré : le rendu Canvas natif n'a pas de DOM où monter un iframe Stripe Elements ou un widget Checkout.js, contrairement à l'ancienne génération WebView de ces gateways.

| Gateway | Classe | Statut |
|---|---|---|
| Feexpay | `Feexpay` | Vérifié pour de vrai (device physique) |
| FedaPay | `Fedapay` | Vérifié pour de vrai (sandbox) |
| Stripe | `Stripe` | Vérifié pour de vrai (test-mode) |
| PayPal | `Paypal` | Vérifié pour de vrai (sandbox) |
| Razorpay | `Razorpay` | Vérifié pour de vrai (sandbox) |
| PayDunya | `Paydunya` | Vérifié pour de vrai (sandbox) |
| Kkiapay, iZiChangePay, TresorPay | — | Pas encore reconstruits |

```php
use Engine\Payments\Fedapay;
use Engine\Device\UrlLauncher;

$result = Fedapay::pay(
    secretKey: getenv('FEDAPAY_SECRET_KEY'),
    amount: 500,
    description: 'Commande #42',
    reference: 'order-42',
    sandbox: true,
);
// $result['url'] -> page de paiement hébergée FedaPay
// redirige : UrlLauncher::openAction($result['url'])

// Plus tard, un vrai check server-to-server (jamais le retour client seul) :
$status = Fedapay::status($secretKey, $result['transaction_id'], sandbox: true);
```

`phpnitro.yml`'s `payments: [fedapay, stripe, paypal, razorpay, paydunya]` + `phpx payments` disent lesquels sont configurés dans `.env` — voir [docs/cli.md](cli.md#phpnitroyml--le-manifeste-de-lapp), chaque gateway a un nom de variable fixe défini par le framework.

## Rappel de sécurité

Un événement de succès côté client (un retour de redirection, une fermeture d'onglet) n'est **jamais** une preuve de paiement, juste un signal d'UI — la commande ne doit être créée/marquée payée qu'après un `status()` server-to-server (voire, pour PayPal spécifiquement, un `Paypal::capture()` explicite — l'approbation seule n'y déplace pas l'argent, voir le docblock de la classe). Chaque classe de ce package suit cette règle elle-même ; ne jamais la contourner côté appelant.

## Reconstruire un gateway manquant (Kkiapay, iZiChangePay, TresorPay)

Même schéma que les six déjà là :
1. Vérifier l'API REST réelle du gateway (docs officielles si accessibles ; sinon lire le vrai SDK PHP officiel sur GitHub si publié — c'est ce qui a été fait pour PayDunya, dont la doc bloque les requêtes non-navigateur).
2. `pay()` crée la transaction et retourne l'URL hébergée ; `status()` fait un vrai GET server-to-server.
3. Pas de test PHPUnit dédié pour ce type de classe — que des appels HTTP réels, rien à asserter sans mocker `file_get_contents()` ; vérifier plutôt contre un vrai compte sandbox.
