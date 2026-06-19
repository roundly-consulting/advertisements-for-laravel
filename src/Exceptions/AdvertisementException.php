<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Exceptions;

use RuntimeException;

/**
 * Base exception for every error thrown by the advertisements package, so
 * consumers can catch the whole package surface with a single type.
 */
class AdvertisementException extends RuntimeException {}
