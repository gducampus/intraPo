<?php

namespace App\Security;

use App\Entity\PoUser;
use App\Repository\PoUserRepository;
use App\Service\LoginHistoryRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Throwable;

class EmailOtpAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PoUserRepository $poUserRepository,
        private readonly LoginHistoryRecorder $loginHistoryRecorder,
        private readonly TrustedDeviceManager $trustedDeviceManager,
        private readonly OtpCodeHasher $otpCodeHasher,
        #[Autowire(service: 'limiter.auth_otp_verify')]
        private readonly RateLimiterFactory $verifyLimiter,
        #[Autowire(service: 'limiter.auth_otp_verify_ip')]
        private readonly RateLimiterFactory $verifyIpLimiter,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_verify_code'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $email = trim((string) $request->request->get('email'));
        $normalizedEmail = strtolower($email);
        $code = trim((string) $request->request->get('code'));
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        $clientIp = (string) ($request->getClientIp() ?? 'unknown');
        $limiterKey = hash('sha256', $clientIp.'|'.$normalizedEmail);

        $isIpAccepted = $this->verifyIpLimiter->create($clientIp)->consume(1)->isAccepted();
        $isEmailAccepted = $this->verifyLimiter->create($limiterKey)->consume(1)->isAccepted();
        if (!$isIpAccepted || !$isEmailAccepted) {
            throw new CustomUserMessageAuthenticationException('Trop de tentatives. Reessayez dans quelques minutes.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new CustomUserMessageAuthenticationException('Email invalide.');
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            throw new CustomUserMessageAuthenticationException('Code invalide.');
        }

        /** @var PoUser|null $user */
        $user = $this->em->getRepository(PoUser::class)->findOneBy(['email' => $email]);
        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Code invalide ou expire.');
        }

        if ($user->getEmailOtpAttempts() >= 5) {
            throw new CustomUserMessageAuthenticationException('Trop de tentatives. Redemandez un code.');
        }

        $expiresAt = $user->getEmailOtpExpiresAt();
        if (!$expiresAt || $expiresAt < new \DateTimeImmutable()) {
            throw new CustomUserMessageAuthenticationException('Code invalide ou expire.');
        }

        if (!$this->otpCodeHasher->verify($code, $user->getEmailOtpCode())) {
            $user->incrementEmailOtpAttempts();
            $this->em->flush();
            throw new CustomUserMessageAuthenticationException('Code invalide ou expire.');
        }

        $deviceAccessResult = $this->trustedDeviceManager->resolveAccess($user, $request);
        if ($deviceAccessResult === TrustedDeviceManager::RESULT_MISSING) {
            throw new CustomUserMessageAuthenticationException('Cookies requis pour valider l\'appareil. Activez-les puis reessayez.');
        }
        if ($deviceAccessResult !== TrustedDeviceManager::RESULT_APPROVED) {
            throw new CustomUserMessageAuthenticationException('Nouvel appareil detecte. Demande envoyee a l\'admin pour approbation.');
        }

        $user->setEmailOtpCode(null);
        $user->setEmailOtpExpiresAt(null);
        $user->setEmailOtpAttempts(0);
        $this->em->flush();
        $this->verifyLimiter->create($limiterKey)->reset();

        return new SelfValidatingPassport(
            new UserBadge($email, fn () => $user),
            [new CsrfTokenBadge('login_verify', $csrfToken)]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?\Symfony\Component\HttpFoundation\Response
    {
        $email = trim((string) $request->request->get('email', ''));
        $user = $token->getUser();
        $this->safeRecordHistory($request, $user instanceof PoUser ? $user : null, $email, true, null);

        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?\Symfony\Component\HttpFoundation\Response
    {
        $email = trim((string) $request->request->get('email', ''));
        $user = null;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = $this->poUserRepository->findOneBy(['email' => $email]);
        }

        $this->safeRecordHistory(
            $request,
            $user,
            $email,
            false,
            $exception->getMessageKey()
        );

        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('app_login_code', ['email' => $email]));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): \Symfony\Component\HttpFoundation\Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login_email'));
    }

    private function safeRecordHistory(
        Request $request,
        ?PoUser $user,
        string $email,
        bool $succeeded,
        ?string $failureReason
    ): void {
        try {
            $this->loginHistoryRecorder->record($request, $user, $email, $succeeded, $failureReason);
        } catch (Throwable) {
            // Never block login flow if history storage fails.
        }
    }
}
