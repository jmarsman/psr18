<?php
namespace Undercloud\Psr18;

use Exception;
use Psr\Http\Client\NetworkExceptionInterface;

/**
 * Class NetworkException
 *
 * @category Psr18HttpClient
 * @package  Undercloud\Psr18
 * @author   undercloud <lodashes@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     http://github.com/undercloud/psr18
 */
class NetworkException extends Exception implements NetworkExceptionInterface
{
    use ExceptionTrait;
}
