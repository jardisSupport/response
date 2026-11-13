<?php

declare(strict_types=1);

namespace JardisSupport\Response\Exception;

/**
 * Raised when what another system sent back is not the answer of a Jardis domain
 * (not JSON, one of the four envelope keys missing, the HTTP status differs from the
 * `status` of the envelope, a status the envelope does not know).
 *
 * The reader of the answer ({@see \JardisSupport\Response\Handler\Response\DecodeAnswer}) throws it;
 * the caller treats it as a failure of the transport, never as an answer of the other domain.
 */
final class NoJardisAnswerException extends \RuntimeException
{
}
