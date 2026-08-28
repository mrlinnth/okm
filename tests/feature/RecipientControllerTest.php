<?php

declare(strict_types=1);

use App\Libraries\SubscriptionsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class RecipientControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        Services::injectMock('subscriptions', new class extends SubscriptionsService {
            public function __construct() {}

            public function findByToken(string $token): ?array
            {
                return match ($token) {
                    'active-token' => ['recipientName' => 'Aung Ko', 'accessUrl' => 'ss://active-key', 'expiryDate' => '2026-09-15', 'status' => 'active'],
                    'disabled-token' => ['recipientName' => 'Aung Ko', 'accessUrl' => 'ss://disabled-key', 'expiryDate' => '2026-09-15', 'status' => 'disabled'],
                    'expired-token' => ['recipientName' => 'Aung Ko', 'accessUrl' => 'ss://expired-key', 'expiryDate' => '2026-08-27', 'status' => 'active'],
                    default => null,
                };
            }

            public function resolveRecipientState(?array $subscription): string
            {
                if ($subscription === null) {
                    return 'not_found';
                }

                if ($subscription['status'] === 'disabled') {
                    return 'disabled';
                }

                return $subscription['expiryDate'] < '2026-08-28' ? 'expired' : 'active';
            }
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
    }

    public function testActiveRecipientPageShowsTheAccessKey(): void
    {
        $result = $this->get('/s/active-token');

        $result->assertStatus(200);
        $result->assertSee('Aung Ko');
        $result->assertSee('ss://active-key');
    }

    /**
     * @dataProvider unavailableTokens
     */
    public function testUnavailableRecipientPagesAlwaysReturnOk(string $token, string $expectedText, string $hiddenKey): void
    {
        $result = $this->get('/s/' . $token);

        $result->assertStatus(200);
        $result->assertSee($expectedText);
        $result->assertDontSee($hiddenKey);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function unavailableTokens(): array
    {
        return [
            'disabled token' => ['disabled-token', 'ပိတ်ထားပါသည်', 'ss://disabled-key'],
            'expired token' => ['expired-token', 'သက်တမ်းကုန်သွားပါပြီ', 'ss://expired-key'],
            'unknown token' => ['unknown-token', 'ယခုအသုံးမပြုနိုင်သေးပါ', 'ss://'],
        ];
    }
}
