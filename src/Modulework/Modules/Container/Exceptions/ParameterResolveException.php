<?php namespace Modulework\Modules\Container\Exceptions;
/*
 * (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the Modulework Framework
 * License: View distributed LICENSE file
 */

use Exception;

/**
* ParameterResolveException
* This is getting thrown if a dependency of a constructor
* cannot be resolved (strings, integers, arrays without default value)
*/
class ParameterResolveException extends ResolveException
{ }