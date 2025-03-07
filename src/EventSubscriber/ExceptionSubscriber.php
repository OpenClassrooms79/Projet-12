<?php

namespace App\EventSubscriber;

use JsonException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function sprintf;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof JsonException) {
            $event->setResponse(new JsonResponse(sprintf('Erreur JSON : %s', $this->translator->trans($exception->getMessage(), [], 'json')), Response::HTTP_INTERNAL_SERVER_ERROR));
        } elseif (($exception instanceof HttpException) && array_key_exists($exception->getStatusCode(), Response::$statusTexts)) {
            $event->setResponse(new JsonResponse($this->translator->trans($exception->getStatusCode(), [], 'http'), $exception->getStatusCode()));
        } else {
            $event->setResponse(new JsonResponse($this->translator->trans(Response::HTTP_INTERNAL_SERVER_ERROR, [], 'http'), Response::HTTP_INTERNAL_SERVER_ERROR));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
