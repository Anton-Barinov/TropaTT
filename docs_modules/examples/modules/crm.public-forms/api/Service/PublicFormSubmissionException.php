<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Service;

use RuntimeException;

final class PublicFormSubmissionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus
    ) {
        parent::__construct($message);
    }
}
