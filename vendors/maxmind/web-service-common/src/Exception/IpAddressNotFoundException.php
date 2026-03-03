<?php

declare (strict_types=1);
namespace HCaptcha\Vendors\MaxMind\Exception;

/**
 * Thrown when the IP address is not found in the database.
 */
class IpAddressNotFoundException extends InvalidRequestException
{
}
