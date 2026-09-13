<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use PDO;

/**
 * Multi-layer Anti-Spam protection for polymorphic web forms (E-COM-11 §4).
 *
 * Implements:
 * 1. Honeypot check: detecting non-empty hidden fields (`website_hp`, `hp_field`, etc.).
 * 2. Stop-words analysis: detecting scam/casino/phishing spam keywords in text inputs.
 * 3. Rate limiting by phone number and IP address (e.g. max 3 submissions per 10 mins).
 * 4. Bot score verification (reCAPTCHA / Cloudflare Turnstile token score threshold).
 */
final class AntiSpamService
{
    /** Common honeypot field names inserted by CMS plugins. */
    public const HONEYPOT_FIELDS = [
        'website_hp',
        'hp_field',
        'honeypot',
        '_hp',
        'email_hp',
        'comment_hp',
    ];

    /** Typical spam stop-words in web forms. */
    public const STOP_WORDS = [
        'casino',
        'казино',
        'вулкан',
        'crypto',
        'крипта',
        'заработок в интернете',
        'быстрый доход',
        'free money',
        'viagra',
        'виагра',
        'sex shop',
        'sexshop',
        'порно',
        'erotic',
        'ставки на спорт',
        '1xbet',
        'betting',
    ];

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly int $phoneRateLimitWindow = 600, // 10 minutes
        private readonly int $maxSubmissionsPerPhone = 3,
        private readonly int $ipRateLimitWindow = 600,
        private readonly int $maxSubmissionsPerIp = 10,
        private readonly float $minCaptchaScore = 0.5
    ) {
    }

    /**
     * Inspects ingestion envelope, payload and headers for spam indicators.
     *
     * @param array<string,mixed> $raw Raw request body
     * @param array<string,mixed> $contact Normalized contact
     * @param string $ip Client IP
     * @return array{
     *     is_spam: bool,
     *     reason: ?string,
     *     rule: ?string,
     *     score: float
     * }
     */
    public function check(array $raw, array $contact, string $ip): array
    {
        // 1. Honeypot Check
        $hpReason = $this->checkHoneypot($raw);
        if ($hpReason !== null) {
            return [
                'is_spam' => true,
                'reason' => $hpReason,
                'rule' => 'honeypot',
                'score' => 1.0,
            ];
        }

        // 2. Stop-words Analysis
        $stopWordReason = $this->checkStopWords($raw);
        if ($stopWordReason !== null) {
            return [
                'is_spam' => true,
                'reason' => $stopWordReason,
                'rule' => 'stop_words',
                'score' => 0.9,
            ];
        }

        // 3. Captcha / Bot Score verification
        $captchaScore = $this->extractCaptchaScore($raw);
        if ($captchaScore !== null && $captchaScore < $this->minCaptchaScore) {
            return [
                'is_spam' => true,
                'reason' => "Captcha score {$captchaScore} is below threshold {$this->minCaptchaScore}",
                'rule' => 'captcha_score',
                'score' => (1.0 - $captchaScore),
            ];
        }

        // 4. Rate Limiting by Phone
        $phone = (string)($contact['phone'] ?? '');
        if ($phone !== '' && $this->isPhoneRateLimited($phone)) {
            return [
                'is_spam' => true,
                'reason' => "Too many submissions for phone {$phone} in recent time",
                'rule' => 'phone_rate_limit',
                'score' => 0.85,
            ];
        }

        // 5. Rate Limiting by IP
        if ($ip !== '' && $this->isIpRateLimited($ip)) {
            return [
                'is_spam' => true,
                'reason' => "Too many submissions from IP {$ip} in recent time",
                'rule' => 'ip_rate_limit',
                'score' => 0.8,
            ];
        }

        return [
            'is_spam' => false,
            'reason' => null,
            'rule' => null,
            'score' => 0.0,
        ];
    }

    /**
     * Inspects envelope and payload for populated honeypot trap fields.
     */
    public function checkHoneypot(array $raw): ?string
    {
        $candidates = [
            $raw,
            $raw['payload'] ?? [],
            $raw['payload']['form_data'] ?? [],
            $raw['form_data'] ?? [],
        ];

        foreach ($candidates as $bag) {
            if (!is_array($bag)) {
                continue;
            }
            foreach (self::HONEYPOT_FIELDS as $hpKey) {
                if (isset($bag[$hpKey]) && trim((string)$bag[$hpKey]) !== '') {
                    return "Honeypot trap field '{$hpKey}' was filled";
                }
            }
        }

        return null;
    }

    /**
     * Inspects text message and comments for prohibited spam words.
     */
    public function checkStopWords(array $raw): ?string
    {
        $texts = [];
        if (isset($raw['payload']) && is_array($raw['payload'])) {
            $p = $raw['payload'];
            if (!empty($p['message']) && is_scalar($p['message'])) {
                $texts[] = (string)$p['message'];
            }
            if (!empty($p['comment']) && is_scalar($p['comment'])) {
                $texts[] = (string)$p['comment'];
            }
            if (!empty($p['subject']) && is_scalar($p['subject'])) {
                $texts[] = (string)$p['subject'];
            }
            if (!empty($p['topic']) && is_scalar($p['topic'])) {
                $texts[] = (string)$p['topic'];
            }
            if (!empty($p['form_data']) && is_array($p['form_data'])) {
                foreach ($p['form_data'] as $v) {
                    if (is_scalar($v)) {
                        $texts[] = (string)$v;
                    }
                }
            }
        }

        $combined = mb_strtolower(implode(' ', $texts));
        foreach (self::STOP_WORDS as $word) {
            if (mb_strpos($combined, $word) !== false) {
                return "Spam stop-word '{$word}' detected";
            }
        }

        return null;
    }

    private function extractCaptchaScore(array $raw): ?float
    {
        $meta = $raw['meta'] ?? $raw['payload']['meta'] ?? null;
        if (is_array($meta)) {
            if (isset($meta['captcha_score']) && is_numeric($meta['captcha_score'])) {
                return (float)$meta['captcha_score'];
            }
            if (isset($meta['turnstile_score']) && is_numeric($meta['turnstile_score'])) {
                return (float)$meta['turnstile_score'];
            }
        }

        return null;
    }

    private function isPhoneRateLimited(string $phone): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            $cutoff = gmdate('Y-m-d H:i:s', time() - $this->phoneRateLimitWindow);
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM ecommerce_ingest_events
                 WHERE payload_json LIKE :phonePattern AND created_at >= :cutoff'
            );
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen((string)$digits) < 7) {
                return false;
            }
            $stmt->execute([
                'phonePattern' => '%' . $digits . '%',
                'cutoff' => $cutoff,
            ]);
            $count = (int)$stmt->fetchColumn();

            return $count >= $this->maxSubmissionsPerPhone;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isIpRateLimited(string $ip): bool
    {
        if ($this->pdo === null || $ip === '' || $ip === '127.0.0.1') {
            return false;
        }

        try {
            $cutoff = gmdate('Y-m-d H:i:s', time() - $this->ipRateLimitWindow);
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM ecommerce_ingest_events
                 WHERE ip = :ip AND created_at >= :cutoff'
            );
            $stmt->execute([
                'ip' => $ip,
                'cutoff' => $cutoff,
            ]);
            $count = (int)$stmt->fetchColumn();

            return $count >= $this->maxSubmissionsPerIp;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
