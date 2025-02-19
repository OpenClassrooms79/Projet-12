<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Contracts\Translation\TranslatorInterface;

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

        if (($exception instanceof HttpException) && array_key_exists($exception->getStatusCode(), Response::$statusTexts)) {
            $event->setResponse(new JsonResponse($this->translator->trans($exception->getStatusCode()), $exception->getStatusCode()));
        } else {
            $event->setResponse(new JsonResponse($this->translator->trans(Response::HTTP_INTERNAL_SERVER_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
