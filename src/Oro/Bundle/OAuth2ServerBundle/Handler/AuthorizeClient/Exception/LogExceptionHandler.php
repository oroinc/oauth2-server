<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\ExtendedOAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * The handler that logs exceptions occurred when authorizing of OAuth application.
 */
class LogExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    #[\Override]
    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void
    {
        $level = LogLevel::INFO;
        $context = [
            'exception' => $exception,
            'errorType' => $exception->getErrorType()
        ];
        $hint = $exception->getHint();
        if ($hint) {
            $context['hint'] = $hint;
        }
        if ($exception instanceof ExtendedOAuthServerException) {
            $requestedLevel = $exception->getLogLevel();
            if ($requestedLevel) {
                $level = $requestedLevel;
            }
            $context = array_merge($context, $exception->getLogContext());
        }
        $this->logger->log($level, $exception->getMessage(), $context);
    }
}
