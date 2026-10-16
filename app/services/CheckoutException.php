<?php
/**
 * GiftAura - a problem the customer can fix (out of stock, cart changed, ...).
 * Thrown inside the order transaction to roll everything back; the message
 * is safe to show to the customer.
 */
declare(strict_types=1);

final class CheckoutException extends RuntimeException
{
}
