<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Bird's Email API (POST /v1/email/messages), selected with
 * MAIL_MAILER=bird. Configured in config/services.php under "bird".
 *
 * Mirrors the official @messagebird/sdk: Bearer auth, and the API host is
 * derived from the key's region (bk_{region}_{token} -> {region}.platform.bird.com).
 */
class BirdTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $baseUrl = null,
        private readonly ?string $fromAddress = null,
        private readonly ?string $fromName = null,
        private readonly int $timeout = 15,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new TransportException('Bird mailer is not configured: set BIRD_API_KEY.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($this->resolveBaseUrl() . '/v1/email/messages', $this->buildPayload($email));

        if (!$response->successful()) {
            throw new TransportException(sprintf(
                'Bird mailer: send failed (HTTP %d): %s',
                $response->status(),
                mb_substr($response->body(), 0, 500)
            ));
        }

        if ($id = $response->json('id')) {
            $message->setMessageId((string) $id);
        }
    }

    private function resolveBaseUrl(): string
    {
        if ($this->baseUrl) {
            return rtrim($this->baseUrl, '/');
        }

        if (!preg_match('/^b[km]_([a-z0-9]+)_/i', $this->apiKey, $m)) {
            throw new TransportException('Bird mailer: cannot determine region from BIRD_API_KEY (expected bk_{region}_...). Set BIRD_API_URL.');
        }

        return 'https://' . strtolower($m[1]) . '.platform.bird.com';
    }

    private function buildPayload(Email $email): array
    {
        $list = static fn (array $addresses) => array_map(
            static fn (Address $a) => $a->getName() !== ''
                ? ['email' => $a->getAddress(), 'name' => $a->getName()]
                : $a->getAddress(),
            $addresses
        );

        // BIRD_FROM_ADDRESS overrides the app's From, e.g. to use Bird's
        // onboarding sender until your own domain is verified in Bird.
        $sender = $email->getFrom()[0] ?? null;
        $from = ['email' => $this->fromAddress ?: ($sender?->getAddress() ?? '')];
        $name = $this->fromName ?: ($sender?->getName() ?? '');
        if ($name !== '') {
            $from['name'] = $name;
        }

        $payload = array_filter([
            'from' => $from,
            'to' => $list($email->getTo()),
            'cc' => $list($email->getCc()),
            'bcc' => $list($email->getBcc()),
            'reply_to' => $list($email->getReplyTo()),
            'subject' => (string) $email->getSubject(),
            'html' => is_string($email->getHtmlBody()) ? $email->getHtmlBody() : null,
            'text' => is_string($email->getTextBody()) ? $email->getTextBody() : null,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

        if (empty($payload['to'])) {
            throw new TransportException('Bird mailer: message has no "to" recipients.');
        }

        return $payload;
    }

    public function __toString(): string
    {
        return 'bird';
    }
}
