<?php

namespace App\Admin\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use NeoPHP\Component\Session\SessionManagerInterface;
use NeoPHP\Package\QrCode\QrCodeManagerInterface;

class TotpManager
{
    public const SESSION = '_battlegame.admin_2fa';
    public const PENDING = '_battlegame.admin_2fa_pending';
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function __construct(
        protected UserRepository $users,
        protected SessionManagerInterface $session,
        protected QrCodeManagerInterface $qrCodes,
    ) {
    }

    public function secret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $chunk): string => self::ALPHABET[bindec(str_pad($chunk, 5, '0'))], str_split($bits, 5)));
    }

    public function code(string $secret, ?int $slice = null): string
    {
        $slice ??= intdiv(time(), self::PERIOD);
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $slice), $this->decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset + 1]) & 0xFF) << 16) | ((ord($hash[$offset + 2]) & 0xFF) << 8) | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function check(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        $slice = intdiv(time(), self::PERIOD);

        foreach ([-1, 0, 1] as $drift) {
            if (strlen((string) $code) === self::DIGITS && hash_equals($this->code($secret, $slice + $drift), (string) $code)) {
                return true;
            }
        }

        return false;
    }

    public function uri(User $user, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode('BattleGame:' . $user->getUsername()) . '?secret=' . $secret . '&issuer=BattleGame&period=' . self::PERIOD . '&digits=' . self::DIGITS;
    }

    public function qr(User $user, string $secret): string
    {
        return $this->qrCodes->create($this->uri($user, $secret))->size(220)->margin(2)->toDataUri('svg');
    }

    public function enable(User $user, string $secret): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4) . '-' . substr(bin2hex(random_bytes(4)), 0, 4));
        }

        $this->users->save($user->setTotpSecret($secret)->setTotpEnabledAt(new \DateTime())->setTotpRecovery(array_map(fn (string $code): string => password_hash($code, PASSWORD_DEFAULT), $codes)), true);
        $this->markVerified($user);

        return $codes;
    }

    public function disable(User $user): void
    {
        $this->users->save($user->setTotpSecret(null)->setTotpEnabledAt(null)->setTotpRecovery(null), true);
        $this->session->remove(self::SESSION);
    }

    public function verify(User $user, string $code): bool
    {
        if (!$user->hasTotp()) {
            return false;
        }

        if ($this->check((string) $user->getTotpSecret(), $code)) {
            $this->markVerified($user);

            return true;
        }

        $normalized = strtoupper(trim($code));
        $remaining = (array) $user->getTotpRecovery();

        foreach ($remaining as $index => $hash) {
            if (password_verify($normalized, $hash)) {
                unset($remaining[$index]);
                $this->users->save($user->setTotpRecovery(array_values($remaining)), true);
                $this->markVerified($user);

                return true;
            }
        }

        return false;
    }

    public function isVerified(User $user): bool
    {
        return $this->session->get(self::SESSION) === $this->fingerprint($user);
    }

    public function markVerified(User $user): void
    {
        $this->session->set(self::SESSION, $this->fingerprint($user));
    }

    protected function fingerprint(User $user): string
    {
        return $user->getId() . ':' . substr(hash('sha256', (string) $user->getTotpSecret()), 0, 16);
    }

    protected function decode(string $secret): string
    {
        $bits = '';

        foreach (str_split(strtoupper($secret)) as $char) {
            $position = strpos(self::ALPHABET, $char);

            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        return implode('', array_map(fn (string $byte): string => chr(bindec($byte)), array_filter(str_split($bits, 8), fn (string $byte): bool => strlen($byte) === 8)));
    }
}
