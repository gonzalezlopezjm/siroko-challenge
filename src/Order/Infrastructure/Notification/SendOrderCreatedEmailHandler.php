<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Notification;

use App\Order\Domain\Event\OrderCreated;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class SendOrderCreatedEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
    ) {}

    public function __invoke(OrderCreated $event): void
    {
        if ($event->customerEmail() === null) {
            return;
        }

        $total    = number_format($event->totalAmount() / 100, 2, ',', '.') . ' ' . $event->totalCurrency();
        $lines    = $this->renderLines($event->lines());
        $orderId  = substr($event->orderId(), 0, 8);
        $address  = implode(', ', [
            $event->shippingStreet(),
            $event->shippingCity(),
            $event->shippingPostalCode(),
            $event->shippingCountry(),
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($event->customerEmail())
            ->subject("Pedido #{$orderId} confirmado — Siroko")
            ->html($this->buildHtml(
                title: '¡Gracias por tu pedido!',
                intro: "Tu pedido <strong>#{$orderId}</strong> ha sido recibido correctamente y está siendo procesado.",
                linesHtml: $lines,
                totalHtml: "<strong>Total:</strong> {$total}",
                extraHtml: "<p><strong>Dirección de envío:</strong><br>{$address}</p>",
            ));

        $this->mailer->send($email);
    }

    private function renderLines(array $lines): string
    {
        $rows = '';
        foreach ($lines as $line) {
            $subtotal = number_format($line['subtotalAmount'] / 100, 2, ',', '.') . ' ' . $line['unitPriceCurrency'];
            $unit     = number_format($line['unitPriceAmount'] / 100, 2, ',', '.') . ' ' . $line['unitPriceCurrency'];
            $rows .= "<tr>
                <td style='padding:6px 12px;border-bottom:1px solid #eee'>{$line['productName']}</td>
                <td style='padding:6px 12px;border-bottom:1px solid #eee;text-align:center'>{$line['quantity']}</td>
                <td style='padding:6px 12px;border-bottom:1px solid #eee;text-align:right'>{$unit}</td>
                <td style='padding:6px 12px;border-bottom:1px solid #eee;text-align:right'>{$subtotal}</td>
            </tr>";
        }

        return "<table style='width:100%;border-collapse:collapse;margin:16px 0'>
            <thead><tr style='background:#f5f5f5'>
                <th style='padding:8px 12px;text-align:left'>Producto</th>
                <th style='padding:8px 12px;text-align:center'>Qty</th>
                <th style='padding:8px 12px;text-align:right'>Precio</th>
                <th style='padding:8px 12px;text-align:right'>Subtotal</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>";
    }

    private function buildHtml(string $title, string $intro, string $linesHtml, string $totalHtml, string $extraHtml): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px">
            <div style="background:#111;padding:20px;text-align:center;margin-bottom:24px">
                <span style="color:#fff;font-size:24px;font-weight:bold;letter-spacing:2px">SIROKO</span>
            </div>
            <h2 style="color:#111">{$title}</h2>
            <p>{$intro}</p>
            {$linesHtml}
            <p style="font-size:16px;margin-top:8px">{$totalHtml}</p>
            {$extraHtml}
            <hr style="border:none;border-top:1px solid #eee;margin:24px 0">
            <p style="font-size:12px;color:#999">Este es un mensaje automático, por favor no respondas a este correo.</p>
        </body>
        </html>
        HTML;
    }
}
