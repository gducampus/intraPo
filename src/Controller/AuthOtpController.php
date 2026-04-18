<?php

namespace App\Controller;

use App\Entity\PoUser;
use App\Security\OtpCodeHasher;
use App\Security\TrustedDeviceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class AuthOtpController extends AbstractController
{
    #[Route('/login', name: 'app_login_email', methods: ['GET', 'POST'])]
    public function loginEmail(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        OtpCodeHasher $otpCodeHasher,
        #[Autowire(service: 'limiter.auth_otp_send')]
        RateLimiterFactory $otpSendLimiter,
        #[Autowire(service: 'limiter.auth_otp_send_ip')]
        RateLimiterFactory $otpSendIpLimiter,
        #[Autowire(env: 'MAILER_SENDER')]
        string $mailerSender,
    ): Response {
        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('login_email', $csrfToken)) {
                $this->addFlash('error', 'Session invalide. Reessayez.');
                return $this->withDeviceCookie($request, $this->redirectToRoute('app_login_email'));
            }

            $email = trim((string) $request->request->get('email'));
            $normalizedEmail = strtolower($email);
            $clientIp = (string) ($request->getClientIp() ?? 'unknown');

            $isIpAccepted = $otpSendIpLimiter->create($clientIp)->consume(1)->isAccepted();
            $isEmailAccepted = $otpSendLimiter->create(
                hash('sha256', $clientIp.'|'.$normalizedEmail)
            )->consume(1)->isAccepted();

            if (!$isIpAccepted || !$isEmailAccepted) {
                $this->addFlash('success', 'Si ce compte existe, un code a ete envoye.');

                return $this->withDeviceCookie(
                    $request,
                    $this->redirectToRoute('app_login_code', ['email' => $email])
                );
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Email invalide.');
                return $this->withDeviceCookie($request, $this->redirectToRoute('app_login_email'));
            }

            /** @var PoUser|null $user */
            $user = $em->getRepository(PoUser::class)->findOneBy(['email' => $email]);
            if (!$user) {
                $this->addFlash('success', 'Si ce compte existe, un code a ete envoye.');

                return $this->withDeviceCookie(
                    $request,
                    $this->redirectToRoute('app_login_code', ['email' => $email])
                );
            }

            $code = (string) random_int(100000, 999999);
            $user->setEmailOtpCode($otpCodeHasher->hash($code));
            $user->setEmailOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $user->setEmailOtpAttempts(0);
            $em->flush();

            $mailer->send(
                (new Email())
                    ->from($mailerSender)
                    ->to($email)
                    ->subject('Votre code de connexion - Intranet PO')
                    ->text(
                        "Votre code de connexion est : {$code}\n\n".
                        "Il expire dans 10 minutes.\n\n".
                        "Si vous n'avez pas demande ce code, ignorez cet email."
                    )
            );

            return $this->withDeviceCookie(
                $request,
                $this->redirectToRoute('app_login_code', ['email' => $email])
            );
        }

        return $this->withDeviceCookie($request, $this->render('auth_otp/login_email.html.twig'));
    }

    #[Route('/login/code', name: 'app_login_code', methods: ['GET'])]
    public function loginCode(Request $request): Response
    {
        $email = (string) $request->query->get('email', '');

        return $this->withDeviceCookie(
            $request,
            $this->render('auth_otp/login_code.html.twig', ['email' => $email])
        );
    }

    #[Route('/login/verify', name: 'app_verify_code', methods: ['POST'])]
    public function verifyCode(): Response
    {
        // Intercepted by EmailOtpAuthenticator (supports() on app_verify_code)
        return $this->redirectToRoute('app_login_email');
    }

    private function withDeviceCookie(Request $request, Response $response): Response
    {
        if ($request->cookies->has(TrustedDeviceManager::DEVICE_COOKIE_NAME)) {
            return $response;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable) {
            return $response;
        }

        $cookie = Cookie::create(TrustedDeviceManager::DEVICE_COOKIE_NAME, $token)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withExpires(new \DateTimeImmutable('+1 year'));

        $response->headers->setCookie($cookie);

        return $response;
    }
}
