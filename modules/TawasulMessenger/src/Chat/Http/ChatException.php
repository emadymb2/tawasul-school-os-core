<?php
namespace Tos\Module\TawasulChat\Http;

/**
 * A refusal the user should see a sentence about.
 *
 * Distinct from a programming error: the caller turns this into a message on
 * the page or an error field in JSON, with the message shown as written.
 */
class ChatException extends \RuntimeException
{
    /** @var string */
    private $field;

    public function __construct(string $message, string $field = '')
    {
        parent::__construct($message);
        $this->field = $field;
    }

    public function field(): string
    {
        return $this->field;
    }

    public static function notAMember(): self
    {
        return new self('You are not in this conversation.');
    }

    public static function chatNotFound(): self
    {
        return new self('That conversation no longer exists.');
    }

    public static function notYourMessage(): self
    {
        return new self('You can only change messages you sent.');
    }
}
