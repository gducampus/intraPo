<?php

namespace App\Security;

use App\Entity\PoUser;
use App\Entity\TrustedDevice;
use App\Repository\TrustedDeviceRepository;
use App\Service\TrustedDeviceApprovalNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class TrustedDeviceManager
{
    public const DEVICE_COOKIE_NAME = 'intra_device_token';
    public const RESULT_APPROVED = 'approved';
    public const RESULT_PENDING = 'pending';
    public const RESULT_MISSING = 'missing';

    public function __construct(
        private readonly TrustedDeviceRepository $trustedDeviceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TrustedDeviceApprovalNotifier $approvalNotifier,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function resolveAccess(PoUser $user, Request $request): string
    {
        $deviceToken = trim((string) $request->cookies->get(self::DEVICE_COOKIE_NAME, ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $deviceToken)) {
            return self::RESULT_MISSING;
        }

        $userAgent = trim((string) $request->headers->get('User-Agent', ''));
        $deviceHash = $this->buildDeviceHash($deviceToken, $userAgent);

        $device = $this->trustedDeviceRepository->findOneByUserAndHash($user, $deviceHash);
        if (!$device instanceof TrustedDevice) {
            $device = new TrustedDevice();
            $device->setUser($user);
            $device->setDeviceHash($deviceHash);
            $device->setLabel($this->guessLabel($userAgent));
            $device->setUserAgent($userAgent !== '' ? $userAgent : null);
            $device->setFirstIp($request->getClientIp());
            $device->setLastIp($request->getClientIp());
            $device->setIsApproved(false);
            $device->setRequestedAt(new \DateTimeImmutable());
            $this->entityManager->persist($device);
            $this->entityManager->flush();

            $this->notifyAdminsSafely($device);

            return self::RESULT_PENDING;
        }

        if (!$device->isApproved()) {
            return self::RESULT_PENDING;
        }

        $device->setLastIp($request->getClientIp());
        $device->setLastSeenAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return self::RESULT_APPROVED;
    }

    private function buildDeviceHash(string $deviceToken, string $userAgent): string
    {
        $normalizedUserAgent = strtolower(trim($userAgent));

        return hash_hmac('sha256', $deviceToken.'|'.$normalizedUserAgent, $this->secret);
    }

    private function guessLabel(string $userAgent): ?string
    {
        $userAgent = trim($userAgent);
        if ($userAgent === '') {
            return null;
        }

        $parts = preg_split('/[();]/', $userAgent);
        $candidate = trim((string) ($parts[0] ?? ''));

        if ($candidate === '') {
            return mb_substr($userAgent, 0, 180);
        }

        return mb_substr($candidate, 0, 180);
    }

    private function notifyAdminsSafely(TrustedDevice $device): void
    {
        try {
            $this->approvalNotifier->notifyAdmins($device);
        } catch (Throwable) {
            // Never block authentication flow if notification fails.
        }
    }
}
