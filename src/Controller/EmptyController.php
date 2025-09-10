<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class EmptyController
{
    #[Route('/', name: 'empty')]
    public function empty(): Response
    {
        return new Response('');
    }
}
