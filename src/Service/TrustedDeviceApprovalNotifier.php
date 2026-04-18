<?php

namespace App\Service;

use App\Entity\TrustedDevice;
use App\Repository\PoUserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TrustedDeviceApprovalNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly PoUserRepository $poUserRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'MAILER_SENDER')]
        private readonly string $mailerSender,
    ) {
    }

    public function notifyAdmins(TrustedDevice $device): void
    {
        $adminEmails = [];
        foreach ($this->poUserRepository->findAll() as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true) && $user->getEmail()) {
                $adminEmails[] = $user->getEmail();
            }
        }
        $adminEmails = array_values(array_unique(array_filter($adminEmails)));
        if ($adminEmails === []) {
            return;
        }

        $reviewUrl = $this->urlGenerator->generate('app_trusted_device_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $subject = sprintf('Nouvel appareil en attente - %s', $device->getUser()?->getEmail() ?? 'Utilisateur');
        $body = sprintf(
            "Un nouvel appareil demande l'acces.\n\nUtilisateur: %s\nIP: %s\nUser-Agent: %s\nDate: %s\n\nValider ici: %s",
            $device->getUser()?->getEmail() ?? '-',
            $device->getFirstIp() ?? '-',
            $device->getUserAgent() ?? '-',
            $device->getRequestedAt()?->format('Y-m-d H:i:s') ?? '-',
            $reviewUrl
        );

        $email = (new Email())
            ->from($this->mailerSender)
            ->to($adminEmails[0])
            ->subject($subject)
            ->text($body);

        if (count($adminEmails) > 1) {
            $email->bcc(...array_slice($adminEmails, 1));
        }

        $this->mailer->send($email);
    }
}
