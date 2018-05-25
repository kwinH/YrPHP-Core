<?php
/**
 * Created by PhpStorm.
 * User: TOYOTA
 * Date: 2016/12/12
 * Time: 15:42
 */

namespace YrPHP\Middleware;

use Closure;
use Debug;
use response;
use YrPHP\IMiddleware;
use YrPHP\Request;

class AssignErrorsFromSession implements IMiddleware
{

    public function handler(Request $request, Closure $next)
    {
        \View::assign('errors', session('errors'));
        $next($request);
    }

}