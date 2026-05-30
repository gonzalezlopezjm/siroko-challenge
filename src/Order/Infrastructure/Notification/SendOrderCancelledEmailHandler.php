<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Notification;

use App\Order\Domain\Event\OrderCancelled;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class SendOrderCancelledEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
    ) {}

    public function __invoke(OrderCancelled $event): void
    {
        if ($event->customerEmail() === null) {
            return;
        }

        $total   = number_format($event->totalAmount() / 100, 2, ',', '.') . ' ' . $event->totalCurrency();
        $orderId = substr($event->orderId(), 0, 8);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($event->customerEmail())
            ->subject("Pedido #{$orderId} cancelado — Siroko")
            ->html(<<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px">
                <div style="background:#111;padding:20px;text-align:center;margin-bottom:24px">
                    <span style="color:#fff;font-size:24px;font-weight:bold;letter-spacing:2px">SIROKO</span>
                </div>
                <h2 style="color:#c0392b">Tu pedido ha sido cancelado</h2>
                <p>El pedido <strong>#{$orderId}</strong> por un importe de <strong>{$total}</strong> ha sido cancelado.</p>
                <p>Si tienes alguna duda, contacta con nuestro equipo de atención al cliente.</p>
                <hr style="border:none;border-top:1px solid #eee;margin:24px 0">
                <p style="font-size:12px;color:#999">Este es un mensaje automático, por favor no respondas a este correo.</p>
            </body>
            </html>
            HTML);

        $this->mailer->send($email);
    }
}
