<?php

namespace App\Services;

use RuntimeException;

/** A workflow action that is not valid for the record's current state. Safe to show to staff. */
class WorkflowException extends RuntimeException {}
