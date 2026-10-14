<?php

namespace App\Controller;

use App\Content\HouseContent;
use App\Content\PracticeContent;
use App\Controller\Form\FormErrorCollector;
use App\Entity\SiteUser;
use App\Form\PracticeFormType;
use App\Infrastructure\Kinescope\KinescopeSpeakJwt;
use App\Practice\PracticeFormat;
use App\Repository\PaymentOfferRepository;
use App\Repository\PracticeSessionRepository;
use App\Service\PracticeEnrollmentService;
use App\Service\PracticeSessionService;
use App\Service\PublicSiteContext;
use App\Service\SiteUserResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PracticeController extends AbstractController
{
    use InquiryFormHandlerTrait;

    #[Route('/practice', name: 'web_practice', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PublicSiteContext $siteContext,
        PracticeEnrollmentService $enrollmentService,
        SiteUserResolver $siteUsers,
        #[Autowire('@limiter.inquiry_form')]
        RateLimiterFactory $inquiryLimiterFactory,
    ): Response {
        if (!HouseContent::isPracticePublicEnabled() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }

        $settings = $siteContext->getSettings();
        $actor = $siteUsers->resolve($this->getUser());
        $authenticated = $actor instanceof SiteUser;
        $accountEmail = $authenticated ? trim((string) $actor->getEmail()) : '';
        $accountHasEmail = '' !== $accountEmail && false !== filter_var($accountEmail, FILTER_VALIDATE_EMAIL);

        $form = $this->createForm(PracticeFormType::class, null, [
            'authenticated' => $authenticated,
            'account_has_email' => $accountHasEmail,
            'default_name' => $authenticated
                ? ($actor->getDisplayName() !== $actor->getEmail() ? $actor->getDisplayName() : '')
                : '',
            'default_email' => $accountHasEmail ? $accountEmail : '',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $limiter = $inquiryLimiterFactory->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume(1)->isAccepted()) {
                return $this->respondInquiry(
                    $request,
                    ok: false,
                    message: 'Слишком много попыток. Подождите 15 минут и попробуйте снова.',
                    redirectRoute: 'web_practice',
                    status: Response::HTTP_TOO_MANY_REQUESTS,
                );
            }

            if ('' !== (string) $form->get('website')->getData()) {
                return $this->respondInquiry($request, ok: true, message: '', redirectRoute: 'web_practice');
            }

            if ($form->isValid()) {
                $data = $form->getData();
                $format = PracticeFormat::tryFrom((string) $form->get('format')->getData()) ?? PracticeFormat::First;
                $email = trim((string) ($data['email'] ?? ''));
                if ($authenticated && $accountHasEmail) {
                    $email = $accountEmail;
                }

                try {
                    $result = $enrollmentService->enroll(
                        name: trim((string) $data['name']),
                        email: $email,
                        format: $format,
                        message: trim((string) ($data['message'] ?? '')),
                        actor: $authenticated ? $actor : null,
                    );
                } catch (\Throwable $exception) {
                    if ($this->wantsJsonResponse($request)) {
                        return $this->respondInquiry(
                            $request,
                            ok: false,
                            message: 'Не удалось сохранить заявку. Попробуйте ещё раз чуть позже.',
                            redirectRoute: 'web_practice',
                            status: Response::HTTP_INTERNAL_SERVER_ERROR,
                        );
                    }

                    throw $exception;
                }

                return $this->respondInquiry(
                    $request,
                    ok: true,
                    message: $result['successMessage'],
                    redirectRoute: 'web_practice',
                );
            }

            if ($this->wantsJsonResponse($request)) {
                return $this->respondInquiry(
                    $request,
                    ok: false,
                    message: 'Проверьте выделенные поля',
                    redirectRoute: 'web_practice',
                    errors: FormErrorCollector::collect($form),
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        return $this->render('web/practice/index.html.twig', [
            'settings' => $settings,
            'form' => $form,
            'content' => $this->pageContent(),
            'practiceAuthenticated' => $authenticated,
            'practiceAccountHasEmail' => $accountHasEmail,
            'practiceAccountEmail' => $accountHasEmail ? $accountEmail : null,
            'practiceSiteUser' => $authenticated ? $actor : null,
        ]);
    }

    #[Route('/practice/room', name: 'web_practice_room', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function room(
        SiteUserResolver $siteUsers,
        PaymentOfferRepository $offers,
        PracticeSessionRepository $sessions,
        PracticeSessionService $practiceSessions,
        KinescopeSpeakJwt $speakJwt,
    ): Response {
        $user = $siteUsers->resolve($this->getUser());
        if (!$user instanceof SiteUser) {
            throw $this->createAccessDeniedException();
        }

        $hasLiveOrReplay = $sessions->hasLiveOrReplay();
        if (!$offers->userHasPracticeRoomAccess($user, $hasLiveOrReplay)) {
            if ($offers->userHasPaidPracticeAccess($user)) {
                $this->addFlash('danger', 'Встречи по этому доступу исчерпаны. Оформи новый доступ на странице практики.');
            } else {
                $this->addFlash('danger', 'Сначала оформи участие и заверши оплату — доступ откроется в разделе Практика.');
            }

            return $this->redirectToRoute('web_account', ['tab' => 'practice']);
        }

        $userOffers = $offers->findForSiteUser($user);
        $balance = $practiceSessions->remainingForUserOffers($userOffers);
        $focusSession = $sessions->findRoomFocus();
        $nextSession = null;
        if (null === $focusSession || !$focusSession->hasPlayerEmbed()) {
            $nextSession = $sessions->findNextScheduled();
        }

        $speakEmbedUrl = $focusSession?->speakEmbedUrl();
        if (
            null !== $focusSession
            && $focusSession->isSpeak()
            && null !== $focusSession->getKinescopeRoomCode()
            && $this->isGranted('ROLE_ADMIN')
            && $speakJwt->isConfigured()
        ) {
            $speakEmbedUrl = $speakJwt->buildAdminEmbedUrl($focusSession->getKinescopeRoomCode());
        }

        $response = $this->render('web/practice/room.html.twig', [
            'siteUser' => $user,
            'focusSession' => $focusSession,
            'nextSession' => $nextSession,
            'sessionsLeft' => $balance['left'],
            'sessionsTotal' => $balance['total'],
            'speakEmbedUrl' => $speakEmbedUrl,
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    /** @return array<string, mixed> */
    private function pageContent(): array
    {
        return [
            'title' => PracticeContent::pageTitle(),
            'heroLead' => PracticeContent::heroLead(),
            'heroBody' => PracticeContent::heroBody(),
            'heroShare' => PracticeContent::heroShare(),
            'whyTitle' => PracticeContent::whyTitle(),
            'whyLead' => PracticeContent::whyLead(),
            'whyEffect' => PracticeContent::whyEffect(),
            'forWhomTitle' => PracticeContent::forWhomTitle(),
            'forWhom' => PracticeContent::forWhom(),
            'whyRoles' => PracticeContent::whyRoles(),
            'howTitle' => PracticeContent::howTitle(),
            'howBody' => PracticeContent::howBody(),
            'howNote' => PracticeContent::howNote(),
            'howFeedback' => PracticeContent::howFeedback(),
            'practicesTitle' => PracticeContent::practicesTitle(),
            'practicesLead' => PracticeContent::practicesLead(),
            'practices' => PracticeContent::practices(),
            'mapTitle' => PracticeContent::mapTitle(),
            'mapBody' => PracticeContent::mapBody(),
            'joinTitle' => PracticeContent::joinTitle(),
            'pricing' => PracticeContent::pricing(),
            'pricingNote' => PracticeContent::pricingNote(),
            'scheduleTitle' => PracticeContent::scheduleTitle(),
            'scheduleBody' => PracticeContent::scheduleBody(),
            'enterTitle' => PracticeContent::enterTitle(),
            'enterBody' => PracticeContent::enterBody(),
            'enterAccount' => PracticeContent::enterAccount(),
            'aboutTitle' => PracticeContent::aboutTitle(),
            'aboutLead' => PracticeContent::aboutLead(),
            'aboutFacts' => PracticeContent::aboutFacts(),
            'aboutLikes' => PracticeContent::aboutLikes(),
            'inviteTitle' => PracticeContent::inviteTitle(),
            'inviteBody' => PracticeContent::inviteBody(),
            'formTitle' => PracticeContent::formTitle(),
            'formLead' => PracticeContent::formLead(),
        ];
    }
}
