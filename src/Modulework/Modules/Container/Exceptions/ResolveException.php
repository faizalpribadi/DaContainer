<?php namespace Modulework\Modules\Container\Exceptions;
/*
 * (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the Modulework Framework
 * License: View distributed LICENSE file
 */

use Exception;

/**
* ResolveException
* This is getting thrown if something cannot get resolved in the IoC Container
*/
class ResolveException extends Exception implements ResolveExceptionInterface
{ }