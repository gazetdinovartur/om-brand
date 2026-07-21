<?php

namespace App\Controller;

use App\Enum\ContentLikeTarget;
use App\Service\ContentLikeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContentLikeController extends AbstractController
{
    #[Route(
        '/api/like/{type}/{id}',
        name: 'web_content_like_toggle',
        methods: ['POST'],
        requirements: ['type' => 'chronicle|case', 'id' => '\d+'],
    )]
    public function toggle(string $type, int $id, Request $request, ContentLikeService $likes): JsonResponse
    {
        $token = (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token'));
        if (!$this->isCsrfTokenValid('content_like', $token)) {
            return $this->json(['error' => 'Недействительный CSRF-токен.'], Response::HTTP_FORBIDDEN);
        }

        $target = ContentLikeTarget::from($type);
        $response = new JsonResponse();

        try {
            $payload = $likes->toggle($target, $id, $request, $response);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Контент не найден.'], Response::HTTP_NOT_FOUND);
        }

        $response->setData($payload);
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }
}
