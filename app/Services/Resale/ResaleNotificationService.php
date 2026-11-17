<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\ResaleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Creates in-app notifications for the resale marketplace and, when SMTP is
 * configured on the site, also sends a short email to the customer.
 *
 * The service is deliberately dependency-free and safe to call from any
 * service / cron tick: email failures do not block the in-app notification.
 */
class ResaleNotificationService
{
    /**
     * Write an in-app notification row. If $emailToo is true and the customer
     * has an email address, a best-effort email is also attempted.
     */
    public function notify(
        Customer $customer,
        string $type,
        string $title,
        string $body = '',
        array $context = [],
        bool $emailToo = true
    ): ResaleNotification {
        $row = ResaleNotification::create([
            'customer_id' => $customer->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'resale_ticket_id' => $context['resale_ticket_id'] ?? null,
            'support_ticket_id' => $context['support_ticket_id'] ?? null,
            'is_read' => false,
        ]);

        if ($emailToo && !empty($customer->email)) {
            $this->tryEmail($customer, $title, $body);
        }

        return $row;
    }

    private function tryEmail(Customer $customer, string $subject, string $body): void
    {
        try {
            $info = DB::table('basic_settings')
                ->select('website_title', 'smtp_status', 'smtp_host', 'smtp_port', 'encryption', 'smtp_username', 'smtp_password', 'from_mail', 'from_name')
                ->first();

            if (!$info || (int) ($info->smtp_status ?? 0) !== 1) {
                return;
            }

            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isSMTP();
            $mail->Host = $info->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $info->smtp_username;
            $mail->Password = $info->smtp_password;
            if (strtoupper((string) $info->encryption) === 'TLS') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->Port = (int) $info->smtp_port;

            $mail->setFrom($info->from_mail, $info->from_name);
            $mail->addAddress($customer->email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $html = '<p>' . e($body) . '</p>';
            $html .= '<p style="color:#888;font-size:12px;">— ' . e($info->website_title ?? 'Resale') . '</p>';
            $mail->Body = $html;
            $mail->AltBody = $body;

            $mail->send();
        } catch (\Throwable $e) {
            Log::warning('ResaleNotification email failed: ' . $e->getMessage());
        }
    }
}
