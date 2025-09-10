<?php

namespace App\Service\Mailer;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class MailerService
{
    public function __construct(
        private LoggerInterface $logger,
        private ContainerBagInterface $params
    ) {}


    public function sendEmail($targetEmail, $hash): Response
    {

        $verificationLink = "https://easypublic.tegernseer-web.de/replace-device/" . $hash;

        $host = $this->params->get('MAILER_HOST');
        $port = $this->params->get('MAILER_PORT');
        $uName = $this->params->get('MAILER_UNAME');
        $uPass = $this->params->get('MAILER_UPASSWORD');

        $transport = new EsmtpTransport($host, $port, true);
        $transport->setUsername($uName);
        $transport->setPassword($uPass);

        $mailer = new Mailer($transport);
        $email = (new Email())
            ->from($uName)
            ->to('boszormenyirobert@yahoo.com')
            ->subject('Debug test Robika alap')
            ->text('Lost / Replaced Device')
            ->html('<p>Verification link: </p><a href="' . $verificationLink . '">Verification link</a>');


        try {
            $mailer->send($email);
            return new Response('Email sent');
        } catch (\Throwable $e) {
            $this->logger->critical('Email sending failed: ' . $e->getMessage());
            return new Response('Email failed: ' . $e->getMessage(), 500);
        }

        return new Response('Email sent');
    }
}
