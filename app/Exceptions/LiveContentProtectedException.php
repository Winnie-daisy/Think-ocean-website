<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code tries to change the live version of published content
 * outside the publishing workflow. This is a programming error, not user input.
 */
class LiveContentProtectedException extends RuntimeException {}
