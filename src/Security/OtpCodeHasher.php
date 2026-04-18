<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OtpCodeHasher
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret
    ) {
    }

    public function hash(string $plainCode): string
    {
        return hash_hmac('sha256', trim($plainCode), $this->secret);
    }

    public function verify(string $plainCode, ?string $storedHash): bool
    {
        if (!is_string($storedHash) || trim($storedHash) === '') {
            return false;
        }

        return hash_equals($storedHash, $this->hash($plainCode));
    }
}
