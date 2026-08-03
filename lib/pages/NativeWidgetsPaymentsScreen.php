<?php

namespace Engine\App;

use Engine\Native\AppBar;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\Container;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flex;
use Engine\Native\Padding;
use Engine\Native\Scaffold;
use Engine\Native\SelectBox;
use Engine\Native\Text;
use Engine\Native\TextField;
use Engine\Native\Tokens;
use Engine\Native\Widget;

/**
 * Feexpay mobile-money checkout — a real server-to-server integration
 * (Engine\Payments\Feexpay, restored from before the WebView/JS-bridge
 * gateway widgets were removed; that REST service class never depended
 * on any of them). payLocal() triggers a USSD push the customer confirms
 * on THEIR phone, not in this app — this screen can only ever show
 * "en attente", never a client-side "success", until a real
 * server-to-server status() check confirms it. See docs/payments.md's
 * security note and OrderRepository, which is what makes that check
 * possible instead of trusting session state alone.
 */
final class NativeWidgetsPaymentsScreen
{
    // Public: public/index.php's "pay" action handler reads this too, so
    // the amount actually charged server-side can never drift from what
    // this screen displays to the customer.
    public const AMOUNT_XOF = 500;

    private const NETWORKS = [
        'MTN' => 'MTN',
        'MOOV' => 'Moov',
        'CELTIIS BJ' => 'Celtiis (Bénin)',
        'MOOV TG' => 'Moov (Togo)',
        'TOGOCOM TG' => 'Togocom (Togo)',
        'ORANGE SN' => 'Orange (Sénégal)',
        'MTN CI' => "MTN (Côte d'Ivoire)",
        'MTN CG' => 'MTN (Congo)',
    ];

    public static function build(float $screenWidth, float $screenHeight, ?string $error, ?array $order): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $phone = $_GET['pay_phone'] ?? '';
        $network = $_GET['pay_network'] ?? '';

        $content = $order !== null
            ? self::pendingOrderView($order, $contentWidth)
            : Flex::column([
                new Text('Paiement mobile money (Feexpay)', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                new Padding(
                    EdgeInsets::only(top: 4),
                    new Text(self::AMOUNT_XOF . ' XOF — démo', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                ),
                new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Banner($error)),
                new Padding(
                    EdgeInsets::only(top: $error !== null ? Tokens::SPACE_LG : 0),
                    new TextField('pay_phone', $phone, 'Numéro de téléphone'),
                ),
                new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new SelectBox('pay_network', self::NETWORKS, $network, 'Réseau...')),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_XL),
                    new Button('Payer ' . self::AMOUNT_XOF . ' XOF', 'submit:pay', width: $contentWidth),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH);

        $body = new Container(
            new Padding(EdgeInsets::all(Tokens::SPACE_XL), $content),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Paiement', backAction: 'back'),
        );
    }

    /** @param array{reference: string, amount: int, phone: string, network: string, status: string} $order */
    private static function pendingOrderView(array $order, float $contentWidth): Widget
    {
        $statusLabel = match ($order['status']) {
            'SUCCESSFUL' => 'Paiement confirmé ✓',
            'FAILED' => 'Paiement échoué',
            default => 'En attente de confirmation sur le téléphone du client...',
        };
        $statusColor = match ($order['status']) {
            'SUCCESSFUL' => Tokens::success(),
            'FAILED' => Tokens::danger(),
            default => Tokens::inkMuted(),
        };

        $rows = [
            new Text('Commande', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
            new Padding(
                EdgeInsets::only(top: Tokens::SPACE_MD),
                new Text("Référence : {$order['reference']}", Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
            ),
            new Padding(
                EdgeInsets::only(top: 4),
                new Text("{$order['amount']} XOF — {$order['phone']} ({$order['network']})", Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
            ),
            new Padding(
                EdgeInsets::only(top: Tokens::SPACE_LG),
                new Text($statusLabel, Tokens::TEXT_BODY, $statusColor->toHex(), bold: true),
            ),
        ];

        if ($order['status'] === 'PENDING') {
            $rows[] = new Padding(
                EdgeInsets::only(top: Tokens::SPACE_XL),
                new Button('Vérifier le statut', 'submit:check_status', width: $contentWidth),
            );
        }

        return Flex::column($rows, crossAxisAlignment: CrossAxisAlignment::STRETCH);
    }
}
