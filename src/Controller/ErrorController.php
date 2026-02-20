<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ErrorController
{
    #[Route('/{_path}', name: 'catch_all', requirements: ['_path' => '.*'])]
    public function show(Request $request): JsonResponse
    {
        $exception = $request->attributes->get('exception');

        if ($exception instanceof NotFoundHttpException) {
            return new JsonResponse([
                'error' => 'Resource not found'
            ], 404);
        }

        return new JsonResponse([
            'error' => 'Internal Server Error'
        ], 500);
    }
}