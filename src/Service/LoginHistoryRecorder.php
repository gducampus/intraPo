<?php

namespace App\Service;

use App\Entity\LoginHistory;
use App\Entity\PoUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class LoginHistoryRecorder
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function record(
        Request $request,
        ?PoUser $user,
        string $email,
        bool $succeeded,
        ?string $failureReason = null
    ): void {
        $resolvedEmail = trim($email);
        if ($resolvedEmail === '' && $user?->getEmail()) {
            $resolvedEmail = (string) $user->getEmail();
        }
        if ($resolvedEmail === '') {
            $resolvedEmail = '(unknown)';
        }

        $history = new LoginHistory();
        $history->setUser($user);
        $history->setEmail($resolvedEmail);
        $history->setSucceeded($succeeded);
        $history->setIpAddress($this->truncate($request->getClientIp(), 45));
        $history->setUserAgent($this->truncate($request->headers->get('User-Agent'), 1000));

        if ($succeeded) {
            $history->setFailureReason(null);
        } else {
            $history->setFailureReason($this->truncate($failureReason, 255));
        }

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }

    private function truncate(?string $value, int $maxLength): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
